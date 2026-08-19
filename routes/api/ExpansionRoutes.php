<?php

use App\Http\Controllers\ExpansionController;
use Illuminate\Support\Facades\Route;

Route::prefix("/expansions")->group(function () {
    Route::get("/", [ExpansionController::class, "index"]);
    Route::get("/{id}", [ExpansionController::class, "show"]);
    Route::post("/{id}/buy", [ExpansionController::class, "buy"])->middleware("auth:api");
});
