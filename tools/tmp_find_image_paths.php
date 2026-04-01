<?php

require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$moduleId = (int) ($argv[1] ?? 0);
$limit = (int) ($argv[2] ?? 25);

if ($moduleId <= 0) {
    fwrite(STDERR, "Usage: php tools/tmp_find_image_paths.php <moduleId> [limit]\n");
    exit(1);
}

$storage = $app->make(Illuminate\Contracts\Filesystem\Factory::class);
$resolver = $app->make(App\Services\TemporaryModules\TemporaryModuleEntryDataService::class);

$q = App\Models\TemporaryModuleEntry::query()
    ->withoutGlobalScopes()
    ->where('temporary_module_id', $moduleId)
    ->orderByDesc('id')
    ->limit($limit)
    ->get(['id', 'data']);

$out = [];
foreach ($q as $entry) {
    $data = is_array($entry->data) ? $entry->data : [];
    foreach ($data as $k => $v) {
        if (!is_string($v)) continue;
        $vv = trim($v);
        if ($vv === '') continue;
        if (!preg_match('~\\.(jpe?g|png|gif|webp|bmp)$~i', $vv)) continue;
        if (stripos($vv, 'temporary') === false && stripos($vv, '/storage/') === false) continue;
        $path = $vv;
        $rawPath = ltrim(parse_url($path, PHP_URL_PATH) ?? $path, '/');
        $candidates = array_values(array_unique(array_filter([
            $rawPath,
            str_replace('temporary-modules/', 'temporary_modules/', $rawPath),
            str_replace('temporary_modules/', 'temporary-modules/', $rawPath),
            preg_replace('~^temporarymodules/~i', 'temporary-modules/', $rawPath),
            preg_replace('~^temporarymodules/~i', 'temporary_modules/', $rawPath),
        ], fn ($x) => is_string($x) && $x !== '')));
        $existsSecure = false;
        $existsPublic = false;
        $hit = null;
        foreach ($candidates as $cand) {
            if (!$existsSecure && $storage->disk('secure_shared')->exists($cand)) {
                $existsSecure = true;
                $hit = $cand;
            }
            if (!$existsPublic && $storage->disk('public')->exists($cand)) {
                $existsPublic = true;
                $hit = $hit ?? $cand;
            }
        }
        $resolved = $resolver->resolveStoredFilePath($vv);
        $out[] = [
            'entry_id' => (int) $entry->id,
            'key' => (string) $k,
            'value' => $vv,
            'candidates' => $candidates,
            'hit_candidate' => $hit,
            'exists_secure_shared' => $existsSecure,
            'exists_public' => $existsPublic,
            'resolved_path' => $resolved,
            'resolved_is_file' => is_string($resolved) && is_file($resolved),
        ];
    }
}

echo json_encode($out, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT).PHP_EOL;

