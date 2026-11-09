<?php

declare(strict_types=1);

use App\Http\Controllers\ConversionBeaconController;
use Illuminate\Support\Facades\Route;

/*
| Beacon / telemetry: géén StartSession. sendBeacon kan parallel met form-POSTs
| lopen; een late sessie-save zou anders geflashte errors/old input overschrijven.
| Wel EncryptCookies zodat gp_flow gelezen/gezet kan worden.
*/

Route::post('/meet/event', ConversionBeaconController::class)
    ->middleware('throttle:60,1')
    ->name('meet.event');
