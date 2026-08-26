<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('folder_permissions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('folder_id')->constrained('folders')->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained('users');
            $table->string('permission', 20); // 'editor' or 'viewer'
            $table->timestamps();
            $table->unique(['folder_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('folder_permissions');
    }
};
