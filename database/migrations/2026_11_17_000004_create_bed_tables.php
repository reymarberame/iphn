<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bed_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->boolean('is_er')->default(false);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::create('wards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hospital_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('ward_type', 30)->default('inpatient');
            $table->string('floor', 30)->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->unique(['hospital_id', 'name']);
            $table->index(['hospital_id', 'active']);
        });

        Schema::create('rooms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ward_id')->constrained()->cascadeOnDelete();
            $table->string('room_code', 30);
            $table->string('name')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->unique(['ward_id', 'room_code']);
        });

        Schema::create('beds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hospital_id')->constrained()->cascadeOnDelete();
            $table->foreignId('room_id')->constrained()->cascadeOnDelete();
            $table->foreignId('bed_category_id')->constrained()->restrictOnDelete();
            $table->string('bed_code', 40);
            $table->string('status', 20)->default('available');
            $table->text('notes')->nullable();
            $table->boolean('active')->default(true);
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->softDeletes();
            $table->timestamps();

            $table->unique(['hospital_id', 'bed_code']);
            $table->index(['hospital_id', 'status']);
            $table->index(['bed_category_id', 'status']);
            $table->index(['room_id', 'active']);
        });

        Schema::create('bed_status_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bed_id')->constrained()->cascadeOnDelete();
            $table->foreignId('hospital_id')->constrained()->cascadeOnDelete();
            $table->string('old_status', 20)->nullable();
            $table->string('new_status', 20);
            $table->string('reason')->nullable();
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('changed_at')->nullable();

            $table->index(['bed_id', 'changed_at']);
            $table->index(['hospital_id', 'changed_at']);
        });

        Schema::create('bed_availability_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hospital_id')->constrained()->cascadeOnDelete();
            $table->json('category_counts');
            $table->unsignedInteger('total_beds')->default(0);
            $table->unsignedInteger('available_beds')->default(0);
            $table->timestamp('captured_at')->nullable();

            $table->index(['hospital_id', 'captured_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bed_availability_snapshots');
        Schema::dropIfExists('bed_status_history');
        Schema::dropIfExists('beds');
        Schema::dropIfExists('rooms');
        Schema::dropIfExists('wards');
        Schema::dropIfExists('bed_categories');
    }
};
