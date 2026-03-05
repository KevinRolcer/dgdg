<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class CanvaController extends Controller
{
    // Genera un code_verifier aleatorio (43-128 caracteres, letras, números, -._~)
    private function generateCodeVerifier($length = 64)
    {
        $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789-._~';
        $verifier = '';
        for ($i = 0; $i < $length; $i++) {
            $verifier .= $chars[random_int(0, strlen($chars) - 1)];
        }
        return $verifier;
    }

    // Genera el code_challenge (SHA-256 + base64url)
    private function generateCodeChallenge($code_verifier)
    {
        $hash = hash('sha256', $code_verifier, true);
        return rtrim(strtr(base64_encode($hash), '+/', '-_'), '=');
    }

    public function authRedirect(Request $request)
    {
        $client_id = env('CANVA_CLIENT_ID');
        $redirect_uri = env('CANVA_REDIRECT_URI');
        $code_verifier = $this->generateCodeVerifier();
        $code_challenge = $this->generateCodeChallenge($code_verifier);

        // Guarda el code_verifier en sesión para el intercambio de token
        session(['canva_code_verifier' => $code_verifier]);

        $url = 'https://www.canva.com/api/oauth/authorize?' . http_build_query([
            'code_challenge_method' => 's256',
            'response_type' => 'code',
            'client_id' => $client_id,
            'redirect_uri' => $redirect_uri,
            'code_challenge' => $code_challenge,
            // Puedes agregar scope y state si lo necesitas
            // 'scope' => 'asset:read asset:write',
            // 'state' => bin2hex(random_bytes(16)),
        ]);

        return redirect($url);
    }

    // Endpoint para generar el documento en Canva y colocar el rango de fechas
    public function generarDocumento(Request $request)
    {
        $access_token = $request->user()->canva_token ?? null; // Ajusta según cómo guardes el token
        $template_id = env('CANVA_TEMPLATE_ID', 'DAHDHr-bAU4');
        $fecha_inicio = $request->input('fecha_inicio');
        $fecha_fin = $request->input('fecha_fin');

        // Validar que el rango no incluya sábados ni domingos
        if ($fecha_inicio && $fecha_fin) {
            $inicio = \Carbon\Carbon::parse($fecha_inicio);
            $fin = \Carbon\Carbon::parse($fecha_fin);
            $dias = [];
            for ($date = $inicio->copy(); $date <= $fin; $date->addDay()) {
                $dayOfWeek = $date->dayOfWeek;
                if ($dayOfWeek === 6 || $dayOfWeek === 0) { // 6 = Sábado, 0 = Domingo
                    return response()->json(['error' => 'El rango de fechas no debe incluir sábados ni domingos.'], 422);
                }
                $dias[] = $date->format('Y-m-d');
            }
            $rango = $inicio->format('d/m/Y') . ' al ' . $fin->format('d/m/Y');
        } else {
            $rango = $fecha_inicio ?: $fecha_fin ?: '';
        }

        // 1. Duplica la plantilla
        $duplicated = Http::withToken($access_token)
            ->post("https://api.canva.com/v1/designs/$template_id/duplicate")
            ->json();

        $design_id = $duplicated['id'] ?? null;
        if (!$design_id) {
            return response()->json(['error' => 'No se pudo duplicar la plantilla'], 500);
        }

        // 2. Obtén los elementos del diseño
        $elements = Http::withToken($access_token)
            ->get("https://api.canva.com/v1/designs/$design_id/elements")
            ->json();

        $element_id = null;
        foreach ($elements as $element) {
            if (isset($element['text']) && stripos($element['text'], 'fecha') !== false) {
                $element_id = $element['id'];
                break;
            }
        }

        if (!$element_id) {
            return response()->json(['error' => 'No se encontró el campo fecha'], 404);
        }

        // 3. Modifica el campo “fecha”
        Http::withToken($access_token)
            ->patch("https://api.canva.com/v1/designs/$design_id/elements/$element_id", [
                'text' => $rango
            ]);

        // 4. Devuelve el enlace para editar
        $url = "https://www.canva.com/design/$design_id/edit";
        return response()->json(['url' => $url]);
    }
}
