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
        Schema::table('flows', function (Blueprint $table) {
            $table->foreignId('latest_version_id')->nullable()->constrained('flow_versions')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('flows', function (Blueprint $table) {
            $table->dropForeign(['latest_version_id']);
            $table->dropColumn('latest_version_id');
        });
    }
};
