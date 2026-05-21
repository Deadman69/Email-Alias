<?php

use App\Http\Controllers\Scim\UserController;
use Illuminate\Support\Facades\Route;

Route::prefix('scim/v2')
    ->middleware(['throttle:api', 'scim.auth'])
    ->group(function (): void {
        Route::get('Users', [UserController::class, 'index']);
        Route::post('Users', [UserController::class, 'store']);
        Route::get('Users/{user}', [UserController::class, 'show']);
        Route::put('Users/{user}', [UserController::class, 'update']);
        Route::patch('Users/{user}', [UserController::class, 'patch']);
        Route::delete('Users/{user}', [UserController::class, 'destroy']);

        Route::get('ServiceProviderConfig', fn () => response()->json([
            'schemas'        => ['urn:ietf:params:scim:schemas:core:2.0:ServiceProviderConfig'],
            'patch'          => ['supported' => true],
            'bulk'           => ['supported' => false, 'maxOperations' => 0, 'maxPayloadSize' => 0],
            'filter'         => ['supported' => true, 'maxResults' => 200],
            'changePassword' => ['supported' => false],
            'sort'           => ['supported' => false],
            'etag'           => ['supported' => false],
        ])->header('Content-Type', 'application/scim+json'));
    });
