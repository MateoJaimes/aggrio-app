<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthApiController extends Controller
{
    /**
     * Login - Devuelve token Bearer
     */
    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'email'       => 'required|email',
            'password'    => 'required|string',
            'device_name' => 'nullable|string',   // Nombre de la app que consume
        ]);

        $user = User::where('email', $request->email)->first();

        //version nueva
        // 1. Verificar credenciales básicas
        if (! $user || ! Hash::check($request->password, $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Credenciales incorrectas.'
            ], 401);
        }

        // 2. NUEVA VERIFICACIÓN: Consultar el estado en la tabla de solicitudes
        // Importante: Asegúrate de importar use App\Models\AccessRequest; arriba
        $solicitud = \App\Models\AccessRequest::where('email', $request->email)->first();

        if (!$solicitud || $solicitud->status !== 'approved') {
            return response()->json([
                'success' => false,
                'message' => 'Tu acceso no ha sido aprobado o ha sido revocado.'
            ], 403); // Error 403: Prohibido
        }

        $deviceName = $request->device_name ?? 'api-client';

        // $token = $user->createToken($deviceName)->plainTextToken;

        // Eliminamos todos los tokens anteriores para forzar una única sesión activa
        $user->tokens()->delete();

        // Generamos el nuevo y único token válido
        $token = $user->createToken($deviceName)->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'Login exitoso',
            'data'    => [
                'user'  => [
                    'id'    => $user->id,
                    'name'  => $user->name,
                    'email' => $user->email,
                ],
                'token' => $token,
                'type'  => 'Bearer',
            ],
        ], 200);
    }

    /**
     * Me - Devuelve datos del usuario autenticado
     */
    public function me(Request $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data'    => [
                'user' => $request->user(),
            ],
        ], 200);
    }

    /**
     * Logout - Revoca el token actual
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'success' => true,
            'message' => 'Sesión cerrada correctamente',
        ], 200);
    }

    /**
     * Logout All - Revoca TODOS los tokens del usuario
     */
    public function logoutAll(Request $request): JsonResponse
    {
        $request->user()->tokens()->delete();

        return response()->json([
            'success' => true,
            'message' => 'Todas las sesiones han sido cerradas',
        ], 200);
    }
}
