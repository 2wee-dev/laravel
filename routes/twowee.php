<?php

use Illuminate\Support\Facades\Route;
use TwoWee\Laravel\Http\Controllers\ActionController;
use TwoWee\Laravel\Http\Controllers\AuthController;
use TwoWee\Laravel\Http\Controllers\DrilldownController;
use TwoWee\Laravel\Http\Controllers\LookupController;
use TwoWee\Laravel\Http\Controllers\MenuController;
use TwoWee\Laravel\Http\Controllers\SaveController;
use TwoWee\Laravel\Http\Controllers\ScreenController;
use TwoWee\Laravel\Http\Controllers\ValidateController;
use TwoWee\Laravel\Http\Middleware\TwoWeeAuthenticate;
use TwoWee\Laravel\Http\Middleware\TwoWeeMiddleware;

$prefix = config('twowee.prefix', '');

// Guest routes — no auth required
Route::group([
    'prefix' => $prefix,
    'middleware' => [TwoWeeMiddleware::class],
], function () {
    Route::get('/', [AuthController::class, 'entryPoint']);

    Route::get('auth/login', [AuthController::class, 'loginForm']);
    Route::post('auth/login', [AuthController::class, 'loginSubmit']);
});

// Authenticated routes — requires Bearer token
Route::group([
    'prefix' => $prefix,
    'middleware' => [TwoWeeMiddleware::class, TwoWeeAuthenticate::class],
], function () {
    Route::post('auth/logout', [AuthController::class, 'logout']);

    Route::get('menu/main', [MenuController::class, 'main']);

    // Lookup, validate, drilldown (resource-scoped)
    Route::get('lookup/{resource}/{fieldId}', [LookupController::class, 'search']);
    Route::get('validate/{resource}/{fieldId}/{value}', [ValidateController::class, 'validate']);
    Route::get('drilldown/{resource}/{fieldId}/{key}', [DrilldownController::class, 'drilldown']);

    // Lookup, validate, drilldown (global fallback)
    Route::get('lookup/{fieldId}', [LookupController::class, 'searchGlobal']);
    Route::get('validate/{fieldId}/{value}', [ValidateController::class, 'validateGlobal']);
    Route::get('drilldown/{fieldId}/{key}', [DrilldownController::class, 'drilldownGlobal']);

    // Screen routes
    Route::get('screen/{resource}/list', [ScreenController::class, 'list']);
    Route::get('screen/{resource}/card/new', [ScreenController::class, 'create']);
    Route::get('screen/{resource}/card/{id}', [ScreenController::class, 'show']);
    Route::post('screen/{resource}/card/new', [SaveController::class, 'store']);
    Route::post('screen/{resource}/card/{id}/save', [SaveController::class, 'update']);
    Route::post('screen/{resource}/card/{id}/delete', [SaveController::class, 'destroy']);

    // Screen actions
    Route::post('action/{resource}/{id}/{actionId}', [ActionController::class, 'execute']);
    Route::post('action/{resource}/{actionId}', [ActionController::class, 'executeGlobal']);
});
