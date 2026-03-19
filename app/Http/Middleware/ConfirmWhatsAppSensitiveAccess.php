<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Step-up: exige TOTP (Google Authenticator) una vez por sesión para chats sensibles.
 */
class ConfirmWhatsAppSensitiveAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $name = $request->route()?->getName();
        if (in_array($name, ['whatsapp-chats.admin.totp', 'whatsapp-chats.admin.totp.post'], true)) {
            return $next($request);
        }

        if ($request->session()->get('whatsapp_totp_session_ok') === true) {
            return $next($request);
        }

        return redirect()->route('whatsapp-chats.admin.totp', [
            'redirect' => $request->fullUrl(),
        ]);
    }
}
