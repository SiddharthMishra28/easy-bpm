<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stage_tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('flow_stage_id')->constrained('flow_stages')->onDelete('cascade');
            $table->string('name');
            $table->text('description')->nullable();
            $table->boolean('is_mandatory')->default(true); // Mandatory tasks block advancement
            $table->integer('sequence');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stage_tasks');
    }
};
