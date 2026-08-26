<?php

use App\Http\Controllers\PermissionController;
use Illuminate\Support\Facades\Route;

Route::prefix('storage')->middleware('auth:api')->group(function () {

    // ─── File Permissions ───────────────────────────────────
    Route::post('/file/{fileId}/permissions', [PermissionController::class, 'storeFilePermission']);
    Route::get('/file/{fileId}/permissions', [PermissionController::class, 'getFilePermissions']);
    Route::patch('/file/{fileId}/permissions/{userId}', [PermissionController::class, 'updateFilePermission']);
    Route::delete('/file/{fileId}/permissions/{userId}', [PermissionController::class, 'revokeFilePermission']);

    // ─── Folder Permissions ─────────────────────────────────
    Route::post('/folder/{folderId}/permissions', [PermissionController::class, 'storeFolderPermission']);
    Route::get('/folder/{folderId}/permissions', [PermissionController::class, 'getFolderPermissions']);
    Route::delete('/folder/{folderId}/permissions/{userId}', [PermissionController::class, 'revokeFolderPermission']);
});
