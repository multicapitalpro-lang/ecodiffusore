<?php

namespace App\Core;

use PhpOffice\PhpWord\TemplateProcessor;

/**
 * Preenche o modelo de contrato do Licenciado (storage/templates/contrato_licenciado.docx --
 * ja normalizado com placeholders ${VAR}) com os dados coletados no onboarding, pronto pra subir
 * pro ClickSign como base64. O ClickSign nao garante substituicao confiavel de variaveis em
 * documento bruto, entao o preenchimento e feito aqui, no servidor.
 */
class ContractTemplateFiller
{
    private const TEMPLATE_PATH = BASE_PATH . '/storage/templates/contrato_licenciado.docx';

    public static function fillLicenciadoContract(array $user): string
    {
        require_once BASE_PATH . '/vendor/autoload.php';

        $processor = new TemplateProcessor(self::TEMPLATE_PATH);

        $processor->setValue('RAZAO_SOCIAL_LICENCIADO', $user['razao_social']);
        $processor->setValue('CNPJ_LICENCIADO', self::formatCnpj($user['cnpj']));
        $processor->setValue('ENDERECO_LICENCIADO', self::formatEndereco($user));
        $processor->setValue('NOME_REPRESENTANTE_LICENCIADO', $user['name']);
        $processor->setValue('CPF_REPRESENTANTE_LICENCIADO', self::formatCpf($user['cpf_representante']));
        $processor->setValue('RG_REPRESENTANTE_LICENCIADO', $user['rg_representante']);
        $processor->setValue('DATA_CONTRATO', date('d/m/Y'));
        $processor->setValue('COMISSAO', self::formatCommission($user['commission_pct'] ?? null));
        $processor->setValue('BASE_CALCULO', 'Total do pedido pago pelo Cliente Final');
        $processor->setValue('OBSERVACOES', 'Conforme Contrato, Cláusula Terceira.');

        $tmpPath = tempnam(sys_get_temp_dir(), 'contrato_') . '.docx';

        try {
            $processor->saveAs($tmpPath);
            $content = file_get_contents($tmpPath);
            return base64_encode($content);
        } finally {
            if (file_exists($tmpPath)) {
                unlink($tmpPath);
            }
        }
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
}
