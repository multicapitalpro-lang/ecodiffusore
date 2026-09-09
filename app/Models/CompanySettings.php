<?php

namespace App\Models;

use App\Core\Database;

/** Dados legais da propria empresa (razao social/CNPJ/endereco) -- usados no Termo de Garantia
 *  (ver WarrantyController::downloadTerm()) e em qualquer outro documento oficial que precise
 *  identificar o emissor. Configuracao unica (1 linha so), editavel so pelo admin. */
class CompanySettings
{
    public static function current(): array
    {
        $row = Database::connection()->query('SELECT * FROM company_settings WHERE id = 1')->fetch();
        return $row ?: ['id' => 1, 'razao_social' => '', 'cnpj' => '', 'endereco' => ''];
    }

    public static function update(string $razaoSocial, string $cnpj, string $endereco): void
    {
        $stmt = Database::connection()->prepare(
            'UPDATE company_settings SET razao_social = :razao_social, cnpj = :cnpj, endereco = :endereco WHERE id = 1'
        );
        $stmt->execute(['razao_social' => $razaoSocial, 'cnpj' => $cnpj, 'endereco' => $endereco]);
    }
}
