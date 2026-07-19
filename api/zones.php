<?php

header('Content-Type: application/json');
require 'db.php';

$cityId = (int) ($_GET['city_id'] ?? 1);
$metric = $_GET['metric'] ?? 'sale_price';

$pdo = getConnection();

if ($metric === 'sale_price') {
    $sql = "SELECT z.id, z.name, z.lat, z.lon, p.price_per_sqm AS value
            FROM zones z
            JOIN prices p ON p.zone_id = z.id
            WHERE z.city_id = ? AND p.type = 'sale' AND p.date = '2022-01-01'"; 

} elseif ($metric === 'yield') {
    $sql = "SELECT z.id, z.name, z.lat, z.lon,
                   CAST(rent.price_per_sqm * 12 / sale.price_per_sqm * 100 AS DECIMAL(10,2)) AS value
            FROM zones z
            JOIN prices sale ON sale.zone_id = z.id AND sale.type = 'sale' AND sale.date = '2022-01-01'
            JOIN prices rent ON rent.zone_id = z.id AND rent.type = 'rent' AND rent.date = '2022-01-01'
            WHERE z.city_id = ?";

} elseif ($metric === 'growth') {
    $sql = "SELECT z.id, z.name, z.lat, z.lon,
                   CAST((POW(p22.price_per_sqm / p10.price_per_sqm, 1/12) - 1) * 100 AS DECIMAL(10,2)) AS value
            FROM zones z
            JOIN prices p10 ON p10.zone_id = z.id AND p10.type = 'sale' AND p10.date = '2010-01-01'
            JOIN prices p22 ON p22.zone_id = z.id AND p22.type = 'sale' AND p22.date = '2022-01-01'
            WHERE z.city_id = ?";

} elseif ($metric === 'investment_score') {
    $sql = "SELECT z.id, z.name, z.lat, z.lon,
                   CAST(rent.price_per_sqm * 12 / sale22.price_per_sqm * 100 AS DECIMAL(10,2)) AS yield_pct,
                   CAST((POW(sale22.price_per_sqm / sale10.price_per_sqm, 1/12) - 1) * 100 AS DECIMAL(10,2)) AS growth_pct
            FROM zones z
            JOIN prices sale22 ON sale22.zone_id = z.id AND sale22.type = 'sale' AND sale22.date = '2022-01-01'
            JOIN prices sale10 ON sale10.zone_id = z.id AND sale10.type = 'sale' AND sale10.date = '2010-01-01'
            JOIN prices rent   ON rent.zone_id = z.id AND rent.type = 'rent' AND rent.date = '2022-01-01'
            WHERE z.city_id = ?";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$cityId]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Normalizar yield y growth a 0-1 y combinarlos
    $yields  = array_column($rows, 'yield_pct');
    $growths = array_column($rows, 'growth_pct');
    $yMin = min($yields);  $yMax = max($yields);
    $gMin = min($growths); $gMax = max($growths);

    $result = [];
    foreach ($rows as $r) {
        $yNorm = ($r['yield_pct'] - $yMin) / ($yMax - $yMin ?: 1);
        $gNorm = ($r['growth_pct'] - $gMin) / ($gMax - $gMin ?: 1);
        $result[] = [
            'id'    => $r['id'],
            'name'  => $r['name'],
            'lat'   => $r['lat'],
            'lon'   => $r['lon'],
            'value' => round(($yNorm * 0.5 + $gNorm * 0.5) * 100, 1)
        ];
    }
    echo json_encode($result);
    exit;

} else {  http_response_code(400);
    echo json_encode(['error' => 'Invalid metric']);
    exit;
}

$stmt = $pdo->prepare($sql);
$stmt->execute([$cityId]);
echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));