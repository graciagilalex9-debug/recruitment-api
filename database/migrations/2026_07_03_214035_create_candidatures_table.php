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
        Schema::create('candidatures', function (Blueprint $table) {
            $table->ulid('id')->primary();                 // ULID identity (char(26))
            $table->string('full_name');
            $table->string('email')->unique();             // business identity: unique index
            $table->unsignedSmallInteger('years_of_experience');
            $table->text('cv');
            $table->timestamp('created_at');               // owned by the domain, not auto-managed
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('candidatures');
    }
};
