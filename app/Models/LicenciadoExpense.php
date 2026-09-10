<?php

namespace App\Models;

use App\Core\Database;

/**
 * Custos completos da operacao do Licenciado (Fase 32) -- categoria totalmente livre (o proprio
 * Licenciado nomeia, pedido explicito do usuario), pra ele acompanhar o financeiro da propria
 * operacao nos minimos detalhes. Sempre liberado, NAO faz parte do paywall de Relatorios/
 * Financeiro (App\Core\SubscriptionGate) -- e' dado que o proprio Licenciado cadastra, nao um
 * relatorio/ferramenta avancada da Ecodiffusore.
 */
class LicenciadoExpense
{
    public static function create(int $licenciadoId, array $data): int
    {
        $stmt = Database::connection()->prepare(
            'INSERT INTO licenciado_expenses (licenciado_id, category, description, amount, expense_date)
             VALUES (:licenciado_id, :category, :description, :amount, :expense_date)'
        );
        $stmt->execute([
            'licenciado_id' => $licenciadoId,
            'category' => trim($data['category']),
            'description' => trim($data['description'] ?? '') ?: null,
            'amount' => $data['amount'],
            'expense_date' => $data['expense_date'],
        ]);

        return (int) Database::connection()->lastInsertId();
    }

    public static function update(int $id, array $data): void
    {
        $stmt = Database::connection()->prepare(
            'UPDATE licenciado_expenses SET category = :category, description = :description,
                amount = :amount, expense_date = :expense_date WHERE id = :id'
        );
        $stmt->execute([
            'id' => $id,
            'category' => trim($data['category']),
            'description' => trim($data['description'] ?? '') ?: null,
            'amount' => $data['amount'],
            'expense_date' => $data['expense_date'],
        ]);
    }

    public static function find(int $id): ?array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM licenciado_expenses WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function delete(int $id): void
    {
        $stmt = Database::connection()->prepare('DELETE FROM licenciado_expenses WHERE id = :id');
        $stmt->execute(['id' => $id]);
    }

    public static function forLicenciado(int $licenciadoId, array $filters = []): array
    {
        $sql = 'SELECT * FROM licenciado_expenses WHERE licenciado_id = :licenciado_id';
        $params = ['licenciado_id' => $licenciadoId];

        if (!empty($filters['from'])) {
            $sql .= ' AND expense_date >= :from';
            $params['from'] = $filters['from'];
        }
        if (!empty($filters['to'])) {
            $sql .= ' AND expense_date <= :to';
            $params['to'] = $filters['to'];
        }

        $sql .= ' ORDER BY expense_date DESC, id DESC';

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /** Categorias ja usadas por esse Licenciado -- pra sugerir no autocomplete do formulario, sem
     *  travar em lista fixa (categoria e' totalmente livre). */
    public static function categoriesFor(int $licenciadoId): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT DISTINCT category FROM licenciado_expenses WHERE licenciado_id = :licenciado_id ORDER BY category'
        );
        $stmt->execute(['licenciado_id' => $licenciadoId]);
        return array_column($stmt->fetchAll(), 'category');
    }

    /** Total geral + total por categoria, pro resumo no topo da tela. */
    public static function totals(int $licenciadoId, array $filters = []): array
    {
        $expenses = self::forLicenciado($licenciadoId, $filters);

        $byCategory = [];
        $total = 0.0;
        foreach ($expenses as $e) {
            $byCategory[$e['category']] = ($byCategory[$e['category']] ?? 0) + (float) $e['amount'];
            $total += (float) $e['amount'];
        }

        arsort($byCategory);

        return ['total' => $total, 'by_category' => $byCategory];
    }
}
