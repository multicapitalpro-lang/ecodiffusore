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

    /** Categoria "Comissões" (filha de Despesas com Pessoal, seedada no schema_fase3) -- usada
     *  pra classificar automaticamente a saida de caixa gerada ao dar baixa numa comissao. */
    public static function commissionCategoryId(): ?int
    {
        $stmt = Database::connection()->query(
            "SELECT id FROM financial_categories WHERE name = 'Comissões' AND type = 'saida' LIMIT 1"
        );
        $id = $stmt->fetchColumn();
        return $id !== false ? (int) $id : null;
    }

    /** Fase 100: categoria "Custo das mercadorias vendidas" (ja seedada no schema_fase3, filha de
     *  Custos) -- usada pra classificar automaticamente o custo de fabrica gerado quando um
     *  pedido normal e' verificado. */
    public static function factoryCostCategoryId(): ?int
    {
        $stmt = Database::connection()->query(
            "SELECT id FROM financial_categories WHERE name = 'Custo das mercadorias vendidas' AND type = 'saida' LIMIT 1"
        );
        $id = $stmt->fetchColumn();
        return $id !== false ? (int) $id : null;
    }

    /** Fase 100: categoria "Impostos sobre vendas" (ja seedada no schema_fase3, filha de Impostos
     *  e Taxas) -- usada pra classificar automaticamente o imposto gerado quando um pedido normal
     *  e' verificado. */
    public static function salesTaxCategoryId(): ?int
    {
        $stmt = Database::connection()->query(
            "SELECT id FROM financial_categories WHERE name = 'Impostos sobre vendas' AND type = 'saida' LIMIT 1"
        );
        $id = $stmt->fetchColumn();
        return $id !== false ? (int) $id : null;
    }
}
