<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class CodigoVerificacion extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $user,
        public string $codigo,
        public int $minutos = 10,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Tu código de verificación - Sistema de Notas ULS');
    }

    public function content(): Content
    {
        return new Content(markdown: 'emails.codigo-verificacion');
    }
}