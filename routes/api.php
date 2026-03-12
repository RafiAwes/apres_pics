<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\PageController;
use App\Http\Controllers\Api\{AdminController, AuthController, EventController, FaceNetController, GuestController, ProfileController, SubscriptionController, UserController, WebhookController};

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::group(['controller' => AuthController::class, 'prefix' => 'auth'], function () {
    Route::post('/register', 'register');
    Route::post('/verify-email', 'verifyEmail');
    Route::post('/resend-otp', 'resendOtp');
    Route::post('/forgot-password', 'forgotPassword');
    Route::post('/reset-password', 'resetPassword');
    Route::post('/login', 'login');
    Route::post('/logout', 'logout')->middleware('auth:api');
    Route::post('/change-password', 'changePassword')->middleware('auth:api');
});

Route::group(['controller' => PageController::class], function () {
    Route::get('/page/{key}', 'PageShow');
    Route::get('/faqs', 'index');
});

Route::group(['middleware' => ['auth:api']], function () {
    Route::group(['controller' => ProfileController::class], function () {
        Route::post('/update-profile', 'updateProfile');
        Route::post('/update-avatar', 'updateAvatar');
        Route::post('/update-password', 'updatePassword');
        Route::post('/update-user-name', 'updateUserName');
        Route::get('/get-profile', 'getProfile');

        //EVENT ACTIVITIES FOR BOTH ADMIN AND USER
        Route::get('users/events/contents/{event}', [EventController::class, 'eventContents']); //both admin and user can use it.
        Route::delete('/events/delete/{id}', [EventController::class, 'deleteEvent']);
    });

    Route::post('/payment/create', [SubscriptionController::class, 'createPayment']);

    Route::post('/events/search-face', [FaceNetController::class, 'search']);
});


Route::group(['prefix' => 'admin', 'middleware' => ['auth:api', 'role:admin']], function () {
    Route::group(['controller' => UserController::class], function () {
        Route::get('/users', 'UserList');
        Route::post('/ban-user/{user}', 'banUser');
    });
    Route::group(['controller' => PageController::class], function () {
        Route::post('/page', 'CreateOrUpdatePage');
        Route::post('/faqs', 'store');
        Route::post('/faqs/{id}', 'update');
        Route::delete('/faqs/{id}', 'destroy');
    });
    Route::group(['controller' => AdminController::class], function () {
        Route::get('/dashboard-stats', 'dashboardStats');
        Route::get('/subscriptions', 'subscriptionList');
        Route::get('/revenue/total', 'totalRevenue');
        Route::get('/revenue/weekly', 'currentWeeklyRevenue');
        Route::get('/revenue/monthly', 'currentMonthlyRevenue');
    });
    Route::get('/events', [EventController::class, 'events']);
    Route::get('/events/details/{id}', [EventController::class, 'eventDetails']);
    Route::delete('/events/delete/{id}', [EventController::class, 'deleteEvent']);
});

Route::group(['prefix' => 'users', 'middleware' => ['auth:api', 'role:user|admin']], function () {
    Route::get('/events', [EventController::class, 'events']);
    Route::post('/events', [EventController::class, 'createEvent']);
    Route::post('/events/update/{id}', [EventController::class, 'updateEvent']);
    
    Route::post('/events/content', [EventController::class, 'UploadContent']);
    Route::delete('/events/content/{id}', [EventController::class, 'deleteContent']);
    // Route::get('/events/contents/{event}', [EventController::class, 'eventContents']);
    Route::get('/events/generate-password', [EventController::class, 'generateEventPassword']);
    Route::get('/events/details/{id}', [EventController::class, 'eventDetails']);
    Route::post('/events/content/update/{id}', [EventController::class, 'editContent']);
    Route::get('/events/guest-list/{eventId}', [EventController::class, 'eventGuestList']);
    Route::post('/events/set-password/{eventId}', [EventController::class, 'setEventPassword']);
    Route::delete('/events/delete-multiple', [EventController::class, 'deleteMultipleEvents']);
});


Route::group(['middleware' => ['auth:api']], function () {
    Route::group(['controller' => GuestController::class, 'prefix' => 'guest'], function () {
        Route::post('/send-invitation', 'sendInvitation');
        Route::post('/edit-email', 'editEmail');
        Route::post('/send-again', 'sendAgain');
        Route::delete('/delete/{id}', 'deleteGuest');
    });
});

// guest module (public)
Route::group(['controller' => GuestController::class, 'prefix' => 'guest'], function () {
    Route::get('/validate-link/{guestId}/{eventId}', 'validateLink')->name('guest.view.event');
    Route::post('/access-event', 'verifyPassword');
});

Route::post('/stripe/webhook', [WebhookController::class, 'handleWebhook']);
