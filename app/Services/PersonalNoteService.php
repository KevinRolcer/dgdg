<?php

namespace App\Services;

use App\Models\PersonalNote;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

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

    public function getNotes(int $userId, string $filter = 'all', string $timeFilter = 'all', $month = null, $year = null, $folderId = null, $priority = null, $creationDate = null)
    {
        $query = PersonalNote::forUser($userId)->with('folder');

        // Apply Priority Filter
        if ($priority && $priority !== 'all') {
            $query->where('priority', $priority);
        }

        // Apply Creation Date Filter
        if ($creationDate) {
            $query->whereDate('created_at', $creationDate);
        }

        // If navigating inside a specific folder
        if ($folderId) {
            $query->where('folder_id', $folderId);
        }

        // Main filter (Sidebar)
        switch ($filter) {
            case 'archive':
                $query->where('is_archived', true);
                break;
            case 'trash':
                $query->onlyTrashed();
                break;
            case 'calendar':
                // Notas con fecha de evento en el mes, o sin evento pero creadas en el mes (para que el calendario no quede vacío).
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
                $query->where('is_archived', false);
                break;
            case 'all':
            default:
                $query->where('is_archived', false);
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
        } else {
            // Calendario: el rango de fechas ya va en case 'calendar'. Evitar duplicar condiciones (y OR raros).
            if ($month && $year && $filter !== 'trash' && $filter !== 'calendar') {
                if ($query instanceof \Illuminate\Database\Eloquent\Builder) {
                    $query->where(function ($q) use ($month, $year, $filter) {
                        $dateField = ($filter === 'calendar') ? 'scheduled_date' : 'created_at';
                        $q->where(function ($q2) use ($month, $year, $dateField) {
                            $q2->whereMonth($dateField, $month)->whereYear($dateField, $year);
                        })->orWhere(function ($q2) use ($month, $year) {
                            $q2->whereMonth('scheduled_date', $month)->whereYear('scheduled_date', $year);
                        });
                    });
                }
            }
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
    public function getFolders(int $userId)
    {
        return \App\Models\PersonalNoteFolder::where('user_id', $userId)
            ->withCount('notes')
            ->orderBy('name')
            ->get();
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
}
