<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('referrals', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->string('referral_number', 30)->unique();
            $table->foreignId('referring_hospital_id')->constrained('hospitals')->restrictOnDelete();
            $table->foreignId('receiving_hospital_id')->constrained('hospitals')->restrictOnDelete();
            $table->string('patient_reference', 60);
            $table->string('patient_name');
            $table->unsignedTinyInteger('patient_age')->nullable();
            $table->string('patient_sex', 20)->nullable();
            $table->string('urgency', 20)->default('routine');
            $table->foreignId('requested_service_id')->nullable()->constrained('services')->nullOnDelete();
            $table->text('referral_reason');
            $table->string('contact_name')->nullable();
            $table->string('contact_phone', 30)->nullable();
            $table->string('status', 30)->default('draft');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('coordinator_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('response_note')->nullable();
            $table->text('decline_reason')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->index(['receiving_hospital_id', 'status', 'submitted_at'], 'referrals_receiving_status_idx');
            $table->index(['referring_hospital_id', 'status', 'submitted_at'], 'referrals_referring_status_idx');
            $table->index('urgency');
        });

        Schema::create('referral_status_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('referral_id')->constrained()->cascadeOnDelete();
            $table->string('old_status', 30)->nullable();
            $table->string('new_status', 30);
            $table->string('reason')->nullable();
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('changed_at')->nullable();

            $table->index(['referral_id', 'changed_at']);
        });

        Schema::create('referral_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('referral_id')->constrained()->cascadeOnDelete();
            $table->string('storage_path');
            $table->string('original_name');
            $table->string('mime_type', 120)->nullable();
            $table->unsignedBigInteger('size_bytes')->default(0);
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('referral_documents');
        Schema::dropIfExists('referral_status_history');
        Schema::dropIfExists('referrals');
    }
};
