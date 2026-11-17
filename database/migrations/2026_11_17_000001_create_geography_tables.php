<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('regions', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('name');
            $table->string('short_name', 50)->nullable();
            $table->timestamps();
        });

        Schema::create('provinces', function (Blueprint $table) {
            $table->id();
            $table->foreignId('region_id')->constrained()->restrictOnDelete();
            $table->string('code', 20)->unique();
            $table->string('name');
            $table->timestamps();

            $table->index(['region_id', 'name']);
        });

        Schema::create('municipalities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('province_id')->constrained()->restrictOnDelete();
            $table->string('code', 20)->nullable();
            $table->string('name');
            $table->string('type', 20)->default('municipality');
            $table->timestamps();

            $table->unique(['province_id', 'name']);
            $table->index('name');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('municipalities');
        Schema::dropIfExists('provinces');
        Schema::dropIfExists('regions');
    }
};
