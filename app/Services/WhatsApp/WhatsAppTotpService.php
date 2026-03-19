<?php

namespace App\Services\WhatsApp;

use App\Models\User;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\SvgWriter;
use PragmaRX\Google2FA\Google2FA;

class WhatsAppTotpService
{
    public function __construct(
        private readonly Google2FA $google2fa,
    ) {}

    public function generateSecret(): string
    {
        return $this->google2fa->generateSecretKey();
    }

    /**
     * Etiqueta que verás en Google Authenticator (p. ej. el correo de la cuenta).
     */
    public function holderLabel(User $user): string
    {
        $override = config('whatsapp_chats.totp_holder_override');

        return is_string($override) && $override !== ''
            ? $override
            : (string) $user->email;
    }

    public function issuer(): string
    {
        return (string) config('whatsapp_chats.totp_issuer');
    }

    public function otpauthUri(string $secret, string $holder): string
    {
        return $this->google2fa->getQRCodeUrl(
            $this->issuer(),
            $holder,
            $secret
        );
    }

    public function qrCodeSvg(string $otpauthUri, int $size = 240): string
    {
        $qr = new QrCode(
            data: $otpauthUri,
            errorCorrectionLevel: ErrorCorrectionLevel::Medium,
            size: $size,
            margin: 8,
        );

        $writer = new SvgWriter;
        $result = $writer->write($qr, null, null, [
            SvgWriter::WRITER_OPTION_EXCLUDE_XML_DECLARATION => true,
        ]);

        return $result->getString();
    }

    public function verify(string $secret, string $code): bool
    {
        $code = (string) preg_replace('/\D/', '', $code);

        if (strlen($code) !== 6) {
            return false;
        }

        try {
            return (bool) $this->google2fa->verifyKey($secret, $code);
        } catch (\Throwable) {
            return false;
        }
    }
}
