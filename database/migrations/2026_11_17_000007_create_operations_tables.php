<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->morphs('notifiable');
            $table->text('data');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('hospital_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action', 80);
            $table->string('subject_type')->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->json('changes')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['hospital_id', 'created_at']);
            $table->index(['user_id', 'created_at']);
            $table->index(['subject_type', 'subject_id']);
        });

        Schema::create('integration_logs', function (Blueprint $table) {
            $table->id();
            $table->string('source', 80);
            $table->string('event_type', 80);
            $table->string('status', 20)->default('info');
            $table->text('message')->nullable();
            $table->json('payload')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['source', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('integration_logs');
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('notifications');
    }
};
