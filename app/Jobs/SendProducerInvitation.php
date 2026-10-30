<?php

namespace App\Jobs;

use App\Mail\ProducerInvitationMail;
use App\Models\AccessRequest;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

class SendProducerInvitation implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 45;

    public function __construct(public int $requestId, public string $version)
    {
        $this->onQueue('invitations');
    }

    public function backoff(): array
    {
        return [30, 120, 300];
    }

    public function handle(): void
    {
        $consoleInvitation = DB::transaction(function () {
            $r = AccessRequest::lockForUpdate()->find($this->requestId);
            if (! $r || $r->status !== 'approved' || $r->invitation_version !== $this->version || in_array($r->invitation_status, ['sent', 'preview', 'completed'])) {
                return;
            }
            $user = User::where('email', $r->email)->lockForUpdate()->first();
            if (! $user || ! $user->isActive() || ($user->must_change_password && $user->temporary_password_expires_at?->isPast())) {
                $r->forceFill(['invitation_status' => 'expired', 'invitation_secret' => null])->save();

                return;
            }
            $mail = new ProducerInvitationMail($user->name, rtrim(config('app.frontend_url'), '/').'/auth/login',
                $user->email, $user->must_change_password ? $r->invitation_secret : null,
                $user->temporary_password_expires_at?->toIso8601String());
            $localPreview = app()->environment('local') && in_array(config('mail.default'), ['log', 'array']);
            // A log transport must never be presented as delivery or leak a credential to logs.
            if ($localPreview) {
                if (! Storage::disk('local')->put('mail-previews/invitation-'.$r->id.'.html', $mail->render())) {
                    throw new \RuntimeException('No se pudo guardar el correo de prueba.');
                }
            } else {
                if (in_array(config('mail.default'), ['log', 'array', 'failover'])) {
                    throw new \RuntimeException('Configura un transporte de correo real para las invitaciones.');
                }
                Mail::to($user->email)->send($mail);
            }
            $r->forceFill(['invitation_status' => $localPreview ? 'preview' : 'sent', 'invitation_sent_at' => now(),
                'invitation_attempted_at' => now(), 'invitation_attempts' => $r->invitation_attempts + 1,
                'invitation_secret' => null])->save();

            return $localPreview && config('invitations.console_enabled') ? $mail : null;
        });

        if ($consoleInvitation && app()->runningInConsole()) {
            $clean = fn (?string $value) => preg_replace('/[\x00-\x1F\x7F]/u', '', $value ?? '');
            echo PHP_EOL.'[AGGRIO · ACCESO DE PRUEBA LOCAL]'.PHP_EOL;
            echo 'Correo: '.$clean($consoleInvitation->email).PHP_EOL;
            echo $consoleInvitation->temporaryPassword
                ? 'Contraseña temporal: '.$clean($consoleInvitation->temporaryPassword).PHP_EOL.'Vence: '.$clean($consoleInvitation->expiresAt).PHP_EOL
                : 'Cuenta existente: utiliza su contraseña personal. No se ha cambiado.'.PHP_EOL;
            echo 'Panel: '.$clean($consoleInvitation->url).PHP_EOL;
            echo 'La contraseña temporal debe cambiarse en el primer acceso. No se envió correo real.'.PHP_EOL.PHP_EOL;
        }
    }

    public function failed(?\Throwable $exception): void
    {
        AccessRequest::whereKey($this->requestId)->where('invitation_version', $this->version)
            ->whereNotIn('invitation_status', ['sent', 'preview'])->update(['invitation_status' => 'failed', 'invitation_attempted_at' => now()]);
    }
}
