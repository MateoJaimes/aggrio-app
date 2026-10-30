<?php

namespace App\Http\Controllers\Api;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\AccessRequest;
use App\Models\User;
use App\Services\ProducerOnboarding;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AccountAdminController extends Controller
{
    public function index(Request $r)
    {
        abort_unless($r->user()->isSuperAdmin(), 403);
        $data = $r->validate(['search' => 'nullable|string|max:100', 'status' => 'nullable|in:active,suspended', 'page' => 'nullable|integer|min:1']);
        $q = User::query()->withCount('fincas')->orderBy('name');
        if (! empty($data['search'])) {
            $term = mb_strtolower($data['search']);
            $q->where(fn ($query) => $query->whereRaw('LOWER(name) LIKE ?', ['%'.$term.'%'])->orWhereRaw('LOWER(email) LIKE ?', ['%'.$term.'%']));
        }
        if (! empty($data['status'])) {
            $q->where('account_status', $data['status']);
        }
        $page = $q->paginate(25, ['id', 'name', 'email', 'phone', 'role', 'account_status', 'suspended_at', 'suspension_reason', 'must_change_password', 'temporary_password_expires_at', 'created_at']);
        $page->through(fn ($u) => $u->toArray() + ['approval_status' => AccessRequest::where('email', $u->email)->value('status')]);

        return response()->json(['success' => true, 'data' => $page]);
    }

    public function history(Request $r, User $user)
    {
        abort_unless($r->user()->isSuperAdmin(), 403);

        return response()->json(['success' => true, 'data' => DB::table('account_events')->join('users as actors', 'actors.id', '=', 'account_events.actor_id')->where('account_events.user_id', $user->id)->orderByDesc('account_events.id')->limit(50)->get(['account_events.id', 'action', 'previous_status', 'new_status', 'reason', 'account_events.created_at', 'actors.name as actor_name'])]);
    }

    public function change(Request $r, User $user)
    {
        abort_unless($r->user()->isSuperAdmin(), 403);
        $data = $r->validate(['action' => 'required|in:suspend,reactivate,recover', 'reason' => 'required|string|min:10|max:1000']);

        return DB::transaction(function () use ($r, $user, $data) {
            $registration = AccessRequest::where('email', $user->email)->lockForUpdate()->first();
            $target = User::lockForUpdate()->findOrFail($user->id);
            abort_unless($target->role === UserRole::Productor && $target->id !== $r->user()->id, 403, 'Esta pantalla administra cuentas de productores. Las cuentas Superadmin están protegidas.');
            $before = $target->account_status;
            if ($data['action'] === 'suspend') {
                abort_unless($before === 'active', 409, 'La cuenta ya está suspendida.');
                $target->forceFill(['account_status' => 'suspended', 'suspended_at' => now(), 'suspension_reason' => trim($data['reason'])])->save();
                $target->tokens()->delete();
                $target->twoFactorChallenges()->delete();
                DB::table('password_reset_tokens')->where('email', $target->email)->delete();
                if ($registration) {
                    $registration->forceFill(['invitation_secret' => null, 'invitation_version' => (string) Str::uuid(), 'invitation_status' => 'cancelled'])->save();
                }
                $message = 'Cuenta suspendida. Se cerraron sus sesiones; sus fincas y registros se conservan.';
            } elseif ($data['action'] === 'reactivate') {
                abort_unless($before === 'suspended', 409, 'La cuenta ya está activa.');
                $target->forceFill(['account_status' => 'active', 'suspended_at' => null, 'suspension_reason' => null])->save();
                $message = 'Cuenta reactivada. El productor puede iniciar una nueva sesión; las aprobaciones de sus fincas no cambian.';
            } else {
                abort_unless($before === 'active', 409, 'Reactiva primero la cuenta si corresponde. Recuperar acceso no levanta una suspensión.');
                abort_unless($registration && $registration->status === 'approved', 409, 'La cuenta necesita una solicitud aprobada antes de recuperar acceso.');
                abort_if($registration->invitation_status === 'queued' && $registration->updated_at->gt(now()->subMinute()), 429, 'Ya hay instrucciones de acceso en cola. Espera un minuto.');
                app(ProducerOnboarding::class)->prepare($registration, $target, true);
                $target->twoFactorChallenges()->delete();
                DB::table('password_reset_tokens')->where('email', $target->email)->delete();
                $message = 'Se generó una clave temporal y se encolaron las instrucciones de acceso. Deberá cambiarla al entrar; las sesiones anteriores se cerraron.';
            }
            DB::table('account_events')->insert(['user_id' => $target->id, 'actor_id' => $r->user()->id, 'action' => $data['action'], 'previous_status' => $before, 'new_status' => $target->account_status, 'reason' => trim($data['reason']), 'created_at' => now()]);

            return response()->json(['success' => true, 'message' => $message, 'data' => $target->toAuthPayload()]);
        }, 3);
    }
}
