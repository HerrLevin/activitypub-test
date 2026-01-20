<?php

use App\Http\Controllers\WebfingerController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ActivityPubController;

Route::get('/', function () {
    return view('welcome');
});

// ActivityPub routes
Route::get('/.well-known/webfinger', [WebfingerController::class, 'webfinger']);
Route::get('/users/{username}', [ActivityPubController::class, 'actor']);
Route::get('/users/{username}/outbox', [ActivityPubController::class, 'outbox']);
Route::post('/users/{username}/inbox', [ActivityPubController::class, 'inbox']);
