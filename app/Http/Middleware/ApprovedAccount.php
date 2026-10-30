<?php

namespace App\Http\Middleware;

use App\Models\AccessRequest;
use Closure;
use Illuminate\Http\Request;

class ApprovedAccount
{
    public function handle(Request $r, Closure $next)
    {
        if (! $r->user()->isActive()) {
            $r->user()->tokens()->delete();

            return response()->json(['success' => false, 'code' => 'account_suspended', 'message' => 'Tu cuenta está suspendida. Contacta al equipo de Aggrio para revisar tu acceso.'], 403);
        }
        if ($r->is('api/admin/*') && ! $r->user()->isSuperAdmin()) {
            abort(403, 'Acceso administrativo no permitido.');
        }
        if (! $r->user()->isSuperAdmin() && ! AccessRequest::where('email', $r->user()->email)->where('status', 'approved')->exists()) {
            $r->user()->tokens()->delete();
            abort(403, 'Tu acceso no está aprobado.');
        }

        if ($r->user()->must_change_password && ! $r->is('api/auth/password', 'api/web/auth/password', 'api/auth/refresh', 'api/me', 'api/logout', 'api/logout-all')) {
            return response()->json(['success' => false, 'code' => 'password_change_required', 'message' => 'Debes cambiar tu contraseña temporal para continuar.'], 428);
        }

        return $next($r);
    }
}
