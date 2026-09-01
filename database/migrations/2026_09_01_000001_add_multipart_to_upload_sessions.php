<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('upload_sessions', function (Blueprint $table) {
            $table->string('upload_id')->nullable()->after('status');
            $table->integer('total_parts')->default(0)->after('upload_id');
            $table->json('parts')->nullable()->after('total_parts');
        });
    }

    public function down(): void
    {
        Schema::table('upload_sessions', function (Blueprint $table) {
            $table->dropColumn(['upload_id', 'total_parts', 'parts']);
        });
    }
};
