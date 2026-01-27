<?php

namespace App\Http\Controllers\Api;

use App\Enum\OrderStatus;
use App\Enum\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Models\Cart;
use App\Models\Order;
use App\Models\Product;
use Faker\Provider\Payment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CheckOutController extends Controller
{
    // Checkout to place order and save it to database
    public function checkout(Request $request)
    {
        // get authenticated user
        $user = $request->user();

        // validate request
        $request->validate([
            'shipping_name' => 'required|string|max:255',
            'shipping_address' => 'required|string|max:255',
            'shipping_city' => 'required|string|max:100',
            'shipping_state' => 'required|string|max:100',
            'shipping_zip_code' => 'required|string|max:20',
            'shipping_country' => 'required|string|max:100',
            'shipping_phone' => 'required|string|max:20',
            'payment_method' => 'required|string|in:credit_card,paypal,cod',
            'notes' => 'nullable|string',
        ]);

        // create order and save to database
        // (Implementation of order creation goes here)
        $cartItems = Cart::where('user_id', $user->id)->with('product')->get();

        if ($cartItems->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'Cart is empty. Cannot proceed to checkout.',
            ], 400);
        }

        $subtotal = 0;
        $item = [];

        foreach ($cartItems as $cartItem) {
            $product = Product::withoutGlobalScope('active')->find($cartItem->product_id);
            // check if product exists
            if (!$product || !$product->is_active) {
                return response()->json([
                    'success' => false,
                    'message' => 'Product ' . ($product ? $product->name : 'Unknown') . ' is no longer available for purchase. Please remove it from your cart to proceed.',
                ], 400);
            }
            // check if product is active
            if (!$product->is_active) {
                return response()->json([
                    'success' => false,
                    'message' => 'Product ' . ($product ? $product->name : 'Unknown') . ' is not available for purchase.',
                ], 400);
            }

            // check product stock
            if ($cartItem->quantity > $product->stock) {
                return response()->json([
                    'success' => false,
                    'message' => 'Not enough stock for product ' . $product->name . '. Available stock: ' . $product->stock,
                ], 400);
            }
            $itemSubTotal = round($product->price * $cartItem->quantity, 2);
            $subtotal += $itemSubTotal;
            $item[] = [
                'product_id' => $product->id,
                'product_name' => $product->name,
                'product_sku' => $product->sku,
                'quantity' => $cartItem->quantity,
                'price' => $product->price,
                'subtotal' => $itemSubTotal,
            ];
        }
        // tax and shipping cost calculation
        $taxRate = 0.15; // 15% tax
        $tax = round($subtotal * $taxRate, 2);
        $shippingCost = 10.00; // flat shipping cost
        $total = round($subtotal + $tax + $shippingCost, 2);

        // create order with db transaction
        DB::beginTransaction();
        try {
            $order = new Order([
                'user_id' => $user->id,
                'status' => OrderStatus::PENDING,
                'shipping_name' => $request->shipping_name,
                'shipping_address' => $request->shipping_address,
                'shipping_city' => $request->shipping_city,
                'shipping_state' => $request->shipping_state,
                'shipping_zip_code' => $request->shipping_zip_code,
                'shipping_country' => $request->shipping_country,
                'shipping_phone' => $request->shipping_phone,
                'subtotal' => $subtotal,
                'tax' => $tax,
                'shipping_cost' => $shippingCost,
                'total' => $total,
                'payment_method' => $request->payment_method,
                'payment_status' => PaymentStatus::PENDING,
                'order_number' => Order::generateOrderNumber(),
                'notes' => $request->notes,
            ]);
            $user->orders()->save($order);

            // save order items
            foreach ($item as $orderItem) {
                $order->orderItems()->create($orderItem);
                // reduce product stock
                $product = Product::where('id', $cartItem['product_id'])
                    ->decrement('stock', $orderItem['quantity']);
            }
            // clear user's cart
            Cart::where('user_id', $user->id)->each(function ($cartItem) {
                $cartItem->delete();
            });
            DB::commit();
            return response()->json([
                'success' => true,
                'message' => 'Order placed successfully',
                'order' => $order->load('orderItems'), // return the created order details with items
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to create order: ' . $e->getMessage(),
            ], 500);
        }
        return response()->json([
            'success' => true,
            'message' => 'Order placed successfully',
            // 'order' => $order, // return the created order details
        ], 201);
    }

    // simulate index method to return a list of orders called orderhistory
    public function orderHistory(Request $request)
    {
        $user = $request->user();
        $orders = $user->orders()->with('orderItems')->get();
        return response()->json([
            'success' => true,
            'message' => 'Order history retrieved successfully',
            'orders' => $orders,
        ], 200);
    }

    // simulate show method to return a single order by id
    public function showOrder(Request $request, $id)
    {
        $user = $request->user();
        $order = $user->orders()->with('orderItems')->find($id);
        if (!$order) {
            return response()->json([
                'success' => false,
                'message' => 'Order not found',
            ], 404);
        }
        return response()->json([
            'success' => true,
            'message' => 'Order retrieved successfully',
            'order' => $order,
        ], 200);
    }
}
