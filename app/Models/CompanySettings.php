<?php

namespace App\Models;

use App\Core\Database;

/** Dados legais da propria empresa (razao social/CNPJ/endereco) -- usados no Termo de Garantia
 *  (ver WarrantyController::downloadTerm()) e em qualquer outro documento oficial que precise
 *  identificar o emissor. Configuracao unica (1 linha so), editavel so pelo admin.
 *  Fase 62: ganhou tambem terms_text (Termos de Compra) -- o texto vigente que o cliente precisa
 *  aceitar antes de pagar (ver Order::acceptTerms(), que grava uma COPIA desse texto no pedido no
 *  momento do aceite, pra editar aqui depois nunca mudar o que um cliente ja aceitou no passado). */
class CompanySettings
{
    public static function current(): array
    {
        $row = Database::connection()->query('SELECT * FROM company_settings WHERE id = 1')->fetch();
        return $row ?: ['id' => 1, 'razao_social' => '', 'cnpj' => '', 'endereco' => '', 'terms_text' => ''];
    }

    public static function update(string $razaoSocial, string $cnpj, string $endereco): void
    {
        $stmt = Database::connection()->prepare(
            'UPDATE company_settings SET razao_social = :razao_social, cnpj = :cnpj, endereco = :endereco WHERE id = 1'
        );
        $stmt->execute(['razao_social' => $razaoSocial, 'cnpj' => $cnpj, 'endereco' => $endereco]);
    }

    public static function updateTerms(string $termsText): void
    {
        $stmt = Database::connection()->prepare('UPDATE company_settings SET terms_text = :terms_text WHERE id = 1');
        $stmt->execute(['terms_text' => $termsText]);
    }
}
