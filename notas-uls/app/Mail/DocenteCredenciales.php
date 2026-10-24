<?php

namespace App\Mail;

use App\Models\Docente;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class DocenteCredenciales extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Docente $docente,
        public string $email,
        public string $password,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Credenciales de acceso - Sistema de Notas ULS');
    }

    public function content(): Content
    {
        return new Content(markdown: 'emails.docente-credenciales');
    }
}
