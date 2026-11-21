<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Supabase exposes the public schema through PostgREST to the anon and
 * authenticated roles. Laravel connects as the table owner (postgres), so it
 * does not need those grants. Revoke them to keep the application tables off
 * the Supabase Data API.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        $roles = DB::table('pg_roles')
            ->whereIn('rolname', ['anon', 'authenticated'])
            ->pluck('rolname')
            ->all();

        if ($roles === []) {
            return;
        }

        $list = implode(', ', array_map(fn (string $role) => '"'.$role.'"', $roles));

        DB::statement("revoke all on all tables in schema public from {$list}");
        DB::statement("revoke all on all sequences in schema public from {$list}");
        DB::statement("alter default privileges in schema public revoke all on tables from {$list}");
        DB::statement("alter default privileges in schema public revoke all on sequences from {$list}");
    }

    public function down(): void
    {
        // Privilege revocation is intentionally not reversed.
    }
};
