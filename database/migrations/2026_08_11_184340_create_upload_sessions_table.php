<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('upload_sessions', function (Blueprint $table) {
            $table->uuid("id")->primary();
            $table->foreignUuid("user_id")->constrained("users");
            $table->foreignUuid("folder_id")->nullable()->constrained("folders");
            $table->string("file_name");
            $table->string("mime_type");
            $table->bigInteger("total_size");
            $table->bigInteger("uploaded_size")->default(0);
            $table->text("storage_path")->nullable();
            $table->string("status", 30)->default("pending");
            $table->timestamp("expires_at");
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('upload_sessions');
    }
};
