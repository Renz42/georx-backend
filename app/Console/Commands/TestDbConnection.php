<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class TestDbConnection extends Command
{
    /**
     * The name and signature of the console command.
     * Usage: php artisan db:test
     *        php artisan db:test --connection=pgsql
     */
    protected $signature = 'db:test {--connection= : The database connection to test (mysql, pgsql)}';

    protected $description = 'Test the database connection and display server info';

    public function handle(): int
    {
        $connection = $this->option('connection') ?? config('database.default');

        $this->info("Testing connection: [{$connection}]");
        $this->line('');

        try {
            $pdo = DB::connection($connection)->getPdo();

            $driver = $pdo->getAttribute(\PDO::ATTR_DRIVER_NAME);
            $version = $pdo->getAttribute(\PDO::ATTR_SERVER_VERSION);

            $this->components->twoColumnDetail('<fg=green>✔ Connection</>', '<fg=green>SUCCESS</>');
            $this->components->twoColumnDetail('Driver', strtoupper($driver));
            $this->components->twoColumnDetail('Server Version', $version);
            $this->components->twoColumnDetail('Database', config("database.connections.{$connection}.database"));
            $this->components->twoColumnDetail('Host', config("database.connections.{$connection}.host"));
            $this->components->twoColumnDetail('Port', config("database.connections.{$connection}.port"));

            // Run a simple query to confirm query execution works
            if ($driver === 'pgsql') {
                $result = DB::connection($connection)->selectOne('SELECT current_database() AS db, version() AS ver');
                $this->components->twoColumnDetail('Active Database', $result->db);
                $this->components->twoColumnDetail('PG Full Version', substr($result->ver, 0, 60) . '...');

                // Check if key tables exist (Phase 3 readiness check)
                $tables = DB::connection($connection)
                    ->select("SELECT tablename FROM pg_tables WHERE schemaname = 'public' ORDER BY tablename");

                if (count($tables) > 0) {
                    $this->line('');
                    $this->info('Tables found in public schema (' . count($tables) . '):');
                    foreach ($tables as $t) {
                        $this->line("  • {$t->tablename}");
                    }
                } else {
                    $this->line('');
                    $this->warn('No tables found yet — schema is empty (expected before Phase 3 migration).');
                }

            } elseif ($driver === 'mysql') {
                $result = DB::connection($connection)->selectOne('SELECT DATABASE() AS db, VERSION() AS ver');
                $this->components->twoColumnDetail('Active Database', $result->db ?? 'unknown');
                $this->components->twoColumnDetail('MySQL Version', $result->ver);
            }

            $this->line('');
            $this->info('✔ Connection test passed.');
            return self::SUCCESS;

        } catch (\Exception $e) {
            $this->line('');
            $this->components->twoColumnDetail('<fg=red>✘ Connection</>', '<fg=red>FAILED</>');
            $this->error($e->getMessage());
            $this->line('');
            $this->warn('Troubleshooting tips:');
            $this->line('  1. Check your .env DB_* values match your Supabase project settings.');
            $this->line('  2. Ensure DB_SSLMODE=require for Supabase.');
            $this->line('  3. Run: php -m | findstr pgsql  (pdo_pgsql must appear)');
            $this->line('  4. Ensure your IP is allowed in Supabase → Settings → Network.');
            return self::FAILURE;
        }
    }
}
