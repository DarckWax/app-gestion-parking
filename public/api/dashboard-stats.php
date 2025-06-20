<?php
header('Content-Type: application/json');
session_start();

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
    
    // Récupérer les statistiques
    $stats = [
        'total_reservations' => 0,
        'active_reservations' => 0,
        'monthly_revenue' => 0,
        'occupancy_rate' => 0
    ];
    
    // Compter les réservations
    try {
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM reservations");
        $stats['total_reservations'] = $stmt->fetchColumn();
        
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM reservations WHERE status = 'active'");
        $stats['active_reservations'] = $stmt->fetchColumn();
        
        // Revenus du mois (simulation)
        $stats['monthly_revenue'] = $stats['total_reservations'] * 5.50;
        
        // Taux d'occupation (simulation)
        $stats['occupancy_rate'] = min(100, ($stats['active_reservations'] / max(1, 10)) * 100);
        
    } catch (Exception $e) {
        // Garder les valeurs par défaut
    }
    
    echo json_encode([
        'success' => true,
        'data' => $stats
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => 'Erreur de connexion à la base de données'
    ]);
}