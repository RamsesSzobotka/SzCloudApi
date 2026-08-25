<?php

use App\Http\Controllers\ShareLinkController;
use Illuminate\Support\Facades\Route;

Route::prefix("/share")->group(function () {
    Route::post("/{token}", [ShareLinkController::class, "getShareLink"]);
    Route::get("/{token}/config", [ShareLinkController::class, "getShareLinkConfig"]);

    Route::middleware("auth:api")->group(function () {
        Route::get("/{token}/data", [ShareLinkController::class, "getShareLinkData"]);
        Route::post("/file/{file_id}", [ShareLinkController::class, "createShareLink"]);
    });
});
