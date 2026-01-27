<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class TestController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/health",
     *     tags={"Health"},
     *     summary="Health Check",
     *     @OA\Response(response="200", description="OK")
     * )
     */
    
    public function check()
    {
        return response()->json(['status' => 'ok']);
    }
}