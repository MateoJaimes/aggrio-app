<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ProducerInvitationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public string $name, public string $url, public string $email = '',
        public ?string $temporaryPassword = null, public ?string $expiresAt = null) {}

    public function build(): static
    {
        return $this->subject('Tu acceso a AGGRIO fue aprobado')->view('emails.producer-invitation');
    }
}
