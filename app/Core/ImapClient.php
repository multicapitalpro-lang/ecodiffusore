<?php

namespace App\Core;

/**
 * Cliente IMAP puro-PHP (extensao imap ja habilitada no servidor, confirmado via SSH) pra ler a
 * caixa de e-mail real da empresa (atendimento@ecodiffusorebrasil.com.br, mesma conta ja usada
 * pelo App\Core\Mailer via SMTP) direto no painel -- sem precisar abrir o webmail da Hostinger.
 * So' leitura (list/show/mark as read); resposta e' enviada via SMTP normal (Mailer::sendReply()),
 * nao pelo IMAP (IMAP nao envia e-mail, so' le/organiza a caixa).
 */
class ImapClient
{
    private string $mailbox;
    private string $user;
    private string $pass;

    public function __construct()
    {
        $config = Config::get('imap', []);
        $smtp = Config::get('smtp', []);

        $host = $config['host'] ?? 'imap.hostinger.com';
        $port = $config['port'] ?? 993;
        $this->mailbox = "{{$host}:{$port}/imap/ssl}INBOX";
        $this->user = $config['user'] ?? ($smtp['user'] ?? '');
        $this->pass = $config['pass'] ?? ($smtp['pass'] ?? '');
    }

    /** @return resource|\IMAP\Connection */
    private function connect()
    {
        $conn = @imap_open($this->mailbox, $this->user, $this->pass);
        if (!$conn) {
            throw new \RuntimeException('Falha ao conectar no IMAP: ' . imap_last_error());
        }
        return $conn;
    }

    /**
     * Lista mensagens (mais recente primeiro), paginado. Retorna so' cabecalho -- sem baixar o
     * corpo inteiro, pra listagem ficar rapida mesmo com caixa grande.
     */
    public function listMessages(int $page = 1, int $perPage = 25): array
    {
        $conn = $this->connect();
        try {
            $total = imap_num_msg($conn);
            if ($total === 0) {
                return ['messages' => [], 'total' => 0];
            }

            // imap_headers numera 1..N do mais antigo pro mais novo -- percorre de tras pra
            // frente pra paginar do mais recente.
            $start = $total - (($page - 1) * $perPage);
            $end = max(1, $start - $perPage + 1);

            $messages = [];
            for ($msgNum = $start; $msgNum >= $end && $msgNum >= 1; $msgNum--) {
                $overview = imap_fetch_overview($conn, (string) $msgNum, 0);
                if (empty($overview[0])) {
                    continue;
                }
                $o = $overview[0];
                $messages[] = [
                    'msg_num' => $msgNum,
                    'uid' => imap_uid($conn, $msgNum),
                    'subject' => self::decodeHeader($o->subject ?? '(sem assunto)'),
                    'from' => self::decodeHeader($o->from ?? '—'),
                    'date' => $o->date ?? '',
                    'unread' => empty($o->seen),
                    'answered' => !empty($o->answered),
                ];
            }

            return ['messages' => $messages, 'total' => $total];
        } finally {
            imap_close($conn);
        }
    }

    /** Corpo + anexos de UMA mensagem, por UID (estavel entre conexoes, diferente do msg_num). */
    public function getMessage(int $uid): array
    {
        $conn = $this->connect();
        try {
            $msgNum = imap_msgno($conn, $uid);
            if (!$msgNum) {
                throw new \RuntimeException('Mensagem não encontrada.');
            }

            $header = imap_headerinfo($conn, $msgNum);
            $structure = imap_fetchstructure($conn, $msgNum);

            [$html, $plain, $attachments] = self::parseParts($conn, $msgNum, $structure, '');

            // Marca como lida ao abrir (comportamento padrao de qualquer cliente de e-mail).
            imap_setflag_full($conn, (string) $msgNum, '\\Seen');

            return [
                'uid' => $uid,
                'subject' => self::decodeHeader($header->subject ?? '(sem assunto)'),
                'from' => self::decodeHeader($header->fromaddress ?? '—'),
                'from_email' => !empty($header->from[0]) ? ($header->from[0]->mailbox . '@' . $header->from[0]->host) : '',
                'to' => self::decodeHeader($header->toaddress ?? '—'),
                'date' => $header->date ?? '',
                'message_id' => $header->message_id ?? '',
                'body_html' => $html,
                'body_plain' => $plain,
                'attachments' => $attachments,
            ];
        } finally {
            imap_close($conn);
        }
    }

    /** Baixa um anexo especifico (por UID da mensagem + indice da parte MIME). */
    public function downloadAttachment(int $uid, string $partNum): array
    {
        $conn = $this->connect();
        try {
            $msgNum = imap_msgno($conn, $uid);
            $structure = imap_fetchstructure($conn, $msgNum);
            [, , $attachments] = self::parseParts($conn, $msgNum, $structure, '');

            foreach ($attachments as $att) {
                if ($att['part_num'] === $partNum) {
                    $raw = imap_fetchbody($conn, $msgNum, $partNum);
                    $decoded = self::decodeBody($raw, $att['encoding']);
                    return ['filename' => $att['filename'], 'content' => $decoded, 'mime' => $att['mime']];
                }
            }
            throw new \RuntimeException('Anexo não encontrado.');
        } finally {
            imap_close($conn);
        }
    }

    /** Percorre a arvore MIME recursivamente separando corpo HTML/texto puro dos anexos. */
    private static function parseParts($conn, int $msgNum, $structure, string $prefix): array
    {
        $html = '';
        $plain = '';
        $attachments = [];

        if (!isset($structure->parts) || !$structure->parts) {
            // Mensagem sem multipart -- o corpo inteiro e' a parte 1.
            $body = imap_fetchbody($conn, $msgNum, '1');
            $decoded = self::decodeBody($body, $structure->encoding ?? 0);
            if (($structure->subtype ?? '') === 'HTML') {
                $html = $decoded;
            } else {
                $plain = $decoded;
            }
            return [$html, $plain, $attachments];
        }

        foreach ($structure->parts as $i => $part) {
            $partNum = $prefix . (string) ($i + 1);
            $disposition = strtolower($part->ifdparameters ? ($part->dparameters[0]->value ?? '') : '');
            $isAttachment = in_array(strtolower($part->disposition ?? ''), ['attachment', 'inline'], true)
                && !empty($part->ifdparameters);

            if (isset($part->parts) && $part->parts && strtolower($part->subtype) !== 'alternative' && !$isAttachment) {
                [$h, $p, $a] = self::parseParts($conn, $msgNum, $part, $partNum . '.');
                $html = $html ?: $h;
                $plain = $plain ?: $p;
                $attachments = array_merge($attachments, $a);
                continue;
            }
            if (isset($part->parts) && $part->parts && strtolower($part->subtype) === 'alternative') {
                [$h, $p, $a] = self::parseParts($conn, $msgNum, $part, $partNum . '.');
                $html = $html ?: $h;
                $plain = $plain ?: $p;
                $attachments = array_merge($attachments, $a);
                continue;
            }

            $filename = self::partFilename($part);

            if ($filename) {
                $attachments[] = [
                    'part_num' => $partNum,
                    'filename' => self::decodeHeader($filename),
                    'mime' => strtolower(($part->type === 0 ? 'text' : 'application') . '/' . $part->subtype),
                    'encoding' => $part->encoding ?? 0,
                    'size' => $part->bytes ?? 0,
                ];
                continue;
            }

            $body = imap_fetchbody($conn, $msgNum, $partNum);
            $decoded = self::decodeBody($body, $part->encoding ?? 0);
            if (strtolower($part->subtype ?? '') === 'html') {
                $html = $decoded;
            } elseif (strtolower($part->subtype ?? '') === 'plain') {
                $plain = $decoded;
            }
        }

        return [$html, $plain, $attachments];
    }

    private static function partFilename($part): ?string
    {
        if (!empty($part->ifdparameters)) {
            foreach ($part->dparameters as $p) {
                if (strtolower($p->attribute) === 'filename') {
                    return $p->value;
                }
            }
        }
        if (!empty($part->ifparameters)) {
            foreach ($part->parameters as $p) {
                if (strtolower($p->attribute) === 'name') {
                    return $p->value;
                }
            }
        }
        return null;
    }

    private static function decodeBody(string $body, int $encoding): string
    {
        return match ($encoding) {
            3 => base64_decode($body), // ENCBASE64
            4 => quoted_printable_decode($body), // ENCQUOTEDPRINTABLE
            default => $body,
        };
    }

    /** Cabecalhos podem vir em MIME encoded-word (=?UTF-8?B?...?=) -- decodifica pra exibir certo. */
    private static function decodeHeader(string $value): string
    {
        $decoded = @imap_mime_header_decode($value);
        if (!$decoded) {
            return $value;
        }
        $out = '';
        foreach ($decoded as $part) {
            $charset = $part->charset === 'default' ? 'UTF-8' : $part->charset;
            $out .= @mb_convert_encoding($part->text, 'UTF-8', $charset) ?: $part->text;
        }
        return $out;
    }
}
