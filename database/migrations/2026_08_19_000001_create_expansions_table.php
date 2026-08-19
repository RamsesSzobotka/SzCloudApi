<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expansions', function (Blueprint $table) {
            $table->id();
            $table->string("name", 100)->unique();
            $table->bigInteger("storage_bytes");
            $table->integer("price_cents");
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expansions');
    }
};
