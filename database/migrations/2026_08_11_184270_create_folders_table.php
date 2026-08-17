<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('folders', function (Blueprint $table) {
            $table->uuid("id")->primary();
            $table->foreignUuid("user_id")->constrained("users");
            $table->foreignUuid("parent_id")->nullable();
            $table->string("name");
            $table->boolean("is_system")->default(false);
            $table->timestamps();
            $table->softDeletes();
        });

        DB::statement('ALTER TABLE folders ADD CONSTRAINT folders_parent_id_foreign FOREIGN KEY (parent_id) REFERENCES folders(id)');

        DB::statement('CREATE UNIQUE INDEX folders_user_id_parent_id_name_unique ON folders (user_id, parent_id, name) WHERE deleted_at IS NULL');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('folders');
    }
};
