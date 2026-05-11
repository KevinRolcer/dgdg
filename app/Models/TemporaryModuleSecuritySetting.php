<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

class TemporaryModuleSecuritySetting extends Model
{
    public const PDF_PASSWORD_KEY = 'pdf_password';

    protected $fillable = [
        'key',
        'value',
    ];

    public static function pdfPassword(): ?string
    {
        $row = self::query()->where('key', self::PDF_PASSWORD_KEY)->first();
        if (! $row || ! is_string($row->value) || trim($row->value) === '') {
            return null;
        }

        try {
            $password = Crypt::decryptString($row->value);
        } catch (\Throwable) {
            return null;
        }

        $password = trim($password);

        return $password !== '' ? $password : null;
    }

    public static function setPdfPassword(string $password): void
    {
        self::query()->updateOrCreate(
            ['key' => self::PDF_PASSWORD_KEY],
            ['value' => Crypt::encryptString($password)]
        );
    }

    public static function clearPdfPassword(): void
    {
        self::query()->where('key', self::PDF_PASSWORD_KEY)->delete();
    }
}
