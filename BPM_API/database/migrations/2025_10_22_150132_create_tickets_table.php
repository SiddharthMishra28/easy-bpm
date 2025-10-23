<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tickets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('flow_id')->constrained('flows')->onDelete('restrict'); // Cannot delete master flow if tickets exist
            $table->foreignId('flow_version_id')->constrained('flow_versions')->onDelete('restrict'); // Immutability
            $table->foreignId('current_stage_id')->nullable()->constrained('flow_stages')->onDelete('restrict');

            $table->enum('status', ['IN_PROGRESS', 'COMPLETE', 'CANCELED', 'ON_HOLD'])->default('IN_PROGRESS');

            // Flexible storage for any business data
            $table->json('ticket_data')->comment('Domain-agnostic payload data');

            $table->unsignedBigInteger('created_by_user_id')->nullable(); // Assuming a users table exists
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tickets');
    }
};
