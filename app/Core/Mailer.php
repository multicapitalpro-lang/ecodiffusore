<?php

namespace App\Core;

class Mailer
{
    public static function send(string $to, string $subject, string $htmlBody): bool
    {
        $from = 'no-reply@ecodiffusorebrasil.com.br';

        $headers = [
            'MIME-Version: 1.0',
            'Content-Type: text/html; charset=UTF-8',
            'From: Ecodiffusore Brasil <' . $from . '>',
            'Reply-To: contato@ecodiffusorebrasil.com.br',
        ];

        $encodedSubject = '=?UTF-8?B?' . base64_encode($subject) . '?=';

        return mail($to, $encodedSubject, $htmlBody, implode("\r\n", $headers));
    }
}
