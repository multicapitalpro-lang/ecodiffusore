<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Router;
use App\Core\View;
use App\Models\Role;
use App\Models\User;

class UserController
{
    public function index(): void
    {
        Auth::requireRole(['admin']);

        View::render('painel/users/index', [
            'user' => Auth::user(),
            'users' => User::all(),
        ]);
    }

    public function create(): void
    {
        Auth::requireRole(['admin']);

        View::render('painel/users/form', [
            'user' => Auth::user(),
            'roles' => Role::all(),
            'editing' => null,
            'errors' => [],
        ]);
    }

    public function store(): void
    {
        Auth::requireRole(['admin']);

        if (!Csrf::verify($_POST['csrf_token'] ?? null)) {
            Router::redirect('/painel/usuarios/novo?erro=1');
        }

        $errors = $this->validate($_POST, null);

        if ($errors) {
            View::render('painel/users/form', [
                'user' => Auth::user(),
                'roles' => Role::all(),
                'editing' => null,
                'errors' => $errors,
                'old' => $_POST,
            ]);
            return;
        }

        User::create([
            'role_id' => (int) $_POST['role_id'],
            'name' => trim($_POST['name']),
            'email' => trim($_POST['email']),
            'whatsapp' => trim($_POST['whatsapp'] ?? ''),
            'password' => $_POST['password'],
            'status' => $_POST['status'] ?? 'active',
        ]);

        Router::redirect('/painel/usuarios?sucesso=1');
    }

    public function edit(string $id): void
    {
        Auth::requireRole(['admin']);

        $editing = User::find((int) $id);
        if (!$editing) {
            Router::redirect('/painel/usuarios');
        }

        View::render('painel/users/form', [
            'user' => Auth::user(),
            'roles' => Role::all(),
            'editing' => $editing,
            'errors' => [],
        ]);
    }

    public function update(string $id): void
    {
        Auth::requireRole(['admin']);
        $id = (int) $id;

        if (!Csrf::verify($_POST['csrf_token'] ?? null)) {
            Router::redirect("/painel/usuarios/{$id}/editar?erro=1");
        }

        $errors = $this->validate($_POST, $id);

        if ($errors) {
            View::render('painel/users/form', [
                'user' => Auth::user(),
                'roles' => Role::all(),
                'editing' => array_merge(['id' => $id], $_POST),
                'errors' => $errors,
            ]);
            return;
        }

        User::update($id, [
            'role_id' => (int) $_POST['role_id'],
            'name' => trim($_POST['name']),
            'email' => trim($_POST['email']),
            'whatsapp' => trim($_POST['whatsapp'] ?? ''),
            'status' => $_POST['status'] ?? 'active',
        ]);

        if (!empty($_POST['reset_password'])) {
            $temp = substr(bin2hex(random_bytes(6)), 0, 10);
            User::resetPassword($id, $temp);
            Router::redirect("/painel/usuarios?sucesso=2&temp={$temp}");
        }

        Router::redirect('/painel/usuarios?sucesso=1');
    }

    private function validate(array $input, ?int $exceptId): array
    {
        $errors = [];

        if (trim($input['name'] ?? '') === '') {
            $errors['name'] = 'Informe o nome.';
        }

        $email = trim($input['email'] ?? '');
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'E-mail inválido.';
        } elseif (User::emailExists($email, $exceptId)) {
            $errors['email'] = 'Já existe um usuário com este e-mail.';
        }

        if (empty($input['role_id'])) {
            $errors['role_id'] = 'Selecione um papel.';
        }

        if ($exceptId === null && strlen($input['password'] ?? '') < 8) {
            $errors['password'] = 'A senha precisa ter pelo menos 8 caracteres.';
        }

        return $errors;
    }
}
