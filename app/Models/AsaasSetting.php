<?php

namespace App\Models;

use App\Core\Database;

/** Chave-valor simples pra cachear dados do Asaas que nao vale a pena buscar ao vivo em toda
 *  carga de pagina (ex: limite disponivel pra antecipar) -- atualizado so quando alguem clica
 *  em "Atualizar do Asaas" na tela de Antecipacoes (ver AnticipationController::sync). */
class AsaasSetting
{
    public static function get(string $key): ?string
    {
        $stmt = Database::connection()->prepare('SELECT setting_value FROM asaas_settings WHERE setting_key = :k');
        $stmt->execute(['k' => $key]);
        $value = $stmt->fetchColumn();
        return $value !== false ? $value : null;
    }

    public static function getJson(string $key): ?array
    {
        $raw = self::get($key);
        if ($raw === null) {
            return null;
        }
        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : null;
    }

    public static function set(string $key, string $value): void
    {
        $stmt = Database::connection()->prepare(
            'INSERT INTO asaas_settings (setting_key, setting_value) VALUES (:k, :v)
             ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)'
        );
        $stmt->execute(['k' => $key, 'v' => $value]);
    }

    public static function setJson(string $key, array $value): void
    {
        self::set($key, json_encode($value));
    }

    public static function updatedAt(string $key): ?string
    {
        $stmt = Database::connection()->prepare('SELECT updated_at FROM asaas_settings WHERE setting_key = :k');
        $stmt->execute(['k' => $key]);
        $value = $stmt->fetchColumn();
        return $value !== false ? $value : null;
    }
}
