<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\BrazilMapData;
use App\Core\BrazilStates;
use App\Core\Database;
use App\Core\DateRange;
use App\Core\GeoMatch;
use App\Core\Response;
use App\Core\Roles;
use App\Core\View;
use App\Models\Commission;
use App\Models\Lead;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;

class PerformanceController
{
    public function sellers(): void
    {
        Auth::requireRole(Roles::MANAGEMENT);

        [$from, $to] = DateRange::fromRequest();

        View::render('painel/performance/sellers', [
            'user' => Auth::user(),
            'from' => $from,
            'to' => $to,
            'ranking' => Order::sellerRanking($from, $to),
            'topProducts' => OrderItem::topProducts($from, $to),
        ]);
    }

    public function team(): void
    {
        Auth::requireRole(array_merge(Roles::MANAGEMENT, Roles::NATIONAL_SUPPORT));
        $user = Auth::user();

        // Admin ve todas as regioes; Gerente/Supervisor veem as regioes sob sua rede nacional
        // (varios licenciados como raiz, igual admin); Licenciado/Gestor veem so a propria.
        if ($user['role_slug'] === 'admin') {
            $users = User::all();
            $roots = array_values(array_filter($users, fn ($u) => $u['role_slug'] === Roles::REGIONAL_OWNER));
        } elseif ($user['role_slug'] === 'gerente') {
            $scopeIds = User::nationalIds((int) $user['id']);
            $users = array_values(array_filter(User::all(), fn ($u) => in_array((int) $u['id'], $scopeIds, true)));
            $roots = array_values(array_filter($users, fn ($u) => $u['role_slug'] === Roles::REGIONAL_OWNER));
        } elseif ($user['role_slug'] === 'supervisor') {
            $scopeIds = User::supervisedIds((int) $user['id']);
            $users = array_values(array_filter(User::all(), fn ($u) => in_array((int) $u['id'], $scopeIds, true)));
            $roots = array_values(array_filter($users, fn ($u) => $u['role_slug'] === Roles::REGIONAL_OWNER));
        } else {
            $downline = User::downlineIds((int) $user['id']);
            $users = array_values(array_filter(User::all(), fn ($u) => in_array((int) $u['id'], $downline, true)));
            $roots = [User::find((int) $user['id'])];
        }

        $byManager = [];
        foreach ($users as $u) {
            $byManager[(int) ($u['manager_id'] ?? 0)][] = $u;
        }

        $totals = [];
        $beneficiaryIds = array_map(fn ($u) => (int) $u['id'], $users);
        foreach (Commission::byBeneficiary(['beneficiary_ids' => $beneficiaryIds]) as $row) {
            $totals[(int) $row['beneficiary_id']] = $row;
        }

        View::render('painel/performance/team', [
            'user' => $user,
            'roots' => $roots,
            'byManager' => $byManager,
            'totals' => $totals,
            'byState' => \App\Core\BrazilStates::groupByState(array_values(array_filter($roots, fn ($u) => $u['role_slug'] === Roles::REGIONAL_OWNER))),
        ]);
    }

    /**
     * Visao nacional (nao escopada por rede) pra admin/gerente/supervisor enxergarem onde a
     * Ecodiffusore ja tem Licenciado e, principalmente, onde ainda nao tem -- cruzando com a
     * cidade dos Leads recebidos pra sinalizar demanda em estado sem cobertura ainda.
     */
    public function panorama(): void
    {
        Auth::requireRole(['admin', 'gerente', 'supervisor']);
        $user = Auth::user();

        $ativos = User::allByRole('licenciado');
        $byState = BrazilStates::groupByState($ativos);

        $missingStates = [];
        foreach (BrazilStates::NAMES as $uf => $name) {
            if (empty($byState[$uf])) {
                $missingStates[$uf] = $name;
            }
        }
        asort($missingStates);

        // Sinal de demanda sem cobertura: em que UFs sem Licenciado ainda estao chegando Leads.
        $cityStateCache = [];
        $leadsByMissingState = [];
        foreach (Lead::all() as $lead) {
            if (empty($lead['city'])) {
                continue;
            }
            $city = $lead['city'];
            if (!array_key_exists($city, $cityStateCache)) {
                $cityStateCache[$city] = GeoMatch::stateForCity($city);
            }
            $uf = $cityStateCache[$city];
            if ($uf && isset($missingStates[$uf])) {
                $leadsByMissingState[$uf] = ($leadsByMissingState[$uf] ?? 0) + 1;
            }
        }
        arsort($leadsByMissingState);

        View::render('painel/performance/panorama', [
            'user' => $user,
            'byState' => $byState,
            'missingStates' => $missingStates,
            'leadsByMissingState' => $leadsByMissingState,
            'totalLicenciados' => count($ativos),
            'estadosCobertos' => count($byState),
            'totalEstados' => count(BrazilStates::NAMES),
        ]);
    }

    /**
     * Dados de um estado especifico pro drill-down clicavel do mapa: a equipe presente la
     * (separada por regiao -- cada Licenciado com seu Supervisor e seu time de Gestor/Vendedor)
     * e as cidades do estado (via br_cities) marcando quais tem Licenciado, pro mini-mapa por
     * coordenada real (sem depender de contorno geografico desenhado a mao).
     */
    public function stateDetail(string $uf): void
    {
        Auth::requireRole(['admin', 'gerente', 'supervisor']);
        $uf = strtoupper($uf);

        if (!isset(BrazilStates::NAMES[$uf])) {
            http_response_code(404);
            Response::json(['error' => 'Estado invalido']);
            return;
        }

        $licenciados = array_values(array_filter(
            User::allByRole('licenciado'),
            fn ($u) => strtoupper(trim($u['state'] ?? '')) === $uf
        ));

        $regions = [];
        foreach ($licenciados as $lic) {
            $supervisor = !empty($lic['supervisor_id']) ? User::find((int) $lic['supervisor_id']) : null;

            $teamIds = array_values(array_diff(User::downlineIds((int) $lic['id']), [(int) $lic['id']]));
            $team = array_values(array_filter(
                array_map(fn ($id) => User::find($id), $teamIds),
                fn ($u) => $u && in_array($u['role_slug'], ['gestor', 'vendedor'], true)
            ));

            $regions[] = [
                'licenciado' => ['id' => (int) $lic['id'], 'name' => $lic['name'], 'city' => $lic['city'], 'whatsapp' => $lic['whatsapp']],
                'supervisor' => $supervisor ? ['id' => (int) $supervisor['id'], 'name' => $supervisor['name'], 'whatsapp' => $supervisor['whatsapp']] : null,
                'team' => array_map(fn ($u) => [
                    'id' => (int) $u['id'],
                    'name' => $u['name'],
                    'role' => $u['role_slug'],
                    'whatsapp' => $u['whatsapp'],
                ], $team),
            ];
        }

        $stmt = Database::connection()->prepare('SELECT name, lat, lng, name_normalized FROM br_cities WHERE uf = :uf');
        $stmt->execute(['uf' => $uf]);
        $allCities = $stmt->fetchAll();

        $licenciadoCityKeys = array_map(fn ($lic) => GeoMatch::normalize($lic['city'] ?? ''), $licenciados);

        $cities = array_map(fn ($c) => [
            'name' => $c['name'],
            'lat' => (float) $c['lat'],
            'lng' => (float) $c['lng'],
            'has_licenciado' => in_array($c['name_normalized'], $licenciadoCityKeys, true),
        ], $allCities);

        $statePath = BrazilMapData::STATE_PATHS[$uf] ?? null;

        Response::json([
            'uf' => $uf,
            'state_name' => BrazilStates::NAMES[$uf],
            'regions' => $regions,
            'cities' => $cities,
            'viewbox' => BrazilMapData::STATE_VIEWBOX,
            'path' => $statePath,
        ]);
    }
}
