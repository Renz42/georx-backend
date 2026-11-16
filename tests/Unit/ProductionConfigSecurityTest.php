<?php

namespace Tests\Unit;

use Illuminate\Support\Env;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class ProductionConfigSecurityTest extends TestCase
{
    public function test_app_config_fails_closed_without_committed_app_key_or_debug_fallback(): void
    {
        $repo = Env::getRepository();
        $keys = ['APP_KEY', 'APP_DEBUG'];
        $backup = [];

        foreach ($keys as $k) {
            $backup[$k] = [
                'has_repo' => $repo->has($k),
                'repo' => $repo->get($k),
                'has_env' => array_key_exists($k, $_ENV),
                'env' => $_ENV[$k] ?? null,
                'has_server' => array_key_exists($k, $_SERVER),
                'server' => $_SERVER[$k] ?? null,
                'getenv' => getenv($k),
            ];

            $repo->clear($k);
            unset($_ENV[$k], $_SERVER[$k]);
            putenv($k);
        }

        try {
            $config = require base_path('config/app.php');
        } finally {
            foreach ($keys as $k) {
                $b = $backup[$k];
                if ($b['has_repo'] && $b['repo'] !== null) {
                    $repo->set($k, $b['repo']);
                } else {
                    $repo->clear($k);
                }

                if ($b['has_env']) {
                    $_ENV[$k] = $b['env'];
                } else {
                    unset($_ENV[$k]);
                }

                if ($b['has_server']) {
                    $_SERVER[$k] = $b['server'];
                } else {
                    unset($_SERVER[$k]);
                }

                if ($b['getenv'] !== false) {
                    putenv($k . '=' . $b['getenv']);
                } else {
                    putenv($k);
                }
            }
        }

        $this->assertFalse($config['debug'], 'APP_DEBUG must default to false when unset.');
        $this->assertNull($config['key'], 'APP_KEY must fail closed (null) instead of using a committed fallback key.');
    }

    public function test_app_config_treats_blank_app_key_as_null(): void
    {
        $repo = Env::getRepository();
        $hadRepo = $repo->has('APP_KEY');
        $prevRepo = $repo->get('APP_KEY');
        $hadEnv = array_key_exists('APP_KEY', $_ENV);
        $prevEnv = $_ENV['APP_KEY'] ?? null;
        $hadServer = array_key_exists('APP_KEY', $_SERVER);
        $prevServer = $_SERVER['APP_KEY'] ?? null;
        $prevGetenv = getenv('APP_KEY');

        $repo->set('APP_KEY', '   ');
        $_ENV['APP_KEY'] = '   ';
        $_SERVER['APP_KEY'] = '   ';
        putenv('APP_KEY=   ');

        try {
            $config = require base_path('config/app.php');
        } finally {
            if ($hadRepo && $prevRepo !== null) {
                $repo->set('APP_KEY', $prevRepo);
            } else {
                $repo->clear('APP_KEY');
            }
            if ($hadEnv) {
                $_ENV['APP_KEY'] = $prevEnv;
            } else {
                unset($_ENV['APP_KEY']);
            }
            if ($hadServer) {
                $_SERVER['APP_KEY'] = $prevServer;
            } else {
                unset($_SERVER['APP_KEY']);
            }
            if ($prevGetenv !== false) {
                putenv('APP_KEY=' . $prevGetenv);
            } else {
                putenv('APP_KEY');
            }
        }

        $this->assertNull($config['key']);
    }

    public function test_api_exception_response_never_leaks_trace_or_secrets_when_debug_is_false(): void
    {
        Config::set('app.debug', false);

        Route::get('/api/_test/security-exception-check', function () {
            throw new \RuntimeException('Sensitive leak sb_secret_do_not_expose');
        });

        $response = $this->getJson('/api/_test/security-exception-check');
        $response->assertStatus(500);
        $response->assertExactJson(['error' => 'Server error.']);
        $this->assertStringNotContainsString('sb_secret_do_not_expose', $response->getContent());
        $this->assertArrayNotHasKey('file', $response->json());
        $this->assertArrayNotHasKey('line', $response->json());
    }
}
