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

    public static function path(string $subdir, string $storedName): string
    {
        return BASE_PATH . '/storage/uploads/' . $subdir . '/' . basename($storedName);
    }
}
