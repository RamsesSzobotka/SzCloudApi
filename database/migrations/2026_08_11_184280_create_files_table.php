<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('files', function (Blueprint $table) {
            $table->uuid("id")->primary();
            $table->foreignUuid("user_id")->constrained("users")->cascadeOnDelete();
            $table->foreignUuid("folder_id")->nullable()->constrained("folders", "id")->nullOnDelete();
            $table->string("original_name");
            $table->string("storage_name")->unique();
            $table->text("storage_path");
            $table->string("mime_type");
            $table->string("extension")->nullable();
            $table->unsignedBigInteger("size");
            $table->char("hash", 64)->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(["user_id", "folder_id"]);
            $table->index(["user_id", "deleted_at"]);
            $table->index("mime_type");
            $table->index("extension");
            $table->index("created_at");
            $table->index("hash");
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('files');
    }
};
