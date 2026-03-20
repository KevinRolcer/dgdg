<?php

namespace App\Http\Controllers;

use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Sirve los .otf de Gilroy desde resources/fonts (única fuente de verdad).
 */
final class GilroyFontController extends Controller
{
    /** @var list<string> */
    private const ALLOWED = [
        'Gilroy-Black.otf',
        'Gilroy-BlackItalic.otf',
        'Gilroy-Bold.otf',
        'Gilroy-BoldItalic.otf',
        'Gilroy-ExtraBold.otf',
        'Gilroy-ExtraBoldItalic.otf',
        'Gilroy-Heavy.otf',
        'Gilroy-HeavyItalic.otf',
        'Gilroy-Light.otf',
        'Gilroy-LightItalic.otf',
        'Gilroy-Medium.otf',
        'Gilroy-MediumItalic.otf',
        'Gilroy-Regular.otf',
        'Gilroy-RegularItalic.otf',
        'Gilroy-SemiBold.otf',
        'Gilroy-SemiBoldItalic.otf',
        'Gilroy-Thin.otf',
        'Gilroy-ThinItalic.otf',
        'Gilroy-UltraLight.otf',
        'Gilroy-UltraLightItalic.otf',
    ];

    public function __invoke(string $file): BinaryFileResponse
    {
        if (! in_array($file, self::ALLOWED, true)) {
            abort(404);
        }

        $path = resource_path('fonts/Fuente Gilroy/'.$file);
        if (! is_readable($path)) {
            abort(404);
        }

        return response()->file($path, [
            'Content-Type' => 'font/otf',
            'Cache-Control' => 'public, max-age=31536000, immutable',
        ]);
    }
}
