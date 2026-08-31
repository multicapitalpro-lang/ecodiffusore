<?php

namespace App\Models;

use App\Core\Database;

class FinancialCategory
{
    /** Retorna os grupos (pais) com seus filhos aninhados, filtrado por tipo (entrada/saida/null=todos) */
    public static function grouped(?string $type = null): array
    {
        $parents = Database::connection()
            ->query('SELECT * FROM financial_categories WHERE parent_id IS NULL ORDER BY name')
            ->fetchAll();

        $sql = 'SELECT * FROM financial_categories WHERE parent_id = :parent_id';
        if ($type !== null) {
            $sql .= " AND type IN ('ambos', :type)";
        }
        $sql .= ' ORDER BY name';
        $stmt = Database::connection()->prepare($sql);

        $groups = [];
        foreach ($parents as $parent) {
            $params = ['parent_id' => $parent['id']];
            if ($type !== null) {
                $params['type'] = $type;
            }
            $stmt->execute($params);
            $children = $stmt->fetchAll();
            if ($children) {
                $groups[] = ['parent' => $parent, 'children' => $children];
            }
        }

        return $groups;
    }

    public static function find(int $id): ?array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM financial_categories WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $cat = $stmt->fetch();
        return $cat ?: null;
    }
}
