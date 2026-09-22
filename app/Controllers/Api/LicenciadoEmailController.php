<?php

namespace App\Controllers\Api;

use App\Core\ApiAuth;
use App\Core\ApiResponse;
use App\Core\Notifier;
use App\Core\SubscriptionGate;
use App\Models\CompanySettings;
use App\Models\LicenciadoEmailRequest;

/** E-mail profissional pro app (Fase 85) -- mesma logica de App\Controllers\
 *  LicenciadoEmailController::index()/store(). Sem tela de compra de assinatura no app ainda
 *  (so no painel web); quando bloqueado por SubscriptionGate, devolve subscribe_url pro app abrir
 *  no navegador do celular. */
class LicenciadoEmailController
{
    public function index(): void
    {
        $user = ApiAuth::requireUser();
        if ($user['role_slug'] !== 'licenciado') {
            ApiResponse::error('So Licenciado pode pedir e-mail profissional.', 403);
        }

        $settings = CompanySettings::current();
        $quota = (int) ($settings['custom_email_quota'] ?? 0);
        $used = LicenciadoEmailRequest::countTowardQuota();

        ApiResponse::json([
            'has_access' => SubscriptionGate::hasAccess($user),
            'subscribe_url' => 'https://ecodiffusorebrasil.com.br/painel/assinatura',
            'domain' => LicenciadoEmailRequest::DOMAIN,
            'quota' => $quota,
            'used' => $used,
            'available' => max(0, $quota - $used),
            'requests' => array_map(fn ($r) => $this->publicRequest($r), LicenciadoEmailRequest::forUser((int) $user['id'])),
        ]);
    }

    public function store(): void
    {
        $user = ApiAuth::requireUser();
        if ($user['role_slug'] !== 'licenciado') {
            ApiResponse::error('So Licenciado pode pedir e-mail profissional.', 403);
        }
        if (!SubscriptionGate::hasAccess($user)) {
            ApiResponse::error('Disponivel so pra quem tem assinatura ativa.', 403);
        }

        $body = json_decode(file_get_contents('php://input'), true) ?: [];
        $localPart = strtolower(trim($body['local_part'] ?? ''));
        $settings = CompanySettings::current();
        $quota = (int) ($settings['custom_email_quota'] ?? 0);

        if ($localPart === '' || !preg_match('/^[a-z0-9._-]{2,60}$/', $localPart)) {
            ApiResponse::json(['errors' => ['local_part' => 'Use so letras minusculas, numeros, ponto, hifen ou underline.']], 422);
        }
        if (LicenciadoEmailRequest::addressTaken($localPart . '@' . LicenciadoEmailRequest::DOMAIN)) {
            ApiResponse::json(['errors' => ['local_part' => 'Esse endereco ja esta em uso ou tem um pedido em andamento.']], 422);
        }
        if (LicenciadoEmailRequest::countTowardQuota() >= $quota) {
            ApiResponse::json(['errors' => ['local_part' => 'Nao ha vagas disponiveis no momento -- fale com o Admin.']], 422);
        }

        $id = LicenciadoEmailRequest::create((int) $user['id'], $localPart);
        Notifier::emailProfissionalSolicitado(LicenciadoEmailRequest::find($id));

        ApiResponse::json(['request' => $this->publicRequest(LicenciadoEmailRequest::find($id))], 201);
    }

    private function publicRequest(array $r): array
    {
        return [
            'id' => (int) $r['id'],
            'full_address' => $r['full_address'],
            'status' => $r['status'],
            'admin_note' => $r['admin_note'],
            'created_at' => $r['created_at'],
        ];
    }
}
