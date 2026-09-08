<?php

namespace App\Models;

use App\Core\Database;

class Client
{
    /** $filters: 'seller_id' (um vendedor so) ou 'seller_ids' (lista -- downline de gestor/
     *  licenciado/supervisor/gerente) + 'include_unassigned' (mostra tambem cliente sem vendedor
     *  vinculado -- so faz sentido junto de 'seller_ids', pra quem gerencia poder assumir/distribuir
     *  um cliente orfao; vendedor comum nunca usa isso, mesmo padrao de Lead::forScope). Sem filtro
     *  nenhum = sem escopo (uso interno do admin). */
    public static function all(array $filters = []): array
    {
        $conditions = ['1=1'];
        $params = [];

        if (!empty($filters['seller_id'])) {
            $conditions[] = 'c.seller_id = :seller_id';
            $params['seller_id'] = $filters['seller_id'];
        } elseif (!empty($filters['seller_ids'])) {
            $names = [];
            foreach (array_values($filters['seller_ids']) as $i => $sid) {
                $key = "sid{$i}";
                $names[] = ":{$key}";
                $params[$key] = $sid;
            }
            $scoped = 'c.seller_id IN (' . implode(',', $names) . ')';
            $conditions[] = !empty($filters['include_unassigned'])
                ? "({$scoped} OR c.seller_id IS NULL)"
                : $scoped;
        }

        $sql = 'SELECT c.*, u.name AS seller_name FROM clients c LEFT JOIN users u ON u.id = c.seller_id
                WHERE ' . implode(' AND ', $conditions) . ' ORDER BY c.name';

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /** Busca por nome, WhatsApp ou documento (CPF/CNPJ, so digitos em ambos os lados da
     *  comparacao) -- usado na busca global do painel. Mesmo escopo de seller_id/seller_ids de
     *  all(), sem include_unassigned (busca global nao precisa disso). */
    public static function search(string $term, array $filters = []): array
    {
        $digits = preg_replace('/\D/', '', $term);
        $termCondition = 'c.name LIKE :term';
        $params = ['term' => '%' . $term . '%'];
        if ($digits !== '') {
            $termCondition .= " OR REGEXP_REPLACE(c.whatsapp, '[^0-9]', '') LIKE :digits OR REGEXP_REPLACE(c.document, '[^0-9]', '') LIKE :digits";
            $params['digits'] = '%' . $digits . '%';
        }
        $conditions = ["({$termCondition})"];

        if (!empty($filters['seller_id'])) {
            $conditions[] = 'c.seller_id = :seller_id';
            $params['seller_id'] = $filters['seller_id'];
        } elseif (!empty($filters['seller_ids'])) {
            $names = [];
            foreach (array_values($filters['seller_ids']) as $i => $sid) {
                $key = "sid{$i}";
                $names[] = ":{$key}";
                $params[$key] = $sid;
            }
            $conditions[] = 'c.seller_id IN (' . implode(',', $names) . ')';
        }

        $sql = 'SELECT c.*, u.name AS seller_name FROM clients c LEFT JOIN users u ON u.id = c.seller_id
                WHERE ' . implode(' AND ', $conditions) . ' ORDER BY c.name LIMIT 20';

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public static function find(int $id): ?array
    {
        $stmt = Database::connection()->prepare(
            'SELECT c.*, u.name AS seller_name FROM clients c LEFT JOIN users u ON u.id = c.seller_id WHERE c.id = :id'
        );
        $stmt->execute(['id' => $id]);
        $client = $stmt->fetch();
        return $client ?: null;
    }

    public static function create(array $data): int
    {
        $stmt = Database::connection()->prepare(
            'INSERT INTO clients (user_id, name, document, person_type, state_registration, email, whatsapp, city, state, address,
                credit_limit_type, credit_limit_value, payment_terms, seller_id, status)
             VALUES (:user_id, :name, :document, :person_type, :state_registration, :email, :whatsapp, :city, :state, :address,
                :credit_limit_type, :credit_limit_value, :payment_terms, :seller_id, :status)'
        );
        $stmt->execute(self::params($data));

        return (int) Database::connection()->lastInsertId();
    }

    public static function update(int $id, array $data): void
    {
        $stmt = Database::connection()->prepare(
            'UPDATE clients SET name = :name, document = :document, person_type = :person_type,
                state_registration = :state_registration, email = :email, whatsapp = :whatsapp, city = :city,
                state = :state, address = :address, credit_limit_type = :credit_limit_type,
                credit_limit_value = :credit_limit_value, payment_terms = :payment_terms, seller_id = :seller_id,
                status = :status
             WHERE id = :id'
        );
        $stmt->execute(array_merge(self::params($data), ['id' => $id]));
    }

    public static function linkUser(int $clientId, int $userId): void
    {
        $stmt = Database::connection()->prepare('UPDATE clients SET user_id = :user_id WHERE id = :id');
        $stmt->execute(['user_id' => $userId, 'id' => $clientId]);
    }

    /** Cria a conta de acesso ao portal do cliente automaticamente (sem passo manual de staff),
     *  mesma logica de ClientController::createAccess() -- reaproveitada aqui pra tambem disparar
     *  sozinha quando um Pedido e' registrado (Fase 27b, pedido explicito do usuario: cliente nao
     *  precisa de cadastro pra comprar, a conta so nasce se ele de fato comprar). Devolve a senha
     *  temporaria gerada, ou null se o cliente ja tem conta / nao tem e-mail valido / e-mail ja
     *  usado por outra conta (mesmos criterios de elegibilidade do fluxo manual). */
    public static function autoCreatePortalAccess(array $client): ?string
    {
        if (!empty($client['user_id'])) {
            return null;
        }
        if (empty($client['email']) || !filter_var($client['email'], FILTER_VALIDATE_EMAIL)) {
            return null;
        }
        if (User::emailExists($client['email'])) {
            return null;
        }

        $tempPassword = substr(bin2hex(random_bytes(6)), 0, 10);

        $userId = User::create([
            'role_id' => Role::idBySlug('cliente'),
            'name' => $client['name'],
            'email' => $client['email'],
            'whatsapp' => $client['whatsapp'] ?? '',
            'password' => $tempPassword,
            'status' => 'active',
            'must_change_password' => true,
            'email_verified' => true,
        ]);

        self::linkUser((int) $client['id'], $userId);

        return $tempPassword;
    }

    /** Preenche o CPF/CNPJ de um cliente criado sem documento (ex: Proposta Facil, que so pede
     *  Nome/WhatsApp de inicio) -- usado na hora de "Concluir Pedido", quando o documento passa a
     *  ser obrigatorio pra gerar cobranca na Asaas. Nao mexe nos outros campos do cliente. */
    public static function updateDocument(int $clientId, string $document): void
    {
        $stmt = Database::connection()->prepare('UPDATE clients SET document = :document WHERE id = :id');
        $stmt->execute(['document' => $document, 'id' => $clientId]);
    }

    /** Cliente edita os proprios dados de contato/endereco (Meus Dados) -- nao mexe em name/email
     *  (login) nem em seller_id, status, limite de credito ou condicao de pagamento (so staff mexe nisso). */
    /** Endereco estruturado (zip_code/street/number/complement/neighborhood + city/state ja
     *  existentes) -- necessario pro cliente conseguir rastrear a entrega (Fase 27c). O campo
     *  legado `address` (texto livre, usado em telas antigas de staff) continua sendo preenchido
     *  automaticamente como um resumo dos campos estruturados, pra nao quebrar nada que ja le' ele. */
    public static function updateOwnProfile(int $id, array $data): void
    {
        $addressSummary = trim(
            ($data['street'] ?? '') . ($data['number'] ? ', ' . $data['number'] : '')
            . (($data['complement'] ?? '') !== '' ? ' - ' . $data['complement'] : '')
            . (($data['neighborhood'] ?? '') !== '' ? ' - ' . $data['neighborhood'] : '')
        );

        $stmt = Database::connection()->prepare(
            'UPDATE clients SET document = :document, person_type = :person_type, state_registration = :state_registration,
                whatsapp = :whatsapp, city = :city, state = :state, address = :address,
                zip_code = :zip_code, street = :street, number = :number, complement = :complement, neighborhood = :neighborhood
             WHERE id = :id'
        );
        $stmt->execute([
            'id' => $id,
            'document' => ($data['document'] ?? '') ?: null,
            'person_type' => ($data['person_type'] ?? '') === 'juridica' ? 'juridica' : 'fisica',
            'state_registration' => ($data['state_registration'] ?? '') ?: null,
            'whatsapp' => ($data['whatsapp'] ?? '') ?: null,
            'city' => ($data['city'] ?? '') ?: null,
            'state' => ($data['state'] ?? '') ?: null,
            'address' => $addressSummary ?: null,
            'zip_code' => ($data['zip_code'] ?? '') ?: null,
            'street' => ($data['street'] ?? '') ?: null,
            'number' => ($data['number'] ?? '') ?: null,
            'complement' => ($data['complement'] ?? '') ?: null,
            'neighborhood' => ($data['neighborhood'] ?? '') ?: null,
        ]);
    }

    public static function bulkAssignSeller(array $clientIds, ?int $sellerId): void
    {
        $clientIds = array_filter(array_map('intval', $clientIds));
        if (!$clientIds) {
            return;
        }

        $placeholders = implode(',', array_fill(0, count($clientIds), '?'));
        $stmt = Database::connection()->prepare("UPDATE clients SET seller_id = ? WHERE id IN ({$placeholders})");
        $stmt->execute([$sellerId, ...$clientIds]);
    }

    public static function delete(int $id): void
    {
        $stmt = Database::connection()->prepare('DELETE FROM clients WHERE id = :id');
        $stmt->execute(['id' => $id]);
    }

    public static function findByUserId(int $userId): ?array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM clients WHERE user_id = :id LIMIT 1');
        $stmt->execute(['id' => $userId]);
        $client = $stmt->fetch();
        return $client ?: null;
    }

    /** Cliente existente com o mesmo CPF/CNPJ ou WhatsApp (comparado so pelos digitos, ignorando
     *  mascara) -- usado pra impedir cadastro duplicado em rede diferente (Fase 36, pedido do
     *  usuario: "nao podemos ter o mesmo lead em dois CRMs diferentes"). Documento/WhatsApp
     *  identificam a pessoa de forma confiavel; nome e' subjetivo demais pra esse fim.
     *  $excludeId ignora o proprio registro (uso em update()). */
    public static function findDuplicate(?string $document, ?string $whatsapp, ?int $excludeId = null): ?array
    {
        $documentDigits = $document ? preg_replace('/\D/', '', $document) : '';
        $whatsappDigits = $whatsapp ? preg_replace('/\D/', '', $whatsapp) : '';

        if ($documentDigits === '' && $whatsappDigits === '') {
            return null;
        }

        $conditions = [];
        $params = [];
        if ($documentDigits !== '') {
            $conditions[] = "REGEXP_REPLACE(c.document, '[^0-9]', '') = :doc";
            $params['doc'] = $documentDigits;
        }
        if ($whatsappDigits !== '') {
            $conditions[] = "REGEXP_REPLACE(c.whatsapp, '[^0-9]', '') = :wa";
            $params['wa'] = $whatsappDigits;
        }

        $sql = 'SELECT c.*, u.name AS seller_name FROM clients c LEFT JOIN users u ON u.id = c.seller_id
                WHERE (' . implode(' OR ', $conditions) . ')';
        if ($excludeId) {
            $sql .= ' AND c.id != :exclude_id';
            $params['exclude_id'] = $excludeId;
        }
        $sql .= ' LIMIT 1';

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    private static function params(array $data): array
    {
        $creditType = in_array($data['credit_limit_type'] ?? '', ['ilimitado', 'zero', 'valor'], true)
            ? $data['credit_limit_type']
            : 'ilimitado';

        return [
            'user_id' => $data['user_id'] ?? null,
            'name' => $data['name'],
            'document' => ($data['document'] ?? '') ?: null,
            'person_type' => ($data['person_type'] ?? '') === 'juridica' ? 'juridica' : 'fisica',
            'state_registration' => ($data['state_registration'] ?? '') ?: null,
            'email' => ($data['email'] ?? '') ?: null,
            'whatsapp' => ($data['whatsapp'] ?? '') ?: null,
            'city' => ($data['city'] ?? '') ?: null,
            'state' => ($data['state'] ?? '') ?: null,
            'address' => ($data['address'] ?? '') ?: null,
            'credit_limit_type' => $creditType,
            'credit_limit_value' => $creditType === 'valor' ? (($data['credit_limit_value'] ?? '') ?: null) : null,
            'payment_terms' => ($data['payment_terms'] ?? '') ?: null,
            'seller_id' => ($data['seller_id'] ?? '') ?: null,
            'status' => $data['status'] ?? 'ativo',
        ];
    }
}
