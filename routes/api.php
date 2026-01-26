<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\PageController;
use App\Http\Controllers\Api\{AuthController, ProfileController, UserController};

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
    Route::post('/logout','logout')->middleware('auth:api');
    Route::post('/change-password', 'changePassword')->middleware('auth:api');
});

Route::group(['controller' => PageController::class], function () {
    Route::get('/page/{key}', 'PageShow');
    Route::get('/faqs', 'index');
    
});

Route::group(['middleware' => ['auth:api']], function () {
    Route::group(['controller' => ProfileController::class], function () {
        Route::post('/update-profile', 'updateProfile');
    });
});

Route::group(['prefix' => 'admin', 'middleware' => ['auth:api','role:admin']], function () {
    Route::group(['controller' => UserController::class], function () {
        Route::get('/users', 'UserList');
        Route::post('/ban-user/{user}', 'banUser');
    });
    Route::group(['controller' => PageController::class], function () {
        Route::post('/page', 'CreateOrUpdatePage');
        Route::post('/faqs', 'store');
        Route::put('/faqs/{id}', 'update');     
        Route::delete('/faqs/{id}', 'destroy');
    });
});