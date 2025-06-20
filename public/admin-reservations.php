<?php
session_start();

// Inclure le middleware admin
require_once '../app/middlewares/AdminMiddleware.php';
use App\Middlewares\AdminMiddleware;

// Vérifier les droits admin
AdminMiddleware::requireAdmin();

// Configuration de base
$config = [
    'db_host' => 'localhost',
    'db_name' => 'parking_management_system',
    'db_user' => 'root',
    'db_pass' => ''
];

$message = '';
$messageType = '';

try {
    $pdo = new PDO("mysql:host={$config['db_host']};dbname={$config['db_name']}", $config['db_user'], $config['db_pass']);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (Exception $e) {
    die("Erreur de connexion à la base de données: " . $e->getMessage());
}

// Gestion des actions CRUD
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'update_status':
            $reservation_id = intval($_POST['reservation_id'] ?? 0);
            $new_status = $_POST['new_status'] ?? '';
            $cancellation_reason = trim($_POST['cancellation_reason'] ?? '');
            
            if ($reservation_id > 0 && !empty($new_status)) {
                try {
                    $update_data = [$new_status];
                    $sql = "UPDATE reservations SET status = ?, updated_at = NOW()";
                    
                    if ($new_status === 'cancelled' && !empty($cancellation_reason)) {
                        $sql .= ", cancellation_reason = ?";
                        $update_data[] = $cancellation_reason;
                    }
                    
                    $sql .= " WHERE reservation_id = ?";
                    $update_data[] = $reservation_id;
                    
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute($update_data);
                    
                    // Mettre à jour le statut de la place si nécessaire
                    if ($new_status === 'active') {
                        $stmt = $pdo->prepare("UPDATE parking_spots SET status = 'occupied' WHERE spot_id = (SELECT spot_id FROM reservations WHERE reservation_id = ?)");
                        $stmt->execute([$reservation_id]);
                    } elseif ($new_status === 'completed' || $new_status === 'cancelled') {
                        $stmt = $pdo->prepare("UPDATE parking_spots SET status = 'available' WHERE spot_id = (SELECT spot_id FROM reservations WHERE reservation_id = ?)");
                        $stmt->execute([$reservation_id]);
                    }
                    
                    $message = "Statut de la réservation mis à jour avec succès.";
                    $messageType = 'success';
                } catch (Exception $e) {
                    $message = "Erreur lors de la mise à jour: " . $e->getMessage();
                    $messageType = 'error';
                }
            } else {
                $message = "Données invalides pour la mise à jour.";
                $messageType = 'error';
            }
            break;
            
        case 'create':
            $user_id = intval($_POST['user_id'] ?? 0);
            $spot_id = intval($_POST['spot_id'] ?? 0);
            $start_datetime = $_POST['start_datetime'] ?? '';
            $end_datetime = $_POST['end_datetime'] ?? '';
            $total_amount = floatval($_POST['total_amount'] ?? 0);
            $vehicle_plate = trim($_POST['vehicle_plate'] ?? '');
            $vehicle_model = trim($_POST['vehicle_model'] ?? '');
            $special_requests = trim($_POST['special_requests'] ?? '');
            
            if ($user_id > 0 && $spot_id > 0 && !empty($start_datetime) && !empty($end_datetime) && $total_amount > 0) {
                try {
                    // Générer un code de réservation unique
                    $reservation_code = 'PF' . date('Y') . str_pad(mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);
                    
                    // Vérifier que la place est disponible
                    $stmt = $pdo->prepare("
                        SELECT COUNT(*) FROM reservations 
                        WHERE spot_id = ? 
                        AND status IN ('confirmed', 'active') 
                        AND ((start_datetime <= ? AND end_datetime > ?) OR (start_datetime < ? AND end_datetime >= ?))
                    ");
                    $stmt->execute([$spot_id, $start_datetime, $start_datetime, $end_datetime, $end_datetime]);
                    
                    if ($stmt->fetchColumn() > 0) {
                        $message = "La place est déjà réservée pour cette période.";
                        $messageType = 'error';
                    } else {
                        $stmt = $pdo->prepare("
                            INSERT INTO reservations (user_id, spot_id, reservation_code, start_datetime, end_datetime, total_amount, vehicle_plate, vehicle_model, special_requests, status, created_at) 
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'confirmed', NOW())
                        ");
                        $stmt->execute([$user_id, $spot_id, $reservation_code, $start_datetime, $end_datetime, $total_amount, $vehicle_plate, $vehicle_model, $special_requests]);
                        
                        $message = "Réservation créée avec succès (Code: $reservation_code).";
                        $messageType = 'success';
                    }
                } catch (Exception $e) {
                    $message = "Erreur lors de la création: " . $e->getMessage();
                    $messageType = 'error';
                }
            } else {
                $message = "Veuillez remplir tous les champs obligatoires.";
                $messageType = 'error';
            }
            break;
            
        case 'delete':
            $reservation_id = intval($_POST['reservation_id'] ?? 0);
            if ($reservation_id > 0) {
                try {
                    // Récupérer les infos de la réservation
                    $stmt = $pdo->prepare("SELECT spot_id, status FROM reservations WHERE reservation_id = ?");
                    $stmt->execute([$reservation_id]);
                    $reservation = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    if ($reservation) {
                        $stmt = $pdo->prepare("DELETE FROM reservations WHERE reservation_id = ?");
                        $stmt->execute([$reservation_id]);
                        
                        // Libérer la place si elle était occupée
                        if ($reservation['status'] === 'active') {
                            $stmt = $pdo->prepare("UPDATE parking_spots SET status = 'available' WHERE spot_id = ?");
                            $stmt->execute([$reservation['spot_id']]);
                        }
                        
                        $message = "Réservation supprimée avec succès.";
                        $messageType = 'success';
                    }
                } catch (Exception $e) {
                    $message = "Erreur lors de la suppression: " . $e->getMessage();
                    $messageType = 'error';
                }
            }
            break;
    }
}

// Gestion du tri et de la recherche
$search = $_GET['search'] ?? '';
$sort_field = $_GET['sort'] ?? 'created_at';
$sort_order = $_GET['order'] ?? 'DESC';
$status_filter = $_GET['status'] ?? '';
$payment_status_filter = $_GET['payment_status'] ?? '';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';
$page = max(1, intval($_GET['page'] ?? 1));
$per_page = 20;
$offset = ($page - 1) * $per_page;

// Champs autorisés pour le tri
$allowed_sort_fields = ['reservation_code', 'start_datetime', 'end_datetime', 'total_amount', 'status', 'payment_status', 'created_at'];
if (!in_array($sort_field, $allowed_sort_fields)) {
    $sort_field = 'created_at';
}

$sort_order = strtoupper($sort_order) === 'DESC' ? 'DESC' : 'ASC';

// Construction de la requête
$where_conditions = ['1=1'];
$params = [];

if (!empty($search)) {
    $where_conditions[] = "(r.reservation_code LIKE ? OR u.email LIKE ? OR CONCAT(u.first_name, ' ', u.last_name) LIKE ? OR r.vehicle_plate LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
}

if (!empty($status_filter)) {
    $where_conditions[] = "r.status = ?";
    $params[] = $status_filter;
}

if (!empty($payment_status_filter)) {
    $where_conditions[] = "r.payment_status = ?";
    $params[] = $payment_status_filter;
}

if (!empty($date_from)) {
    $where_conditions[] = "DATE(r.start_datetime) >= ?";
    $params[] = $date_from;
}

if (!empty($date_to)) {
    $where_conditions[] = "DATE(r.end_datetime) <= ?";
    $params[] = $date_to;
}

$where_clause = implode(' AND ', $where_conditions);

// Récupération des données
try {
    // Compter le total
    $count_query = "
        SELECT COUNT(*) 
        FROM reservations r
        JOIN users u ON r.user_id = u.user_id
        JOIN parking_spots ps ON r.spot_id = ps.spot_id
        WHERE $where_clause
    ";
    $stmt = $pdo->prepare($count_query);
    $stmt->execute($params);
    $total_records = $stmt->fetchColumn();
    $total_pages = ceil($total_records / $per_page);
    
    // Récupérer les données paginées
    $query = "
        SELECT r.*, 
               u.first_name, u.last_name, u.email, u.phone,
               ps.spot_number, ps.spot_type, ps.zone_section, ps.floor_level,
               p.amount as payment_amount, p.status as payment_status_detail
        FROM reservations r
        JOIN users u ON r.user_id = u.user_id
        JOIN parking_spots ps ON r.spot_id = ps.spot_id
        LEFT JOIN payments p ON r.reservation_id = p.reservation_id
        WHERE $where_clause
        ORDER BY r.$sort_field $sort_order
        LIMIT $per_page OFFSET $offset
    ";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $reservations = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Statistiques générales
    $stats_query = "
        SELECT 
            COUNT(*) as total_reservations,
            SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_reservations,
            SUM(CASE WHEN status = 'confirmed' THEN 1 ELSE 0 END) as confirmed_reservations,
            SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active_reservations,
            SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_reservations,
            SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled_reservations,
            SUM(CASE WHEN status = 'no_show' THEN 1 ELSE 0 END) as no_show_reservations,
            SUM(CASE WHEN payment_status = 'pending' THEN 1 ELSE 0 END) as unpaid_reservations,
            SUM(total_amount) as total_revenue,
            AVG(total_amount) as avg_reservation_amount
        FROM reservations
    ";
    $stmt = $pdo->query($stats_query);
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    $message = "Erreur lors de la récupération des données: " . $e->getMessage();
    $messageType = 'error';
    $reservations = [];
    $stats = [];
}

// Fonctions utilitaires
function getSortLink($field, $current_field, $current_order, $search, $status_filter, $payment_status_filter, $date_from, $date_to) {
    $new_order = ($field === $current_field && $current_order === 'ASC') ? 'DESC' : 'ASC';
    $params = [
        'sort' => $field,
        'order' => $new_order,
        'search' => $search,
        'status' => $status_filter,
        'payment_status' => $payment_status_filter,
        'date_from' => $date_from,
        'date_to' => $date_to
    ];
    return 'admin-reservations.php?' . http_build_query(array_filter($params));
}

function getSortIcon($field, $current_field, $current_order) {
    if ($field !== $current_field) return '↕️';
    return $current_order === 'ASC' ? '↑' : '↓';
}

function formatDate($date) {
    return $date ? date('d/m/Y H:i', strtotime($date)) : 'N/A';
}

function formatCurrency($amount) {
    return number_format($amount, 2, ',', ' ') . ' €';
}

function getStatusBadgeClass($status) {
    $classes = [
        'pending' => 'status-pending',
        'confirmed' => 'status-confirmed',
        'active' => 'status-active',
        'completed' => 'status-completed',
        'cancelled' => 'status-cancelled',
        'no_show' => 'status-no-show'
    ];
    return $classes[$status] ?? 'status-unknown';
}

function getStatusLabel($status) {
    $labels = [
        'pending' => 'En attente',
        'confirmed' => 'Confirmée',
        'active' => 'Active',
        'completed' => 'Terminée',
        'cancelled' => 'Annulée',
        'no_show' => 'Absent'
    ];
    return $labels[$status] ?? ucfirst($status);
}

function getPaymentStatusBadgeClass($status) {
    $classes = [
        'pending' => 'payment-pending',
        'paid' => 'payment-paid',
        'refunded' => 'payment-refunded',
        'failed' => 'payment-failed'
    ];
    return $classes[$status] ?? 'payment-unknown';
}

function getPaymentStatusLabel($status) {
    $labels = [
        'pending' => 'En attente',
        'paid' => 'Payé',
        'refunded' => 'Remboursé',
        'failed' => 'Échoué'
    ];
    return $labels[$status] ?? ucfirst($status);
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des Réservations - ParkFinder Admin</title>
      <!-- Styles CSS -->
    <link rel="stylesheet" href="assets/css/admin-dashboard.css">
    <link rel="stylesheet" href="assets/css/admin-reservations.css">
</head>
<body>
    <div class="admin-layout">
        <!-- Sidebar -->
        <aside class="admin-sidebar">
            <div class="sidebar-header">
                <div class="logo">
                    <div class="logo-icon">P</div>
                    <span class="logo-text">ParkFinder</span>
                </div>
                <div class="admin-badge">ADMINISTRATION</div>
            </div>
            
            <nav class="sidebar-nav">
                <div class="nav-section">
                    <div class="nav-title">TABLEAU DE BORD</div>
                    <a href="admin-dashboard.php" class="nav-item">
                        <span class="nav-icon">📊</span>
                        <span class="nav-text">Vue d'ensemble</span>
                    </a>
                    <a href="admin-analytics.php" class="nav-item">
                        <span class="nav-icon">📈</span>
                        <span class="nav-text">Analytiques</span>
                    </a>
                </div>
                
                <div class="nav-section">
                    <div class="nav-title">GESTION</div>
                    <a href="admin-reservations.php" class="nav-item active">
                        <span class="nav-icon">📅</span>
                        <span class="nav-text">Réservations</span>
                    </a>
                    <a href="admin-parking-spots.php" class="nav-item">
                        <span class="nav-icon">🅿️</span>
                        <span class="nav-text">Places de parking</span>
                    </a>
                    <a href="admin-users.php" class="nav-item">
                        <span class="nav-icon">👥</span>
                        <span class="nav-text">Utilisateurs</span>
                    </a>
                    <a href="admin-payments.php" class="nav-item">
                        <span class="nav-icon">💳</span>
                        <span class="nav-text">Paiements</span>
                    </a>
                </div>
                
                <div class="nav-section">
                    <div class="nav-title">CONFIGURATION</div>
                    <a href="admin-tarification.php" class="nav-item">
                        <span class="nav-icon">🔥</span>
                        <span class="nav-text">Tarification</span>
                    </a>
                    <a href="admin-settings.php" class="nav-item">
                        <span class="nav-icon">⚙️</span>
                        <span class="nav-text">Paramètres</span>
                    </a>
                </div>
                
                <div class="nav-section">
                    <div class="nav-title">SYSTÈME</div>
                    <a href="admin-logs.php" class="nav-item">
                        <span class="nav-icon">📋</span>
                        <span class="nav-text">Logs</span>
                    </a>
                </div>
                
                <div class="nav-section nav-footer">
                    <a href="../index.php" class="nav-item nav-return">
                        <span class="nav-icon">🏠</span>
                        <span class="nav-text">Retour au site</span>
                    </a>
                    <a href="../logout.php" class="nav-item nav-logout">
                        <span class="nav-icon">🚪</span>
                        <span class="nav-text">Déconnexion</span>
                    </a>
                </div>
            </nav>
        </aside>

        <!-- Main Content -->
        <main class="admin-main">
            <div class="admin-header">
                <h1>Gestion des Réservations</h1>
                <div class="admin-actions">
                    <button class="btn btn-secondary" onclick="location.reload()">
                        🔄 Actualiser
                    </button>
                    <button class="btn" onclick="openModal('createModal')">
                        ➕ Nouvelle réservation
                    </button>
                </div>
            </div>

            <?php if ($message): ?>
                <div class="alert alert-<?= $messageType ?>">
                    <?= htmlspecialchars($message) ?>
                </div>
            <?php endif; ?>

            <!-- Statistiques -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-number"><?= $stats['total_reservations'] ?? 0 ?></div>
                    <div class="stat-label">Total réservations</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?= $stats['active_reservations'] ?? 0 ?></div>
                    <div class="stat-label">Réservations actives</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?= $stats['confirmed_reservations'] ?? 0 ?></div>
                    <div class="stat-label">Confirmées</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?= $stats['completed_reservations'] ?? 0 ?></div>
                    <div class="stat-label">Terminées</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?= formatCurrency($stats['total_revenue'] ?? 0) ?></div>
                    <div class="stat-label">Revenus totaux</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?= $stats['unpaid_reservations'] ?? 0 ?></div>
                    <div class="stat-label">Non payées</div>
                </div>
            </div>

            <!-- Filtres et recherche -->
            <div class="filters-section">
                <form method="GET" class="filters-grid">
                    <div class="form-group">
                        <label for="search">Rechercher</label>
                        <input type="text" id="search" name="search" value="<?= htmlspecialchars($search) ?>" 
                               placeholder="Code, email, nom, plaque...">
                    </div>
                    <div class="form-group">
                        <label for="status">Statut</label>
                        <select id="status" name="status">
                            <option value="">Tous les statuts</option>
                            <option value="pending" <?= $status_filter === 'pending' ? 'selected' : '' ?>>En attente</option>
                            <option value="confirmed" <?= $status_filter === 'confirmed' ? 'selected' : '' ?>>Confirmée</option>
                            <option value="active" <?= $status_filter === 'active' ? 'selected' : '' ?>>Active</option>
                            <option value="completed" <?= $status_filter === 'completed' ? 'selected' : '' ?>>Terminée</option>
                            <option value="cancelled" <?= $status_filter === 'cancelled' ? 'selected' : '' ?>>Annulée</option>
                            <option value="no_show" <?= $status_filter === 'no_show' ? 'selected' : '' ?>>Absent</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="payment_status">Paiement</label>
                        <select id="payment_status" name="payment_status">
                            <option value="">Tous les paiements</option>
                            <option value="pending" <?= $payment_status_filter === 'pending' ? 'selected' : '' ?>>En attente</option>
                            <option value="paid" <?= $payment_status_filter === 'paid' ? 'selected' : '' ?>>Payé</option>
                            <option value="refunded" <?= $payment_status_filter === 'refunded' ? 'selected' : '' ?>>Remboursé</option>
                            <option value="failed" <?= $payment_status_filter === 'failed' ? 'selected' : '' ?>>Échoué</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="date_from">Du</label>
                        <input type="date" id="date_from" name="date_from" value="<?= htmlspecialchars($date_from) ?>">
                    </div>
                    <div class="form-group">
                        <label for="date_to">Au</label>
                        <input type="date" id="date_to" name="date_to" value="<?= htmlspecialchars($date_to) ?>">
                    </div>
                    <div class="form-group">
                        <button type="submit" class="btn">🔍 Filtrer</button>
                        <a href="admin-reservations.php" class="btn btn-secondary">Réinitialiser</a>
                    </div>
                </form>
            </div>

            <!-- Tableau des réservations -->
            <div class="table-container">
                <div class="table-header">
                    <h3>Réservations (<?= $total_records ?> résultats)</h3>
                </div>
                
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>
                                <a href="<?= getSortLink('reservation_code', $sort_field, $sort_order, $search, $status_filter, $payment_status_filter, $date_from, $date_to) ?>" class="sort-header">
                                    Code <?= getSortIcon('reservation_code', $sort_field, $sort_order) ?>
                                </a>
                            </th>
                            <th>Client</th>
                            <th>Place</th>
                            <th>
                                <a href="<?= getSortLink('start_datetime', $sort_field, $sort_order, $search, $status_filter, $payment_status_filter, $date_from, $date_to) ?>" class="sort-header">
                                    Période <?= getSortIcon('start_datetime', $sort_field, $sort_order) ?>
                                </a>
                            </th>
                            <th>
                                <a href="<?= getSortLink('total_amount', $sort_field, $sort_order, $search, $status_filter, $payment_status_filter, $date_from, $date_to) ?>" class="sort-header">
                                    Montant <?= getSortIcon('total_amount', $sort_field, $sort_order) ?>
                                </a>
                            </th>
                            <th>
                                <a href="<?= getSortLink('status', $sort_field, $sort_order, $search, $status_filter, $payment_status_filter, $date_from, $date_to) ?>" class="sort-header">
                                    Statut <?= getSortIcon('status', $sort_field, $sort_order) ?>
                                </a>
                            </th>
                            <th>Paiement</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($reservations)): ?>
                            <tr>
                                <td colspan="8" style="text-align: center; padding: 2rem; color: var(--text-muted);">
                                    Aucune réservation trouvée
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($reservations as $reservation): ?>
                                <tr>
                                    <td>
                                        <strong><?= htmlspecialchars($reservation['reservation_code']) ?></strong>
                                        <br>
                                        <small>ID: <?= $reservation['reservation_id'] ?></small>
                                    </td>
                                    <td>
                                        <?= htmlspecialchars($reservation['first_name'] . ' ' . $reservation['last_name']) ?>
                                        <br>
                                        <small style="color: var(--text-muted);"><?= htmlspecialchars($reservation['email']) ?></small>
                                        <?php if ($reservation['vehicle_plate']): ?>
                                            <br>
                                            <small>🚗 <?= htmlspecialchars($reservation['vehicle_plate']) ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <strong>Place <?= htmlspecialchars($reservation['spot_number']) ?></strong>
                                        <br>
                                        <small><?= htmlspecialchars($reservation['zone_section']) ?> - Étage <?= $reservation['floor_level'] ?></small>
                                        <br>
                                        <span class="type-badge type-<?= $reservation['spot_type'] ?>"><?= ucfirst($reservation['spot_type']) ?></span>
                                    </td>
                                    <td>
                                        <strong>Du:</strong> <?= formatDate($reservation['start_datetime']) ?>
                                        <br>
                                        <strong>Au:</strong> <?= formatDate($reservation['end_datetime']) ?>
                                    </td>
                                    <td>
                                        <strong><?= formatCurrency($reservation['total_amount']) ?></strong>
                                    </td>
                                    <td>
                                        <span class="status-badge <?= getStatusBadgeClass($reservation['status']) ?>">
                                            <?= getStatusLabel($reservation['status']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="payment-badge <?= getPaymentStatusBadgeClass($reservation['payment_status']) ?>">
                                            <?= getPaymentStatusLabel($reservation['payment_status']) ?>
                                        </span>
                                    </td>
                                    <td class="actions-cell">
                                        <button class="btn btn-small" onclick="viewReservationDetails(<?= htmlspecialchars(json_encode($reservation)) ?>)">
                                            👁️ Détails
                                        </button>
                                        <?php if (in_array($reservation['status'], ['pending', 'confirmed'])): ?>
                                            <button class="btn btn-small btn-warning" onclick="updateReservationStatus(<?= $reservation['reservation_id'] ?>, '<?= htmlspecialchars($reservation['reservation_code']) ?>')">
                                                ✏️ Statut
                                            </button>
                                        <?php endif; ?>
                                        <?php if ($reservation['status'] === 'cancelled' || $reservation['status'] === 'no_show'): ?>
                                            <button class="btn btn-small btn-danger" onclick="deleteReservation(<?= $reservation['reservation_id'] ?>, '<?= htmlspecialchars($reservation['reservation_code']) ?>')">
                                                🗑️ Supprimer
                                            </button>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>

                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                    <div class="pagination">
                        <?php if ($page > 1): ?>
                            <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>">← Précédent</a>
                        <?php endif; ?>
                        
                        <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                            <?php if ($i == $page): ?>
                                <span class="current"><?= $i ?></span>
                            <?php else: ?>
                                <a href="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>"><?= $i ?></a>
                            <?php endif; ?>
                        <?php endfor; ?>
                        
                        <?php if ($page < $total_pages): ?>
                            <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>">Suivant →</a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <!-- Modal de création -->
    <div id="createModal" class="modal">
        <div class="modal-content">
            <h2>Nouvelle réservation</h2>
            <form method="POST" id="createForm">
                <input type="hidden" name="action" value="create">
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="create_user_search">Rechercher un client *</label>
                        <input type="text" id="create_user_search" placeholder="Email ou nom du client...">
                        <div id="user_search_results" class="search-results"></div>
                        <input type="hidden" name="user_id" id="selected_user_id" required>
                    </div>
                    <div class="form-group">
                        <label for="create_spot_search">Rechercher une place *</label>
                        <input type="text" id="create_spot_search" placeholder="Numéro de place...">
                        <div id="spot_search_results" class="search-results"></div>
                        <input type="hidden" name="spot_id" id="selected_spot_id" required>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="create_start_datetime">Date/heure de début *</label>
                        <input type="datetime-local" id="create_start_datetime" name="start_datetime" required>
                    </div>
                    <div class="form-group">
                        <label for="create_end_datetime">Date/heure de fin *</label>
                        <input type="datetime-local" id="create_end_datetime" name="end_datetime" required>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="create_total_amount">Montant (€) *</label>
                        <input type="number" id="create_total_amount" name="total_amount" step="0.01" min="0" required>
                    </div>
                    <div class="form-group">
                        <label for="create_vehicle_plate">Plaque d'immatriculation</label>
                        <input type="text" id="create_vehicle_plate" name="vehicle_plate" placeholder="Ex: AB-123-CD">
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="create_vehicle_model">Modèle de véhicule</label>
                    <input type="text" id="create_vehicle_model" name="vehicle_model" placeholder="Ex: Peugeot 308">
                </div>
                
                <div class="form-group">
                    <label for="create_special_requests">Demandes spéciales</label>
                    <textarea id="create_special_requests" name="special_requests" placeholder="Demandes particulières du client..."></textarea>
                </div>
                
                <div class="modal-actions">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('createModal')">Annuler</button>
                    <button type="submit" class="btn">Créer la réservation</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal de mise à jour du statut -->
    <div id="statusModal" class="modal">
        <div class="modal-content">
            <h2>Modifier le statut de la réservation</h2>
            <div id="status-reservation-info"></div>
            <form method="POST" id="statusForm">
                <input type="hidden" name="action" value="update_status">
                <input type="hidden" name="reservation_id" id="status_reservation_id">
                
                <div class="form-group">
                    <label for="new_status">Nouveau statut *</label>
                    <select id="new_status" name="new_status" required>
                        <option value="pending">En attente</option>
                        <option value="confirmed">Confirmée</option>
                        <option value="active">Active</option>
                        <option value="completed">Terminée</option>
                        <option value="cancelled">Annulée</option>
                        <option value="no_show">Absent</option>
                    </select>
                </div>
                
                <div class="form-group" id="cancellation_reason_group" style="display: none;">
                    <label for="cancellation_reason">Raison de l'annulation</label>
                    <textarea id="cancellation_reason" name="cancellation_reason" placeholder="Expliquez la raison de l'annulation..."></textarea>
                </div>
                
                <div class="modal-actions">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('statusModal')">Annuler</button>
                    <button type="submit" class="btn">Mettre à jour</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal de détails -->
    <div id="detailsModal" class="modal">
        <div class="modal-content">
            <h2>Détails de la réservation</h2>
            <div id="reservation-details-content"></div>
            <div class="modal-actions">
                <button type="button" class="btn btn-secondary" onclick="closeModal('detailsModal')">Fermer</button>
            </div>
        </div>
    </div>

    <!-- Form de suppression -->
    <form method="POST" id="deleteForm" style="display: none;">
        <input type="hidden" name="action" value="delete">
        <input type="hidden" name="reservation_id" id="delete_reservation_id">
    </form>

    <!-- Scripts JavaScript -->
    <script src="assets/js/admin-reservations.js"></script>
</body>
</html>