<?php

declare(strict_types=1);

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
        Schema::create('assignments', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('candidature_id')->unique();          // one current evaluator per candidature
            $table->ulid('evaluator_id')->index();             // the listing groups by evaluator
            $table->timestamp('assigned_at');

            $table->foreign('candidature_id')->references('id')->on('candidatures')->cascadeOnDelete();
            $table->foreign('evaluator_id')->references('id')->on('evaluators')->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('assignments');
    }
};
