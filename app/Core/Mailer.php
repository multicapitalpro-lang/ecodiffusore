<?php

namespace App\Core;

use PHPMailer\PHPMailer\Exception as PHPMailerException;
use PHPMailer\PHPMailer\PHPMailer;

/**
 * SMTP autenticado (nao mais mail() puro do PHP) -- confirmado com mail-tester.com que mail()
 * na hospedagem compartilhada nao entregava em lugar nenhum (nem chegava no destino de teste,
 * nao era so filtro de spam do Gmail). O mesmo problema provavelmente era o From
 * (no-reply@ecodiffusorebrasil.com.br) nao ser uma caixa de e-mail de verdade -- SMTP autenticado
 * usando uma caixa real (ver App\Core\Config::get('smtp')) passa pelo mesmo caminho confiavel que
 * um cliente de e-mail normal usaria.
 */
class Mailer
{
    public static function send(string $to, string $subject, string $htmlBody): bool
    {
        require_once BASE_PATH . '/vendor/autoload.php';

        $smtp = Config::get('smtp', []);

        $mail = new PHPMailer(true);

        try {
            $mail->isSMTP();
            $mail->Host = $smtp['host'] ?? 'smtp.hostinger.com';
            $mail->SMTPAuth = true;
            $mail->Username = $smtp['user'] ?? '';
            $mail->Password = $smtp['pass'] ?? '';
            $mail->SMTPSecure = ($smtp['encryption'] ?? 'ssl') === 'tls' ? PHPMailer::ENCRYPTION_STARTTLS : PHPMailer::ENCRYPTION_SMTPS;
            $mail->Port = (int) ($smtp['port'] ?? 465);
            $mail->CharSet = 'UTF-8';

            $mail->setFrom($smtp['user'] ?? 'no-reply@ecodiffusorebrasil.com.br', $smtp['from_name'] ?? 'Ecodiffusore Brasil');
            if (!empty($smtp['reply_to'])) {
                $mail->addReplyTo($smtp['reply_to'], $smtp['from_name'] ?? 'Ecodiffusore Brasil');
            }
            $mail->addAddress($to);

            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body = $htmlBody;

            return $mail->send();
        } catch (PHPMailerException $e) {
            error_log('Mailer SMTP error: ' . $mail->ErrorInfo);
            return false;
        }
    }
}
