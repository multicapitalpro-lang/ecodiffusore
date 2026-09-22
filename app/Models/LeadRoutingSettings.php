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

    /** Quem enxerga item SEM DONO (Lead/Cliente sem vendedor vinculado) -- so' o Licenciado
     *  central configurado aqui, e a downline dele (Gestor/Vendedor da equipe dele tambem
     *  precisam poder assumir um orfao) -- NUNCA todo mundo. Sem isso, qualquer Licenciado do
     *  Brasil inteiro via cliente/lead orfao de qualquer cidade (bug real reportado ao vivo,
     *  Fase 88 -- um Licenciado de Goiania via clientes de Cascavel/Palotina-PR sem vendedor
     *  vinculado). Admin nao passa por aqui (o scopeFilters() dele ja e' sem filtro nenhum).
     *  Sem central configurado, ninguem (fora Admin) ve orfao -- mais seguro que vazar geral. */
    public static function canSeeUnassigned(array $user): bool
    {
        $centralId = self::centralLicenciadoId();
        if (!$centralId) {
            return false;
        }
        if ((int) $user['id'] === $centralId) {
            return true;
        }

        return in_array((int) $user['id'], User::downlineIds($centralId), true);
    }
}
