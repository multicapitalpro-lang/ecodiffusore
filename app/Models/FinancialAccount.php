<?php

namespace App\Models;

use App\Core\Database;

class FinancialAccount
{
    public static function all(): array
    {
        return Database::connection()
            ->query('SELECT * FROM financial_accounts ORDER BY name')
            ->fetchAll();
    }

    public static function find(int $id): ?array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM financial_accounts WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $account = $stmt->fetch();
        return $account ?: null;
    }

    public static function create(array $data): int
    {
        $stmt = Database::connection()->prepare(
            'INSERT INTO financial_accounts (name, type, initial_balance) VALUES (:name, :type, :initial_balance)'
        );
        $stmt->execute([
            'name' => $data['name'],
            'type' => $data['type'],
            'initial_balance' => $data['initial_balance'] ?: 0,
        ]);
        $id = (int) Database::connection()->lastInsertId();

        if (!empty($data['is_default'])) {
            self::setDefault($id);
        }

        return $id;
    }

    /** Marca essa conta como padrao (destino automatico de recebimento de pedido/comissao) e
     *  desmarca qualquer outra -- so pode haver uma conta padrao por vez. */
    public static function setDefault(int $id): void
    {
        $db = Database::connection();
        $db->beginTransaction();
        try {
            $db->exec('UPDATE financial_accounts SET is_default = 0');
            $stmt = $db->prepare('UPDATE financial_accounts SET is_default = 1 WHERE id = :id');
            $stmt->execute(['id' => $id]);
            $db->commit();
        } catch (\Throwable $e) {
            $db->rollBack();
            throw $e;
        }
    }

    public static function defaultAccountId(): ?int
    {
        $stmt = Database::connection()->query(
            "SELECT id FROM financial_accounts WHERE active = 1 AND is_default = 1 ORDER BY id LIMIT 1"
        );
        $id = $stmt->fetchColumn();
        if ($id !== false) {
            return (int) $id;
        }

        // Nenhuma conta marcada como padrao (ex: dado antigo) -- cai pro comportamento anterior.
        $stmt = Database::connection()->query(
            'SELECT id FROM financial_accounts WHERE active = 1 ORDER BY id LIMIT 1'
        );
        $id = $stmt->fetchColumn();
        return $id !== false ? (int) $id : null;
    }

    public static function currentBalance(int $accountId): float
    {
        $account = self::find($accountId);
        if (!$account) {
            return 0.0;
        }

        $stmt = Database::connection()->prepare(
            "SELECT COALESCE(SUM(CASE WHEN type = 'entrada' THEN amount ELSE -amount END), 0)
             FROM financial_transactions WHERE account_id = :id AND status IN ('pago','conciliado')"
        );
        $stmt->execute(['id' => $accountId]);

        return (float) $account['initial_balance'] + (float) $stmt->fetchColumn();
    }

    /** Fase 115: pra conta com balance_source='asaas', busca o saldo REAL na API do Asaas em vez
     *  do calculo local (que depende de todo recebimento/pagamento ter sido categorizado na conta
     *  certa aqui dentro -- na pratica ja divergiu, ver Fase 115 no changelog). Best-effort: se a
     *  API falhar (rede, chave invalida), cai pro calculo local em vez de quebrar a pagina. */
    public static function liveBalance(array $account): float
    {
        if (($account['balance_source'] ?? 'manual') === 'asaas') {
            try {
                return (new \App\Core\AsaasClient())->getBalance();
            } catch (\Throwable $e) {
                // segue pro calculo local abaixo
            }
        }

        return self::currentBalance((int) $account['id']);
    }
}
