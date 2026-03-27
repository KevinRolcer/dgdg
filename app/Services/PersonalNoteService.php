<?php

namespace App\Services;

use App\Models\PersonalNote;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class PersonalNoteService
{
    private const ENCRYPTION_ALGO = 'AES-256-CBC';
    private const PBKDF2_ITERATIONS = 10000;
    private const PBKDF2_SALT = 'segob_personal_note_salt'; // In a real app, this should be unique per note

    /**
     * Encrypt content with a user-provided password.
     */
    public function encryptContent(string $content, string $password): string
    {
        $key = $this->deriveKey($password);
        $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length(self::ENCRYPTION_ALGO));
        $encrypted = openssl_encrypt($content, self::ENCRYPTION_ALGO, $key, 0, $iv);

        // Prepend IV to the encrypted content for later decryption
        return base64_encode($iv . $encrypted);
    }

    /**
     * Decrypt content with a user-provided password.
     */
    public function decryptContent(string $encryptedData, string $password): ?string
    {
        try {
            $decoded = base64_decode($encryptedData, true);
            if ($decoded === false) {
                Log::error('Decryption failed: Invalid base64 data.');
                return null;
            }

            $ivLength = openssl_cipher_iv_length(self::ENCRYPTION_ALGO);
            if (strlen($decoded) < $ivLength) {
                Log::error('Decryption failed: Data too short for IV.');
                return null;
            }

            $iv = substr($decoded, 0, $ivLength);
            $cipherText = substr($decoded, $ivLength);

            $key = $this->deriveKey($password);
            $decrypted = openssl_decrypt($cipherText, self::ENCRYPTION_ALGO, $key, 0, $iv);

            return $decrypted !== false ? $decrypted : null;
        } catch (\Exception $e) {
            Log::error('Decryption failed: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Derive a 256-bit key from a password using PBKDF2.
     */
    private function deriveKey(string $password): string
    {
        return hash_pbkdf2('sha256', $password, self::PBKDF2_SALT, self::PBKDF2_ITERATIONS, 32, true);
    }

    public function getNotes(int $userId, string $filter = 'all', string $timeFilter = 'all', $month = null, $year = null, $folderId = null, $priority = null, $creationDate = null, ?string $search = null)
    {
        $query = PersonalNote::forUser($userId);
        if ($filter === 'archive') {
            // Carpetas archivadas (soft delete): mantener nombre en la nota cargando la carpeta eliminada
            $query->with(['folder' => function ($q) {
                $q->withTrashed();
            }]);
        } else {
            $query->with('folder');
        }

        // Apply Priority Filter
        if ($priority && $priority !== 'all') {
            $query->where('priority', $priority);
        }

        // Apply Creation Date Filter
        if ($creationDate) {
            $query->whereDate('created_at', $creationDate);
        }

        // If navigating inside a specific folder
        if ($folderId && $folderId !== 'all') {
            $query->where('folder_id', (int)$folderId);
        }

        // Main filter (Sidebar/Context)
        switch ($filter) {
            case 'archive':
                $query->where('is_archived', true);
                break;
            case 'trash':
                $query->onlyTrashed();
                break;
            case 'calendar':
                $query->where('is_archived', false);
                if ($month && $year) {
                    $query->where(function ($q) use ($month, $year) {
                        $q->where(function ($q2) use ($month, $year) {
                            $q2->whereNotNull('scheduled_date')
                                ->whereMonth('scheduled_date', $month)
                                ->whereYear('scheduled_date', $year);
                        })->orWhere(function ($q2) use ($month, $year) {
                            $q2->whereNull('scheduled_date')
                                ->whereMonth('created_at', $month)
                                ->whereYear('created_at', $year);
                        });
                    });
                }
                break;
            case 'folders':
            case 'folder':
            case 'all':
            default:
                $query->where('is_archived', false);
                // Si estamos navegando en una carpeta, forzar el filtro aunque el switch caiga en default/all
                if ($folderId && $folderId !== 'all') {
                    $query->where('folder_id', (int)$folderId);
                } else if ($filter === 'folder' || ($filter === 'folders' && $folderId)) {
                    // Caso de seguridad: si pide carpeta pero no hay ID, mostrar vacío o filtrar nulos si fuera el caso
                    $query->where('folder_id', -1); 
                }
                break;
        }

        // Apply Time Filter (Today, Week, Month) — no aplica a la vista Calendario: el mes ya se acota con month/year.
        if ($timeFilter !== 'all' && $filter !== 'trash' && !$creationDate && $filter !== 'calendar') {
            $dateField = ($filter === 'calendar') ? 'scheduled_date' : 'created_at';

            switch ($timeFilter) {
                case 'todays':
                    $query->whereDate($dateField, now());
                    break;
                case 'week':
                    $query->whereBetween($dateField, [now()->startOfWeek(), now()->endOfWeek()]);
                    break;
                case 'month':
                    $m = $month ?: now()->month;
                    $y = $year ?: now()->year;
                    $query->whereMonth($dateField, $m)->whereYear($dateField, $y);
                    break;
            }
        } else if ($month && $year && $filter !== 'trash' && $filter !== 'calendar') {
            // Rango de fechas mensual (fuera de Calendario)
            $query->where(function ($q) use ($month, $year) {
                $q->where(function ($q2) use ($month, $year) {
                    $q2->whereMonth('created_at', $month)->whereYear('created_at', $year);
                })->orWhere(function ($q2) use ($month, $year) {
                    $q2->whereMonth('scheduled_date', $month)->whereYear('scheduled_date', $year);
                });
            });
        }

        if ($search !== null && $search !== '') {
            $term = '%'.addcslashes($search, '%_\\').'%';
            $query->where(function ($q) use ($term) {
                $q->where('title', 'like', $term)
                    ->orWhere(function ($q2) use ($term) {
                        $q2->where('is_encrypted', false)
                            ->where('content', 'like', $term);
                    });
            });
        }

        return $query->orderBy('is_encrypted', 'desc')
            ->orderByRaw("CASE priority
                WHEN 'high' THEN 1
                WHEN 'medium' THEN 2
                WHEN 'low' THEN 3
                WHEN 'none' THEN 4
                ELSE 5 END ASC")
            ->orderByRaw("COALESCE(scheduled_time, '23:59:59') ASC")
            ->orderBy('created_at', 'desc')
            ->get();
    }

    public function archiveNote(PersonalNote $note): bool
    {
        return $note->update(['is_archived' => true]);
    }

    public function unarchiveNote(PersonalNote $note): bool
    {
        return $note->update(['is_archived' => false]);
    }

    /**
     * Create a new personal note.
     */
    public function createNote(array $data, int $userId): PersonalNote
    {
        $password = isset($data['password']) ? trim((string) $data['password']) : '';
        if (!empty($data['is_encrypted']) && $password !== '') {
            $data['content'] = $this->encryptContent($data['content'] ?? '', $password);
            $data['password_verify_hash'] = Hash::make($password);
            $data['is_encrypted'] = true;
        } else {
            $data['is_encrypted'] = false;
            $data['password_verify_hash'] = null;
        }

        $data['user_id'] = $userId;
        unset($data['password']);

        return PersonalNote::create($data);
    }

    /**
     * Folders Management
     */
    public function getFolders(int $userId, ?string $search = null)
    {
        $query = \App\Models\PersonalNoteFolder::where('user_id', '=', $userId)
            ->withCount('notes')
            ->orderByDesc('is_pinned')
            ->orderByDesc('pinned_at')
            ->orderBy('name');

        if ($search !== null && $search !== '') {
            $term = '%'.addcslashes($search, '%_\\').'%';
            $query->where('name', 'like', $term);
        }

        return $query->get();
    }

    /**
     * Carpetas archivadas (soft delete) del usuario, para la vista Archivo.
     */
    public function getArchivedFolders(int $userId, ?string $search = null)
    {
        $query = \App\Models\PersonalNoteFolder::onlyTrashed()
            ->where('user_id', '=', $userId)
            ->withCount('notes')
            ->orderByDesc('deleted_at');

        if ($search !== null && $search !== '') {
            $term = '%'.addcslashes($search, '%_\\').'%';
            $query->where('name', 'like', $term);
        }

        return $query->get();
    }

    /**
     * Notas con fecha programada hoy o mañana (activas, no archivadas, no papelera).
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, PersonalNote>
     */
    public function getPersonalNotesScheduledTodayOrTomorrow(int $userId, int $limit = 30)
    {
        $today = today();
        $tomorrow = today()->copy()->addDay();

        return PersonalNote::forUser($userId)
            ->with(['folder', 'attachments'])
            ->where('is_archived', false)
            ->whereNotNull('scheduled_date')
            ->where(function ($q) use ($today, $tomorrow) {
                $q->whereDate('scheduled_date', $today)
                    ->orWhereDate('scheduled_date', $tomorrow);
            })
            ->orderBy('scheduled_date')
            ->orderByRaw('COALESCE(scheduled_time, "23:59:59") ASC')
            ->orderByRaw("CASE priority WHEN 'high' THEN 1 WHEN 'medium' THEN 2 WHEN 'low' THEN 3 WHEN 'none' THEN 4 ELSE 5 END ASC")
            ->limit($limit)
            ->get();
    }

    public function pinnedFoldersCount(int $userId): int
    {
        return \App\Models\PersonalNoteFolder::where('user_id', $userId)
            ->where('is_pinned', true)
            ->count();
    }

    /**
     * @return array{success: bool, pinned?: bool, pinned_count?: int, message?: string}
     */
    public function toggleFolderPin(\App\Models\PersonalNoteFolder $folder): array
    {
        if ($folder->is_pinned) {
            $folder->update(['is_pinned' => false, 'pinned_at' => null]);

            return [
                'success' => true,
                'pinned' => false,
                'pinned_count' => $this->pinnedFoldersCount($folder->user_id),
            ];
        }

        $count = $this->pinnedFoldersCount($folder->user_id);
        if ($count >= 6) {
            return [
                'success' => false,
                'message' => 'Solo puedes fijar hasta 6 carpetas. Quita la fijación de una para añadir otra.',
            ];
        }

        $folder->update(['is_pinned' => true, 'pinned_at' => now()]);

        return [
            'success' => true,
            'pinned' => true,
            'pinned_count' => $count + 1,
        ];
    }

    public function createFolder(array $data, int $userId): \App\Models\PersonalNoteFolder
    {
        $data['user_id'] = $userId;
        return \App\Models\PersonalNoteFolder::create($data);
    }

    public function updateFolder(\App\Models\PersonalNoteFolder $folder, array $data): bool
    {
        return $folder->update($data);
    }

    public function archiveFolder(\App\Models\PersonalNoteFolder $folder): bool
    {
        return DB::transaction(function () use ($folder) {
            $folder->notes()->update(['is_archived' => true]);

            return $folder->delete();
        });
    }

    /**
     * Restaurar carpeta archivada (soft delete): vuelve a la lista de carpetas activas
     * y las notas que pertenecían a ella se desarchivan.
     */
    public function restoreFolder(\App\Models\PersonalNoteFolder $folder): bool
    {
        return DB::transaction(function () use ($folder) {
            $folder->restore();
            return $folder->notes()->update(['is_archived' => false]);
        });
    }

    public function deleteFolder(\App\Models\PersonalNoteFolder $folder, bool $deleteNotes = false): bool
    {
        if ($deleteNotes) {
            $folder->notes()->delete();
        } else {
            // Nullify folder_id in associated notes
            $folder->notes()->update(['folder_id' => null]);
        }
        return $folder->delete();
    }

    /**
     * Update an existing personal note.
     */
    public function updateNote(PersonalNote $note, array $data): bool
    {
        $password = isset($data['password']) ? trim((string) $data['password']) : '';
        if (!empty($data['is_encrypted']) && $password !== '') {
            $data['content'] = $this->encryptContent($data['content'] ?? '', $password);
            $data['password_verify_hash'] = Hash::make($password);
            $data['is_encrypted'] = true;
        } else {
            $data['is_encrypted'] = false;
            $data['password_verify_hash'] = null;
        }

        unset($data['password']);
        return $note->update($data);
    }

    /**
     * Eliminar definitivamente todas las notas en la papelera del usuario (archivos adjuntos y registros).
     */
    public function emptyTrash(int $userId): int
    {
        return (int) DB::transaction(function () use ($userId) {
            $notes = PersonalNote::onlyTrashed()
                ->forUser($userId)
                ->with('attachments')
                ->get();

            $count = 0;
            foreach ($notes as $note) {
                foreach ($note->attachments as $attachment) {
                    foreach (['secure_shared', 'public'] as $disk) {
                        if (Storage::disk($disk)->exists($attachment->file_path)) {
                            Storage::disk($disk)->delete($attachment->file_path);
                            break;
                        }
                    }
                }
                $note->forceDelete();
                $count++;
            }

            return $count;
        });
    }
}
