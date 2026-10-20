<?php

namespace App\Console\Commands;

use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

class MinistryDoctorCommand extends Command
{
    protected $signature = 'ministry:doctor {--profile=local : local, shared, or production} {--json : Output JSON} {--strict : Treat warnings as failures}';

    protected $description = 'Check installation configuration and core dependencies without changing data';

    public function handle(): int
    {
        $profile = $this->option('profile');
        if (! in_array($profile, ['local', 'shared', 'production'], true)) {
            $this->error('Profile must be local, shared, or production.');

            return self::FAILURE;
        }

        $checks = [];
        $add = function (string $name, string $status, string $message) use (&$checks): void {
            $checks[] = compact('name', 'status', 'message');
        };
        $production = $profile === 'production';
        $shared = $profile === 'shared';

        $requiredExtensions = ['ctype', 'curl', 'dom', 'fileinfo', 'filter', 'hash', 'mbstring', 'openssl', 'pcre', 'pdo', 'session', 'tokenizer', 'xml', 'pdo_mysql'];
        $missingExtensions = array_values(array_filter($requiredExtensions, fn (string $extension): bool => ! extension_loaded($extension)));
        $supportedPhp = PHP_VERSION_ID >= 80300;
        $runtimeReady = $supportedPhp && $missingExtensions === [];
        $runtimeMessage = $runtimeReady
            ? 'PHP '.PHP_VERSION.' and required shared-host extensions are available.'
            : (! $supportedPhp
                ? 'Use PHP 8.3 or later.'
                : 'Enable required PHP extension(s): '.implode(', ', $missingExtensions).'.');
        $add('runtime', $runtimeReady ? 'pass' : 'fail', $runtimeMessage);

        $key = (string) config('app.key');
        $add('app_key', $key !== '' && strlen($key) >= 32 ? 'pass' : 'fail',
            $key !== '' && strlen($key) >= 32 ? 'Application key is configured.' : 'Set a unique APP_KEY.');

        $url = (string) config('app.url');
        $https = str_starts_with($url, 'https://');
        $add('public_url', $https ? 'pass' : ($production ? 'fail' : 'warn'),
            $https ? 'Public URL uses HTTPS.' : 'Use a real HTTPS URL before production.');

        try {
            DB::connection()->getPdo();
            $driver = DB::connection()->getDriverName();
            $supported = in_array($driver, ['mysql', 'mariadb', 'pgsql'], true);
            $add('database', $supported ? 'pass' : 'fail', $supported ? strtoupper($driver).' connection is available.' : 'Use MySQL, MariaDB, or PostgreSQL.');
            $ran = Schema::hasTable('migrations') ? DB::table('migrations')->pluck('migration')->all() : [];
            $files = array_map(fn (string $path): string => pathinfo($path, PATHINFO_FILENAME), glob(database_path('migrations/*.php')) ?: []);
            $pending = array_values(array_diff($files, $ran));
            $add('migrations', $pending === [] ? 'pass' : 'fail',
                $pending === [] ? 'All migrations have run.' : count($pending).' migration(s) pending.');
            $installed = Schema::hasTable('ministry_settings') && DB::table('ministry_settings')->exists();
            $add('installation', $installed ? 'pass' : ($production ? 'fail' : 'warn'),
                $installed ? 'Ministry setup is complete.' : 'Run php artisan ministry:install.');
            $sessions = Schema::hasTable('sessions') && config('session.driver') === 'database';
            $add('sessions', $sessions ? 'pass' : 'fail',
                $sessions ? 'Revocable database sessions are configured.' : 'Configure the database session driver and run migrations.');
        } catch (\Throwable) {
            $add('database', 'fail', 'Database connection failed; check host, credentials, and service health.');
            $add('migrations', 'fail', 'Cannot inspect migrations until the database is available.');
            $add('installation', 'fail', 'Cannot inspect ministry setup until the database is available.');
            $add('sessions', 'fail', 'Cannot inspect sessions until the database is available.');
        }

        if ($shared && ! in_array(config('cache.default'), ['redis'], true) && config('queue.default') !== 'redis') {
            $add('redis', 'pass', 'Redis is not required for this shared-hosting profile.');
        } else {
            try {
                Redis::connection()->ping();
                $add('redis', 'pass', 'Redis connection is available.');
            } catch (\Throwable) {
                $add('redis', $shared ? 'warn' : 'fail', 'Redis connection failed; use database/sync fallbacks or configure Redis.');
            }
        }

        $writable = is_writable(storage_path()) && is_writable(base_path('bootstrap/cache'));
        $add('storage', $writable ? 'pass' : 'fail',
            $writable ? 'Application storage is writable.' : 'Make storage and bootstrap/cache writable by the API user.');

        $queue = (string) config('queue.default');
        $queueReady = in_array($queue, ['database', 'sync'], true) || ($queue === 'redis' && ! $shared);
        $queueStatus = $queueReady ? 'pass' : ($shared ? 'warn' : 'fail');
        $add('queue', $queueStatus, $queueReady ? "{$queue} queue is configured." : 'Use database or sync queue on shared hosting, or configure the selected queue provider.');

        $heartbeat = $this->schedulerHeartbeat();
        $heartbeatFresh = $heartbeat !== null && $heartbeat->greaterThan(now()->subMinutes(5));
        $add('scheduler', $heartbeatFresh ? 'pass' : ($shared ? 'warn' : 'fail'), $heartbeatFresh ? 'Scheduler heartbeat is recent.' : 'Configure host cron to run php artisan schedule:run every minute, then wait for a heartbeat.');

        $mail = (string) config('mail.default');
        $mailReady = ! in_array($mail, ['', 'log', 'array'], true);
        $add('mail', $mailReady ? 'pass' : ($production ? 'fail' : 'warn'),
            $mailReady ? 'A mail transport is configured; send a test message before launch.' : 'Configure a real mail transport before account email flows or production launch.');

        $bucket = (string) config('filesystems.disks.s3.bucket');
        $privateStore = config('filesystems.default') === 's3' && $bucket !== '' && (string) config('filesystems.disks.s3.key') !== '' && (string) config('filesystems.disks.s3.secret') !== '' && (string) config('filesystems.disks.s3.region') !== '';
        $add('object_storage', $privateStore ? 'pass' : ($production ? 'fail' : 'warn'),
            $privateStore ? 'Private S3-compatible storage is configured; run ministry:storage-preflight before launch.' : 'Set FILESYSTEM_DISK=s3 and private object-storage credentials before media or production launch.');

        $hasFailure = in_array('fail', array_column($checks, 'status'), true);
        $hasWarning = in_array('warn', array_column($checks, 'status'), true);
        $ready = ! $hasFailure && (! $this->option('strict') || ! $hasWarning);

        if ($this->option('json')) {
            $this->line(json_encode(['profile' => $profile, 'ready' => $ready, 'checks' => $checks], JSON_THROW_ON_ERROR));
        } else {
            $this->table(['Check', 'Status', 'Result'], array_map(fn (array $check): array => array_values($check), $checks));
            $this->line($ready ? 'Core checks passed for this profile.' : 'Setup needs attention.');
        }

        return $ready ? self::SUCCESS : self::FAILURE;
    }

    private function schedulerHeartbeat(): ?CarbonImmutable
    {
        try {
            if (! Storage::disk('local')->exists('ministry-health/scheduler.json')) {
                return null;
            }

            return CarbonImmutable::createFromTimestamp(Storage::disk('local')->lastModified('ministry-health/scheduler.json'));
        } catch (\Throwable) {
            return null;
        }
    }
}
