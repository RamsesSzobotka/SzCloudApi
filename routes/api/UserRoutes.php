<?php

use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::prefix("/user")->middleware("auth:api")->group(function (){
    Route::put("/",[UserController::class, "putUser"]);
    Route::patch("/",[UserController::class, "patchPassword"]);
    Route::delete("/",[UserController::class, "deleteUser"]);


});