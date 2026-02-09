<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\{AdminController, AuthController, EventController, FaceNetController, GuestController, PaymentController, ProfileController, SubscriptionController, UserController};
use App\Http\Controllers\PageController;

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
        Route::post('/update-avatar','updateAvatar');
    });
    
    // Route::post('/subscription/purchase', [SubscriptionController::class, 'purchase']);
    Route::post('/subscription/payment-intent', [SubscriptionController::class, 'createPaymentIntent']);
    Route::post('/subscription/confirm', [SubscriptionController::class, 'confirmSubscription']);

    // Route::post('/events/upload-photo', [FaceNetController::class, 'uploadPhoto']);
    Route::post('/events/search-face', [FaceNetController::class, 'search']);
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
    Route::group(['controller' => AdminController::class], function () {
        Route::get('/dashboard-stats', 'dashboardStats');
    });
});

Route::group(['prefix' => 'users', 'middleware' => ['auth:api', 'role:user']], function () {
    Route::get('/events', [EventController::class, 'events']);
    Route::post('/events', [EventController::class, 'createEvent']);
    Route::put('/events/{id}', [EventController::class, 'updateEvent']);
    Route::delete('/events/{id}', [EventController::class, 'deleteEvent']);
    Route::post('/events/content', [EventController::class, 'UploadContent']);
    Route::delete('/events/content/{id}', [EventController::class, 'deleteContent']);
    Route::get('/events/contents/{event}', [EventController::class, 'eventContents']);
    Route::get('/events/generate-password', [EventController::class, 'generateEventPassword']);
    Route::post('/events/set-password/{eventId}', [EventController::class, 'setEventPassword']);
});


// guest module
Route::group(['controller' => GuestController::class, 'prefix' => 'guest'], function () {
    Route::post('/send-invitation', 'sendInvitation');
    Route::get('/validate-link/{guestId}/{eventId}', 'validateLink')->name('guest.view.event');
    Route::post('/access-event', 'verifyPassword');
});
