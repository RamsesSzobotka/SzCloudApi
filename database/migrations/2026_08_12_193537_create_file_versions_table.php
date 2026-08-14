<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('file_versions', function (Blueprint $table) {
            $table->uuid("id")->primary();
            $table->foreignUuid("file_id")->constrained("files")->cascadeOnDelete();
            $table->integer("version");
            $table->string("storage_name");
            $table->text("storage_path");
            $table->string("mime_type");
            $table->unsignedBigInteger("size");
            $table->char("hash", 64)->nullable();
            $table->unique(["file_id", "version"]);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('file_versions');
    }
};
