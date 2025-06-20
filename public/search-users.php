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

if (strlen($query) < 2) {
    echo json_encode([]);
    exit;
}

try {
    $stmt = $pdo->prepare("
        SELECT user_id, first_name, last_name, email, phone 
        FROM users 
        WHERE is_active = 1 
        AND role = 'customer'
        AND (
            first_name LIKE ? 
            OR last_name LIKE ? 
            OR email LIKE ? 
            OR CONCAT(first_name, ' ', last_name) LIKE ?
        )
        ORDER BY first_name, last_name
        LIMIT 10
    ");
    
    $searchParam = "%$query%";
    $stmt->execute([$searchParam, $searchParam, $searchParam, $searchParam]);
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode($users);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Erreur lors de la recherche']);
}
?>