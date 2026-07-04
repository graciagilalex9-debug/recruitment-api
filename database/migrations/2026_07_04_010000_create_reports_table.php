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
        Schema::create('reports', function (Blueprint $table) {
            $table->ulid('id')->primary();                 // ULID identity (char(26))
            $table->string('type');                        // ReportType (e.g. consolidated_listing)
            $table->string('status')->index();             // lifecycle state; indexed for "pending" scans
            $table->string('sort');                        // criteria snapshot: sort column
            $table->string('direction');                   // criteria snapshot: asc|desc
            $table->json('filters');                        // criteria snapshot: column => value
            $table->string('file_path')->nullable();       // set once completed
            $table->text('failure_reason')->nullable();    // set once failed
            $table->timestamp('requested_at');             // owned by the domain, not auto-managed
            $table->timestamp('completed_at')->nullable(); // set once completed
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reports');
    }
};
