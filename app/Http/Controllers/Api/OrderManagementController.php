<?php

namespace App\Http\Controllers\Api;

use App\Enum\OrderStatus;
use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use PhpParser\Node\Stmt\TryCatch;

class OrderManagementController extends Controller
{
    // index
    public function index(Request $request)
    {
        // validate request parameters
        $request->validate([
            'status' => 'in:' . implode(',', OrderStatus::values()),
            'from_date' => 'date',
            'to_date' => 'date',
        ]);
        // build query with optional filters
        $query = Order::with(['user', 'orderItems_product']);

        // filter by status if provided
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // filter by date range if provided
        if ($request->has('from_date')) {
            $query->whereDate('created_at', '>=', $request->from_date);
        }
        if ($request->has('to_date')) {
            $query->whereDate('created_at', '<=', $request->to_date);
        }

        // get orders with pagination
        $orders = $query->latest()->paginate(15);

        return response()->json([
            'orders' => $orders,
            'available_statuses' => OrderStatus::values(),
        ]);
    }

    // show
    public function show(Order $order)
    {
        // load all related data for admin view
        $order->load([
            'user',
            'orderItems.product',
            'statusHistory.changedBy',
        ]);

        return response()->json([
            'order' => $order,
            'available_transitions' => $order->getAvailableTransitions(),
        ]);
    }

    // update status
    public function updateStatus(Request $request, Order $order)
    {
        // validate the new status
        $request->validate([
            'status' => 'required|string|in:' . implode(',', OrderStatus::values()),
            'notes' => 'nullable|string|max:500',
        ]);

        try {
            // convert string to enum
            $newStatus = OrderStatus::from($request->status);

            // attempt the transition
            $ok = $order->transitionTo($newStatus, Auth::user(), $request->notes);
            if (! $ok) {
                return response()->json([
                    'success' => false,
                    'message' => "Invalid status transition from {$order->status->value} to {$newStatus->value}.",
                    'allowed' => $order->getAllowedTransitions(), // optional helpful info
                ], 422);
            }

            // reload order with fresh data
            $order->load(['statusHistory.changedBy']);

            return response()->json([
                'success' => true,
                'message' => "Order status updated to {$newStatus}",
                'order' => $order,
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'success' => false,
                'message' => $th->getMessage(),
            ], 400);
        }
    }

    // cancel
    public function cancel(Request $request, Order $order)
    {
        // validation
        $request->validate([
            'notes' => 'required|string|max:500',
        ]);

        try {
            // check of order can be canceled
            if (!$order->canBeCancelled()) {
                return response()->json([
                    'success' => false,
                    'message' => "This order cannot be cancelled in its current status",
                ], 422);
            }

            // cancel the order
            $ok = $order->transitionTo(OrderStatus::CANCELLED, Auth::user(), "Cancelled: " . $request->notes);
            if (!$ok) {
                return response()->json([
                    'success' => false,
                    'message' => "Invalid status transition from {$order->status->value} to cancelled.",
                    'allowed' => $order->getAllowedTransitions(),
                ], 422);
            }

            return response()->json([
                'success' => true,
                'message' => "Order has been cancelled",
                'order' => $order->fresh(['statusHistory.changedBy']),
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'success' => false,
                'message' => $th->getMessage(),
            ], 500);
        }
    }
}
