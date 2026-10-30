<?php

namespace App\Http\Controllers\Api;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\AccessRequest;
use App\Models\Finca;
use App\Models\User;
use App\Services\ProducerOnboarding;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class AccessRequestApiController extends Controller
{
    /**
     * Listar las solicitudes de acceso para el panel Angular.
     */
    public function index(Request $request): JsonResponse
    {
        if (! $request->user()->isSuperAdmin()) {
            return $this->forbiddenResponse();
        }

        return response()->json([
            'success' => true,
            'message' => 'Solicitudes recuperadas con éxito.',
            'data' => AccessRequest::query()->latest()->get(),
            'delivery' => ['configured' => ! in_array(config('mail.default'), ['log', 'array', 'failover']),
                'console_reset' => app()->environment('local') && config('invitations.console_enabled') && in_array(config('mail.default'), ['log', 'array']),
                'local_preview' => app()->environment('local') && in_array(config('mail.default'), ['log', 'array'])],
        ]);
    }

    /**
     * Aplicar una decisión a una solicitud desde el panel Angular.
     */
    public function updateStatus(Request $request, AccessRequest $accessRequest): JsonResponse
    {
        if (! $request->user()->isSuperAdmin()) {
            return $this->forbiddenResponse();
        }
        $data = $request->validate(['action' => 'required|in:approve,waitlist,deny']);

        return DB::transaction(function () use ($accessRequest, $data) {
            $locked = AccessRequest::lockForUpdate()->findOrFail($accessRequest->id);
            if (in_array($locked->status, ['approved', 'denied'])) {
                abort(409, 'Esta solicitud ya tiene una decisión definitiva. No se modificó ninguna cuenta.');
            }
            if ($data['action'] === 'approve') {
                return $this->approve($locked);
            }
            $locked->update(['status' => $data['action'] === 'deny' ? 'denied' : 'waitlisted']);

            return response()->json(['success' => true, 'data' => $locked]);
        }, 3);
    }

    private function approve(AccessRequest $accessRequest): JsonResponse
    {
        $user = User::where('email', $accessRequest->email)->lockForUpdate()->first();
        if (! $user) {
            $user = User::create(['name' => trim($accessRequest->firstname.' '.$accessRequest->lastname),
                'email' => $accessRequest->email, 'password' => Hash::make(Str::random(64)), 'role' => 'productor']);
        }
        abort_unless($user->role === UserRole::Productor, 409, 'La solicitud no corresponde a una cuenta productora.');
        // The access request already names the initial farm. One approval
        // authorizes that relationship; the producer completes its field data.
        Finca::firstOrCreate(['user_id' => $user->id, 'nombre' => $accessRequest->landname], ['estado' => 'aprobado']);
        $accessRequest->update(['status' => 'approved']);
        app(ProducerOnboarding::class)->prepare($accessRequest, $user, true);

        return response()->json(['success' => true, 'data' => $accessRequest->fresh(), 'message' => 'Acceso y primera finca aprobados. Se encoló el correo con la contraseña temporal. El productor cambiará su contraseña, completará su finca y creará sus lotes.', 'email_sent' => false, 'email_status' => 'queued']);
    }

    public function resend(Request $request, AccessRequest $accessRequest): JsonResponse
    {
        abort_unless($request->user()->isSuperAdmin(), 403);
        $request->validate(['reset_password' => 'sometimes|boolean']);
        $resetPassword = $request->boolean('reset_password');
        abort_if($resetPassword && (! app()->environment('local') || ! config('invitations.console_enabled') || ! in_array(config('mail.default'), ['log', 'array'])), 403, 'La generación por consola solo está disponible en pruebas locales sin SMTP.');

        return DB::transaction(function () use ($accessRequest, $resetPassword) {
            $r = AccessRequest::lockForUpdate()->findOrFail($accessRequest->id);
            abort_unless($r->status === 'approved', 409, 'Primero debes aprobar la solicitud.');
            abort_if($r->updated_at->gt(now()->subMinute()) && $r->invitation_status === 'queued', 429, 'La invitación está en cola. Espera un minuto antes de reenviar.');
            $user = User::where('email', $r->email)->lockForUpdate()->firstOrFail();
            abort_unless($user->role === UserRole::Productor, 403, 'Solo se puede generar acceso para productores.');
            app(ProducerOnboarding::class)->prepare($r, $user, $resetPassword);

            return response()->json(['success' => true, 'data' => $r->fresh(), 'message' => $resetPassword
                ? 'Nueva contraseña temporal generada. Se cerraron las sesiones anteriores. Aparecerá en la consola del trabajador al procesar la invitación.'
                : 'Invitación encolada. Si aún usaba una contraseña temporal, la anterior queda invalidada.']);
        });
    }

    public function preview(Request $request, AccessRequest $accessRequest): JsonResponse
    {
        abort_unless(app()->environment('local') && $request->user()->isSuperAdmin(), 404);
        $path = 'mail-previews/invitation-'.$accessRequest->id.'.html';
        abort_unless($accessRequest->invitation_status === 'preview' && Storage::disk('local')->exists($path), 404, 'El correo de prueba todavía no está disponible.');

        return response()->json(['success' => true, 'data' => ['html' => Storage::disk('local')->get($path)]]);
    }

    private function forbiddenResponse(): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => 'No tienes permisos para acceder a esta funcionalidad.',
        ], 403);
    }
}
