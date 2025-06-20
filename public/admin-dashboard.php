<?php
session_start();

// Inclure la configuration admin
require_once 'admin-config.php';

// Configuration de base
$config = [
    'db_host' => 'localhost',
    'db_name' => 'parking_management_system',
    'db_user' => 'root',
    'db_pass' => ''
];

$error = null;
$dashboardData = [];

try {
    $pdo = new PDO("mysql:host={$config['db_host']};dbname={$config['db_name']}", $config['db_user'], $config['db_pass']);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Vérifier l'accès admin avec la nouvelle fonction
    checkAdminAccess(true);
    
    // Charger les données du tableau de bord
    $dashboardData = loadDashboardData($pdo);
    
} catch (Exception $e) {
    $error = "Erreur de connexion à la base de données: " . $e->getMessage();
    error_log("Erreur admin dashboard: " . $e->getMessage());
}

/**
 * Charge toutes les données nécessaires pour le tableau de bord
 */
function loadDashboardData($pdo) {
    $data = [];
    
    try {
        // Statistiques générales
        $data['stats'] = getDashboardStats($pdo);
        
        // Réservations récentes
        $data['recent_reservations'] = getRecentReservations($pdo);
        
        // Places de parking avec statut
        $data['parking_spots'] = getParkingSpotsStatus($pdo);
        
        // Revenus par période
        $data['revenue_data'] = getRevenueData($pdo);
        
        // Alertes et notifications
        $data['alerts'] = getSystemAlerts($pdo);
        
    } catch (Exception $e) {
        error_log("Erreur lors du chargement des données: " . $e->getMessage());
    }
    
    return $data;
}

/**
 * Récupère les statistiques principales
 */
function getDashboardStats($pdo) {
    $stats = [];
    
    // Total des réservations
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM reservations");
    $stats['total_reservations'] = $stmt->fetch()['total'];
    
    // Réservations actives (aujourd'hui)
    $stmt = $pdo->query("
        SELECT COUNT(*) as active 
        FROM reservations 
        WHERE DATE(start_datetime) <= CURDATE() 
        AND DATE(end_datetime) >= CURDATE() 
        AND status = 'confirmed'
    ");
    $stats['active_reservations'] = $stmt->fetch()['active'];
    
    // Revenus du mois
    $stmt = $pdo->query("
        SELECT COALESCE(SUM(total_amount), 0) as revenue 
        FROM reservations 
        WHERE MONTH(created_at) = MONTH(CURDATE()) 
        AND YEAR(created_at) = YEAR(CURDATE())
        AND payment_status = 'paid'
    ");
    $stats['monthly_revenue'] = $stmt->fetch()['revenue'];
    
    // Taux d'occupation
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM parking_spots WHERE is_active = 1");
    $totalSpots = $stmt->fetch()['total'];
    
    $stmt = $pdo->query("
        SELECT COUNT(DISTINCT spot_id) as occupied 
        FROM reservations 
        WHERE NOW() BETWEEN start_datetime AND end_datetime 
        AND status = 'confirmed'
    ");
    $occupiedSpots = $stmt->fetch()['occupied'];
    
    $stats['occupancy_rate'] = $totalSpots > 0 ? round(($occupiedSpots / $totalSpots) * 100, 1) : 0;
    
    // Évolution par rapport au mois précédent
    $stmt = $pdo->query("
        SELECT 
            COUNT(*) as last_month_reservations,
            COALESCE(SUM(total_amount), 0) as last_month_revenue
        FROM reservations 
        WHERE MONTH(created_at) = MONTH(DATE_SUB(CURDATE(), INTERVAL 1 MONTH))
        AND YEAR(created_at) = YEAR(DATE_SUB(CURDATE(), INTERVAL 1 MONTH))
    ");
    $lastMonth = $stmt->fetch();
    
    $stats['reservations_change'] = calculatePercentageChange(
        $lastMonth['last_month_reservations'], 
        $stats['total_reservations']
    );
    
    $stats['revenue_change'] = calculatePercentageChange(
        $lastMonth['last_month_revenue'], 
        $stats['monthly_revenue']
    );
    
    return $stats;
}

/**
 * Calcule le pourcentage de changement
 */
function calculatePercentageChange($oldValue, $newValue) {
    if ($oldValue == 0) return $newValue > 0 ? 100 : 0;
    return round((($newValue - $oldValue) / $oldValue) * 100, 1);
}

/**
 * Récupère les réservations récentes
 */
function getRecentReservations($pdo) {
    $stmt = $pdo->prepare("
        SELECT 
            r.reservation_id,
            r.reservation_code,
            r.start_datetime,
            r.end_datetime,
            r.total_amount,
            r.status,
            r.payment_status,
            r.created_at,
            u.first_name,
            u.last_name,
            u.email,
            ps.spot_number,
            ps.spot_type
        FROM reservations r
        JOIN users u ON r.user_id = u.user_id
        JOIN parking_spots ps ON r.spot_id = ps.spot_id
        ORDER BY r.created_at DESC
        LIMIT 10
    ");
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Récupère le statut des places de parking
 */
function getParkingSpotsStatus($pdo) {
    $stmt = $pdo->prepare("
        SELECT 
            ps.*,
            CASE 
                WHEN r.reservation_id IS NOT NULL THEN 'occupied'
                WHEN ps.status = 'maintenance' THEN 'maintenance'
                ELSE 'available'
            END as current_status,
            r.end_datetime as occupied_until
        FROM parking_spots ps
        LEFT JOIN reservations r ON ps.spot_id = r.spot_id 
            AND NOW() BETWEEN r.start_datetime AND r.end_datetime
            AND r.status = 'confirmed'
        WHERE ps.is_active = 1
        ORDER BY ps.zone_section, ps.spot_number
    ");
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Récupère les données de revenus
 */
function getRevenueData($pdo) {
    $stmt = $pdo->prepare("
        SELECT 
            DATE(created_at) as date,
            COUNT(*) as reservations,
            SUM(total_amount) as revenue
        FROM reservations 
        WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAYS)
        AND payment_status = 'paid'
        GROUP BY DATE(created_at)
        ORDER BY date DESC
    ");
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Récupère les alertes système
 */
function getSystemAlerts($pdo) {
    $alerts = [];
    
    // Vérifier les places en maintenance
    $stmt = $pdo->query("
        SELECT COUNT(*) as count 
        FROM parking_spots 
        WHERE status = 'maintenance' AND is_active = 1
    ");
    $maintenanceCount = $stmt->fetch()['count'];
    
    if ($maintenanceCount > 0) {
        $alerts[] = [
            'type' => 'warning',
            'message' => "$maintenanceCount place(s) en maintenance",
            'action' => 'Voir les détails',
            'link' => '#maintenance-spots'
        ];
    }
    
    // Vérifier les réservations en attente de paiement
    $stmt = $pdo->query("
        SELECT COUNT(*) as count 
        FROM reservations 
        WHERE payment_status = 'pending' 
        AND created_at < DATE_SUB(NOW(), INTERVAL 1 HOUR)
    ");
    $pendingPayments = $stmt->fetch()['count'];
    
    if ($pendingPayments > 0) {
        $alerts[] = [
            'type' => 'error',
            'message' => "$pendingPayments réservation(s) avec paiement en attente depuis plus d'1h",
            'action' => 'Gérer les paiements',
            'link' => '#pending-payments'
        ];
    }
    
    // Vérifier le taux d'occupation élevé
    $stats = getDashboardStats($pdo);
    if ($stats['occupancy_rate'] > 90) {
        $alerts[] = [
            'type' => 'info',
            'message' => "Taux d'occupation élevé: {$stats['occupancy_rate']}%",
            'action' => 'Voir l\'occupation',
            'link' => '#occupancy-details'
        ];
    }
    
    return $alerts;
}

/**
 * Formatte une date pour l'affichage
 */
function formatDate($date, $format = 'd/m/Y H:i') {
    return date($format, strtotime($date));
}

/**
 * Formatte un montant en euros
 */
function formatCurrency($amount) {
    return number_format($amount, 2, ',', ' ') . ' €';
}

/**
 * Obtient la classe CSS pour un statut
 */
function getStatusClass($status) {
    $classes = [
        'confirmed' => 'success',
        'pending' => 'warning',
        'cancelled' => 'error',
        'completed' => 'info',
        'paid' => 'success',
        'refunded' => 'warning'
    ];
    
    return $classes[$status] ?? 'info';
}

/**
 * Obtient le libellé d'un statut
 */
function getStatusLabel($status) {
    $labels = [
        'confirmed' => 'Confirmée',
        'pending' => 'En attente',
        'cancelled' => 'Annulée',
        'completed' => 'Terminée',
        'paid' => 'Payée',
        'pending' => 'En attente',
        'refunded' => 'Remboursée'
    ];
    
    return $labels[$status] ?? $status;
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tableau de bord - ParkFinder Admin</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Space+Grotesk:wght@400;500;600;700&display=swap" rel="stylesheet">    <!-- Styles CSS -->
    <link rel="stylesheet" href="assets/css/admin-dashboard.css">
</head>
<body>
    <div class="admin-layout">
        <!-- Sidebar -->
        <aside class="admin-sidebar">
            <div class="sidebar-logo">
                <div class="sidebar-logo-icon">P</div>
                <div>
                    <div class="sidebar-logo-text">ParkFinder</div>
                    <div class="sidebar-subtitle">Administration</div>
                </div>
            </div>
            
            <nav class="sidebar-nav">
                <div class="nav-section">
                    <div class="nav-section-title">Tableau de bord</div>
                    <ul>
                        <li class="nav-item">
                            <a href="admin-dashboard.php" class="nav-link active">
                                <span class="nav-icon">📊</span>
                                <span>Vue d'ensemble</span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="admin-analytics.php" class="nav-link">
                                <span class="nav-icon">📈</span>
                                <span>Analytiques</span>
                            </a>
                        </li>
                    </ul>
                </div>
                
                <div class="nav-section">
                    <div class="nav-section-title">Gestion</div>
                    <ul>
                        <li class="nav-item">
                            <a href="admin-reservations.php" class="nav-link">
                                <span class="nav-icon">🎫</span>
                                <span>Réservations</span>
                                <?php if (($dashboardData['stats']['active_reservations'] ?? 0) > 0): ?>
                                    <span class="nav-badge"><?= $dashboardData['stats']['active_reservations'] ?></span>
                                <?php endif; ?>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="admin-parking-spots.php" class="nav-link">
                                <span class="nav-icon">🅿️</span>
                                <span>Places de parking</span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="admin-users.php" class="nav-link">
                                <span class="nav-icon">👥</span>
                                <span>Utilisateurs</span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="admin-payments.php" class="nav-link">
                                <span class="nav-icon">💳</span>
                                <span>Paiements</span>
                            </a>
                        </li>
                    </ul>
                </div>
                
                <div class="nav-section">
                    <div class="nav-section-title">Configuration</div>
                    <ul>
                        <li class="nav-item">
                            <a href="admin-pricing.php" class="nav-link">
                                <span class="nav-icon">💰</span>
                                <span>Tarification</span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="admin-settings.php" class="nav-link">
                                <span class="nav-icon">⚙️</span>
                                <span>Paramètres</span>
                            </a>
                        </li>
                    </ul>
                </div>
                
                <div class="nav-section">
                    <div class="nav-section-title">Système</div>
                    <ul>
                        <li class="nav-item">
                            <a href="admin-logs.php" class="nav-link">
                                <span class="nav-icon">📋</span>
                                <span>Logs</span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="index.php" class="nav-link">
                                <span class="nav-icon">🏠</span>
                                <span>Retour au site</span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="logout.php" class="nav-link">
                                <span class="nav-icon">🚪</span>
                                <span>Déconnexion</span>
                            </a>
                        </li>
                    </ul>
                </div>
            </nav>
        </aside>

        <!-- Contenu principal -->
        <main class="admin-main">
            <?php if ($error): ?>
                <div class="status-message error">
                    <span>❌</span>
                    <span><?= htmlspecialchars($error) ?></span>
                </div>
            <?php endif; ?>

            <!-- Header -->
            <div class="admin-header">
                <div>
                    <h1 class="admin-title">Tableau de bord</h1>
                    <p class="admin-subtitle">
                        Vue d'ensemble de votre système de parking intelligent
                    </p>
                </div>                <div class="admin-actions">
                    <button class="btn btn-secondary" onclick="refreshDashboard()">
                        🔄 Actualiser
                    </button>
                    <button class="btn" data-modal="new-reservation-modal">
                        ➕ Nouvelle réservation
                    </button>
                </div>
            </div>

            <!-- Alertes système -->
            <?php if (!empty($dashboardData['alerts'])): ?>
                <div style="margin-bottom: 2rem;">
                    <?php foreach ($dashboardData['alerts'] as $alert): ?>
                        <div class="status-message <?= $alert['type'] ?>" style="margin-bottom: 1rem;">
                            <span><?= $alert['type'] === 'warning' ? '⚠️' : ($alert['type'] === 'error' ? '❌' : 'ℹ️') ?></span>
                            <span><?= htmlspecialchars($alert['message']) ?></span>
                            <a href="<?= $alert['link'] ?>" style="margin-left: auto; color: inherit; font-weight: 600;">
                                <?= $alert['action'] ?> →
                            </a>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <!-- Statistiques principales -->
            <div class="stats-grid">
                <div class="stat-card" id="total-reservations">
                    <div class="stat-header">
                        <div class="stat-icon">🎫</div>
                        <div class="stat-change positive">
                            <span>↗</span>
                            <span class="change-value"><?= abs($dashboardData['stats']['reservations_change'] ?? 0) ?>%</span>
                        </div>
                    </div>
                    <div class="stat-number" data-type="number"><?= $dashboardData['stats']['total_reservations'] ?? 0 ?></div>
                    <div class="stat-label">Total réservations</div>
                    <div class="stat-description">Ce mois-ci</div>
                </div>

                <div class="stat-card" id="active-reservations">
                    <div class="stat-header">
                        <div class="stat-icon">⏰</div>
                        <div class="stat-change positive">
                            <span>↗</span>
                            <span>En cours</span>
                        </div>
                    </div>
                    <div class="stat-number" data-type="number"><?= $dashboardData['stats']['active_reservations'] ?? 0 ?></div>
                    <div class="stat-label">Réservations actives</div>
                    <div class="stat-description">Actuellement</div>
                </div>

                <div class="stat-card" id="total-revenue">
                    <div class="stat-header">
                        <div class="stat-icon">💰</div>
                        <div class="stat-change <?= ($dashboardData['stats']['revenue_change'] ?? 0) >= 0 ? 'positive' : 'negative' ?>">
                            <span><?= ($dashboardData['stats']['revenue_change'] ?? 0) >= 0 ? '↗' : '↘' ?></span>
                            <span class="change-value"><?= abs($dashboardData['stats']['revenue_change'] ?? 0) ?>%</span>
                        </div>
                    </div>
                    <div class="stat-number" data-type="currency"><?= $dashboardData['stats']['monthly_revenue'] ?? 0 ?></div>
                    <div class="stat-label">Revenus du mois</div>
                    <div class="stat-description">Par rapport au mois dernier</div>
                </div>

                <div class="stat-card" id="occupancy-rate">
                    <div class="stat-header">
                        <div class="stat-icon">📊</div>
                        <div class="stat-change info">
                            <span>📈</span>
                            <span>Temps réel</span>
                        </div>
                    </div>
                    <div class="stat-number" data-type="percentage"><?= $dashboardData['stats']['occupancy_rate'] ?? 0 ?></div>
                    <div class="stat-label">Taux d'occupation</div>
                    <div class="stat-description">Maintenant</div>
                </div>
            </div>

            <!-- Grille de contenu principal -->
            <div class="admin-grid">
                <!-- Réservations récentes -->
                <div class="content-card">
                    <div class="card-header">
                        <h2 class="card-title">
                            📋 Réservations récentes
                        </h2>
                        <div class="card-actions">
                            <a href="admin-reservations.php" class="btn btn-sm btn-secondary">
                                Voir tout
                            </a>
                        </div>
                    </div>

                    <div class="table-responsive">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Code</th>
                                    <th>Client</th>
                                    <th>Place</th>
                                    <th>Début</th>
                                    <th>Montant</th>
                                    <th>Statut</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($dashboardData['recent_reservations'])): ?>
                                    <?php foreach ($dashboardData['recent_reservations'] as $reservation): ?>
                                        <tr>
                                            <td>
                                                <strong><?= htmlspecialchars($reservation['reservation_code']) ?></strong>
                                            </td>
                                            <td>
                                                <?= htmlspecialchars($reservation['first_name'] . ' ' . $reservation['last_name']) ?>
                                                <br>
                                                <small style="color: var(--gray-600);"><?= htmlspecialchars($reservation['email']) ?></small>
                                            </td>
                                            <td>
                                                <?= htmlspecialchars($reservation['spot_number']) ?>
                                                <br>
                                                <small style="color: var(--gray-600);"><?= ucfirst($reservation['spot_type']) ?></small>
                                            </td>
                                            <td>
                                                <?= formatDate($reservation['start_datetime']) ?>
                                            </td>
                                            <td>
                                                <strong><?= formatCurrency($reservation['total_amount']) ?></strong>
                                            </td>
                                            <td>
                                                <span class="status-badge <?= getStatusClass($reservation['status']) ?>">
                                                    <?= getStatusLabel($reservation['status']) ?>
                                                </span>
                                                <br>
                                                <span class="status-badge <?= getStatusClass($reservation['payment_status']) ?>" style="margin-top: 0.25rem;">
                                                    <?= getStatusLabel($reservation['payment_status']) ?>
                                                </span>
                                            </td>
                                            <td>
                                                <button class="btn btn-sm btn-secondary" 
                                                        onclick="handleCRUDAction('view', 'reservation', <?= $reservation['reservation_id'] ?>)"
                                                        data-tooltip="Voir les détails">
                                                    👁️
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="7" style="text-align: center; color: var(--gray-600); padding: 2rem;">
                                            Aucune réservation récente
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Sidebar droite -->
                <div style="display: flex; flex-direction: column; gap: 1.5rem;">
                    <!-- Occupation en temps réel -->
                    <div class="content-card">
                        <div class="card-header">
                            <h2 class="card-title">
                                🅿️ Occupation temps réel
                            </h2>
                        </div>

                        <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 0.5rem;">
                            <?php 
                            $spotCounts = ['available' => 0, 'occupied' => 0, 'maintenance' => 0];
                            if (!empty($dashboardData['parking_spots'])) {
                                foreach ($dashboardData['parking_spots'] as $spot) {
                                    $spotCounts[$spot['current_status']]++;
                                }
                            }
                            ?>
                            
                            <div style="text-align: center; padding: 1rem; background: var(--pale-green); border-radius: 0.5rem;">
                                <div style="font-size: 1.5rem; font-weight: bold; color: var(--primary-green);">
                                    <?= $spotCounts['available'] ?>
                                </div>
                                <div style="font-size: 0.75rem; color: var(--gray-600);">
                                    Libres
                                </div>
                            </div>
                            
                            <div style="text-align: center; padding: 1rem; background: #FEE2E2; border-radius: 0.5rem;">
                                <div style="font-size: 1.5rem; font-weight: bold; color: #DC2626;">
                                    <?= $spotCounts['occupied'] ?>
                                </div>
                                <div style="font-size: 0.75rem; color: var(--gray-600);">
                                    Occupées
                                </div>
                            </div>
                            
                            <div style="text-align: center; padding: 1rem; background: #FEF3C7; border-radius: 0.5rem;">
                                <div style="font-size: 1.5rem; font-weight: bold; color: #D97706;">
                                    <?= $spotCounts['maintenance'] ?>
                                </div>
                                <div style="font-size: 0.75rem; color: var(--gray-600);">
                                    Maintenance
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Actions rapides -->
                    <div class="content-card">
                        <div class="card-header">
                            <h2 class="card-title">
                                ⚡ Actions rapides
                            </h2>
                        </div>                        <div style="display: flex; flex-direction: column; gap: 0.75rem;">
                            <button class="btn btn-lg" data-modal="new-reservation-modal">
                                ➕ Nouvelle réservation
                            </button>
                            <button class="btn btn-lg btn-secondary" onclick="window.location.href='admin-parking-spots.php'">
                                🔧 Gérer les places
                            </button>
                            <button class="btn btn-lg btn-warning" onclick="window.location.href='admin-reports.php'">
                                📊 Générer un rapport
                            </button>
                        </div>
                    </div>

                    <!-- Dernière mise à jour -->
                    <div style="text-align: center; color: var(--gray-600); font-size: 0.875rem;">
                        <span id="last-refresh">Dernière mise à jour: <?= date('H:i:s') ?></span>
                    </div>
                </div>
            </div>
        </main>
    </div>    <!-- Scripts JavaScript -->
    <script src="assets/js/admin-dashboard.js"></script>
</body>
</html>