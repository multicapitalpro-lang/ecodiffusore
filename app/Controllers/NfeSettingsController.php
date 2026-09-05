<?php

namespace App\Controllers;

use App\Core\AsaasClient;
use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Response;
use App\Core\Router;
use App\Core\View;
use App\Models\NfeSettings;

/** Configuracao da emissao automatica de NF-e por pedido pago (Fase 27) -- admin-only. */
class NfeSettingsController
{
    public function index(): void
    {
        Auth::requireRole(['admin']);

        View::render('painel/settings/nfe', [
            'user' => Auth::user(),
            'settings' => NfeSettings::current(),
            'errors' => [],
        ]);
    }

    public function update(): void
    {
        Auth::requireRole(['admin']);

        if (!Csrf::verify($_POST['csrf_token'] ?? null)) {
            Router::redirect('/painel/configuracoes/nfe?erro=1');
        }

        $errors = $this->validate($_POST);
        if ($errors) {
            View::render('painel/settings/nfe', [
                'user' => Auth::user(),
                'settings' => $_POST,
                'errors' => $errors,
            ]);
            return;
        }

        NfeSettings::update($_POST);

        Router::redirect('/painel/configuracoes/nfe?sucesso=1');
    }

    /** AJAX: busca servicos municipais cadastrados na Asaas pra essa conta, pra preencher o
     *  seletor sem o admin precisar digitar o codigo cego. */
    public function searchService(): void
    {
        Auth::requireRole(['admin']);

        $query = trim((string) ($_GET['q'] ?? ''));
        if ($query === '') {
            Response::json(['ok' => true, 'data' => []]);
            return;
        }

        try {
            $result = (new AsaasClient())->searchFiscalServices($query);
            Response::json(['ok' => true, 'data' => $result['data'] ?? []]);
        } catch (\Throwable $e) {
            Response::json(['ok' => false, 'error' => $e->getMessage()]);
        }
    }

    private function validate(array $input): array
    {
        $errors = [];

        if (!empty($input['enabled']) && empty($input['municipal_service_id'])) {
            $errors['municipal_service_id'] = 'Busque e selecione o serviço municipal antes de ativar a emissão automática.';
        }

        foreach (['iss_pct', 'cofins_pct', 'csll_pct', 'inss_pct', 'ir_pct', 'pis_pct'] as $field) {
            if (!is_numeric($input[$field] ?? null) || (float) $input[$field] < 0 || (float) $input[$field] > 100) {
                $errors[$field] = 'Informe uma % entre 0 e 100.';
            }
        }

        return $errors;
    }
}
