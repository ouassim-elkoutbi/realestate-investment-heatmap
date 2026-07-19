<?php

header('Content-Type: application/json');
require 'db.php';

$pdo = getConnection();
$stmt = $pdo->query('SELECT id, name, country, center_lat, center_lon FROM cities');
echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));