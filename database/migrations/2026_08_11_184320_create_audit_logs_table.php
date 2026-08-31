<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->uuid("id")->primary();
            $table->foreignUuid("user_id")->nullable()->constrained("users");
            $table->string("action", 50);
            $table->string("resource_type", 50)->nullable();
            $table->uuid("resource_id")->nullable();
            $table->text("user_agent")->nullable();
            $table->jsonb("metadata")->nullable();
            $table->timestamp("created_at");
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
