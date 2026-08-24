<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('file_activities', function (Blueprint $table) {
            $table->uuid("id")->primary();
            $table->foreignUuid("file_id")->constrained("files")->cascadeOnDelete();
            $table->string("action"); // create, update
            $table->json("old_values")->nullable();
            $table->json("new_values")->nullable();
            $table->boolean('is_undone')->default(false);
            $table->timestamps();

            $table->index("file_id");
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('file_activities');
    }
};
