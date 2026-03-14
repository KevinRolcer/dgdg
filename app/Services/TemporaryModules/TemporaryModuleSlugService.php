<?php

namespace App\Services\TemporaryModules;

use App\Models\TemporaryModule;
use Illuminate\Support\Facades\DB;

/**
 * Los módulos usan soft delete; el slug sigue único en BD. Al crear de nuevo con el mismo nombre,
 * hay que liberar el slug borrando en firme las versiones solo en papelera.
 */
class TemporaryModuleSlugService
{
    public function __construct(
        private readonly TemporaryModuleEntryDataService $entryDataService,
    ) {}

    /**
     * Elimina en firme todos los módulos en papelera que usen este slug (y datos asociados).
     * Así se puede volver a crear un módulo con el mismo nombre/slug.
     */
    public function forcePurgeTrashedBySlug(string $slug): int
    {
        $slug = trim($slug);
        if ($slug === '') {
            return 0;
        }

        $modules = TemporaryModule::onlyTrashed()->where('slug', $slug)->get();
        $count = 0;
        foreach ($modules as $module) {
            DB::transaction(function () use ($module): void {
                $module->loadMissing('fields');
                $this->entryDataService->clearModuleEntriesData($module);
                $module->targetUsers()->detach();
                $module->forceDelete();
            });
            $count++;
        }

        return $count;
    }

    /**
     * Libera el slug deseado: purga papelera con ese slug y, si hace falta, slugs derivados mi-modulo-2..N en papelera
     * para evitar bucles enormes al crear.
     */
    public function reclaimSlugForCreate(string $baseSlug, int $maxSuffixScan = 500): array
    {
        $baseSlug = trim($baseSlug);
        if ($baseSlug === '') {
            return ['purged' => 0, 'slug' => $baseSlug];
        }

        $purged = $this->forcePurgeTrashedBySlug($baseSlug);
        for ($i = 2; $i <= $maxSuffixScan; $i++) {
            $purged += $this->forcePurgeTrashedBySlug($baseSlug.'-'.$i);
        }

        return ['purged' => $purged, 'slug' => $baseSlug];
    }
}
