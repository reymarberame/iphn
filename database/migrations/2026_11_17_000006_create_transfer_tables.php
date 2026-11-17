<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bed_reservations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('referral_id')->constrained()->cascadeOnDelete();
            $table->foreignId('bed_id')->constrained()->restrictOnDelete();
            $table->foreignId('hospital_id')->constrained()->cascadeOnDelete();
            $table->string('status', 20)->default('active');
            $table->foreignId('reserved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reserved_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('released_at')->nullable();
            $table->string('notes')->nullable();
            $table->timestamps();

            $table->index(['bed_id', 'status']);
            $table->index(['referral_id', 'status']);
            $table->index(['hospital_id', 'status']);
        });

        Schema::create('transfer_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('referral_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('status', 30)->default('scheduled');
            $table->string('transport_type', 30)->default('ambulance');
            $table->string('vehicle_reference')->nullable();
            $table->string('escort_contact')->nullable();
            $table->timestamp('departure_at')->nullable();
            $table->timestamp('estimated_arrival_at')->nullable();
            $table->timestamp('arrival_at')->nullable();
            $table->foreignId('coordinator_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['status', 'departure_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transfer_records');
        Schema::dropIfExists('bed_reservations');
    }
};
