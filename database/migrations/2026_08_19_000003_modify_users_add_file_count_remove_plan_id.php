<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->bigInteger("file_count")->default(0)->after("storage_used");
            $table->dropForeign(["plan_id"]);
            $table->dropColumn("plan_id");
            $table->bigInteger("storage_limit")->default(10737418240)->change();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignUuid("plan_id")->nullable()->after("storage_used")->constrained("plans");
            $table->dropColumn("file_count");
            $table->bigInteger("storage_limit")->default(5368709120)->change();
        });
    }
};
