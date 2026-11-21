<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

class CopyDatabaseToPostgres extends Command
{
    /**
     * Tables copied in foreign-key dependency order. Ephemeral framework
     * tables (migrations, sessions, cache, jobs, tokens) are intentionally
     * excluded and rebuilt on the destination instead.
     *
     * @var list<string>
     */
    private const TABLES = [
        'regions',
        'provinces',
        'municipalities',
        'hospitals',
        'services',
        'hospital_services',
        'roles',
        'permissions',
        'role_permissions',
        'users',
        'user_roles',
        'hospital_user_memberships',
        'admin_scopes',
        'system_settings',
        'login_logs',
        'bed_categories',
        'wards',
        'rooms',
        'beds',
        'bed_status_history',
        'bed_availability_snapshots',
        'referrals',
        'referral_status_history',
        'referral_documents',
        'bed_reservations',
        'transfer_records',
        'conversations',
        'conversation_participants',
        'messages',
        'notifications',
        'audit_logs',
        'integration_logs',
    ];

    protected $signature = 'db:copy-to-postgres
        {--source=legacy_mysql : Source MySQL connection name}
        {--target=pgsql : Destination PostgreSQL connection name}
        {--fresh : Truncate destination business tables before copying}
        {--skip-verification : Skip the source/target row-count comparison}
        {--documents : Copy referral documents from the local disk to the configured disk}';

    protected $description = 'Copy existing MySQL business data into the PostgreSQL database used by Laravel';

    public function handle(): int
    {
        $sourceName = (string) $this->option('source');
        $targetName = (string) $this->option('target');

        if (! config("database.connections.{$sourceName}")) {
            $this->error("Unknown source connection [{$sourceName}].");

            return self::FAILURE;
        }

        if (! config("database.connections.{$targetName}")) {
            $this->error("Unknown target connection [{$targetName}].");

            return self::FAILURE;
        }

        $source = DB::connection($sourceName);
        $target = DB::connection($targetName);

        if ($target->getDriverName() !== 'pgsql') {
            $this->error("The target connection [{$targetName}] must use the pgsql driver.");

            return self::FAILURE;
        }

        try {
            $target->getPdo();
            $source->getPdo();
        } catch (Throwable $exception) {
            $this->error('Could not connect: '.$exception->getMessage());

            return self::FAILURE;
        }

        $this->info("Copying {$source->getDatabaseName()} → {$target->getDatabaseName()}");

        if ($this->option('fresh')) {
            $this->truncateTarget($target);
        }

        $copied = [];
        $failed = false;

        foreach (self::TABLES as $table) {
            try {
                $copied[$table] = $this->copyTable($source, $target, $table);
                $this->line(sprintf('  %-32s %6d row(s)', $table, $copied[$table]));
            } catch (Throwable $exception) {
                $failed = true;
                $this->newLine();
                $this->error("Failed copying [{$table}]: ".$exception->getMessage());

                break;
            }
        }

        if ($failed) {
            return self::FAILURE;
        }

        if ($this->option('documents')) {
            $this->copyReferralDocuments($target);
        }

        if (! $this->option('skip-verification')) {
            return $this->verify($source, $target, $copied) ? self::SUCCESS : self::FAILURE;
        }

        $this->info('Copy completed.');

        return self::SUCCESS;
    }

    private function truncateTarget(Connection $target): void
    {
        $this->warn('Truncating destination business tables…');

        $tables = implode(', ', array_map(
            fn (string $table) => '"'.$table.'"',
            array_reverse(self::TABLES)
        ));

        $target->statement("TRUNCATE TABLE {$tables} RESTART IDENTITY CASCADE");

        $this->info('Destination tables truncated.');
    }

    /**
     * Copy every row of a table in bounded chunks and return the row count.
     */
    private function copyTable(Connection $source, Connection $target, string $table): int
    {
        $schema = $this->targetSchema($target, $table);

        if ($schema === []) {
            throw new \RuntimeException("Table [{$table}] does not exist on the target database. Run `php artisan migrate` first.");
        }

        $columnCount = max(1, count($schema));
        $chunkSize = max(1, min(500, intdiv(60000, $columnCount)));

        $total = 0;

        $target->transaction(function () use ($source, $target, $table, $schema, $chunkSize, &$total) {
            $source->table($table)
                ->orderBy('id')
                ->chunk($chunkSize, function ($rows) use ($target, $table, $schema, &$total) {
                    $prepared = [];

                    foreach ($rows as $row) {
                        $prepared[] = $this->normalizeRow((array) $row, $schema);
                    }

                    if ($prepared !== []) {
                        $target->table($table)->insert($prepared);
                        $total += count($prepared);
                    }
                });
        });

        $this->resetSequence($target, $table);

        return $total;
    }

    /**
     * Column metadata keyed by column name.
     *
     * @return array<string, array{data_type: string, is_nullable: bool}>
     */
    private function targetSchema(Connection $target, string $table): array
    {
        $rows = $target->select(
            'select column_name, data_type, is_nullable
             from information_schema.columns
             where table_schema = current_schema() and table_name = ?',
            [$table]
        );

        $schema = [];

        foreach ($rows as $row) {
            $schema[$row->column_name] = [
                'data_type' => $row->data_type,
                'is_nullable' => $row->is_nullable === 'YES',
            ];
        }

        return $schema;
    }

    /**
     * Convert MySQL-specific values into something PostgreSQL accepts.
     *
     * @param  array<string, mixed>  $row
     * @param  array<string, array{data_type: string, is_nullable: bool}>  $schema
     * @return array<string, mixed>
     */
    private function normalizeRow(array $row, array $schema): array
    {
        foreach ($row as $column => $value) {
            $type = $schema[$column]['data_type'] ?? null;
            $nullable = $schema[$column]['is_nullable'] ?? true;

            if ($value === null) {
                continue;
            }

            if ($type === 'boolean') {
                $row[$column] = (bool) $value ? 'true' : 'false';

                continue;
            }

            if (in_array($type, ['json', 'jsonb'], true)) {
                if (is_string($value) && trim($value) === '') {
                    $row[$column] = $nullable ? null : '{}';
                }

                continue;
            }

            if (in_array($type, ['timestamp without time zone', 'timestamp with time zone', 'date'], true)) {
                if ($value === '' || $this->isZeroDate((string) $value)) {
                    if (! $nullable) {
                        throw new \RuntimeException("Column [{$column}] contains an invalid date but is NOT NULL.");
                    }

                    $row[$column] = null;
                }
            }
        }

        return $row;
    }

    private function isZeroDate(string $value): bool
    {
        return str_starts_with($value, '0000-00-00');
    }

    /**
     * Move the PostgreSQL sequence forward so new inserts do not collide with
     * the ids that were copied from MySQL.
     */
    private function resetSequence(Connection $target, string $table): void
    {
        $sequence = $target->selectOne(
            "select pg_get_serial_sequence(?, 'id') as sequence",
            [$table]
        )?->sequence;

        if (! $sequence) {
            return;
        }

        $target->statement(
            "select setval(?, (select coalesce(max(id), 1) from \"{$table}\"), (select count(*) > 0 from \"{$table}\"))",
            [$sequence]
        );
    }

    /**
     * Copy referral files stored on the local disk to the configured disk.
     * Database rows are already copied by the time this runs.
     */
    private function copyReferralDocuments(Connection $target): void
    {
        $disk = (string) config('bhn.referral_documents_disk', 'local');

        if ($disk === 'local') {
            $this->line('Referral documents already use the local disk; nothing to copy.');

            return;
        }

        $documents = $target->table('referral_documents')->get(['id', 'storage_path']);

        if ($documents->isEmpty()) {
            $this->line('No referral documents to copy.');

            return;
        }

        $this->info("Copying {$documents->count()} referral document(s) to the [{$disk}] disk…");

        $copied = 0;
        $missing = 0;

        foreach ($documents as $document) {
            $path = $document->storage_path;

            if (! Storage::disk('local')->exists($path)) {
                $missing++;
                $this->warn("  Missing local file: {$path}");

                continue;
            }

            $stream = Storage::disk('local')->readStream($path);

            if ($stream === null) {
                $missing++;

                continue;
            }

            try {
                Storage::disk($disk)->writeStream($path, $stream);
                $copied++;
            } finally {
                if (is_resource($stream)) {
                    fclose($stream);
                }
            }
        }

        $this->line("  Copied {$copied} file(s), skipped {$missing}.");

        if ($missing > 0) {
            $this->warn('Some document rows reference files that were not found on the local disk.');
        }
    }

    /**
     * @param  array<string, int>  $copied
     */
    private function verify(Connection $source, Connection $target, array $copied): bool
    {
        $this->newLine();
        $this->info('Verifying row counts…');

        $mismatches = 0;

        foreach ($copied as $table => $expected) {
            $actual = (int) $target->table($table)->count();

            if ($actual !== $expected) {
                $mismatches++;
                $this->error(sprintf('  %-32s source=%d target=%d', $table, $expected, $actual));
            } else {
                $this->line(sprintf('  %-32s %d ✓', $table, $actual));
            }
        }

        $this->newLine();

        if ($mismatches > 0) {
            $this->error("{$mismatches} table(s) did not match. Inspect the errors above before using the database.");

            return false;
        }

        $this->info('All copied tables match the source row counts.');

        return true;
    }
}
