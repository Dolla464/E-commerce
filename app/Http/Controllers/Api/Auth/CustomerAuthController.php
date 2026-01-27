<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class CustomerAuthController extends Controller
{
    //register
    public function register(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8|confirmed',
        ]);

        //Hash password
        $data['password'] = Hash::make($request->password);

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'],
            'type' => 'customer',
        ]);

        //Create token
        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => $user,
            'message' => 'User created successfully',
        ], 201);
    }

    //login
    public function login(Request $request)
    {
        //Validate data
        $data = $request->validate([
            'email' => 'required|string|email|max:255',
            'password' => 'required|string|min:8',
        ]);
        
        //Check email
        $user = User::where('email', $data['email'])->first();

        //Check password
        if (!$user || !Hash::check($data['password'], $user->password)) {
            return response()->json([
                'message' => 'Invalid credentials',
            ], 401);
        }
        //generate token
        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => $user,
            'message' => 'Login successful',
        ], 200);
    }

    //logout
    public function logout(Request $request)
    {
        //revoke token
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Logged out successfully',
        ], 200);
    }

    //get user
    public function me(Request $request)
    {
        //retrieve user
        return response()->json([
            'user' => $request->user(),
            'message' => 'User retrieved successfully',
        ], 200);
    }

    //get access token
    public function getAccessToken(Request $request)
    {
        //retrieve access token
        return response()->json([
            'access_token' => $request->user()->currentAccessToken(),
            'message' => 'Access token retrieved successfully',
        ], 200);
    }
}
