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

    Route::get("/folders/hierarchy", [StorageController::class, "getFolderHierarchy"]);
   
    Route::prefix("/file")->group(function (){
        Route::get("/check-name", [StorageController::class, "checkFileName"]);
        Route::get("/{file_id}", [StorageController::class, "getFile"]);
        Route::post("/", [StorageController::class, "postFile"]);
        Route::put("/{file_id}", [StorageController::class, "replaceFile"]);
        Route::patch("/{file_id}/rename", [StorageController::class, "renameFile"]);
        Route::patch("/{file_id}/move", [StorageController::class, "moveFile"]);
        Route::delete("/{file_id}", [StorageController::class, "deleteFile"]);
        Route::post("/{file_id}/restore", [StorageController::class, "restoreFile"]);
        Route::get("/{file_id}/download",[StorageController::class, "download"]);
        Route::get("/{file_id}/versions/check", [StorageController::class, "checkVersions"]);
        Route::get("/{file_id}/versions", [StorageController::class, "getVersions"]);
        Route::post("/{file_id}/versions/restore-back", [StorageController::class, "restoreBackVersion"]);
        Route::post("/{file_id}/versions/restore-front", [StorageController::class, "restoreFrontVersion"]);
        Route::get("/{file_id}/activity", [StorageController::class, "getActivity"]);
        Route::post("/{file_id}/activity/restore-back", [StorageController::class, "restoreBackActivity"]);
        Route::post("/{file_id}/activity/restore-front", [StorageController::class, "restoreFrontActivity"]);
    });

    Route::prefix("/upload")->group(function (){
        Route::post("/init", [StorageController::class, "initUpload"]);
        Route::put("/{session_id}/chunk", [StorageController::class, "uploadChunk"]);
        Route::post("/{session_id}/complete", [StorageController::class, "completeUpload"]);
        Route::post("/{session_id}/abort", [StorageController::class, "abortUpload"]);
    });

    Route::prefix("/trash")->group(function (){
        Route::get("/", [StorageController::class, "getTrash"]);
        Route::delete("/", [StorageController::class, "deleteTrash"]);
        Route::delete("/{id}/permanent",[StorageController::class, "deletePermanent"]);
    });
});
