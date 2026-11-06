<?php

declare(strict_types=1);

$basePath = is_file(__DIR__.'/artisan') ? __DIR__ : dirname(__DIR__);
$publicPath = is_file(__DIR__.'/artisan') ? __DIR__ : $basePath.'/public';
$envPath = $basePath.'/.env';
$envExamplePath = $basePath.'/.env.example';
$lockPath = $basePath.'/storage/app/install-bootstrap.complete';
$logPath = $basePath.'/storage/logs/install.log';
$message = null;
$old = $_POST;

function installerUrl(string $path = ''): string
{
    $directory = str_replace('\\', '/', dirname((string) ($_SERVER['SCRIPT_NAME'] ?? '/install.php')));
    $directory = in_array($directory, ['/', '.'], true) ? '' : rtrim($directory, '/');

    return $directory.'/'.ltrim($path, '/');
}

function installerWriteEnv(string $contents, string $key, string $value): string
{
    $encoded = preg_match('/^[A-Za-z0-9_.:\\/-]+$/', $value) ? $value : '"'.addcslashes($value, "\\\"").'"';
    $line = $key.'='.$encoded;
    $pattern = '/^'.preg_quote($key, '/').'=.*/m';

    return preg_match($pattern, $contents)
        ? (string) preg_replace($pattern, $line, $contents)
        : rtrim($contents)."\n{$line}\n";
}

function installerLog(string $logPath, Throwable $exception, string $commandOutput = ''): void
{
    $directory = dirname($logPath);
    if (! is_dir($directory)) {
        @mkdir($directory, 0775, true);
    }
    $entry = sprintf(
        "[%s] %s: %s\n%s%s\n\n",
        date(DATE_ATOM),
        get_class($exception),
        $exception->getMessage(),
        $commandOutput === '' ? '' : "Migration output:\n{$commandOutput}\n",
        $exception->getTraceAsString(),
    );
    @file_put_contents($logPath, $entry, FILE_APPEND | LOCK_EX);
}

function installerPreflight(string $basePath, string $driver = 'mysql'): array
{
    $failures = [];
    if (PHP_VERSION_ID < 80401) {
        $failures[] = 'PHP 8.4.1 or later is required.';
    }
    $requiredExtensions = ['pdo', $driver === 'pgsql' ? 'pdo_pgsql' : 'pdo_mysql', 'openssl', 'fileinfo', 'dom', 'session', 'tokenizer'];
    foreach ($requiredExtensions as $extension) {
        if (! extension_loaded($extension)) {
            $failures[] = "The PHP {$extension} extension is required.";
        }
    }
    foreach (['artisan', 'vendor/autoload.php', 'bootstrap/app.php', 'database/migrations/2026_10_01_000001_create_installation_tables.php'] as $file) {
        if (! is_file($basePath.'/'.$file)) {
            $failures[] = "The deployment is incomplete: {$file} is missing.";
        }
    }
    foreach (['storage', 'storage/app', 'storage/app/public', 'storage/logs', 'storage/framework', 'storage/framework/cache', 'storage/framework/sessions', 'storage/framework/views', 'bootstrap/cache'] as $directory) {
        $path = $basePath.'/'.$directory;
        if (! is_dir($path) && ! @mkdir($path, 0775, true)) {
            $failures[] = "Cannot create {$directory}.";
        } elseif (! is_writable($path)) {
            $failures[] = "{$directory} is not writable.";
        }
    }
    if (is_file($basePath.'/.env') ? ! is_writable($basePath.'/.env') : ! is_writable($basePath)) {
        $failures[] = 'The application directory cannot create or update .env.';
    }

    return $failures;
}

function installerMissingTables(PDO $pdo, string $driver): array
{
    $required = ['migrations', 'users', 'sessions', 'cache', 'jobs', 'ministry_settings', 'locations', 'feature_settings', 'roles', 'permissions'];
    if ($driver === 'pgsql') {
        $query = $pdo->query("SELECT table_name FROM information_schema.tables WHERE table_schema = 'public'");
    } else {
        $database = (string) $pdo->query('SELECT DATABASE()')->fetchColumn();
        $query = $pdo->prepare('SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = ?');
        $query->execute([$database]);
    }
    $present = array_fill_keys(array_map('strtolower', $query->fetchAll(PDO::FETCH_COLUMN)), true);

    return array_values(array_filter($required, static fn (string $table): bool => ! isset($present[$table])));
}

$driver = (string) ($_POST['db_driver'] ?? 'mysql');
$driver = in_array($driver, ['mysql', 'pgsql'], true) ? $driver : 'mysql';
$preflightFailures = installerPreflight($basePath, $driver);

if (is_file($lockPath)) {
    header('Location: '.installerUrl('index.php?setup=1'), true, 302);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $host = trim((string) ($_POST['db_host'] ?? ''));
    $port = (int) ($_POST['db_port'] ?? ($driver === 'pgsql' ? 5432 : 3306));
    $database = trim((string) ($_POST['db_database'] ?? ''));
    $username = trim((string) ($_POST['db_username'] ?? ''));
    $password = (string) ($_POST['db_password'] ?? '');
    $migrationOutput = '';

    if ($preflightFailures !== []) {
        $message = 'Resolve the hosting checks below before continuing.';
    } elseif ($host === '' || $database === '' || $username === '' || $port < 1 || $port > 65535) {
        $message = 'Enter the database host, port, name, and username.';
    } else {
        try {
            $dsn = $driver === 'pgsql'
                ? "pgsql:host={$host};port={$port};dbname={$database}"
                : "mysql:host={$host};port={$port};dbname={$database};charset=utf8mb4";
            $pdo = new PDO(
                $dsn,
                $username,
                $password,
                [PDO::ATTR_TIMEOUT => 8, PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC],
            );
            $env = is_file($envPath)
                ? (string) file_get_contents($envPath)
                : (is_file($envExamplePath) ? (string) file_get_contents($envExamplePath) : "APP_NAME=\"Ministry Platform\"\n");
            $https = (! empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
                || strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https';
            $appUrl = ($https ? 'https' : 'http').'://'.($_SERVER['HTTP_HOST'] ?? 'localhost').rtrim(installerUrl(), '/');
            $values = [
                'APP_ENV' => 'production',
                'APP_DEBUG' => 'false',
                'APP_URL' => $appUrl,
                'APP_KEY' => 'base64:'.base64_encode(random_bytes(32)),
                'DB_CONNECTION' => $driver,
                'DB_HOST' => $host,
                'DB_PORT' => (string) $port,
                'DB_DATABASE' => $database,
                'DB_USERNAME' => $username,
                'DB_PASSWORD' => $password,
                'SESSION_DRIVER' => 'file',
                'CACHE_STORE' => 'file',
                'QUEUE_CONNECTION' => 'sync',
            ];
            foreach ($values as $key => $value) {
                $env = installerWriteEnv($env, $key, $value);
            }
            if (file_put_contents($envPath, $env, LOCK_EX) === false) {
                throw new RuntimeException('The installer could not write .env.');
            }

            foreach (['config.php', 'packages.php', 'services.php'] as $cacheFile) {
                $path = $basePath.'/bootstrap/cache/'.$cacheFile;
                if (is_file($path) && ! @unlink($path)) {
                    throw new RuntimeException("The installer could not remove bootstrap/cache/{$cacheFile}.");
                }
            }

            require_once $basePath.'/vendor/autoload.php';
            $application = require $basePath.'/bootstrap/app.php';
            ob_start();
            try {
                $status = $application->handleCommand(new Symfony\Component\Console\Input\ArrayInput([
                    'command' => 'migrate',
                    '--force' => true,
                    '--no-interaction' => true,
                ]));
            } finally {
                $migrationOutput = trim((string) ob_get_contents());
                ob_end_clean();
            }
            if ($status !== 0) {
                throw new RuntimeException('Database migrations failed. '.$migrationOutput);
            }

            $missingTables = installerMissingTables($pdo, $driver);
            if ($missingTables !== []) {
                throw new RuntimeException(
                    'Migration verification failed. Missing tables: '.implode(', ', $missingTables).'. '
                    .'Use a new empty database if this database contains an incomplete earlier installation.'
                );
            }

            $publicStoragePath = $publicPath.'/storage';
            if (! is_link($publicStoragePath) && ! is_dir($publicStoragePath)) {
                ob_start();
                try {
                    $application->handleCommand(new Symfony\Component\Console\Input\ArrayInput([
                        'command' => 'storage:link',
                        '--no-interaction' => true,
                    ]));
                } catch (Throwable) {
                    // The application route serves public uploads when the
                    // hosting provider does not permit symbolic links.
                } finally {
                    ob_end_clean();
                }
            }

            foreach (['SESSION_DRIVER' => 'database', 'CACHE_STORE' => 'database', 'QUEUE_CONNECTION' => 'database'] as $key => $value) {
                $env = installerWriteEnv($env, $key, $value);
            }
            if (file_put_contents($envPath, $env, LOCK_EX) === false) {
                throw new RuntimeException('The installer could not finalize .env.');
            }
            $configCache = $basePath.'/bootstrap/cache/config.php';
            if (is_file($configCache)) {
                @unlink($configCache);
            }
            if (! is_dir(dirname($lockPath)) && ! mkdir(dirname($lockPath), 0775, true)) {
                throw new RuntimeException('The installer could not create storage/app.');
            }
            if (file_put_contents($lockPath, date(DATE_ATOM), LOCK_EX) === false) {
                throw new RuntimeException('The installer could not write its completion marker.');
            }

            header('Location: '.installerUrl('index.php?setup=1'), true, 302);
            exit;
        } catch (Throwable $exception) {
            installerLog($logPath, $exception, $migrationOutput);
            $message = 'Installation failed: '.$exception->getMessage();
        }
    }
}
?><!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Install Ministry Platform</title>
    <style>
        :root {
            color-scheme: light;
            --setup-blue: #2563eb;
            --setup-blue-hover: #1d4ed8;
            --setup-canvas: #f6f9ff;
            --setup-surface: #ffffff;
            --setup-ink: #10213d;
            --setup-muted: #53637c;
            --setup-line: #d7e2f3;
            --setup-line-strong: #b8cbe7;
            --setup-success: #067647;
            --setup-danger: #b42318;
            --setup-focus: rgb(37 99 235 / 14%);
            --setup-shadow: rgb(15 49 102 / 12%);
        }

        *, *::before, *::after { box-sizing: border-box; }
        html, body { min-width: 0; min-height: 100%; margin: 0; }
        body {
            background: var(--setup-canvas);
            color: var(--setup-ink);
            font-family: Inter, ui-sans-serif, system-ui, -apple-system, "Segoe UI", sans-serif;
        }

        .setup-shell {
            display: grid;
            width: 100%;
            min-height: 100dvh;
            grid-template-columns: minmax(310px, .9fr) minmax(560px, 1.1fr);
            background: var(--setup-canvas);
        }

        .setup-aside {
            display: flex;
            min-height: 100%;
            flex-direction: column;
            padding: clamp(28px, 5vw, 72px);
            background: var(--setup-blue);
            color: #fff;
        }

        .setup-brand {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            color: #fff;
            font-size: .78rem;
            font-weight: 780;
        }

        .setup-brand i {
            display: grid;
            width: 33px;
            height: 33px;
            place-items: center;
            border-radius: 10px;
            background: rgb(255 255 255 / 14%);
            color: #fff;
            font-style: normal;
            font-size: 1rem;
        }

        .setup-aside-copy {
            max-width: 440px;
            margin-top: clamp(60px, 11vh, 136px);
        }

        .setup-aside-copy > p {
            margin: 0 0 11px;
            color: #fff;
            font-size: .61rem;
            font-weight: 840;
            letter-spacing: .15em;
        }

        .setup-aside-copy h1 {
            margin: 0;
            color: #fff;
            font-size: clamp(2.45rem, 4.1vw, 4.7rem);
            font-weight: 760;
            letter-spacing: -.067em;
            line-height: .98;
        }

        .setup-aside-copy > span {
            display: block;
            max-width: 39ch;
            margin-top: 21px;
            color: rgb(255 255 255 / 88%);
            font-size: .82rem;
            line-height: 1.65;
        }

        .setup-progress {
            display: grid;
            gap: 14px;
            margin: clamp(48px, 9vh, 104px) 0 0;
            padding: 0;
            list-style: none;
        }

        .setup-progress li {
            display: flex;
            align-items: center;
            gap: 12px;
            color: #fff;
            opacity: .46;
        }

        .setup-progress li.is-active { opacity: 1; }

        .setup-progress b {
            display: grid;
            width: 28px;
            height: 28px;
            place-items: center;
            border: 1px solid rgb(255 255 255 / 38%);
            border-radius: 50%;
            color: #fff;
            font-size: .55rem;
            font-weight: 780;
        }

        .setup-progress li.is-active b {
            border-color: #fff;
            background: #fff;
            color: var(--setup-blue);
        }

        .setup-progress li > span { display: grid; gap: 2px; }
        .setup-progress strong { color: #fff; font-size: .68rem; }
        .setup-progress small { color: rgb(255 255 255 / 78%); font-size: .58rem; }
        .setup-aside-foot { margin-top: auto; color: rgb(255 255 255 / 78%); font-size: .62rem; }

        .setup-workspace {
            display: grid;
            width: 100%;
            max-width: 780px;
            align-content: center;
            padding: clamp(30px, 7vw, 108px);
            background: var(--setup-canvas);
        }

        .setup-topbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            margin-bottom: clamp(35px, 6vh, 72px);
            color: var(--setup-muted);
            font-size: .64rem;
            font-weight: 740;
        }

        .setup-save-note { display: inline-flex; align-items: center; gap: 7px; }
        .setup-save-note i {
            width: 7px;
            height: 7px;
            border-radius: 50%;
            background: var(--setup-blue);
            box-shadow: 0 0 0 4px var(--setup-focus);
        }

        .setup-eyebrow {
            margin: 0 0 11px;
            color: var(--setup-blue);
            font-size: .61rem;
            font-weight: 840;
            letter-spacing: .15em;
        }

        .setup-workspace h2 {
            max-width: 590px;
            margin: 0;
            color: var(--setup-ink);
            font-size: clamp(2.1rem, 3.5vw, 3.35rem);
            font-weight: 760;
            letter-spacing: -.064em;
            line-height: 1.02;
        }

        .setup-intro {
            display: block;
            max-width: 540px;
            margin: 15px 0 0;
            color: var(--setup-muted);
            font-size: .79rem;
            line-height: 1.65;
        }

        .checks {
            display: grid;
            width: 100%;
            max-width: 620px;
            margin: 26px 0 0;
            border: 1px solid var(--setup-line);
            border-radius: 14px;
            padding: 0;
            background: #edf4ff;
            list-style: none;
        }

        .checks li {
            display: flex;
            gap: 10px;
            padding: 13px 15px;
            color: var(--setup-success);
            font-size: .68rem;
            line-height: 1.5;
        }

        .checks li.fail { color: var(--setup-danger); }

        .error {
            width: 100%;
            max-width: 620px;
            margin-top: 18px;
            border: 1px solid rgb(180 35 24 / 28%);
            border-radius: 12px;
            padding: 13px 15px;
            background: #fff1f0;
            color: var(--setup-danger);
            font-size: .68rem;
            line-height: 1.5;
        }

        .setup-form {
            width: 100%;
            max-width: 620px;
            min-width: 0;
            margin: 0;
            justify-self: stretch;
        }

        .setup-fields {
            display: grid;
            width: 100%;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 15px;
            margin-top: 37px;
        }

        .setup-fields label {
            display: grid;
            min-width: 0;
            gap: 8px;
            color: var(--setup-muted);
            font-size: .66rem;
            font-weight: 760;
        }

        .setup-field-wide { grid-column: 1 / -1; }

        .setup-fields input,
        .setup-fields select {
            width: 100%;
            min-width: 0;
            min-height: 47px;
            border: 1px solid var(--setup-line-strong);
            border-radius: 11px;
            padding: 0 12px;
            background-color: var(--setup-surface);
            color: var(--setup-ink);
            font: inherit;
            font-size: .73rem;
            font-weight: 620;
            outline: 0;
            transition: border-color .16s ease, box-shadow .16s ease;
        }

        .setup-fields select {
            appearance: none;
            padding-right: 39px;
            background-image:
                linear-gradient(45deg, transparent 50%, var(--setup-blue) 50%),
                linear-gradient(135deg, var(--setup-blue) 50%, transparent 50%);
            background-position: calc(100% - 18px) 20px, calc(100% - 13px) 20px;
            background-repeat: no-repeat;
            background-size: 5px 5px, 5px 5px;
            cursor: pointer;
        }

        .setup-fields input:hover,
        .setup-fields select:hover { border-color: #8cafe6; }

        .setup-fields input:focus,
        .setup-fields select:focus {
            border-color: var(--setup-blue);
            box-shadow: 0 0 0 4px var(--setup-focus);
        }

        .setup-form button[type="submit"] {
            min-height: 45px;
            margin-top: 42px;
            border: 1px solid var(--setup-blue);
            border-radius: 11px;
            padding: 0 16px;
            background: var(--setup-blue);
            color: #fff;
            font: inherit;
            font-size: .68rem;
            font-weight: 790;
            box-shadow: 0 10px 21px rgb(37 99 235 / 19%);
            cursor: pointer;
            transition: background .16s ease, transform .16s ease;
        }

        .setup-form button[type="submit"]:hover {
            background: var(--setup-blue-hover);
            transform: translateY(-1px);
        }

        .setup-form button[type="submit"]:focus-visible {
            outline: 3px solid rgb(37 99 235 / 38%);
            outline-offset: 3px;
        }

        .setup-form button[type="submit"]:disabled {
            cursor: not-allowed;
            opacity: .55;
            transform: none;
        }

        @media (max-width: 860px) {
            .setup-shell { grid-template-columns: 1fr; }
            .setup-aside { min-height: auto; padding: 25px 32px 30px; }
            .setup-aside-copy { margin-top: 34px; }
            .setup-aside-copy h1 { max-width: 620px; font-size: 2.5rem; }
            .setup-progress {
                grid-template-columns: repeat(5, minmax(0, 1fr));
                gap: 8px;
                margin-top: 30px;
            }
            .setup-progress li { gap: 7px; }
            .setup-progress li > span { display: none; }
            .setup-aside-foot { display: none; }
            .setup-workspace {
                max-width: none;
                padding: 48px 32px 65px;
            }
            .setup-topbar { margin-bottom: 48px; }
            .setup-form {
                width: 100%;
                max-width: none;
                margin: 0;
            }
        }

        @media (max-width: 520px) {
            .setup-aside { padding: 21px 18px 26px; }
            .setup-aside-copy { margin-top: 26px; }
            .setup-aside-copy h1 { font-size: 2.18rem; }
            .setup-aside-copy > span { font-size: .71rem; }
            .setup-progress { margin-top: 23px; }
            .setup-progress b { width: 25px; height: 25px; font-size: .5rem; }
            .setup-workspace { padding: 38px 18px 50px; }
            .setup-topbar { margin-bottom: 36px; }
            .setup-save-note { display: none; }
            .setup-workspace h2 { font-size: 2.05rem; }
            .setup-intro { font-size: .72rem; }
            .setup-fields {
                grid-template-columns: 1fr;
                gap: 14px;
                margin-top: 29px;
            }
            .setup-field-wide { grid-column: auto; }
            .setup-form button[type="submit"] {
                width: 100%;
                margin-top: 33px;
            }
        }
    </style>
</head>
<body>
<main class="shell setup-shell">
    <aside class="intro setup-aside"><div class="brand setup-brand"><i>✦</i><span>Ministry Platform</span></div><div class="install-intro-copy setup-aside-copy"><p>START YOUR INSTALLATION</p><h1>Build a home for your ministry.</h1><span>A guided start for your public presence, people, and everyday ministry work.</span></div><ol class="install-progress setup-progress" aria-label="Installation progress"><li class="is-active"><b>01</b><span><strong>Database</strong><small>Connect the installation</small></span></li><li><b>02</b><span><strong>Ministry</strong><small>Your identity</small></span></li><li><b>03</b><span><strong>Home base</strong><small>Time and location</small></span></li><li><b>04</b><span><strong>Theme</strong><small>Starting preset</small></span></li><li><b>05</b><span><strong>Administrator</strong><small>Secure access</small></span></li></ol><p class="install-aside-foot setup-aside-foot">Your ministry information stays with this installation.</p></aside>
    <section class="panel setup-workspace">
        <header class="setup-topbar"><span>Step 1 of 5</span><span class="setup-save-note"><i></i> Setup takes about 3 minutes</span></header>
        <p class="eyebrow setup-eyebrow">ONE-MINISTRY INSTALLATION</p>
        <h2>Connect your database.</h2>
        <p class="lede setup-intro">Use a new, empty MySQL or PostgreSQL database. The installer generates the application key, runs every migration, verifies the schema, and then opens ministry setup.</p>
        <?php if ($preflightFailures !== []): ?><ul class="checks"><?php foreach ($preflightFailures as $failure): ?><li class="fail"><span>×</span><span><?= htmlspecialchars($failure, ENT_QUOTES, 'UTF-8') ?></span></li><?php endforeach; ?></ul><?php else: ?><ul class="checks"><li><span>✓</span><span>PHP, deployment files, and writable application directories are ready.</span></li></ul><?php endif; ?>
        <?php if ($message): ?><div class="error" role="alert"><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
        <form class="setup-form" method="post" action="<?= htmlspecialchars(installerUrl('install.php'), ENT_QUOTES, 'UTF-8') ?>">
            <div class="grid setup-fields">
                <label class="wide setup-field-wide">Database driver<select name="db_driver" id="db_driver"><option value="mysql" <?= $driver === 'mysql' ? 'selected' : '' ?>>MySQL</option><option value="pgsql" <?= $driver === 'pgsql' ? 'selected' : '' ?>>PostgreSQL</option></select></label>
                <label>Database host<input name="db_host" value="<?= htmlspecialchars((string) ($old['db_host'] ?? 'localhost'), ENT_QUOTES, 'UTF-8') ?>" required></label>
                <label>Database port<input name="db_port" id="db_port" value="<?= htmlspecialchars((string) ($old['db_port'] ?? ($driver === 'pgsql' ? '5432' : '3306')), ENT_QUOTES, 'UTF-8') ?>" inputmode="numeric" required></label>
                <label class="wide setup-field-wide">Database name<input name="db_database" value="<?= htmlspecialchars((string) ($old['db_database'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" required></label>
                <label>Database username<input name="db_username" value="<?= htmlspecialchars((string) ($old['db_username'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" required></label>
                <label>Database password<input type="password" name="db_password" autocomplete="new-password"></label>
            </div>
            <button type="submit" <?= $preflightFailures !== [] ? 'disabled' : '' ?>>Install and continue →</button>
        </form>
        <script>
            document.getElementById('db_driver')?.addEventListener('change', function () {
                const port = document.getElementById('db_port');
                if (port && (!port.value || port.value === '3306' || port.value === '5432')) port.value = this.value === 'pgsql' ? '5432' : '3306';
            });
        </script>
    </section>
</main>
</body>
</html>
