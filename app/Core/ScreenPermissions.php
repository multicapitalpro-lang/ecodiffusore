<?php

namespace App\Core;

/**
 * Permissao granular por tela, so pra Gestor/Vendedor (pedido do usuario: "quando o Licenciado
 * criar um Gestor/Vendedor, ele define TODAS as funcoes que um ou outro podera ver"). Todo o
 * resto do sistema continua 100% baseado em papel (Roles::STAFF/MANAGEMENT etc.) -- isso aqui e'
 * uma RESTRICAO ADICIONAL, opcional, só sobre as telas que Gestor/Vendedor já veem por papel.
 *
 * Sem nenhuma linha salva pra um usuario = sem personalizacao configurada ainda = libera tudo
 * (comportamento antigo, pra nao quebrar contas ja existentes). So passa a restringir de verdade
 * depois que alguem salva o formulario de usuario com a lista de telas (mesmo que marque todas --
 * ai fica "configurado com tudo", que da no mesmo resultado, mas fica registrado).
 */
class ScreenPermissions
{
    public const SCREENS = [
        'leads' => 'Leads',
        'clientes' => 'Clientes',
        'simulador' => 'Simulador de Economia',
        'materiais' => 'Materiais de Venda',
        'pedidos' => 'Pedidos',
        'orcamentos' => 'Orçamentos',
        'entregas' => 'Acompanhar Entregas',
        'desempenho' => 'Desempenho',
        'comissoes' => 'Comissões',
    ];

    /** Marcador gravado junto das telas escolhidas -- distingue "nunca configurado" (null, libera
     *  tudo) de "configurado, mas sem nenhuma tela marcada" (bloqueia tudo de verdade). */
    private const CONFIGURED_MARKER = '__configured__';

    private const PATH_MAP = [
        '/painel/leads' => 'leads',
        '/painel/clientes' => 'clientes',
        '/painel/simulador' => 'simulador',
        '/painel/materiais' => 'materiais',
        '/painel/pedidos' => 'pedidos',
        '/painel/orcamentos' => 'orcamentos',
        '/painel/entregas' => 'entregas',
        '/painel/desempenho' => 'desempenho',
        '/painel/meu-ranking' => 'desempenho',
        '/painel/financeiro/comissoes' => 'comissoes',
    ];

    /** So esses papeis sao afetados -- Licenciado/Admin/Gestor(de outros)/etc nunca tem tela
     *  escondida por esse sistema, so quem foi cadastrado como Gestor ou Vendedor. */
    private const APPLIES_TO_ROLES = ['gestor', 'vendedor'];

    public static function screenForPath(string $path): ?string
    {
        foreach (self::PATH_MAP as $prefix => $key) {
            if ($path === $prefix || str_starts_with($path, $prefix . '/')) {
                return $key;
            }
        }
        return null;
    }

    public static function can(array $user, string $screenKey): bool
    {
        if (!in_array($user['role_slug'] ?? '', self::APPLIES_TO_ROLES, true)) {
            return true;
        }

        $allowed = self::getFor((int) $user['id']);
        if ($allowed === null) {
            return true;
        }

        return in_array($screenKey, $allowed, true);
    }

    /** @return string[]|null null = nunca configurado (sem restricao) */
    public static function getFor(int $userId): ?array
    {
        $stmt = Database::connection()->prepare('SELECT screen_key FROM user_screen_permissions WHERE user_id = :id');
        $stmt->execute(['id' => $userId]);
        $rows = $stmt->fetchAll(\PDO::FETCH_COLUMN);

        if (!in_array(self::CONFIGURED_MARKER, $rows, true)) {
            return null;
        }

        return array_values(array_diff($rows, [self::CONFIGURED_MARKER]));
    }

    /** Substitui a lista de telas liberadas -- sempre grava o marcador de "configurado", mesmo
     *  que $screenKeys venha vazio (usuario desmarcou tudo de proposito). */
    public static function setFor(int $userId, array $screenKeys): void
    {
        $db = Database::connection();
        $db->prepare('DELETE FROM user_screen_permissions WHERE user_id = :id')->execute(['id' => $userId]);

        $valid = array_values(array_intersect($screenKeys, array_keys(self::SCREENS)));

        $stmt = $db->prepare('INSERT INTO user_screen_permissions (user_id, screen_key) VALUES (:id, :key)');
        $stmt->execute(['id' => $userId, 'key' => self::CONFIGURED_MARKER]);
        foreach ($valid as $key) {
            $stmt->execute(['id' => $userId, 'key' => $key]);
        }
    }
}
