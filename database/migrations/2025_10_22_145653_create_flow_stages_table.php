<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('flow_stages', function (Blueprint $table) {
            $table->id();
            // Stages are tied to a specific immutable version
            $table->foreignId('flow_version_id')->constrained('flow_versions')->onDelete('cascade');
            $table->string('name');
            $table->integer('sequence')->comment('Order of the stage in the flow');
            $table->boolean('is_start_stage')->default(false);
            $table->boolean('is_end_stage')->default(false);
            $table->timestamps();

            $table->unique(['flow_version_id', 'sequence']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('flow_stages');
    }
};
