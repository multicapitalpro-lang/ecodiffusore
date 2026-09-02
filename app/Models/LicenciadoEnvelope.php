<?php

namespace App\Models;

use App\Core\Database;

class LicenciadoEnvelope
{
    public static function create(array $data): int
    {
        $stmt = Database::connection()->prepare(
            'INSERT INTO licenciado_envelopes (user_id, clicksign_envelope_id, clicksign_document_id, clicksign_signer_id, signing_url, status)
             VALUES (:user_id, :envelope_id, :document_id, :signer_id, :signing_url, :status)'
        );
        $stmt->execute([
            'user_id' => $data['user_id'],
            'envelope_id' => $data['clicksign_envelope_id'],
            'document_id' => $data['clicksign_document_id'] ?? null,
            'signer_id' => $data['clicksign_signer_id'] ?? null,
            'signing_url' => $data['signing_url'] ?? null,
            'status' => $data['status'] ?? 'created',
        ]);

        return (int) Database::connection()->lastInsertId();
    }

    public static function find(int $id): ?array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM licenciado_envelopes WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function findLatestByUser(int $userId): ?array
    {
        $stmt = Database::connection()->prepare(
            'SELECT * FROM licenciado_envelopes WHERE user_id = :user_id ORDER BY created_at DESC LIMIT 1'
        );
        $stmt->execute(['user_id' => $userId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function findByClickSignEnvelopeId(string $envelopeId): ?array
    {
        $stmt = Database::connection()->prepare(
            'SELECT * FROM licenciado_envelopes WHERE clicksign_envelope_id = :envelope_id LIMIT 1'
        );
        $stmt->execute(['envelope_id' => $envelopeId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function updateStatus(int $id, string $status): void
    {
        $stmt = Database::connection()->prepare('UPDATE licenciado_envelopes SET status = :status WHERE id = :id');
        $stmt->execute(['status' => $status, 'id' => $id]);
    }

    public static function attachSignedDocument(int $id, string $storedName): void
    {
        $stmt = Database::connection()->prepare('UPDATE licenciado_envelopes SET signed_document_path = :path WHERE id = :id');
        $stmt->execute(['path' => $storedName, 'id' => $id]);
    }
}
