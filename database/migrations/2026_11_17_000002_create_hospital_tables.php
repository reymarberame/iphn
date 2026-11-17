<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hospitals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('municipality_id')->constrained()->restrictOnDelete();
            $table->string('hospital_code', 30)->unique();
            $table->string('name');
            $table->string('short_name', 80)->nullable();
            $table->string('facility_type', 50)->default('general');
            $table->string('ownership_type', 20)->default('government')->index();
            $table->string('address')->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('email')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->unsignedInteger('bed_capacity')->nullable();
            $table->string('status', 20)->default('operational')->index();
            $table->text('notes')->nullable();
            $table->timestamp('profile_updated_at')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->index(['ownership_type', 'status']);
        });

        Schema::create('services', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::create('hospital_services', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hospital_id')->constrained()->cascadeOnDelete();
            $table->foreignId('service_id')->constrained()->restrictOnDelete();
            $table->string('availability_notes')->nullable();
            $table->timestamps();

            $table->unique(['hospital_id', 'service_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hospital_services');
        Schema::dropIfExists('services');
        Schema::dropIfExists('hospitals');
    }
};
