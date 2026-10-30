<?php

use App\Jobs\SendProducerInvitation;
use App\Models\AccessRequest;
use App\Models\ArchivoMultimedia;
use App\Models\AuthenticatorDevice;
use App\Models\TrustedDevice;
use App\Models\TwoFactorChallenge;
use App\Models\UploadTicket;
use App\Models\User;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Facades\Storage;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Limpieza de credenciales caducadas (RF003 / RF004).
// Requiere que el scheduler esté corriendo: php artisan schedule:work
Schedule::command('sanctum:prune-expired --hours=24')->daily();

Schedule::call(fn () => TwoFactorChallenge::pruneStale())
    ->hourly()
    ->name('prune-two-factor-challenges');

Schedule::call(function () {
    TrustedDevice::where('expires_at', '<=', now())->delete();
    AuthenticatorDevice::whereNull('confirmed_at')->where('expires_at', '<=', now())->delete();
})->hourly()->name('prune-security-devices')->withoutOverlapping();

Artisan::command('evidence:cleanup', function () {
    UploadTicket::where('expires_at', '<', now()->subHour())
        ->chunkById(100, function ($tickets) {
            foreach ($tickets as $ticket) {
                $disk = Storage::disk($ticket->storage_disk);
                if ($disk->exists($ticket->file_key) && ! $disk->delete($ticket->file_key)) {
                    continue;
                }
                $orphan = 'documentos/usuario_'.$ticket->user_id.'/evidencia/'.$ticket->id.'.'.pathinfo($ticket->file_key, PATHINFO_EXTENSION);
                if (! ArchivoMultimedia::withTrashed()->where('ruta_archivo', $orphan)->exists() && $disk->exists($orphan)) {
                    if (! $disk->delete($orphan)) {
                        continue;
                    }
                }
                $ticket->delete();
            }
        });
    $this->info('Cargas abandonadas verificadas.');
})->purpose('Retirar únicamente cargas abandonadas autorizadas y caducadas, sin tocar evidencia registrada');
Schedule::command('evidence:cleanup')->daily()->withoutOverlapping();

Artisan::command('invitations:recover', function () {
    AccessRequest::where('status', 'approved')->where('invitation_status', 'queued')
        ->where('updated_at', '<', now()->subMinutes(5))->each(function ($r) {
            SendProducerInvitation::dispatch($r->id, $r->invitation_version);
        });
    $this->info('Invitaciones pendientes verificadas.');
});
Schedule::command('invitations:recover')->everyFiveMinutes()->withoutOverlapping();

Artisan::command('mail:check-smtp {--connect : Comprobar conexión y autenticación sin enviar mensajes}', function () {
    if (config('mail.default') !== 'smtp') {
        $this->error('No hay envío SMTP activo. Configura MAIL_MAILER=smtp en .env.');

        return 1;
    }
    if (! config('mail.mailers.smtp.host') || ! filter_var(config('mail.from.address'), FILTER_VALIDATE_EMAIL) || config('mail.from.address') === 'hello@example.com') {
        $this->error('Faltan servidor SMTP o remitente válidos. Configura .env con los datos de tu proveedor.');

        return 1;
    }
    if ($this->option('connect')) {
        try {
            $transport = Mail::mailer('smtp')->getSymfonyTransport();
            $transport->start();
            $transport->stop();
        } catch (Throwable) {
            $this->error('No se pudo conectar o autenticar con SMTP. Revisa servidor, puerto, esquema, credenciales y conectividad. No se enviaron mensajes.');

            return 1;
        }
    }
    $this->info($this->option('connect') ? 'Conexión SMTP y autenticación verificadas. No se enviaron mensajes.' : 'Configuración SMTP presente. Usa --connect para comprobar la conexión.');

    return 0;
});

Artisan::command('invitations:cleanup', function () {
    AccessRequest::whereNotNull('invitation_version')->each(function ($r) {
        $user = User::where('email', $r->email)->first();
        if (! $user || ! $user->must_change_password || $user->temporary_password_expires_at?->isPast()) {
            DB::transaction(function () use ($r, $user) {
                $locked = AccessRequest::lockForUpdate()->find($r->id);
                if ($locked->invitation_version !== $r->invitation_version) {
                    return;
                }
                $locked->forceFill(['invitation_secret' => null, 'invitation_status' => $user?->must_change_password ? 'expired' : ($locked->invitation_status === 'preview' ? 'completed' : $locked->invitation_status)])->save();
                Storage::disk('local')->delete('mail-previews/invitation-'.$r->id.'.html');
            });
        }
    });
    $this->info('Credenciales de invitación caducadas retiradas.');
});
Schedule::command('invitations:cleanup')->hourly()->withoutOverlapping();
