<?php

namespace App\Models;

use App\Core\Database;

/**
 * Configuracao unica (1 linha so) de pra onde vao os leads do orcamento por placa quando o
 * GeoMatch nao acha nenhum Vendedor num raio de 100km da cidade do cliente (Fase 35) --
 * antes ficavam sem dono e apareciam pra TODO licenciado/gestor do pais poder pegar (vazamento
 * entre redes, o mesmo problema que as Fases 30/31 corrigiram pra Clientes/Financeiro).
 */
class LeadRoutingSettings
{
    public static function current(): array
    {
        $row = Database::connection()->query('SELECT * FROM lead_routing_settings WHERE id = 1')->fetch();
        return $row ?: ['id' => 1, 'central_licenciado_id' => null];
    }

    public static function centralLicenciadoId(): ?int
    {
        $id = self::current()['central_licenciado_id'] ?? null;
        return $id ? (int) $id : null;
    }

    public static function update(?int $licenciadoId): void
    {
        $stmt = Database::connection()->prepare('UPDATE lead_routing_settings SET central_licenciado_id = :id WHERE id = 1');
        $stmt->execute(['id' => $licenciadoId]);
    }
}
