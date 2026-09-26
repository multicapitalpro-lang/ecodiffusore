<?php

namespace App\Models;

use App\Core\Database;

/** Fase 138: dados da empresa do Licenciado pra emissao de nota fiscal (ferramenta premium
 *  "Nota Fiscal Automatica"). Fase 1 -- so coleta e guarda; emissao de verdade via API de
 *  terceiro fica pra Fase 2, quando o provedor for escolhido. */
class LicenciadoFiscalData
{
    public static function find(int $licenciadoId): ?array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM licenciado_fiscal_data WHERE licenciado_id = :id');
        $stmt->execute(['id' => $licenciadoId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function upsert(int $licenciadoId, array $data): void
    {
        $stmt = Database::connection()->prepare(
            'INSERT INTO licenciado_fiscal_data
                (licenciado_id, razao_social, cnpj, inscricao_municipal, endereco_cep, endereco_logradouro,
                 endereco_numero, endereco_complemento, endereco_bairro, endereco_cidade, endereco_uf, email_nota)
             VALUES
                (:licenciado_id, :razao_social, :cnpj, :inscricao_municipal, :endereco_cep, :endereco_logradouro,
                 :endereco_numero, :endereco_complemento, :endereco_bairro, :endereco_cidade, :endereco_uf, :email_nota)
             ON DUPLICATE KEY UPDATE
                razao_social = VALUES(razao_social), cnpj = VALUES(cnpj),
                inscricao_municipal = VALUES(inscricao_municipal), endereco_cep = VALUES(endereco_cep),
                endereco_logradouro = VALUES(endereco_logradouro), endereco_numero = VALUES(endereco_numero),
                endereco_complemento = VALUES(endereco_complemento), endereco_bairro = VALUES(endereco_bairro),
                endereco_cidade = VALUES(endereco_cidade), endereco_uf = VALUES(endereco_uf),
                email_nota = VALUES(email_nota)'
        );
        $stmt->execute([
            'licenciado_id' => $licenciadoId,
            'razao_social' => trim($data['razao_social'] ?? ''),
            'cnpj' => preg_replace('/\D/', '', (string) ($data['cnpj'] ?? '')),
            'inscricao_municipal' => trim($data['inscricao_municipal'] ?? '') ?: null,
            'endereco_cep' => trim($data['endereco_cep'] ?? '') ?: null,
            'endereco_logradouro' => trim($data['endereco_logradouro'] ?? '') ?: null,
            'endereco_numero' => trim($data['endereco_numero'] ?? '') ?: null,
            'endereco_complemento' => trim($data['endereco_complemento'] ?? '') ?: null,
            'endereco_bairro' => trim($data['endereco_bairro'] ?? '') ?: null,
            'endereco_cidade' => trim($data['endereco_cidade'] ?? '') ?: null,
            'endereco_uf' => strtoupper(trim($data['endereco_uf'] ?? '')) ?: null,
            'email_nota' => trim($data['email_nota'] ?? '') ?: null,
        ]);
    }
}
