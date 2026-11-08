<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;

abstract class TestCase extends BaseTestCase
{
    /**
     * Boot testing helper traits with SQLite in-memory test isolation and schema parity.
     */
    protected function setUpTraits()
    {
        if (config('database.default') === 'sqlite') {
            try {
                DB::statement("ATTACH ':memory:' AS information_schema");
                DB::statement("CREATE TABLE IF NOT EXISTS information_schema.table_constraints (table_name TEXT, constraint_name TEXT, constraint_type TEXT)");
            } catch (\Throwable $e) {}
        }

        $res = parent::setUpTraits();

        return $res;
    }
}
