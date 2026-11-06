<?php

use App\Http\Controllers\EventOperationsController;
use App\Http\Controllers\GivingController;
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
Route::post('/public/services/{service}/chat', [ServiceChatController::class, 'store']);
Route::post('/public/services/{service}/responses', [ServiceResponseController::class, 'connection']);
Route::post('/public/services/{service}/prayer-requests', [ServiceResponseController::class, 'prayer']);
    Route::get('/public/services/{service}/polls', [ServicePollController::class, 'publicIndex']);
Route::post('/public/services/{service}/polls/{poll}/responses', [ServicePollController::class, 'respond']);
Route::post('/public/services/{service}/playback-diagnostics', [PlaybackDiagnosticController::class, 'store']);
Route::post('/public/contact', [StaffPeopleController::class, 'contact']);
    Route::get('/public/announcements', [StaffCommunityController::class, 'announcements']);
    Route::get('/public/events', [StaffCommunityController::class, 'publicEvents']);
Route::post('/public/events/{event}/registrations', [EventOperationsController::class, 'register']);
    Route::get('/public/media', [StaffMeetingsMediaController::class, 'media']);
    Route::get('/public/libraries/{library}', [LibraryController::class, 'publicIndex']);
    Route::get('/public/sermon-series/{series}', [LibraryController::class, 'publicSeries']);
    Route::get('/public/libraries/{library}/{asset}/download', [LibraryController::class, 'download']);
    Route::get('/public/giving/funds', [GivingController::class, 'funds']);
    Route::get('/public/giving/options', [GivingController::class, 'options']);
Route::post('/public/giving/intents', [GivingController::class, 'intent']);
Route::get('/public/giving/donations/{donation}/status', [GivingController::class, 'status']);
Route::post('/webhooks/giving/{provider}', [GivingController::class, 'webhook']);
Route::post('/webhooks/livestream/{provider}', LiveStreamWebhookController::class);
});
