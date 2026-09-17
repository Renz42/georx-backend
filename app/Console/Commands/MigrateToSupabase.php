<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class MigrateToSupabase extends Command
{
    // =========================================================================
    //  GEORX — Supabase Data Migration Tool
    //
    //  Reads from MySQL (XAMPP) and writes to Supabase PostgreSQL.
    //  Both connections must be configured in config/database.php.
    //
    //  Usage:
    //    php artisan supabase:migrate              # full migration
    //    php artisan supabase:migrate --dry-run    # preview counts only
    //    php artisan supabase:migrate --table=users
    //    php artisan supabase:migrate --rollback   # TRUNCATE Supabase tables
    //    php artisan supabase:migrate --skip-system
    //    php artisan supabase:migrate --chunk=1000
    // =========================================================================

    protected $signature = 'supabase:migrate
                            {--table=       : Migrate only this specific table}
                            {--dry-run      : Preview record counts without writing}
                            {--rollback     : TRUNCATE all tables in Supabase (rollback)}
                            {--chunk=500    : Records per INSERT batch}
                            {--skip-system  : Skip Laravel system tables}
                            {--force        : Skip confirmation prompts}';

    protected $description = 'Migrate GEORX data from MySQL (XAMPP) to Supabase PostgreSQL';

    // =========================================================================
    // MIGRATION PLAN
    // Ordered by FK dependency: no table appears before its FK parents.
    //
    // Each entry:
    //   table       → target table name
    //   bool_cols   → MySQL TINYINT(1) columns to cast → PHP bool
    //   json_cols   → JSON string columns (validated/passed through as-is)
    //   str_pk      → true if the primary key is NOT a BIGSERIAL (no seq reset)
    //   system      → true if this is a Laravel infrastructure table
    // =========================================================================
    private array $plan = [
        // ── LAYER 0: Root tables ─────────────────────────────────────────────
        [
            'table'     => 'users',
            'bool_cols' => [],
            'json_cols' => [],
            'str_pk'    => false,
            'system'    => false,
        ],
        [
            'table'     => 'pharmacies',
            'bool_cols' => ['is_active', 'is_approved'],
            'json_cols' => ['operating_hours'],
            'str_pk'    => false,
            'system'    => false,
        ],
        [
            'table'     => 'medicines',
            'bool_cols' => ['prescription_required', 'controlled_substance_flag'],
            'json_cols' => [],
            'str_pk'    => false,
            'system'    => false,
        ],
        // ── LAYER 0: Laravel system ───────────────────────────────────────────
        [
            'table'     => 'password_reset_tokens',
            'bool_cols' => [],
            'json_cols' => [],
            'str_pk'    => true,  // PK is email (string)
            'system'    => true,
        ],
        [
            'table'     => 'sessions',
            'bool_cols' => [],
            'json_cols' => [],
            'str_pk'    => true,  // PK is id (string)
            'system'    => true,
        ],
        [
            'table'     => 'cache',
            'bool_cols' => [],
            'json_cols' => [],
            'str_pk'    => true,  // PK is key (string)
            'system'    => true,
        ],
        [
            'table'     => 'cache_locks',
            'bool_cols' => [],
            'json_cols' => [],
            'str_pk'    => true,  // PK is key (string)
            'system'    => true,
        ],
        [
            'table'     => 'jobs',
            'bool_cols' => [],
            'json_cols' => [],
            'str_pk'    => false,
            'system'    => true,
        ],
        [
            'table'     => 'job_batches',
            'bool_cols' => [],
            'json_cols' => [],
            'str_pk'    => true,  // PK is id (string)
            'system'    => true,
        ],
        [
            'table'     => 'failed_jobs',
            'bool_cols' => [],
            'json_cols' => [],
            'str_pk'    => false,
            'system'    => true,
        ],
        [
            'table'     => 'personal_access_tokens',
            'bool_cols' => [],
            'json_cols' => [],
            'str_pk'    => false,
            'system'    => true,
        ],
        [
            'table'     => 'notifications',
            'bool_cols' => [],
            'json_cols' => [],    // data is TEXT (not JSON col), no transform
            'str_pk'    => true,  // PK is UUID (string)
            'system'    => true,
        ],
        [
            'table'     => 'global_settings',
            'bool_cols' => [],
            'json_cols' => [],
            'str_pk'    => false,
            'system'    => false,
        ],
        // ── LAYER 2: Inventory ────────────────────────────────────────────────
        [
            'table'     => 'pharmacy_medicine',
            'bool_cols' => ['is_available'],
            'json_cols' => [],
            'str_pk'    => false,
            'system'    => false,
        ],
        [
            'table'     => 'inventory_batches',
            'bool_cols' => [],
            'json_cols' => [],
            'str_pk'    => false,
            'system'    => false,
        ],
        // ── LAYER 3: User-linked ──────────────────────────────────────────────
        [
            'table'     => 'stock_alerts',
            'bool_cols' => ['is_active'],
            'json_cols' => [],
            'str_pk'    => false,
            'system'    => false,
        ],
        [
            'table'     => 'user_favorites',
            'bool_cols' => [],
            'json_cols' => [],
            'str_pk'    => false,
            'system'    => false,
        ],
        // ── LAYER 4: Commerce ─────────────────────────────────────────────────
        [
            'table'     => 'cart_items',
            'bool_cols' => [],
            'json_cols' => [],
            'str_pk'    => false,
            'system'    => false,
        ],
        [
            'table'     => 'orders',
            'bool_cols' => ['is_prepared', 'pharmacy_confirmed'],
            'json_cols' => ['fifo_deductions'],
            'str_pk'    => false,
            'system'    => false,
        ],
        [
            'table'     => 'order_items',
            'bool_cols' => [],
            'json_cols' => [],
            'str_pk'    => false,
            'system'    => false,
        ],
        // ── LAYER 5: Post-order ───────────────────────────────────────────────
        [
            'table'     => 'reviews',
            'bool_cols' => [],
            'json_cols' => [],
            'str_pk'    => false,
            'system'    => false,
        ],
        [
            'table'     => 'audit_logs',
            'bool_cols' => [],
            'json_cols' => [],
            'str_pk'    => false,
            'system'    => false,
        ],
        [
            'table'     => 'conversations',
            'bool_cols' => [],
            'json_cols' => [],
            'str_pk'    => false,
            'system'    => false,
        ],
        [
            'table'     => 'messages',
            'bool_cols' => ['is_read'],
            'json_cols' => [],
            'str_pk'    => false,
            'system'    => false,
        ],
        [
            'table'     => 'search_logs',
            'bool_cols' => [],
            'json_cols' => [],
            'str_pk'    => false,
            'system'    => false,
        ],
    ];

    // =========================================================================
    // ENTRY POINT
    // =========================================================================
    public function handle(): int
    {
        $this->newLine();

        // ── Validate both connections ─────────────────────────────────────────
        $this->info('Validating connections...');

        try {
            DB::connection('mysql')->getPdo();
            $this->components->twoColumnDetail('MySQL (source)', '<fg=green>✓ Connected</>');
        } catch (\Exception $e) {
            $this->error('Cannot connect to MySQL: ' . $e->getMessage());
            return self::FAILURE;
        }

        try {
            DB::connection('pgsql')->getPdo();
            $this->components->twoColumnDetail('Supabase (target)', '<fg=green>✓ Connected</>');
        } catch (\Exception $e) {
            $this->error('Cannot connect to Supabase PostgreSQL: ' . $e->getMessage());
            $this->warn('Is DB_CONNECTION=pgsql and Supabase credentials set in .env?');
            return self::FAILURE;
        }

        $this->newLine();

        // ── Route to action ───────────────────────────────────────────────────
        if ($this->option('rollback')) {
            return $this->doRollback();
        }

        if ($this->option('dry-run')) {
            return $this->doDryRun();
        }

        return $this->doMigrate();
    }

    // =========================================================================
    // ACTION: MIGRATE
    // =========================================================================
    private function doMigrate(): int
    {
        $chunkSize   = max(1, (int) $this->option('chunk'));
        $skipSystem  = $this->option('skip-system');
        $targetTable = $this->option('table');

        $plan = $this->buildPlan($targetTable, $skipSystem);

        if (empty($plan)) {
            $this->warn('No tables to migrate with the given options.');
            return self::SUCCESS;
        }

        // Confirmation
        if (! $this->option('force')) {
            $this->warn('This operation will INSERT data into Supabase PostgreSQL.');
            $this->warn('Existing rows with the same primary key will CONFLICT (duplicate error).');
            $this->warn('Run --dry-run first to preview counts, or --rollback to clear Supabase first.');
            $this->newLine();
            if (! $this->confirm('Proceed with migration?')) {
                $this->info('Cancelled.');
                return self::SUCCESS;
            }
            $this->newLine();
        }

        $this->info("Migrating " . count($plan) . " table(s) — chunk size: {$chunkSize}");
        $this->line(str_repeat('─', 70));

        $errors       = [];
        $totalRows    = 0;

        // Disable FK checks for the session on PostgreSQL.
        // session_replication_role = 'replica' disables FK triggers,
        // allowing us to insert in any order without FK violations.
        // This is an alternative to MySQL's SET FOREIGN_KEY_CHECKS=0.
        DB::connection('pgsql')->statement("SET session_replication_role = 'replica'");

        try {
            foreach ($plan as $entry) {
                $table = $entry['table'];

                // Skip tables that don't exist in MySQL source
                if (! $this->tableExistsOnMySQL($table)) {
                    $this->warn("  [{$table}] Not found in MySQL — skipping");
                    continue;
                }

                $sourceCount = DB::connection('mysql')->table($table)->count();
                $this->line('');
                $this->line("  <fg=cyan;options=bold>[{$table}]</> — {$sourceCount} records in MySQL");

                if ($sourceCount === 0) {
                    $this->line("  <fg=gray>  → Empty. Skipping.</>");
                    continue;
                }

                $migrated = 0;
                $bar      = $this->output->createProgressBar($sourceCount);
                $bar->setFormat('  %current%/%max% [%bar%] %percent:3s%% — %elapsed:6s%');
                $bar->start();

                try {
                    $this->chunkMigrate($table, $entry, $chunkSize, $migrated, $bar);

                    $bar->finish();
                    $this->newLine();
                    $this->line("  <fg=green>  ✓ {$migrated} records inserted</>");

                    // Reset BIGSERIAL sequence so new INSERTs won't conflict with copied IDs
                    if (! $entry['str_pk']) {
                        $this->resetSequence($table);
                        $this->line("  <fg=gray>  → Sequence reset to MAX(id)</>");
                    }

                    $totalRows += $migrated;

                } catch (\Exception $e) {
                    $bar->finish();
                    $this->newLine();
                    $this->error("  ✗ FAILED: " . $e->getMessage());
                    $errors[] = ['table' => $table, 'error' => $e->getMessage()];
                }
            }

        } finally {
            // ALWAYS re-enable FK enforcement — even if an error occurred
            DB::connection('pgsql')->statement("SET session_replication_role = 'DEFAULT'");
            $this->newLine();
            $this->line('<fg=gray>  → FK enforcement restored (session_replication_role = DEFAULT)</>');
        }

        // ── Summary ───────────────────────────────────────────────────────────
        $this->newLine();
        $this->line(str_repeat('─', 70));
        $this->info("Migration complete — {$totalRows} total rows inserted into Supabase.");

        if (! empty($errors)) {
            $this->newLine();
            $this->warn(count($errors) . ' table(s) encountered errors:');
            foreach ($errors as $err) {
                $this->error("  [{$err['table']}]: {$err['error']}");
            }
            $this->newLine();
            $this->warn('Tip: Re-run with --table=<name> to retry individual tables.');
            return self::FAILURE;
        }

        $this->newLine();
        $this->info('Next step: php artisan supabase:validate');

        return self::SUCCESS;
    }

    // =========================================================================
    // CHUNKED READING + WRITING
    // =========================================================================
    private function chunkMigrate(
        string $table,
        array  $entry,
        int    $chunkSize,
        int    &$migrated,
        mixed  $bar
    ): void {
        // Tables with string/UUID PKs can't use `orderBy('id')` safely for all.
        // We use forPage() for string-PK tables, chunk() for int-PK tables.
        if ($entry['str_pk']) {
            $total = DB::connection('mysql')->table($table)->count();
            $pages = (int) ceil($total / $chunkSize);

            for ($page = 1; $page <= $pages; $page++) {
                $rows = DB::connection('mysql')
                    ->table($table)
                    ->forPage($page, $chunkSize)
                    ->get();

                if ($rows->isEmpty()) {
                    break;
                }

                $transformed = $rows->map(fn ($row) => $this->transform($row, $entry))->all();
                DB::connection('pgsql')->table($table)->insert($transformed);

                $migrated += count($transformed);
                $bar->advance(count($transformed));
            }
        } else {
            DB::connection('mysql')
                ->table($table)
                ->orderBy('id')
                ->chunk($chunkSize, function ($rows) use ($table, $entry, &$migrated, $bar) {
                    $transformed = $rows->map(fn ($row) => $this->transform($row, $entry))->all();
                    DB::connection('pgsql')->table($table)->insert($transformed);
                    $migrated += count($transformed);
                    $bar->advance(count($transformed));
                });
        }
    }

    // =========================================================================
    // ROW TRANSFORMATION
    // MySQL → PostgreSQL type coercion
    // =========================================================================
    private function transform(\stdClass $row, array $entry): array
    {
        $data = (array) $row;

        // ── Boolean cast: MySQL TINYINT(1) [0|1] → PHP bool [false|true] ──────
        // PostgreSQL's BOOLEAN column requires true/false, not 0/1.
        foreach ($entry['bool_cols'] as $col) {
            if (array_key_exists($col, $data) && $data[$col] !== null) {
                $data[$col] = (bool) $data[$col];
            }
        }

        // ── JSON validation: ensure JSON columns contain valid JSON ──────────
        // MySQL JSON → PostgreSQL JSONB: the string content is identical,
        // but we validate and re-encode to be safe.
        foreach ($entry['json_cols'] as $col) {
            if (! array_key_exists($col, $data) || $data[$col] === null) {
                continue; // NULL is fine in both databases
            }

            // Decode then re-encode to normalise whitespace and validate
            $decoded = json_decode($data[$col], true);

            if (json_last_error() === JSON_ERROR_NONE) {
                $data[$col] = json_encode($decoded); // Normalised JSON string
            } else {
                // Invalid JSON in MySQL — set to null rather than corrupt the row
                $data[$col] = null;
                $this->warn("    ⚠ Invalid JSON in column [{$col}] — set to NULL");
            }
        }

        return $data;
    }

    // =========================================================================
    // SEQUENCE RESET
    // After INSERTing rows with explicit IDs, PostgreSQL's BIGSERIAL sequence
    // is still at its default start value (1). The next auto-generated INSERT
    // would get ID=1 → conflict. This resets it to MAX(id) of the table.
    // =========================================================================
    private function resetSequence(string $table): void
    {
        try {
            DB::connection('pgsql')->statement(
                "SELECT setval(
                    pg_get_serial_sequence('{$table}', 'id'),
                    COALESCE((SELECT MAX(id) FROM \"{$table}\"), 0) + 1,
                    false
                )"
            );
        } catch (\Exception $e) {
            // Table uses a non-BIGSERIAL PK or sequence doesn't exist — safe to ignore
            $this->line("  <fg=gray>  → No sequence to reset for [{$table}]</>");
        }
    }

    // =========================================================================
    // ACTION: DRY RUN — Preview counts without writing
    // =========================================================================
    private function doDryRun(): int
    {
        $skipSystem  = $this->option('skip-system');
        $targetTable = $this->option('table');
        $plan        = $this->buildPlan($targetTable, $skipSystem);

        $this->info('DRY RUN — Record counts (MySQL source). No data will be written.');
        $this->newLine();

        $rows = [];
        $totalMySQL = 0;

        foreach ($plan as $entry) {
            $table = $entry['table'];

            if (! $this->tableExistsOnMySQL($table)) {
                $rows[] = [$table, '(not found)', 'N/A', '─'];
                continue;
            }

            $mysqlCount = DB::connection('mysql')->table($table)->count();
            $pgsqlCount = 0;

            try {
                $pgsqlCount = DB::connection('pgsql')->table($table)->count();
            } catch (\Exception $e) {
                // Table doesn't exist in PG yet
            }

            $status = $mysqlCount === 0
                ? '<fg=gray>empty</>'
                : ($pgsqlCount > 0 ? '<fg=yellow>⚠ already has data</>' : '<fg=green>ready</>');

            $rows[]      = [
                $table,
                number_format($mysqlCount),
                number_format($pgsqlCount),
                $status,
            ];
            $totalMySQL += $mysqlCount;
        }

        $this->table(
            ['Table', 'MySQL (source)', 'Supabase (target)', 'Status'],
            $rows
        );

        $this->newLine();
        $this->components->twoColumnDetail('Total rows to migrate', number_format($totalMySQL));
        $this->newLine();
        $this->info('Run without --dry-run to begin migration.');

        return self::SUCCESS;
    }

    // =========================================================================
    // ACTION: ROLLBACK — TRUNCATE all tables in Supabase
    // Restores Supabase to a clean state for re-migration.
    // =========================================================================
    private function doRollback(): int
    {
        $this->newLine();
        $this->error('⚠  ROLLBACK MODE');
        $this->warn('This will TRUNCATE all GEORX tables in Supabase PostgreSQL.');
        $this->warn('All migrated data will be PERMANENTLY DELETED from Supabase.');
        $this->warn('Your MySQL data is NOT affected.');
        $this->newLine();

        if (! $this->option('force') && ! $this->confirm('Are you absolutely sure?')) {
            $this->info('Rollback cancelled.');
            return self::SUCCESS;
        }

        // Truncate in reverse FK order to avoid constraint violations
        $tables = array_reverse(array_column($this->plan, 'table'));

        // Disable FK triggers for truncation
        DB::connection('pgsql')->statement("SET session_replication_role = 'replica'");

        $this->info('Truncating tables in Supabase...');
        $this->newLine();

        try {
            foreach ($tables as $table) {
                try {
                    // Check if table exists in PostgreSQL before truncating
                    $exists = DB::connection('pgsql')
                        ->select(
                            "SELECT 1 FROM pg_tables WHERE schemaname='public' AND tablename=?",
                            [$table]
                        );

                    if (! empty($exists)) {
                        DB::connection('pgsql')->table($table)->truncate();

                        // Reset sequence for BIGSERIAL tables
                        $entry = collect($this->plan)->firstWhere('table', $table);
                        if ($entry && ! $entry['str_pk']) {
                            $this->resetSequence($table);
                        }

                        $this->line("  <fg=green>✓</> Truncated: {$table}");
                    } else {
                        $this->line("  <fg=gray>─</> Not found: {$table} (skipped)");
                    }
                } catch (\Exception $e) {
                    $this->error("  ✗ [{$table}]: " . $e->getMessage());
                }
            }
        } finally {
            DB::connection('pgsql')->statement("SET session_replication_role = 'DEFAULT'");
        }

        $this->newLine();
        $this->info('Rollback complete. Supabase is now clean.');
        $this->info('You can safely re-run: php artisan supabase:migrate');

        return self::SUCCESS;
    }

    // =========================================================================
    // HELPERS
    // =========================================================================

    /** Filter the full plan based on --table= and --skip-system options */
    private function buildPlan(?string $specificTable, bool $skipSystem): array
    {
        return collect($this->plan)
            ->when($specificTable, fn ($c) => $c->where('table', $specificTable))
            ->when($skipSystem, fn ($c) => $c->where('system', false))
            ->values()
            ->all();
    }

    /** Check if a table exists on the MySQL connection */
    private function tableExistsOnMySQL(string $table): bool
    {
        try {
            $result = DB::connection('mysql')->select(
                "SELECT 1 FROM information_schema.tables
                 WHERE table_schema = DATABASE() AND table_name = ?",
                [$table]
            );
            return ! empty($result);
        } catch (\Exception $e) {
            return false;
        }
    }
}
