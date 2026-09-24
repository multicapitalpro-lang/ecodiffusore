<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Pdf;
use App\Core\Roles;
use App\Core\Router;
use App\Core\View;
use App\Core\WordDoc;
use App\Models\PricingTier;

/** Fase 104: "Proposta Comercial" -- documento de venda personalizado e com apresentacao
 *  profissional (PDF + Word), pra apresentar ao cliente. Diferente da Proposta Facil (essa e' uma
 *  calculadora interna de economia/payback, com Lead/Cliente/Pedido de verdade por tras) -- essa
 *  aqui e' so' geracao de documento, sem gravar nada no CRM. Cobre os 2 modos pedidos pelo usuario
 *  (1 produto variando so' a quantidade, ou varios modelos de frota diferentes) com a MESMA tabela
 *  dinamica -- 1 linha ou N linhas, sem precisar de 2 fluxos separados. */
class PropostaComercialController
{
    public function create(): void
    {
        Auth::requireRole(Roles::STAFF);
        View::render('painel/proposta_comercial/form', [
            'user' => Auth::user(),
            'errors' => [],
            'values' => [],
            'items' => [],
        ]);
    }

    private function parseItems(array $input): array
    {
        $items = [];
        $produtos = $input['produto'] ?? [];
        $quantidades = $input['quantidade'] ?? [];
        $precos = $input['valor_unitario'] ?? [];

        foreach ($produtos as $i => $produto) {
            if (trim((string) $produto) === '') {
                continue;
            }
            $qty = max(1, (int) ($quantidades[$i] ?? 1));
            $unitPrice = (float) ($precos[$i] ?? 0);
            $items[] = [
                'produto' => trim((string) $produto),
                'quantity' => $qty,
                'unit_price' => $unitPrice,
                'subtotal' => round($qty * $unitPrice, 2),
            ];
        }

        return $items;
    }

    private function validate(array $input, array $items): array
    {
        $errors = [];

        if (trim($input['client_name'] ?? '') === '') {
            $errors['client_name'] = 'Informe o nome do cliente.';
        }
        if (!$items) {
            $errors['items'] = 'Adicione pelo menos um produto.';
        }

        foreach ($items as $item) {
            if ($item['unit_price'] > 0 && !PricingTier::forPrice($item['unit_price'])) {
                $floorTier = PricingTier::all()[0] ?? null;
                $floor = $floorTier ? number_format((float) $floorTier['min_price'], 2, ',', '.') : '0,00';
                $errors['items'] = "Preço unitário abaixo do mínimo negociável (R$ {$floor}).";
                break;
            }
            if ($item['unit_price'] <= 0) {
                $errors['items'] = 'Informe um valor unitário válido pra cada produto.';
                break;
            }
        }

        return $errors;
    }

    /** @return array{0: array, 1: array, 2: float} [items, errors, total] */
    private function prepare(): array
    {
        $items = $this->parseItems($_POST);
        $errors = $this->validate($_POST, $items);
        $total = array_sum(array_map(fn ($i) => $i['subtotal'], $items));

        return [$items, $errors, $total];
    }

    private function documentData(array $items, float $total): array
    {
        $user = Auth::user();
        return [
            'clientName' => trim($_POST['client_name'] ?? ''),
            'city' => trim($_POST['city'] ?? ''),
            'responsible' => trim($_POST['responsible'] ?? '') ?: $user['name'],
            'items' => $items,
            'total' => $total,
            'paymentTerms' => trim($_POST['payment_terms'] ?? ''),
            'validity' => trim($_POST['validity'] ?? ''),
            'notes' => trim($_POST['notes'] ?? ''),
        ];
    }

    public function pdf(): void
    {
        Auth::requireRole(Roles::STAFF);
        $user = Auth::user();

        if (!Csrf::verify($_POST['csrf_token'] ?? null)) {
            Router::redirect('/painel/proposta-comercial?erro=1');
        }

        [$items, $errors, $total] = $this->prepare();
        if ($errors) {
            View::render('painel/proposta_comercial/form', [
                'user' => $user, 'errors' => $errors, 'values' => $_POST, 'items' => $items,
            ]);
            return;
        }

        ob_start();
        View::render('painel/proposta_comercial/pdf', $this->documentData($items, $total), null);
        $html = ob_get_clean();

        Pdf::download($html, 'proposta-comercial-ecodiffusore.pdf', 'portrait');
    }

    public function docx(): void
    {
        Auth::requireRole(Roles::STAFF);
        $user = Auth::user();

        if (!Csrf::verify($_POST['csrf_token'] ?? null)) {
            Router::redirect('/painel/proposta-comercial?erro=1');
        }

        [$items, $errors, $total] = $this->prepare();
        if ($errors) {
            View::render('painel/proposta_comercial/form', [
                'user' => $user, 'errors' => $errors, 'values' => $_POST, 'items' => $items,
            ]);
            return;
        }

        WordDoc::downloadCommercialProposal($this->documentData($items, $total));
    }
}
