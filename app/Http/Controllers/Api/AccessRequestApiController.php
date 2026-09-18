<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Mail\ProducerCredentialsMail;
use App\Models\AccessRequest;
use App\Models\Finca;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
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

        $validated = $request->validate([
            'action' => 'required|in:approve,waitlist,deny',
        ]);

        return match ($validated['action']) {
            'approve' => $this->approve($accessRequest),
            'waitlist' => $this->updateRequestStatus($accessRequest, 'waitlisted', 'Solicitud puesta en espera.'),
            'deny' => $this->deny($accessRequest),
        };
    }

    private function approve(AccessRequest $accessRequest): JsonResponse
    {
        if ($accessRequest->status === 'approved') {
            return response()->json([
                'success' => false,
                'message' => 'Esta solicitud ya fue aprobada.',
            ], 422);
        }

        $password = Str::random(8);

        $user = User::updateOrCreate(
            ['email' => $accessRequest->email],
            [
                'name' => trim("{$accessRequest->firstname} {$accessRequest->lastname}"),
                'password' => Hash::make($password),
            ],
        );

        // Una nueva aprobación invalida cualquier sesión anterior del productor.
        $user->tokens()->delete();

        // Evita duplicar la finca si una solicitud aprobada previamente fue denegada y se aprueba de nuevo.
        Finca::firstOrCreate(
            [
                'user_id' => $user->id,
                'nombre' => $accessRequest->landname,
            ],
            ['estado' => 'pendiente'],
        );

        $accessRequest->update(['status' => 'approved']);

        $emailSent = true;

        try {
            Mail::to($user->email)->send(new ProducerCredentialsMail(
                user: $user,
                password: $password,
                farmName: $accessRequest->landname,
            ));
        } catch (\Throwable $exception) {
            $emailSent = false;

            Log::error('No se pudieron enviar las credenciales del productor.', [
                'access_request_id' => $accessRequest->id,
                'user_id' => $user->id,
                'email' => $user->email,
                'exception' => $exception,
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => $emailSent
                ? 'Acceso permitido y credenciales enviadas al productor.'
                : 'Acceso permitido, pero no se pudieron enviar las credenciales por correo.',
            'data' => $accessRequest->fresh(),
            'email_sent' => $emailSent,
        ]);
    }

    private function deny(AccessRequest $accessRequest): JsonResponse
    {
        if ($accessRequest->status === 'denied') {
            return response()->json([
                'success' => false,
                'message' => 'Esta solicitud ya fue denegada.',
            ], 422);
        }

        $accessRequest->update(['status' => 'denied']);

        // Si el productor tenía una sesión activa, se revoca de inmediato.
        User::where('email', $accessRequest->email)->first()?->tokens()->delete();

        return response()->json([
            'success' => true,
            'message' => 'Solicitud denegada.',
            'data' => $accessRequest->fresh(),
        ]);
    }

    private function updateRequestStatus(AccessRequest $accessRequest, string $status, string $message): JsonResponse
    {
        if ($accessRequest->status === $status) {
            return response()->json([
                'success' => false,
                'message' => 'La solicitud ya tiene este estado.',
            ], 422);
        }

        $accessRequest->update(['status' => $status]);

        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $accessRequest->fresh(),
        ]);
    }

    private function forbiddenResponse(): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => 'No tienes permisos para acceder a esta funcionalidad.',
        ], 403);
    }
}
