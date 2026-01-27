<?php

namespace App\Http\Controllers\Api;

use App\Enum\PaymentProvider;
use App\Enum\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Stripe\Exception\ApiErrorException;
use Stripe\Exception\SignatureVerificationException;
use Stripe\PaymentIntent;
use Stripe\Stripe;
use Stripe\Webhook;
use UnexpectedValueException;

class PaymentController extends Controller
{
    // initialize controller
    public function __construct()
    {
        Stripe::setApiKey(config('services.stripe.secret'));
    }

    public function createPayment(Request $request, Order $order)
    {
        // validate request
        $request->validate([
            'provider' => 'required|string|in:' . implode(',', PaymentProvider::Values()),
        ]);

        // check if order belongs to current authenticated user
        if ($order->user_id !== $request->user()->id) {
            return response()->json([
                'message' => 'Unauthorized. This order does not belong to you.'
            ], 403);
        }

        // check if order can be paid
        if (!$order->canAcceptPayment()) {
            return response()->json([
                'message' => 'This order cannot be paid.'
            ], 400);
        }

        // check payment provider
        $provider = PaymentProvider::from($request->input('provider'));
        if ($provider === PaymentProvider::STRIPE) {
            return $this->createStripePayment($order);
        } else {
            return response()->json([
                'message' => 'Unsupported payment provider.'
            ], 400);
        }
    }

    protected function createStripePayment(Order $order)
    {
        try {
            // create a payment record
            $payment = Payment::create([
                'order_id' => $order->id,
                'user' => $order->user_id,
                'amount' => $order->total,
                'provider' => PaymentProvider::STRIPE,
                'currency' => 'usd',
                'status' => PaymentStatus::PENDING,
                'metadata' => [
                    'order_number' => $order->order_number,
                    'created_at' => now()->toIso8601String(),
                ],
            ]);

            // create a Stripe PaymentIntent
            $paymentIntent = PaymentIntent::create([
                'amount' => (int)($order->total * 100), // amount in cents
                'currency' => $payment->currency,
                'metadata' => [
                    'payment_id' => $payment->id,
                    'order_id' => $order->id,
                ],
                'description' => 'Payment for Order #' . $order->order_number,
            ]);
            // update a payment record
            $payment->update([
                'payment_intent_id' => $paymentIntent->id,
                'metadata' => array_merge($payment->metadata, [
                    'payment_intent_client_secret' => $paymentIntent->client_secret,
                ]),
            ]);
            // return client secret to the frontend
            return response()->json([
                'status' => true,
                'client_secret' => $paymentIntent->client_secret,
                'payment_id' => $payment->id,
                'publishable_key' => config('services.stripe.key'),
            ]);
        } catch (ApiErrorException $e) {
            Log::error('Stripe payment Error: ' . $e->getMessage(), [
                'message' => 'Failed to create Stripe payment intent.',
                'error' => $e->getMessage(),
                'order_id' => $order->id,
            ], 500);
        }
    }

    public function confirmPayment(Request $request, $paymentId)
    {
        // find payment
        $payment = Payment::find($paymentId);
        if (!$payment) {
            return response()->json([
                'message' => 'Payment not found.'
            ], 404);
        }

        // check if payment belongs to current authenticated user
        if ($payment->user_id !== $request->user()->id) {
            return response()->json([
                'message' => 'Unauthorized. This payment does not belong to you.'
            ], 403);
        }
        return response()->json([
            'status' => true,
            'message' => 'Payment confirmed successfully.',
            'payment' => $payment,
            'order' => $payment->order,
        ]);
    }

    public function stripeWebhook(Request $request)
    {
        $payload = $request->getContent();
        $sigHeader = $request->header('Stripe-Signature');
        $webhookSecret = config('services.stripe.webhook.secret');
        try {
            // verify the webhook signature
            $event = Webhook::constructEvent(
                $payload,
                $sigHeader,
                $webhookSecret
            );
            // handle the event
            switch ($event->type) {
                case 'payment_intent.succeeded':
                    return $this->handlePaymentIntentSucceeded($event->data->object);
                case 'payment_intent.payment_failed':
                    return $this->handlePaymentIntentFailed($event->data->object);
                default:
                    Log::warning('Unhandled Stripe webhook event type: ' . $event->type);
                    return response()->json(['status' => 'ignored']);
            }
        } catch (UnexpectedValueException $e) {
            Log::error('Stripe Webhook Error: ' . $e->getMessage(), [
                'message' => 'Invalid payload.',
                'error' => $e->getMessage(),
            ], 400);
            return response()->json(['message' => 'Invalid payload.'], 400);
        } catch (SignatureVerificationException $e) {
            Log::error('Stripe Webhook Error: ' . $e->getMessage(), [
                'message' => 'Invalid signature.',
                'error' => $e->getMessage(),
            ], 400);
            return response()->json(['message' => 'Invalid signature.'], 400);
        }
    }

    // handle successful payment
    protected function handleSuccessfulPayment($paymentIntent)
    {
        $payment = Payment::where('payment_intent_id', $paymentIntent->id)->first();
        if (!$payment) {
            Log::error('Payment not found for PaymentIntent ID: ' . $paymentIntent->id);
            return response()->json([
                'message' => 'Payment not found.',
                'success' => false,
            ], 404);
        }
        if (!$payment->isFinal()) {
            $payment->markAsCompleted($paymentIntent->id, [
                'stripe_data' => [
                    'amount' => $paymentIntent->amount / 100,
                    'currency' => $paymentIntent->currency,
                    'status' => $paymentIntent->status,
                    'description' => $paymentIntent->description,
                    'completed_at' => now()->toIso8601String(),
                ]
            ]);
        }
        return response()->json([
            'success' => true,
            'message' => 'Payment completed successfully.',
            'payment' => $payment,
            'order' => $payment->order,
        ]);
    }

    // handle failed payment
    protected function handleFailedPayment($paymentIntent)
    {
        $payment = Payment::where('payment_intent_id', $paymentIntent->id)->first();
        if (!$payment) {
            Log::error('Payment not found for PaymentIntent ID: ' . $paymentIntent->id);
            return response()->json([
                'message' => 'Payment not found.',
                'success' => false,
            ], 404);
        }
        if (!$payment->isFinal()) {
            $payment->markAsFailed($paymentIntent->id, [
                'stripe_data' => [
                    'error' => $paymentIntent->last_payment_error ? $paymentIntent->last_payment_error->message : 'Unknown error',
                    'status' => $paymentIntent->status,
                    'description' => $paymentIntent->description,
                    'failed_at' => now()->toIso8601String(),
                ]
            ]);
        }
        return response()->json([
            'success' => true,
            'message' => 'Payment failed.',
            'payment' => $payment,
            'order' => $payment->order,
        ]);
    }
}
