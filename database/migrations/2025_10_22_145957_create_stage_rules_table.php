<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stage_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('flow_stage_id')->constrained('flow_stages')->onDelete('cascade');
            $table->enum('rule_type', ['ROUTING', 'AUTO_ADVANCE']); // For future expansion

            // Stores the complex nested JSON rule structure (Section 3A)
            $table->json('rule_definition_json');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stage_rules');
    }
};
