<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

// Configuration de base
$config = [
    'db_host' => 'localhost',
    'db_name' => 'parking_management_system',
    'db_user' => 'root',
    'db_pass' => ''
];

try {
    $pdo = new PDO("mysql:host={$config['db_host']};dbname={$config['db_name']}", $config['db_user'], $config['db_pass']);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Erreur de connexion à la base de données']);
    exit;
}

$query = $_GET['q'] ?? '';

if (strlen($query) < 1) {
    echo json_encode([]);
    exit;
}

try {
    $stmt = $pdo->prepare("
        SELECT spot_id, spot_number, spot_type, zone_section, floor_level, status
        FROM parking_spots 
        WHERE is_active = 1 
        AND (
            spot_number LIKE ? 
            OR zone_section LIKE ?
            OR CONCAT(zone_section, spot_number) LIKE ?
        )
        ORDER BY 
            CASE WHEN status = 'available' THEN 1 ELSE 2 END,
            zone_section, 
            spot_number
        LIMIT 15
    ");
    
    $searchParam = "%$query%";
    $stmt->execute([$searchParam, $searchParam, $searchParam]);
    $spots = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode($spots);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Erreur lors de la recherche']);
}
?>