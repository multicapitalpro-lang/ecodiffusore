<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Response;
use App\Core\Router;
use App\Core\View;
use App\Models\PricingTier;

/** Faixas de preco negociavel (Fase 31) -- admin-only, mesmo padrao do ProductController. */
class PricingTierController
{
    public function index(): void
    {
        Auth::requireRole(['admin']);

        View::render('painel/pricing/index', [
            'user' => Auth::user(),
            'tiers' => PricingTier::all(),
        ]);
    }

    public function store(): void
    {
        Auth::requireRole(['admin']);

        if (!Csrf::verify($_POST['csrf_token'] ?? null)) {
            if (Response::isAjax()) {
                Response::json(['ok' => false, 'errors' => ['min_price' => 'Sessão expirada, recarregue a página.']]);
            }
            Router::redirect('/painel/tabela-precos?erro=1');
        }

        $errors = $this->validate($_POST);

        if ($errors) {
            if (Response::isAjax()) {
                Response::json(['ok' => false, 'errors' => $errors]);
            }
            View::render('painel/pricing/index', [
                'user' => Auth::user(),
                'tiers' => PricingTier::all(),
                'errors' => $errors,
                'values' => $_POST,
            ]);
            return;
        }

        PricingTier::create($_POST);

        if (Response::isAjax()) {
            Response::json(['ok' => true, 'redirect' => '/painel/tabela-precos?sucesso=1']);
        }

        Router::redirect('/painel/tabela-precos?sucesso=1');
    }

    public function edit(string $id): void
    {
        Auth::requireRole(['admin']);

        $tier = PricingTier::find((int) $id);
        if (!$tier) {
            Router::redirect('/painel/tabela-precos');
        }

        View::render('painel/pricing/form', [
            'user' => Auth::user(),
            'editing' => $tier,
            'errors' => [],
        ]);
    }

    public function update(string $id): void
    {
        Auth::requireRole(['admin']);
        $id = (int) $id;

        if (!Csrf::verify($_POST['csrf_token'] ?? null)) {
            Router::redirect("/painel/tabela-precos/{$id}/editar?erro=1");
        }

        $errors = $this->validate($_POST);

        if ($errors) {
            View::render('painel/pricing/form', [
                'user' => Auth::user(),
                'editing' => array_merge(['id' => $id], $_POST),
                'errors' => $errors,
            ]);
            return;
        }

        PricingTier::update($id, $_POST);

        Router::redirect('/painel/tabela-precos?sucesso=1');
    }

    public function destroy(string $id): void
    {
        Auth::requireRole(['admin']);
        $id = (int) $id;

        if (!Csrf::verify($_POST['csrf_token'] ?? null)) {
            Router::redirect('/painel/tabela-precos?erro=csrf');
        }

        try {
            PricingTier::delete($id);
        } catch (\PDOException $e) {
            Router::redirect('/painel/tabela-precos?erro=vinculo');
        }

        Router::redirect('/painel/tabela-precos?sucesso=2');
    }

    private function validate(array $input): array
    {
        $errors = [];

        if (!is_numeric($input['min_price'] ?? null) || (float) $input['min_price'] <= 0) {
            $errors['min_price'] = 'Informe um preço mínimo válido.';
        }
        if (($input['max_price'] ?? '') !== '' && (!is_numeric($input['max_price']) || (float) $input['max_price'] <= (float) ($input['min_price'] ?? 0))) {
            $errors['max_price'] = 'O preço máximo deve ser maior que o mínimo (ou deixe em branco pra "sem limite superior").';
        }
        if (!is_numeric($input['licenciado_commission_pct'] ?? null) || (float) $input['licenciado_commission_pct'] < 0 || (float) $input['licenciado_commission_pct'] > 100) {
            $errors['licenciado_commission_pct'] = 'Informe uma % entre 0 e 100.';
        }
        if (($input['cost_price'] ?? '') !== '' && (!is_numeric($input['cost_price']) || (float) $input['cost_price'] < 0)) {
            $errors['cost_price'] = 'Informe um custo válido.';
        }
        if (($input['tax_pct'] ?? '') !== '' && (!is_numeric($input['tax_pct']) || (float) $input['tax_pct'] < 0 || (float) $input['tax_pct'] > 100)) {
            $errors['tax_pct'] = 'Informe uma % entre 0 e 100.';
        }

        return $errors;
    }
}
