<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\{AuthController, UserController};

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::group(['controller' => AuthController::class, 'prefix' => 'auth'], function () {
    Route::post('/register', 'register');
    Route::post('/verify-email', 'verifyEmail');
    Route::post('/resend-otp', 'resendOtp');
    Route::post('/forgot-password', 'forgotPassword');
    Route::post('/reset-password', 'resetPassword');
    Route::post('/login','login');
    Route::post('/logout','logout');
});

Route::group(['prefix' => 'admin'], function () {
    Route::group(['controller' => UserController::class], function () {
        Route::get('/users', 'UserList');
        Route::post('/ban-user/{user}', 'banUser');
    });    
});