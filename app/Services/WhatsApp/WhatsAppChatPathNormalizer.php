<?php

namespace App\Services\WhatsApp;

/**
 * Rutas de message_parts en BD: preferir relativas a storage_root_path para no repetir
 * el prefijo largo (whatsapp-chats/uuid/…) miles de veces en el JSON.
 */
class WhatsAppChatPathNormalizer
{
    public static function normalizeStoragePaths(array $paths, string $storageRootPath): array
    {
        $root = self::normRoot($storageRootPath);
        $out = [];
        foreach ($paths as $p) {
            $p = self::normPath((string) $p);
            if ($p === '') {
                continue;
            }
            if ($root !== '' && (str_starts_with($p, $root.'/') || $p === $root)) {
                $rel = $p === $root ? '' : substr($p, strlen($root) + 1);
                $out[] = $rel !== '' ? $rel : $p;
            } else {
                $out[] = $p;
            }
        }

        return $out;
    }

    /**
     * @return list<string> rutas completas relativas a la raíz del disco (como espera media() y rutas)
     */
    public static function expandStoragePaths(array $paths, string $storageRootPath): array
    {
        $root = self::normRoot($storageRootPath);
        $out = [];
        foreach ($paths as $p) {
            $p = self::normPath((string) $p);
            if ($p === '') {
                continue;
            }
            if ($root !== '' && (str_starts_with($p, $root.'/') || $p === $root)) {
                $out[] = $p;
            } elseif ($root !== '') {
                $out[] = $root.'/'.$p;
            } else {
                $out[] = $p;
            }
        }

        return $out;
    }

    private static function normRoot(string $r): string
    {
        return trim(str_replace('\\', '/', $r), '/');
    }

    private static function normPath(string $p): string
    {
        return trim(str_replace('\\', '/', $p), '/');
    }
}
