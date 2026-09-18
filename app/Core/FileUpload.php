<?php

namespace App\Core;

class FileUpload
{
    private const ALLOWED_MIME = [
        'application/pdf' => 'pdf',
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];

    private const MAX_SIZE = 5 * 1024 * 1024; // 5MB

    /**
     * Move um arquivo enviado (de $_FILES) para storage/uploads/{$subdir}, validando tipo e
     * tamanho. Retorna dados prontos pra gravar no banco ou null se inválido/vazio.
     */
    public static function store(array $file, string $subdir): ?array
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return null;
        }
        if ($file['error'] !== UPLOAD_ERR_OK) {
            throw new \RuntimeException('Falha no upload do arquivo.');
        }
        if ($file['size'] > self::MAX_SIZE) {
            throw new \RuntimeException('Arquivo maior que 5MB: ' . $file['name']);
        }

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);

        if (!isset(self::ALLOWED_MIME[$mime])) {
            throw new \RuntimeException('Tipo de arquivo não permitido (use PDF, JPG, PNG ou WEBP): ' . $file['name']);
        }

        $dir = BASE_PATH . '/storage/uploads/' . $subdir;
        if (!is_dir($dir)) {
            mkdir($dir, 0750, true);
        }

        $storedName = bin2hex(random_bytes(16)) . '.' . self::ALLOWED_MIME[$mime];

        if (!move_uploaded_file($file['tmp_name'], $dir . '/' . $storedName)) {
            throw new \RuntimeException('Não foi possível salvar o arquivo: ' . $file['name']);
        }

        return [
            'original_name' => mb_substr(basename($file['name']), 0, 255),
            'stored_name' => $storedName,
            'mime_type' => $mime,
            'size_bytes' => $file['size'],
        ];
    }

    public static function storeFinancialAttachment(array $file): ?array
    {
        return self::store($file, 'financial');
    }

    public static function storeLicenciadoDocument(array $file): ?array
    {
        return self::store($file, 'licenciados');
    }

    public static function storeVehicleDocument(array $file): ?array
    {
        return self::store($file, 'vehicle_docs');
    }

    public static function storeCnhDocument(array $file): ?array
    {
        return self::store($file, 'cnh_docs');
    }

    public static function storeVehiclePhoto(array $file): ?array
    {
        return self::store($file, 'vehicle_photos');
    }

    public static function storeTelemetryFile(array $file): ?array
    {
        return self::store($file, 'telemetry');
    }

    public static function storeLeadExtensionAttachment(array $file): ?array
    {
        return self::store($file, 'lead_extensions');
    }

    public static function storeLeadNoteAttachment(array $file): ?array
    {
        return self::store($file, 'lead_notes');
    }

    public static function storeClientNoteAttachment(array $file): ?array
    {
        return self::store($file, 'client_notes');
    }

    public static function storeWarrantyAttachment(array $file): ?array
    {
        return self::store($file, 'warranties');
    }

    /** Fotos da cotacao publica de maquina agricola (Fase 45) -- enviadas por visitante anonimo
     *  em /comprar, sem sessao de painel. store() nao depende de auth, entao funciona igual. */
    public static function storeMachineQuotePhoto(array $file): ?array
    {
        return self::store($file, 'machine_quotes');
    }

    public static function path(string $subdir, string $storedName): string
    {
        return BASE_PATH . '/storage/uploads/' . $subdir . '/' . basename($storedName);
    }

    /** Guarda midia baixada do WhatsApp (base64 decodificado da Evolution API) -- diferente de
     *  store(), nao vem de $_FILES/move_uploaded_file e nao restringe mimetype (fotos/videos/audios/
     *  documentos de conversa tem um leque muito maior que os formularios do sistema; o risco de
     *  servir de volta e' baixo, sempre com Content-Type/Content-Disposition corretos, nunca
     *  executado). */
    public static function storeWhatsAppMedia(string $binaryContent, string $mimeType): array
    {
        $dir = BASE_PATH . '/storage/uploads/whatsapp';
        if (!is_dir($dir)) {
            mkdir($dir, 0750, true);
        }

        $ext = self::extensionForMime($mimeType);
        $storedName = bin2hex(random_bytes(16)) . ($ext ? '.' . $ext : '');
        file_put_contents($dir . '/' . $storedName, $binaryContent);

        return ['stored_name' => $storedName, 'size_bytes' => strlen($binaryContent)];
    }

    private static function extensionForMime(string $mime): ?string
    {
        $map = [
            'image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif',
            'video/mp4' => 'mp4', 'video/3gpp' => '3gp', 'video/quicktime' => 'mov',
            'audio/ogg' => 'ogg', 'audio/ogg; codecs=opus' => 'ogg', 'audio/mpeg' => 'mp3', 'audio/mp4' => 'm4a', 'audio/aac' => 'aac',
            'application/pdf' => 'pdf',
            'application/msword' => 'doc',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
            'application/vnd.ms-excel' => 'xls',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'xlsx',
            'text/plain' => 'txt',
        ];
        return $map[$mime] ?? null;
    }
}
