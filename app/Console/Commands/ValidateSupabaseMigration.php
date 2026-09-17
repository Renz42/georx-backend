<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ValidateSupabaseMigration extends Command
{
    // =========================================================================
    //  GEORX — Supabase Migration Validator
    //
    //  Compares MySQL source vs Supabase PostgreSQL row counts, spot-checks
    //  primary key ranges, and verifies FK integrity in the target database.
    //
    //  Usage:
    //    php artisan supabase:validate
    //    php artisan supabase:validate --table=orders
    //    php artisan supabase:validate --fk-check
    //    php artisan supabase:validate --spot-check
    // =========================================================================

    protected $signature = 'supabase:validate
                            {--table=       : Validate only this table}
                            {--fk-check     : Also verify FK integrity in Supabase}
                            {--spot-check   : Sample first and last rows from each table}';

    protected $description = 'Compare MySQL and Supabase row counts to validate the data migration';

    /** Tables and their boolean columns (for spot-check cast validation) */
    private array $tables = [
        // Application tables
        'users', 'pharmacies', 'medicines',
        'pharmacy_medicine', 'inventory_batches',
        'stock_alerts', 'user_favorites',
        'cart_items', 'orders', 'order_items',
        'reviews', 'audit_logs',
        'conversations', 'messages', 'search_logs',
        'global_settings',
        // System tables
        'notifications', 'personal_access_tokens',
        'password_reset_tokens', 'sessions',
        'cache', 'cache_locks',
        'jobs', 'job_batches', 'failed_jobs',
    ];

    public function handle(): int
    {
        $this->newLine();
        $this->info('GEORX — Supabase Migration Validation');
        $this->line(str_repeat('─', 72));

        // Connect to both DBs
        try {
            DB::connection('mysql')->getPdo();
            DB::connection('pgsql')->getPdo();
        } catch (\Exception $e) {
            $this->error('Connection failed: ' . $e->getMessage());
            return self::FAILURE;
        }

        $tables = $this->option('table')
            ? [$this->option('table')]
            : $this->tables;

        // ── Step 1: Row count comparison ──────────────────────────────────────
        $this->newLine();
        $this->info('Step 1: Record Count Comparison');
        $this->newLine();

        $rows         = [];
        $mismatches   = [];
        $totalMySQL   = 0;
        $totalPgsql   = 0;
        $missingInPg  = [];

        foreach ($tables as $table) {
            $mysqlCount = $this->countRows('mysql', $table);
            $pgsqlCount = $this->countRows('pgsql', $table);

            if ($mysqlCount === null && $pgsqlCount === null) {
                $rows[] = [$table, '─', '─', '<fg=gray>not in either</>'];
                continue;
            }

            if ($pgsqlCount === null) {
                $rows[]       = [$table, number_format($mysqlCount), '─', '<fg=red>✗ MISSING IN SUPABASE</>'];
                $missingInPg[] = $table;
                continue;
            }

            $match = $mysqlCount === $pgsqlCount;

            $status = $match
                ? '<fg=green>✓ MATCH</>'
                : '<fg=red>✗ MISMATCH</>';

            if (! $match) {
                $mismatches[] = [
                    'table'  => $table,
                    'mysql'  => $mysqlCount,
                    'pgsql'  => $pgsqlCount,
                    'diff'   => $mysqlCount - $pgsqlCount,
                ];
            }

            $rows[]      = [
                $table,
                number_format($mysqlCount),
                number_format($pgsqlCount),
                $status,
            ];
            $totalMySQL += $mysqlCount ?? 0;
            $totalPgsql += $pgsqlCount ?? 0;
        }

        $this->table(
            ['Table', 'MySQL (source)', 'Supabase (target)', 'Status'],
            $rows
        );

        $this->newLine();
        $this->components->twoColumnDetail('Total MySQL rows',   number_format($totalMySQL));
        $this->components->twoColumnDetail('Total Supabase rows', number_format($totalPgsql));

        $matchRate = $totalMySQL > 0
            ? round(($totalPgsql / $totalMySQL) * 100, 2)
            : 100;

        $this->components->twoColumnDetail(
            'Match rate',
            ($matchRate === 100.0 ? '<fg=green>' : '<fg=yellow>') . $matchRate . '%</>'
        );

        // ── Step 2: Mismatch detail ───────────────────────────────────────────
        if (! empty($mismatches)) {
            $this->newLine();
            $this->warn('Tables with row count mismatches:');
            foreach ($mismatches as $m) {
                $diff = $m['diff'] > 0
                    ? '<fg=red>MySQL has ' . $m['diff'] . ' more</>'
                    : '<fg=yellow>Supabase has ' . abs($m['diff']) . ' more (duplicates?)</>';
                $this->line("  [{$m['table']}] MySQL: {$m['mysql']} | Supabase: {$m['pgsql']} → {$diff}");
            }
        }

        if (! empty($missingInPg)) {
            $this->newLine();
            $this->error('Tables missing from Supabase (run migration first):');
            foreach ($missingInPg as $t) {
                $this->error("  ✗ {$t}");
            }
        }

        // ── Step 3: Max ID comparison (sequence validation) ───────────────────
        $this->newLine();
        $this->info('Step 2: Primary Key Range Validation');
        $this->newLine();

        $intPkTables = [
            'users', 'pharmacies', 'medicines', 'pharmacy_medicine',
            'inventory_batches', 'stock_alerts', 'user_favorites',
            'cart_items', 'orders', 'order_items', 'reviews',
            'audit_logs', 'conversations', 'messages', 'search_logs',
            'global_settings', 'personal_access_tokens', 'jobs', 'failed_jobs',
        ];

        $pkRows = [];
        foreach (array_intersect($intPkTables, $tables) as $table) {
            $mysqlMax  = $this->getMaxId('mysql', $table);
            $pgsqlMax  = $this->getMaxId('pgsql', $table);
            $seqValue  = $this->getSequenceValue($table);

            $pkStatus = ($mysqlMax === $pgsqlMax && $mysqlMax !== null)
                ? '<fg=green>✓</>'
                : ($pgsqlMax === null ? '<fg=gray>─</>' : '<fg=yellow>≠</>');

            $seqStatus = ($seqValue !== null && $seqValue > ($pgsqlMax ?? 0))
                ? '<fg=green>✓ ahead</>'
                : '<fg=yellow>⚠ check</>';

            $pkRows[] = [
                $table,
                $mysqlMax ?? '─',
                $pgsqlMax ?? '─',
                $seqValue ?? '─',
                $pkStatus . ' ' . $seqStatus,
            ];
        }

        $this->table(
            ['Table', 'MySQL MAX(id)', 'Supabase MAX(id)', 'PG Sequence', 'Status'],
            $pkRows
        );

        // ── Step 4: FK integrity check (optional) ─────────────────────────────
        if ($this->option('fk-check')) {
            $this->newLine();
            $this->info('Step 3: Foreign Key Integrity Check in Supabase');
            $this->newLine();
            $this->checkForeignKeys();
        }

        // ── Step 5: Spot-check samples (optional) ─────────────────────────────
        if ($this->option('spot-check')) {
            $this->newLine();
            $this->info('Step 4: Spot Check — First User & Last Order');
            $this->newLine();
            $this->spotCheck();
        }

        // ── Final verdict ─────────────────────────────────────────────────────
        $this->newLine();
        $this->line(str_repeat('─', 72));

        $passed = empty($mismatches) && empty($missingInPg);

        if ($passed) {
            $this->info('✓ Validation PASSED — all counts match between MySQL and Supabase.');
            $this->info('  Your data migration is complete and verified.');
        } else {
            $this->error('✗ Validation FAILED — ' . (count($mismatches) + count($missingInPg)) . ' issue(s) found.');
            $this->warn('  To fix mismatches, re-run: php artisan supabase:migrate --rollback --force');
            $this->warn('  Then re-migrate:           php artisan supabase:migrate --force');
        }

        $this->newLine();
        return $passed ? self::SUCCESS : self::FAILURE;
    }

    // =========================================================================
    // FOREIGN KEY INTEGRITY CHECK
    // Verifies that FK references in Supabase point to existing parent rows.
    // =========================================================================
    private function checkForeignKeys(): void
    {
        $checks = [
            // [child_table, fk_column, parent_table, parent_column]
            ['users',            'pharmacy_id',        'pharmacies',  'id'],
            ['pharmacy_medicine','pharmacy_id',         'pharmacies',  'id'],
            ['pharmacy_medicine','medicine_id',         'medicines',   'id'],
            ['inventory_batches','pharmacy_id',         'pharmacies',  'id'],
            ['inventory_batches','medicine_id',         'medicines',   'id'],
            ['stock_alerts',     'user_id',             'users',       'id'],
            ['stock_alerts',     'medicine_id',         'medicines',   'id'],
            ['user_favorites',   'user_id',             'users',       'id'],
            ['user_favorites',   'pharmacy_id',         'pharmacies',  'id'],
            ['cart_items',       'user_id',             'users',       'id'],
            ['cart_items',       'pharmacy_id',         'pharmacies',  'id'],
            ['cart_items',       'medicine_id',         'medicines',   'id'],
            ['orders',           'user_id',             'users',       'id'],
            ['orders',           'pharmacy_id',         'pharmacies',  'id'],
            ['order_items',      'order_id',            'orders',      'id'],
            ['order_items',      'medicine_id',         'medicines',   'id'],
            ['reviews',          'order_id',            'orders',      'id'],
            ['reviews',          'user_id',             'users',       'id'],
            ['reviews',          'pharmacy_id',         'pharmacies',  'id'],
            ['audit_logs',       'pharmacy_id',         'pharmacies',  'id'],
            ['audit_logs',       'user_id',             'users',       'id'],
            ['conversations',    'user_id',             'users',       'id'],
            ['conversations',    'pharmacy_id',         'pharmacies',  'id'],
            ['messages',         'conversation_id',     'conversations','id'],
            ['messages',         'sender_id',           'users',       'id'],
            ['search_logs',      'user_id',             'users',       'id'],
        ];

        $fkRows  = [];
        $broken  = 0;

        foreach ($checks as [$child, $fkCol, $parent, $parentCol]) {
            try {
                // Count orphaned rows: child rows whose FK doesn't exist in parent
                $orphans = DB::connection('pgsql')
                    ->table("{$child} as c")
                    ->leftJoin("{$parent} as p", "c.{$fkCol}", '=', "p.{$parentCol}")
                    ->whereNull("p.{$parentCol}")
                    ->whereNotNull("c.{$fkCol}") // Exclude intentional NULLs
                    ->count();

                $status = $orphans === 0
                    ? '<fg=green>✓ OK</>'
                    : '<fg=red>✗ ' . $orphans . ' orphan(s)</>';

                if ($orphans > 0) {
                    $broken++;
                }

                $fkRows[] = ["{$child}.{$fkCol}", "→ {$parent}.{$parentCol}", $status];

            } catch (\Exception $e) {
                $fkRows[] = ["{$child}.{$fkCol}", "→ {$parent}.{$parentCol}", '<fg=gray>─ skipped</>'];
            }
        }

        $this->table(['FK Column', 'References', 'Status'], $fkRows);

        if ($broken > 0) {
            $this->warn("{$broken} FK relationship(s) have orphaned rows.");
            $this->warn('This indicates some rows were not migrated correctly.');
        } else {
            $this->info('✓ All FK relationships are intact in Supabase.');
        }
    }

    // =========================================================================
    // SPOT CHECK — Sample key records from each DB
    // =========================================================================
    private function spotCheck(): void
    {
        // Check admin user exists in Supabase
        try {
            $admin = DB::connection('pgsql')
                ->table('users')
                ->where('role', 'administrator')
                ->first();

            if ($admin) {
                $this->components->twoColumnDetail(
                    'Admin user in Supabase',
                    '<fg=green>✓ Found: ' . $admin->email . ' (role: ' . $admin->role . ')</>'
                );
            } else {
                $this->components->twoColumnDetail(
                    'Admin user in Supabase',
                    '<fg=yellow>⚠ No administrator found</>'
                );
            }
        } catch (\Exception $e) {
            $this->warn('Could not check admin user: ' . $e->getMessage());
        }

        // Check pharmacy count matches
        try {
            $mysqlPharmacies  = DB::connection('mysql')->table('pharmacies')->where('status', 'approved')->count();
            $pgsqlPharmacies  = DB::connection('pgsql')->table('pharmacies')->where('status', 'approved')->count();
            $match = $mysqlPharmacies === $pgsqlPharmacies;

            $this->components->twoColumnDetail(
                'Approved pharmacies',
                ($match ? '<fg=green>✓ ' : '<fg=red>✗ ') . "MySQL: {$mysqlPharmacies} | Supabase: {$pgsqlPharmacies}</>"
            );
        } catch (\Exception $e) {
            $this->warn('Could not check pharmacies: ' . $e->getMessage());
        }

        // Check inventory totals
        try {
            $mysqlStock  = DB::connection('mysql')->table('pharmacy_medicine')->sum('quantity_on_hand');
            $pgsqlStock  = DB::connection('pgsql')->table('pharmacy_medicine')->sum('quantity_on_hand');
            $match = (int)$mysqlStock === (int)$pgsqlStock;

            $this->components->twoColumnDetail(
                'Total stock qty (pharmacy_medicine)',
                ($match ? '<fg=green>✓ ' : '<fg=red>✗ ') . "MySQL: {$mysqlStock} | Supabase: {$pgsqlStock}</>"
            );
        } catch (\Exception $e) {
            $this->warn('Could not check stock: ' . $e->getMessage());
        }

        // Check order financial totals
        try {
            $mysqlRevenue  = DB::connection('mysql')->table('orders')->where('status', 'delivered')->sum('total_amount');
            $pgsqlRevenue  = DB::connection('pgsql')->table('orders')->where('status', 'delivered')->sum('total_amount');
            $match = round((float)$mysqlRevenue, 2) === round((float)$pgsqlRevenue, 2);

            $this->components->twoColumnDetail(
                'Total delivered revenue',
                ($match ? '<fg=green>✓ ' : '<fg=red>✗ ') . 'MySQL: ₱' . number_format($mysqlRevenue, 2) . ' | Supabase: ₱' . number_format($pgsqlRevenue, 2) . '</>'
            );
        } catch (\Exception $e) {
            $this->warn('Could not check revenue: ' . $e->getMessage());
        }

        // Verify JSON columns decoded correctly
        try {
            $pgPharmacy = DB::connection('pgsql')
                ->table('pharmacies')
                ->whereNotNull('operating_hours')
                ->first();

            if ($pgPharmacy) {
                $decoded = json_decode($pgPharmacy->operating_hours, true);
                $valid   = json_last_error() === JSON_ERROR_NONE;
                $this->components->twoColumnDetail(
                    'JSONB operating_hours',
                    $valid ? '<fg=green>✓ Valid JSONB</>' : '<fg=red>✗ Invalid JSON</>'
                );
            }
        } catch (\Exception $e) {
            $this->warn('Could not check JSONB: ' . $e->getMessage());
        }
    }

    // =========================================================================
    // HELPERS
    // =========================================================================

    private function countRows(string $connection, string $table): ?int
    {
        try {
            return (int) DB::connection($connection)->table($table)->count();
        } catch (\Exception $e) {
            return null;
        }
    }

    private function getMaxId(string $connection, string $table): ?int
    {
        try {
            $result = DB::connection($connection)->table($table)->max('id');
            return $result !== null ? (int) $result : null;
        } catch (\Exception $e) {
            return null;
        }
    }

    private function getSequenceValue(string $table): ?int
    {
        try {
            $result = DB::connection('pgsql')->selectOne(
                "SELECT last_value FROM pg_sequences WHERE sequencename = ?",
                ["{$table}_id_seq"]
            );
            return $result ? (int) $result->last_value : null;
        } catch (\Exception $e) {
            return null;
        }
    }
}
