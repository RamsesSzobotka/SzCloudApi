<?php

use App\Http\Controllers\StorageController;
use Illuminate\Support\Facades\Route;

Route::prefix("/storage")->middleware("auth:api")->group(function (){

    Route::get("/info", [StorageController::class, "getStorageInfo"]);
    Route::post("/verify", [StorageController::class, "storageVerify"]);

    Route::prefix("/folder")->group(function (){
        Route::get("/check-name", [StorageController::class, "checkFolderName"]);
        Route::get("/content/{folder_Id?}", [StorageController::class, "getFolderContent"]);
        Route::get("/{folder_Id}", [StorageController::class, "getFolderInfo"]);
        Route::post("/", [StorageController::class, "postFolder"]);
        Route::patch("/{folder_id}/rename", [StorageController::class, "renameFolder"]);
        Route::patch("/{folder_id?}/move", [StorageController::class, "moveFolder"]);
        Route::delete("/{folder_id}", [StorageController::class, "deleteFolder"]);
        Route::post("/{folder_id}/restore", [StorageController::class, "restoreFolder"]);
    });
   
    Route::prefix("/file")->group(function (){
        Route::get("/check-name", [StorageController::class, "checkFileName"]);
        Route::get("/{file_id}", [StorageController::class, "getFile"]);
        Route::post("/", [StorageController::class, "postFile"]);
        Route::patch("/{file_id}/rename", [StorageController::class, "renameFile"]);
        Route::patch("/{file_id}/move", [StorageController::class, "moveFile"]);
        Route::delete("/{file_id}", [StorageController::class, "deleteFile"]);
        Route::post("/{file_id}/restore", [StorageController::class, "restoreFile"]);
        Route::get("/{file_id}/download",[StorageController::class, "download"]);
    });

    Route::prefix("/trash")->group(function (){
        Route::get("/", [StorageController::class, "getTrash"]);
        Route::delete("/", [StorageController::class, "deleteTrash"]);
        Route::delete("/{id}/permanent",[StorageController::class, "deletePermanent"]);
    });
});
