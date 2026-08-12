<?php

use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::prefix("/user")->middleware("api")->group(function (){
    Route::get("/me",[UserController::class, "getMe"]);
    Route::put("/",[UserController::class, "putUser"]);
    Route::patch("/",[UserController::class, "patchPassword"]);
    Route::delete("/",[UserController::class, "deleteUser"]);

    #aun en desarrollo
    //Route::get("/{id}",[UserController::class, "getUser"]);
});