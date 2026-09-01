<?php
/**
 * Script standalone (roda 1x via SSH) -- baixa o dataset publico de municipios brasileiros
 * (kelvins/municipios-brasileiros, MIT) e popula a tabela br_cities. Nao faz parte do fluxo da
 * aplicacao, so carga inicial de dados de referencia.
 */
define('BASE_PATH', '/home/u719183319/domains/ecodiffusorebrasil.com.br');
require BASE_PATH . '/app/Core/Config.php';
require BASE_PATH . '/app/Core/Database.php';

use App\Core\Config;
use App\Core\Database;

Config::load();
$db = Database::connection();

function normalize(string $s): string
{
    $s = iconv('UTF-8', 'ASCII//TRANSLIT', $s) ?: $s;
    return mb_strtolower(trim($s));
}

$ch = curl_init('https://raw.githubusercontent.com/kelvins/municipios-brasileiros/main/csv/municipios.csv');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 60,
    CURLOPT_HTTPHEADER => ['User-Agent: EcodiffusoreBrasil-Import'],
]);
$csv = curl_exec($ch);
$error = curl_error($ch);
curl_close($ch);

if ($csv === false || $csv === '') {
    echo "ERRO ao baixar CSV: $error\n";
    exit(1);
}

$lines = str_getcsv($csv, "\n");
$header = str_getcsv(array_shift($lines));
// codigo_ibge,nome,latitude,longitude,capital,codigo_uf,siafi_id,ddd,fuso_horario
$ufByCode = [
    11 => 'RO', 12 => 'AC', 13 => 'AM', 14 => 'RR', 15 => 'PA', 16 => 'AP', 17 => 'TO',
    21 => 'MA', 22 => 'PI', 23 => 'CE', 24 => 'RN', 25 => 'PB', 26 => 'PE', 27 => 'AL', 28 => 'SE', 29 => 'BA',
    31 => 'MG', 32 => 'ES', 33 => 'RJ', 35 => 'SP',
    41 => 'PR', 42 => 'SC', 43 => 'RS',
    50 => 'MS', 51 => 'MT', 52 => 'GO', 53 => 'DF',
];

$db->exec('TRUNCATE TABLE br_cities');

$stmt = $db->prepare(
    'INSERT INTO br_cities (ibge_code, name, name_normalized, uf, lat, lng) VALUES (:ibge_code, :name, :name_normalized, :uf, :lat, :lng)'
);

$count = 0;
foreach ($lines as $line) {
    if (trim($line) === '') {
        continue;
    }
    $row = str_getcsv($line);
    $data = array_combine($header, $row);
    if (!$data) {
        continue;
    }

    $ufCode = (int) $data['codigo_uf'];
    $stmt->execute([
        'ibge_code' => (int) $data['codigo_ibge'],
        'name' => $data['nome'],
        'name_normalized' => normalize($data['nome']),
        'uf' => $ufByCode[$ufCode] ?? '??',
        'lat' => (float) $data['latitude'],
        'lng' => (float) $data['longitude'],
    ]);
    $count++;
}

echo "OK: $count municipios importados.\n";
