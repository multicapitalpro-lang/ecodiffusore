<?php

namespace App\Core;

/**
 * Monta os dados do contrato do Licenciado no formato que o Modelo cadastrado no ClickSign
 * (Automação > Modelos, "Contrato Assinatura Diferencial") espera: um mapa "Nome do Campo" =>
 * valor, usando exatamente os nomes de campo que o ClickSign detectou a partir dos ${{...}}
 * dentro do .docx que foi cadastrado como modelo lá. Confirmado contra
 * GET /templates/{key}/template_fields em 2026-09-02.
 */
class ContractTemplateFiller
{
    public static function buildTemplateData(array $user): array
    {
        return [
            'Razão Social' => $user['razao_social'],
            'CNPJ' => self::formatCnpj($user['cnpj']),
            'Endereço' => self::formatEndereco($user),
            'Nome do Representante' => $user['name'],
            'CPF do Representante' => self::formatCpf($user['cpf_representante']),
            'RG do Representante' => $user['rg_representante'],
            'Data' => date('d/m/Y'),
            'Comissão' => self::formatCommission($user['commission_pct'] ?? null),
            'Base de Cálculo' => 'Total do pedido pago pelo Cliente Final',
            'Observações' => 'Conforme Contrato, Cláusula Terceira.',
        ];
    }

    public static function formatCnpj(string $digits): string
    {
        $digits = preg_replace('/\D/', '', $digits);
        if (strlen($digits) !== 14) {
            return $digits;
        }
        return substr($digits, 0, 2) . '.' . substr($digits, 2, 3) . '.' . substr($digits, 5, 3)
            . '/' . substr($digits, 8, 4) . '-' . substr($digits, 12, 2);
    }

    public static function formatCpf(string $digits): string
    {
        $digits = preg_replace('/\D/', '', $digits);
        if (strlen($digits) !== 11) {
            return $digits;
        }
        return substr($digits, 0, 3) . '.' . substr($digits, 3, 3) . '.' . substr($digits, 6, 3)
            . '-' . substr($digits, 9, 2);
    }

    private static function formatCommission(?string $pct): string
    {
        return $pct !== null && $pct !== '' ? number_format((float) $pct, 2, ',', '.') . '%' : 'A definir';
    }

    public static function formatEndereco(array $user): string
    {
        $partes = [];
        $partes[] = trim($user['endereco_logradouro'] . ', ' . $user['endereco_numero']);
        if (!empty($user['endereco_complemento'])) {
            $partes[] = $user['endereco_complemento'];
        }
        $partes[] = $user['endereco_bairro'];
        $partes[] = $user['endereco_cidade'] . '/' . $user['endereco_uf'];
        $partes[] = 'CEP ' . self::formatCep($user['endereco_cep']);

        return implode(', ', array_filter($partes, fn ($p) => trim((string) $p) !== ''));
    }

    public static function formatCep(string $digits): string
    {
        $digits = preg_replace('/\D/', '', $digits);
        if (strlen($digits) !== 8) {
            return $digits;
        }
        return substr($digits, 0, 5) . '-' . substr($digits, 5, 3);
    }

    /** ClickSign exige phone_number em E.164 (ex: +5545991358427) -- nossos campos guardam
     * "(45) 991358427" (so DDD+numero, sem +55). */
    public static function formatPhoneE164(string $phone): string
    {
        $digits = preg_replace('/\D/', '', $phone);
        if ($digits === '') {
            return '';
        }
        if (strlen($digits) <= 11) {
            $digits = '55' . $digits;
        }
        return '+' . $digits;
    }
}
