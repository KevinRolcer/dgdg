<?php

require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$svc = $app->make(App\Services\TemporaryModules\TemporaryModuleEntryDataService::class);

$p = $argv[1] ?? '';
$r = $svc->resolveStoredFilePath($p);

$storage = $app->make(Illuminate\Contracts\Filesystem\Factory::class);
$rootSecure = (string) config('filesystems.disks.secure_shared.root');
$rootPublic = (string) config('filesystems.disks.public.root');
$existsSecure = $storage->disk('secure_shared')->exists($p);
$existsPublic = $storage->disk('public')->exists($p);

echo json_encode([
    'in' => $p,
    'secure_shared_root' => $rootSecure,
    'public_root' => $rootPublic,
    'exists_secure_shared' => $existsSecure,
    'exists_public' => $existsPublic,
    'resolved' => $r,
    'is_file' => is_string($r) && is_file($r),
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT).PHP_EOL;

