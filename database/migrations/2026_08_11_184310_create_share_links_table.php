<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('share_links', function (Blueprint $table) {
            $table->uuid("id")->primary();
            $table->foreignUuid("file_id")->constrained("files")->cascadeOnDelete();
            $table->char("token_hash", 64)->unique();
            $table->timestamp("expires_at")->nullable();
            $table->integer("max_downloads")->nullable();
            $table->integer("download_count")->default(0);
            $table->string("password_hash")->nullable();
            $table->timestamps();
            $table->timestamp("revoked_at")->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('share_links');
    }
};
