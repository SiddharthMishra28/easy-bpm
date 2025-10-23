<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ticket_task_statuses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->constrained('tickets')->onDelete('cascade');
            // Link to the specific task definition from the immutable flow version
            $table->foreignId('stage_task_id')->constrained('stage_tasks')->onDelete('cascade');

            $table->boolean('is_complete')->default(false);
            $table->timestamp('completed_at')->nullable();
            $table->unsignedBigInteger('completed_by_user_id')->nullable();
            $table->timestamps();

            $table->unique(['ticket_id', 'stage_task_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ticket_task_statuses');
    }
};
