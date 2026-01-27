<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/docs/{jsonFile?}', function ($jsonFile = null) {
    $jsonFile = $jsonFile ?: 'api-docs.json';
    $path = storage_path('api-docs/' . $jsonFile);
    
    if (!file_exists($path)) {
        abort(404, 'Documentation file not found: ' . $jsonFile);
    }
    
    return response()->file($path, [
        'Content-Type' => 'application/json',
        'Access-Control-Allow-Origin' => '*',
        'Access-Control-Allow-Methods' => 'GET, POST, PUT, DELETE, OPTIONS',
        'Access-Control-Allow-Headers' => 'Content-Type, Authorization'
    ]);
})->where('jsonFile', '.*');