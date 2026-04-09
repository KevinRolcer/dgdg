<?php

namespace App\Services;

use App\Models\PersonalNote;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class PersonalNoteService
{
    private const ENCRYPTION_ALGO = 'aes-256-gcm';
    private const LEGACY_ENCRYPTION_ALGO = 'AES-256-CBC';
    private const PBKDF2_ITERATIONS = 120000;
    private const LEGACY_PBKDF2_ITERATIONS = 10000;
    private const LEGACY_PBKDF2_SALT = 'segob_personal_note_salt';

    /**
     * Encrypt content with a user-provided password.
     */
    public function encryptContent(string $content, string $password): string
    {
        $salt = random_bytes(16);
        $iv = random_bytes(openssl_cipher_iv_length(self::ENCRYPTION_ALGO));
        $key = $this->deriveKey($password, $salt, self::PBKDF2_ITERATIONS);
        $tag = '';
        $encrypted = openssl_encrypt($content, self::ENCRYPTION_ALGO, $key, OPENSSL_RAW_DATA, $iv, $tag);

        if ($encrypted === false) {
            throw new \RuntimeException('No se pudo cifrar la nota personal.');
        }

        return json_encode([
            'v' => 2,
            'alg' => self::ENCRYPTION_ALGO,
            'iter' => self::PBKDF2_ITERATIONS,
            'salt' => base64_encode($salt),
            'iv' => base64_encode($iv),
            'tag' => base64_encode($tag),
            'data' => base64_encode($encrypted),
        ], JSON_UNESCAPED_SLASHES);
    }

    /**
     * Decrypt content with a user-provided password.
     */
    public function decryptContent(string $encryptedData, string $password): ?string
    {
        try {
            $payload = json_decode($encryptedData, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($payload) && (int) ($payload['v'] ?? 0) >= 2) {
                $cipher = (string) ($payload['alg'] ?? self::ENCRYPTION_ALGO);
                $salt = base64_decode((string) ($payload['salt'] ?? ''), true);
                $iv = base64_decode((string) ($payload['iv'] ?? ''), true);
                $tag = base64_decode((string) ($payload['tag'] ?? ''), true);
                $cipherText = base64_decode((string) ($payload['data'] ?? ''), true);
                $iterations = max((int) ($payload['iter'] ?? self::PBKDF2_ITERATIONS), 10000);

                if (! is_string($salt) || ! is_string($iv) || ! is_string($tag) || ! is_string($cipherText)) {
                    Log::warning('Decryption failed: invalid encrypted payload.');
                    return null;
                }

                $key = $this->deriveKey($password, $salt, $iterations);
                $decrypted = openssl_decrypt($cipherText, $cipher, $key, OPENSSL_RAW_DATA, $iv, $tag);

                return $decrypted !== false ? $decrypted : null;
            }

            $decoded = base64_decode($encryptedData, true);
            if ($decoded === false) {
                Log::warning('Decryption failed: invalid legacy base64 data.');
                return null;
            }

            $ivLength = openssl_cipher_iv_length(self::LEGACY_ENCRYPTION_ALGO);
            if (strlen($decoded) < $ivLength) {
                Log::warning('Decryption failed: legacy data too short for IV.');
                return null;
            }

            $iv = substr($decoded, 0, $ivLength);
            $cipherText = substr($decoded, $ivLength);

            $key = $this->deriveKey($password, self::LEGACY_PBKDF2_SALT, self::LEGACY_PBKDF2_ITERATIONS);
            $decrypted = openssl_decrypt($cipherText, self::LEGACY_ENCRYPTION_ALGO, $key, 0, $iv);

            return $decrypted !== false ? $decrypted : null;
        } catch (\Exception $e) {
            Log::warning('Decryption failed: '.$e->getMessage());
            return null;
        }
    }

    /**
     * Derive a 256-bit key from a password using PBKDF2.
     */
    private function deriveKey(string $password, string $salt, int $iterations): string
    {
        return hash_pbkdf2('sha256', $password, $salt, $iterations, 32, true);
    }

    public function getNotes(int $userId, string $filter = 'all', string $timeFilter = 'all', $month = null, $year = null, $folderId = null, $priority = null, $creationDate = null, ?string $search = null)
    {
        $query = PersonalNote::forUser($userId);
        $isFolderExplorer = in_array($filter, ['folder', 'folders'], true);
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
                if ($folderId && $folderId !== 'all') {
                    $query->where('folder_id', (int)$folderId);
                }
                break;
        }

        // Apply Time Filter (Today, Week, Month) — no aplica a la vista Calendario: el mes ya se acota con month/year.
        // Tampoco aplica si estamos viendo una CARPETA específica, para evitar que las notas "desaparezcan" por fecha dentro de la carpeta.
        if ($timeFilter !== 'all' && $filter !== 'trash' && !$creationDate && $filter !== 'calendar' && !$folderId && ! $isFolderExplorer) {
            switch ($timeFilter) {
                case 'todays':
                    $query->where(function ($q) {
                        $q->whereDate('created_at', now())
                            ->orWhereDate('scheduled_date', now());
                    });
                    break;
                case 'week':
                    $startOfWeek = now()->startOfWeek();
                    $endOfWeek = now()->endOfWeek();
                    $query->where(function ($q) use ($startOfWeek, $endOfWeek) {
                        $q->whereBetween('created_at', [$startOfWeek, $endOfWeek])
                            ->orWhereBetween('scheduled_date', [$startOfWeek->toDateString(), $endOfWeek->toDateString()]);
                    });
                    break;
                case 'month':
                    $m = $month ?: now()->month;
                    $y = $year ?: now()->year;
                    $query->where(function ($q) use ($m, $y) {
                        $q->where(function ($q2) use ($m, $y) {
                            $q2->whereMonth('created_at', $m)->whereYear('created_at', $y);
                        })->orWhere(function ($q2) use ($m, $y) {
                            $q2->whereMonth('scheduled_date', $m)->whereYear('scheduled_date', $y);
                        });
                    });
                    break;
            }
        } else if ($month && $year && $filter !== 'trash' && $filter !== 'calendar' && ! $isFolderExplorer) {
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
            ->withCount([
                'notes' => function ($q) {
                    $q->where('is_archived', false);
                }
            ])
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

    /**
     * Notas con recordatorio (fecha programada) agrupadas por día, para el calendario de inicio.
     *
     * @return array<string, list<array<string, mixed>>>
     */
    public function getScheduledNotesByDayForHomeCalendar(int $userId): array
    {
        $from = now()->subYear()->startOfDay();
        $to = now()->addYears(2)->endOfDay();

        $notes = PersonalNote::forUser($userId)
            ->with(['folder', 'attachments'])
            ->where('is_archived', false)
            ->whereNotNull('scheduled_date')
            ->whereBetween('scheduled_date', [$from->toDateString(), $to->toDateString()])
            ->orderBy('scheduled_date')
            ->orderByRaw('COALESCE(scheduled_time, "23:59:59") ASC')
            ->get();

        $fallbackColors = ['#f0f4ff', '#f0fff4', '#fffdf0', '#fff0f0', '#f8f0ff'];
        $byDay = [];
        $idx = 0;

        foreach ($notes as $note) {
            $key = $note->scheduled_date->format('Y-m-d');
            $dotColor = $note->color ? (string) $note->color : $fallbackColors[$idx % count($fallbackColors)];
            $idx++;

            if (! isset($byDay[$key])) {
                $byDay[$key] = [];
            }
            $byDay[$key][] = $this->serializeNoteForHomeCalendarClient($note, $dotColor);
        }

        return $byDay;
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeNoteForHomeCalendarClient(PersonalNote $note, string $dotColor): array
    {
        $scheduled = $note->scheduled_date;
        $displayDate = $scheduled
            ? $scheduled->translatedFormat('d M Y')
                . ($note->scheduled_time ? ' · '.\Carbon\Carbon::parse($note->scheduled_time)->format('H:i') : ' · Todo el día')
            : $note->created_at->translatedFormat('d M Y');

        return [
            'kind' => 'note',
            'id' => $note->id,
            'title' => $note->title,
            'content' => $note->is_encrypted ? null : (string) $note->content,
            'priority' => $note->priority,
            'color' => $note->color,
            'dot_color' => $dotColor,
            'folder_id' => $note->folder_id,
            'is_encrypted' => (bool) $note->is_encrypted,
            'is_archived' => (bool) $note->is_archived,
            'scheduled_date' => $scheduled ? $scheduled->format('Y-m-d') : null,
            'scheduled_time' => $note->scheduled_time,
            'displayDate' => $displayDate,
            'attachments' => $note->attachments->map(fn ($a) => [
                'id' => $a->id,
                'file_name' => $a->file_name,
                'file_path' => route('personal-agenda.attachments.serve', $a->id),
                'file_type' => $a->file_type,
            ])->values()->all(),
        ];
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
