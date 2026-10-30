<?php

namespace App\Services;

use App\Jobs\SendProducerInvitation;
use App\Models\AccessRequest;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ProducerOnboarding
{
    // Called with the request and user locked in the approval/resend transaction.
    public function prepare(AccessRequest $request, User $user, bool $issueTemporaryPassword = false): void
    {
        abort_unless($user->isActive(), 409, 'La cuenta está suspendida. Reactívala antes de enviar instrucciones de acceso.');
        $password = null;
        if ($issueTemporaryPassword || $user->must_change_password) {
            $password = Str::password(20, symbols: false);
            $user->forceFill(['password' => Hash::make($password), 'must_change_password' => true,
                'temporary_password_expires_at' => now()->addHours(24)])->save();
            $user->tokens()->delete();
        }
        $request->forceFill(['invitation_version' => (string) Str::uuid(), 'invitation_secret' => $password,
            'invitation_status' => 'queued', 'invitation_attempts' => 0, 'invitation_attempted_at' => null,
            'invitation_sent_at' => null])->save();
        Storage::disk('local')->delete('mail-previews/invitation-'.$request->id.'.html');
        $id = $request->id;
        $version = $request->invitation_version;
        DB::afterCommit(function () use ($id, $version) {
            try {
                SendProducerInvitation::dispatch($id, $version);
            } catch (\Throwable) {
                // The database outbox remains queued; invitations:recover will dispatch it.
            }
        });
    }
}
