<?php

use App\Http\Controllers\EventOperationsController;
use App\Http\Controllers\GivingController;
use App\Http\Controllers\GroupDiscoveryController;
use App\Http\Controllers\LibraryController;
use App\Http\Controllers\LiveStreamWebhookController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\PlaybackDiagnosticController;
use App\Http\Controllers\PublicHomeController;
use App\Http\Controllers\PublicServiceController;
use App\Http\Controllers\PublicSiteController;
use App\Http\Controllers\ServiceChatController;
use App\Http\Controllers\ServicePollController;
use App\Http\Controllers\ServiceResponseController;
use App\Http\Controllers\StaffCommunityController;
use App\Http\Controllers\StaffMeetingsMediaController;
use App\Http\Controllers\StaffPeopleController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    Route::get('/public/site', PublicSiteController::class);
    Route::get('/public/home', PublicHomeController::class);
    Route::get('/public/pages/{slug}', [PageController::class, 'show']);
    Route::get('/public/services/{service}', [PublicServiceController::class, 'show']);
    Route::get('/public/services/{service}/chat', [ServiceChatController::class, 'index']);
    Route::post('/public/services/{service}/chat', [ServiceChatController::class, 'store'])->middleware('throttle:20,1');
    Route::post('/public/services/{service}/responses', [ServiceResponseController::class, 'connection'])->middleware('throttle:10,1');
    Route::post('/public/services/{service}/prayer-requests', [ServiceResponseController::class, 'prayer'])->middleware('throttle:5,1');
    Route::get('/public/services/{service}/polls', [ServicePollController::class, 'publicIndex']);
    Route::post('/public/services/{service}/polls/{poll}/responses', [ServicePollController::class, 'respond'])->middleware('throttle:10,1');
    Route::post('/public/services/{service}/playback-diagnostics', [PlaybackDiagnosticController::class, 'store'])->middleware('throttle:120,1');
    Route::post('/public/contact', [StaffPeopleController::class, 'visitor'])->middleware('throttle:10,1');
    Route::get('/public/announcements', [StaffCommunityController::class, 'announcements']);
    Route::get('/public/events', [StaffCommunityController::class, 'publicEvents']);
    Route::post('/public/events/{event}/registrations', [EventOperationsController::class, 'register'])->middleware('throttle:10,1');
    Route::get('/public/media', [StaffMeetingsMediaController::class, 'media']);
    Route::get('/public/libraries/{library}', [LibraryController::class, 'publicIndex']);
    Route::get('/public/sermon-series/{series}', [LibraryController::class, 'publicSeries']);
    Route::get('/public/libraries/{library}/{asset}/download', [LibraryController::class, 'download']);
    Route::get('/public/giving/funds', [GivingController::class, 'funds']);
    Route::post('/public/giving/intents', [GivingController::class, 'intent'])->middleware('throttle:10,1');
    Route::get('/public/groups', [GroupDiscoveryController::class, 'index']);
    Route::post('/public/groups/{group}/join-requests', [GroupDiscoveryController::class, 'request'])->middleware('throttle:10,1');
    Route::post('/webhooks/giving/{provider}', [GivingController::class, 'webhook'])->middleware('throttle:60,1');
    Route::post('/webhooks/livestream/{provider}', LiveStreamWebhookController::class)->middleware('throttle:120,1');
});
