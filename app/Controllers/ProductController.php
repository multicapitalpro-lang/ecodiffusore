<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Response;
use App\Core\Router;
use App\Core\View;
use App\Models\Product;

class ProductController
{
    public function index(): void
    {
        Auth::requireRole(['admin']);

        View::render('painel/products/index', [
            'user' => Auth::user(),
            'products' => Product::all(),
        ]);
    }

    public function create(): void
    {
        Auth::requireRole(['admin']);
        Router::redirect('/painel/produtos?novo=1');
    }

    public function store(): void
    {
        Auth::requireRole(['admin']);

        if (!Csrf::verify($_POST['csrf_token'] ?? null)) {
            if (Response::isAjax()) {
                Response::json(['ok' => false, 'errors' => ['sku' => 'Sessão expirada, recarregue a página.']]);
            }
            Router::redirect('/painel/produtos?erro=1');
        }

        $errors = $this->validate($_POST);

        if ($errors) {
            if (Response::isAjax()) {
                Response::json(['ok' => false, 'errors' => $errors]);
            }
            View::render('painel/products/index', [
                'user' => Auth::user(),
                'products' => Product::all(),
                'errors' => $errors,
                'values' => $_POST,
            ]);
            return;
        }

        Product::create($_POST + ['active' => isset($_POST['active'])]);

        if (Response::isAjax()) {
            Response::json(['ok' => true, 'redirect' => '/painel/produtos?sucesso=1']);
        }

        Router::redirect('/painel/produtos?sucesso=1');
    }

    public function edit(string $id): void
    {
        Auth::requireRole(['admin']);

        $product = Product::find((int) $id);
        if (!$product) {
            Router::redirect('/painel/produtos');
        }

        View::render('painel/products/form', [
            'user' => Auth::user(),
            'editing' => $product,
            'errors' => [],
        ]);
    }

    public function update(string $id): void
    {
        Auth::requireRole(['admin']);
        $id = (int) $id;

        if (!Csrf::verify($_POST['csrf_token'] ?? null)) {
            Router::redirect("/painel/produtos/{$id}/editar?erro=1");
        }

        $errors = $this->validate($_POST);

        if ($errors) {
            View::render('painel/products/form', [
                'user' => Auth::user(),
                'editing' => array_merge(['id' => $id], $_POST),
                'errors' => $errors,
            ]);
            return;
        }

        Product::update($id, $_POST + ['active' => isset($_POST['active'])]);

        Router::redirect('/painel/produtos?sucesso=1');
    }

    private function validate(array $input): array
    {
        $errors = [];

        if (trim($input['sku'] ?? '') === '') {
            $errors['sku'] = 'Informe o SKU.';
        }
        if (trim($input['name'] ?? '') === '') {
            $errors['name'] = 'Informe o nome do produto.';
        }
        if (!is_numeric($input['price_cash'] ?? null) || (float) $input['price_cash'] <= 0) {
            $errors['price_cash'] = 'Informe um preço à vista válido.';
        }
        if (!is_numeric($input['price_installment'] ?? null) || (float) $input['price_installment'] <= 0) {
            $errors['price_installment'] = 'Informe um valor de parcela válido.';
        }

        return $errors;
    }
}
