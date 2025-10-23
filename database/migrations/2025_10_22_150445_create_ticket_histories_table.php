<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ticket_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->constrained('tickets')->onDelete('cascade');
            $table->enum('event_type', ['STAGE_ENTERED', 'STAGE_EXITED', 'RULE_EVAL_PASS', 'RULE_EVAL_FAIL', 'TASK_COMPLETE', 'DATA_UPDATE', 'STATUS_CHANGE']);
            $table->json('details')->nullable()->comment('Contextual data for the event (e.g., rule evaluation output)');
            $table->unsignedBigInteger('actor_user_id')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ticket_histories');
    }
};
