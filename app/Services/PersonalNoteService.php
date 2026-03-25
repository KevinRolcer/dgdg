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

    public function getNotes(int $userId, string $filter = 'all', string $timeFilter = 'all', $month = null, $year = null, $folderId = null)
    {
        $query = PersonalNote::forUser($userId)->with('folder');

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
                $query->whereNotNull('scheduled_date')->where('is_archived', false);
                if ($month && $year) {
                    $query->whereMonth('scheduled_date', $month)->whereYear('scheduled_date', $year);
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

        if ($timeFilter !== 'all' && $filter !== 'trash') {
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
            if ($month && $year && $filter !== 'trash') {
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

        return $query->orderBy('is_encrypted', 'desc')
            ->orderBy('priority', 'desc')
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
        if (!empty($data['is_encrypted']) && !empty($data['password'])) {
            $data['content'] = $this->encryptContent($data['content'] ?? '', $data['password']);
            $data['password_verify_hash'] = Hash::make($data['password']);
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
        if (!empty($data['is_encrypted']) && !empty($data['password'])) {
            $data['content'] = $this->encryptContent($data['content'] ?? '', $data['password']);
            $data['password_verify_hash'] = Hash::make($data['password']);
        } elseif (empty($data['is_encrypted']) && $note->is_encrypted) {
            // Note was encrypted, now it's not. We need the old password to decrypt first?
            // Or only allow changing if password is provided.
            // Simplified: if is_encrypted is toggled OFF, we need the password to decrypt it first.
        }

        unset($data['password']);
        return $note->update($data);
    }
}
