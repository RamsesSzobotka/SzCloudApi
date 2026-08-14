<?php

use App\Http\Controllers\StorageController;
use Illuminate\Support\Facades\Route;

Route::prefix("/storage")->middleware("api")->group(function (){

    Route::get("/folders/{folder_Id?}", [StorageController::class, "getFolderContent"]);
    Route::get("/folder/{folder_Id?}", [StorageController::class, "getFolderInfo"]);
    Route::post("/folder", [StorageController::class, "postFolder"]);
    Route::patch("/folder/{folder_id}/rename", [StorageController::class, "renameFolder"]);
    Route::patch("/folder/{folder_id}/move", [StorageController::class, "moveFolder"]);
    Route::delete("/folder/{folder_id}", [StorageController::class, "deleteFolder"]);
    Route::post("/folder/{folder_id}/restore", [StorageController::class, "restoreFolder"]);

    Route::get("/file/{file_id}", [StorageController::class, "getFile"]);
    Route::patch("/file/{file_id}/rename", [StorageController::class, "renameFile"]);
    Route::patch("/file/{file_id}/move", [StorageController::class, "moveFile"]);
    Route::delete("/file/{file_id}", [StorageController::class, "deleteFile"]);
    Route::post("/file/{file_id}/restore", [StorageController::class, "restoreFile"]);
});
