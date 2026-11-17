<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('slug')->unique();
            $table->string('scope_type', 20)->default('hospital');
            $table->string('description')->nullable();
            $table->timestamps();
        });

        Schema::create('permissions', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('group', 40)->nullable();
            $table->string('description')->nullable();
            $table->timestamps();
        });

        Schema::create('role_permissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('role_id')->constrained()->cascadeOnDelete();
            $table->foreignId('permission_id')->constrained()->cascadeOnDelete();

            $table->unique(['role_id', 'permission_id']);
        });

        Schema::create('user_roles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('role_id')->constrained()->restrictOnDelete();
            $table->timestamps();

            $table->unique(['user_id', 'role_id']);
        });

        Schema::create('hospital_user_memberships', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('hospital_id')->constrained()->cascadeOnDelete();
            $table->foreignId('role_id')->constrained()->restrictOnDelete();
            $table->string('status', 20)->default('active');
            $table->timestamps();

            $table->unique(['user_id', 'hospital_id', 'role_id'], 'membership_unique');
            $table->index(['hospital_id', 'status']);
        });

        Schema::create('admin_scopes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('region_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('province_id')->nullable()->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['user_id', 'region_id', 'province_id'], 'admin_scope_unique');
        });

        Schema::create('login_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('email');
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent')->nullable();
            $table->boolean('success')->default(false);
            $table->timestamp('created_at')->nullable();

            $table->index(['email', 'created_at']);
        });

        Schema::create('system_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->text('value')->nullable();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('system_settings');
        Schema::dropIfExists('login_logs');
        Schema::dropIfExists('admin_scopes');
        Schema::dropIfExists('hospital_user_memberships');
        Schema::dropIfExists('user_roles');
        Schema::dropIfExists('role_permissions');
        Schema::dropIfExists('permissions');
        Schema::dropIfExists('roles');
    }
};
