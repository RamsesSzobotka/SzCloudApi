<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('file_permissions', function (Blueprint $table) {
            $table->uuid("id")->primary();
            $table->foreignUuid("file_id")->constrained("files")->cascadeOnDelete();
            $table->foreignUuid("user_id")->constrained("users");
            $table->string("permission", 20);
            $table->timestamps();

            $table->unique(["file_id", "user_id"]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('file_permissions');
    }
};
