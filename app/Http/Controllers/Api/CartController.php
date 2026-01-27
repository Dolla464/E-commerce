<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Cart;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CartController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        // get authenticated user
        $user = $request->user();

        // 

        // get user's cart items and ignore global scope 'active' on products
        $cartItems = Cart::where('user_id', $user->id)->with(['product' => function ($query) {
            $query->withoutGlobalScope('active');
        }])->get();
        // $total = $cartItems->sum(function($item) {
        //     return $item->product->price * $item->quantity;
        // });
        $total = $cartItems->sum(function ($item) {
            // calculate total only if product active and exists
            if ($item->product && $item->product->is_active) {
                return $item->product->price * $item->quantity;
            }
            return 0;
        });
        return response()->json([
            'success' => true,
            'message' => 'Cart retrieved successfully',
            'cart' => $cartItems,
            'total' => round($total, 2),
        ]);
    }

    /**
     * Store a newly created resource in storage.
     * add item to cart
     */
    public function store(Request $request)
    {
        // get authenticated user
        $user = $request->user();

        // validate request
        $data = $request->validate([
            'product_id' => 'required|exists:products,id',
            'quantity' => 'required|integer|min:1',
        ]);

        // get cart item
        $cartItem = Cart::where('user_id', $user->id)
            ->where('product_id', $data['product_id'])
            ->first();

        if ($cartItem) {
            // if item already in cart, update quantity
            $cartItem->quantity += $data['quantity'];
            $cartItem->save();
            $statusCode = 200;
            $message = 'Cart item quantity updated successfully';
        } else {
            // else create new cart item
            $cartItem = Cart::create([
                'user_id' => $user->id,
                'product_id' => $data['product_id'],
                'quantity' => $data['quantity'],
            ]);
            $statusCode = 201;
            $message = 'Product added to cart successfully';
        }
        return response()->json([
            'success' => true,
            'message' => $message,
            'cart_item' => $cartItem,
        ], $statusCode);
    }

    /**
     * Display the specified resource.
     */
    public function show(Cart $cart)
    {
       //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Cart $cart)
    {
        // enshure user owns the cart item
        $user = $request->user();

        if ($cart->user_id !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 403);
        }
         // validate request
        $data = $request->validate([
            'quantity' => 'required|integer|min:1',
        ]);

        // update cart item quantity
        $cart->update(['quantity' => $data['quantity']]);

        return response()->json([
            'success' => true,
            'message' => 'Cart item quantity updated successfully',
            'cart_item' => $cart,
        ], 200);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request, Cart $cart)
    {
        // enshure user owns the cart item
        $user = $request->user();
        if ($cart->user_id !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 403);
        }
        // delete cart item
        $cart->delete();

        return response()->json([
            'success' => true,
            'message' => 'Cart item removed successfully',
        ], 200);
    }

    /**
     * Remove all items from the cart.
     */
    public function clear(Request $request)
    {
        // get authenticated user
        $user = $request->user();

        // delete all cart items for the user
        Cart::where('user_id', $user->id)->delete();

        return response()->json([
            'success' => true,
            'message' => 'All cart items removed successfully',
        ], 200);
    }
}
