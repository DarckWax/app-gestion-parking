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

// Gestion des actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'process_refund':
            $payment_id = intval($_POST['payment_id'] ?? 0);
            $refund_amount = floatval($_POST['refund_amount'] ?? 0);
            $refund_reason = trim($_POST['refund_reason'] ?? '');
            
            if ($payment_id > 0 && $refund_amount > 0 && !empty($refund_reason)) {
                try {
                    // Vérifier que le paiement existe et est valide pour remboursement
                    $stmt = $pdo->prepare("
                        SELECT p.*, r.reservation_code 
                        FROM payments p 
                        JOIN reservations r ON p.reservation_id = r.reservation_id 
                        WHERE p.payment_id = ? AND p.status = 'completed'
                    ");
                    $stmt->execute([$payment_id]);
                    $payment = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    if (!$payment) {
                        $message = "Paiement non trouvé ou non éligible au remboursement.";
                        $messageType = 'error';
                    } elseif ($refund_amount > $payment['amount']) {
                        $message = "Le montant du remboursement ne peut pas dépasser le montant payé.";
                        $messageType = 'error';
                    } else {
                        $pdo->beginTransaction();
                        
                        // Créer l'enregistrement de remboursement
                        $stmt = $pdo->prepare("
                            INSERT INTO refunds (payment_id, amount, reason, status, processed_by, created_at) 
                            VALUES (?, ?, ?, 'completed', ?, NOW())
                        ");
                        $stmt->execute([$payment_id, $refund_amount, $refund_reason, $_SESSION['user_id']]);
                        
                        // Mettre à jour le statut du paiement
                        if ($refund_amount >= $payment['amount']) {
                            $new_status = 'refunded';
                        } else {
                            $new_status = 'partially_refunded';
                        }
                        
                        $stmt = $pdo->prepare("UPDATE payments SET status = ?, updated_at = NOW() WHERE payment_id = ?");
                        $stmt->execute([$new_status, $payment_id]);
                        
                        // Mettre à jour le statut de la réservation si remboursement complet
                        if ($refund_amount >= $payment['amount']) {
                            $stmt = $pdo->prepare("UPDATE reservations SET payment_status = 'refunded', updated_at = NOW() WHERE reservation_id = ?");
                            $stmt->execute([$payment['reservation_id']]);
                        }
                        
                        $pdo->commit();
                        $message = "Remboursement traité avec succès.";
                        $messageType = 'success';
                    }
                } catch (Exception $e) {
                    $pdo->rollBack();
                    $message = "Erreur lors du traitement du remboursement: " . $e->getMessage();
                    $messageType = 'error';
                }
            } else {
                $message = "Veuillez remplir tous les champs requis.";
                $messageType = 'error';
            }
            break;
            
        case 'mark_as_paid':
            $reservation_id = intval($_POST['reservation_id'] ?? 0);
            $amount = floatval($_POST['amount'] ?? 0);
            $payment_method = $_POST['payment_method'] ?? 'manual';
            
            if ($reservation_id > 0 && $amount > 0) {
                try {
                    $pdo->beginTransaction();
                    
                    // Créer l'enregistrement de paiement
                    $transaction_id = 'MANUAL_' . date('YmdHis') . '_' . $reservation_id;
                    $stmt = $pdo->prepare("
                        INSERT INTO payments (reservation_id, amount, payment_method, transaction_id, status, created_at) 
                        VALUES (?, ?, ?, ?, 'completed', NOW())
                    ");
                    $stmt->execute([$reservation_id, $amount, $payment_method, $transaction_id]);
                    
                    // Mettre à jour le statut de la réservation
                    $stmt = $pdo->prepare("UPDATE reservations SET payment_status = 'paid', updated_at = NOW() WHERE reservation_id = ?");
                    $stmt->execute([$reservation_id]);
                    
                    $pdo->commit();
                    $message = "Paiement enregistré avec succès.";
                    $messageType = 'success';
                } catch (Exception $e) {
                    $pdo->rollBack();
                    $message = "Erreur lors de l'enregistrement du paiement: " . $e->getMessage();
                    $messageType = 'error';
                }
            } else {
                $message = "Données invalides pour l'enregistrement du paiement.";
                $messageType = 'error';
            }
            break;
    }
}

// Gestion du tri et de la recherche
$search = $_GET['search'] ?? '';
$sort_field = $_GET['sort'] ?? 'created_at';
$sort_order = $_GET['order'] ?? 'DESC';
$status_filter = $_GET['status'] ?? '';
$method_filter = $_GET['method'] ?? '';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';
$page = max(1, intval($_GET['page'] ?? 1));
$per_page = 20;
$offset = ($page - 1) * $per_page;

// Champs autorisés pour le tri
$allowed_sort_fields = ['amount', 'payment_method', 'status', 'created_at', 'transaction_id'];
if (!in_array($sort_field, $allowed_sort_fields)) {
    $sort_field = 'created_at';
}

$sort_order = strtoupper($sort_order) === 'DESC' ? 'DESC' : 'ASC';

// Construction de la requête
$where_conditions = ['1=1'];
$params = [];

if (!empty($search)) {
    $where_conditions[] = "(p.transaction_id LIKE ? OR r.reservation_code LIKE ? OR u.email LIKE ? OR CONCAT(u.first_name, ' ', u.last_name) LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
}

if (!empty($status_filter)) {
    $where_conditions[] = "p.status = ?";
    $params[] = $status_filter;
}

if (!empty($method_filter)) {
    $where_conditions[] = "p.payment_method = ?";
    $params[] = $method_filter;
}

if (!empty($date_from)) {
    $where_conditions[] = "DATE(p.created_at) >= ?";
    $params[] = $date_from;
}

if (!empty($date_to)) {
    $where_conditions[] = "DATE(p.created_at) <= ?";
    $params[] = $date_to;
}

$where_clause = implode(' AND ', $where_conditions);

// Récupération des données
try {
    // Compter le total
    $count_query = "
        SELECT COUNT(*) 
        FROM payments p
        JOIN reservations r ON p.reservation_id = r.reservation_id
        JOIN users u ON r.user_id = u.user_id
        WHERE $where_clause
    ";
    $stmt = $pdo->prepare($count_query);
    $stmt->execute($params);
    $total_records = $stmt->fetchColumn();
    $total_pages = ceil($total_records / $per_page);    // Récupérer les données paginées
    $query = "
        SELECT p.*, 
               r.reservation_code, r.start_datetime, r.end_datetime, r.total_amount as reservation_amount,
               u.first_name, u.last_name, u.email,
               ps.spot_number,
               COALESCE(SUM(rf.amount), 0) as total_refunded
        FROM payments p
        JOIN reservations r ON p.reservation_id = r.reservation_id
        JOIN users u ON r.user_id = u.user_id
        JOIN parking_spots ps ON r.spot_id = ps.spot_id
        LEFT JOIN refunds rf ON p.payment_id = rf.payment_id AND rf.status = 'completed'
        WHERE $where_clause
        GROUP BY p.payment_id
        ORDER BY p.$sort_field $sort_order
        LIMIT $per_page OFFSET $offset
    ";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $payments = $stmt->fetchAll(PDO::FETCH_ASSOC);    // Statistiques générales
    $stats_query = "
        SELECT 
            COUNT(*) as total_payments,
            SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_payments,
            SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_payments,
            SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed_payments,
            SUM(CASE WHEN status IN ('refunded', 'partially_refunded') THEN 1 ELSE 0 END) as refunded_payments,
            SUM(CASE WHEN status = 'completed' THEN amount ELSE 0 END) as total_revenue,
            COALESCE((SELECT SUM(amount) FROM refunds WHERE status = 'completed'), 0) as total_refunds,
            AVG(CASE WHEN status = 'completed' THEN amount ELSE NULL END) as avg_payment_amount
        FROM payments
    ";
    $stmt = $pdo->query($stats_query);
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Réservations non payées
    $unpaid_query = "
        SELECT COUNT(*) as unpaid_reservations
        FROM reservations 
        WHERE payment_status IN ('pending', 'failed') 
        AND status != 'cancelled'
    ";
    $stmt = $pdo->query($unpaid_query);
    $unpaid_stats = $stmt->fetch(PDO::FETCH_ASSOC);
    $stats = array_merge($stats, $unpaid_stats);
    
} catch (Exception $e) {
    $message = "Erreur lors de la récupération des données: " . $e->getMessage();
    $messageType = 'error';
    $payments = [];
    $stats = [];
}

// Fonctions utilitaires
function getSortLink($field, $current_field, $current_order, $search, $status_filter, $method_filter, $date_from, $date_to) {
    $new_order = ($field === $current_field && $current_order === 'ASC') ? 'DESC' : 'ASC';
    $params = [
        'sort' => $field,
        'order' => $new_order,
        'search' => $search,
        'status' => $status_filter,
        'method' => $method_filter,
        'date_from' => $date_from,
        'date_to' => $date_to
    ];
    return 'admin-payments.php?' . http_build_query(array_filter($params));
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
        'completed' => 'status-completed',
        'pending' => 'status-pending',
        'failed' => 'status-failed',
        'refunded' => 'status-refunded',
        'partially_refunded' => 'status-partial-refund'
    ];
    return $classes[$status] ?? 'status-unknown';
}

function getStatusLabel($status) {
    $labels = [
        'completed' => 'Terminé',
        'pending' => 'En attente',
        'failed' => 'Échoué',
        'refunded' => 'Remboursé',
        'partially_refunded' => 'Partiellement remboursé'
    ];
    return $labels[$status] ?? ucfirst($status);
}

function getMethodLabel($method) {
    $labels = [
        'card' => 'Carte bancaire',
        'paypal' => 'PayPal',
        'bank_transfer' => 'Virement bancaire',
        'cash' => 'Espèces',
        'manual' => 'Manuel'
    ];
    return $labels[$method] ?? ucfirst($method);
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des Paiements - ParkFinder Admin</title>
    
    <!-- Styles CSS -->
    <link rel="stylesheet" href="assets/css/admin-dashboard.css">
    <link rel="stylesheet" href="assets/css/admin-payments.css">
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
                    <a href="admin-reservations.php" class="nav-item">
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
                    <a href="admin-payments.php" class="nav-item active">
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
        <main class="admin-main">            <div class="admin-header">
                <h1>Gestion des Paiements</h1>
                <div class="admin-actions">
                    <button class="btn btn-secondary" onclick="location.reload()">
                        🔄 Actualiser
                    </button>
                    <button class="btn" onclick="openModal('manualPaymentModal')">
                        ➕ Paiement manuel
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
                    <div class="stat-number"><?= $stats['total_payments'] ?? 0 ?></div>
                    <div class="stat-label">Total paiements</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?= formatCurrency($stats['total_revenue'] ?? 0) ?></div>
                    <div class="stat-label">Revenus totaux</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?= $stats['completed_payments'] ?? 0 ?></div>
                    <div class="stat-label">Paiements réussis</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?= $stats['pending_payments'] ?? 0 ?></div>
                    <div class="stat-label">En attente</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?= formatCurrency($stats['total_refunds'] ?? 0) ?></div>
                    <div class="stat-label">Total remboursements</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?= $stats['unpaid_reservations'] ?? 0 ?></div>
                    <div class="stat-label">Réservations impayées</div>
                </div>
            </div>

            <!-- Filtres et recherche -->
            <div class="filters-section">
                <form method="GET" class="filters-grid">
                    <div class="form-group">
                        <label for="search">Rechercher</label>
                        <input type="text" id="search" name="search" value="<?= htmlspecialchars($search) ?>" 
                               placeholder="ID transaction, code réservation, email...">
                    </div>
                    <div class="form-group">
                        <label for="status">Statut</label>
                        <select id="status" name="status">
                            <option value="">Tous les statuts</option>
                            <option value="completed" <?= $status_filter === 'completed' ? 'selected' : '' ?>>Terminé</option>
                            <option value="pending" <?= $status_filter === 'pending' ? 'selected' : '' ?>>En attente</option>
                            <option value="failed" <?= $status_filter === 'failed' ? 'selected' : '' ?>>Échoué</option>
                            <option value="refunded" <?= $status_filter === 'refunded' ? 'selected' : '' ?>>Remboursé</option>
                            <option value="partially_refunded" <?= $status_filter === 'partially_refunded' ? 'selected' : '' ?>>Partiellement remboursé</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="method">Méthode</label>
                        <select id="method" name="method">
                            <option value="">Toutes les méthodes</option>
                            <option value="card" <?= $method_filter === 'card' ? 'selected' : '' ?>>Carte bancaire</option>
                            <option value="paypal" <?= $method_filter === 'paypal' ? 'selected' : '' ?>>PayPal</option>
                            <option value="bank_transfer" <?= $method_filter === 'bank_transfer' ? 'selected' : '' ?>>Virement</option>
                            <option value="cash" <?= $method_filter === 'cash' ? 'selected' : '' ?>>Espèces</option>
                            <option value="manual" <?= $method_filter === 'manual' ? 'selected' : '' ?>>Manuel</option>
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
                        <a href="admin-payments.php" class="btn btn-secondary">Réinitialiser</a>
                    </div>
                </form>
            </div>

            <!-- Tableau des paiements -->
            <div class="table-container">
                <div class="table-header">
                    <h3>Paiements (<?= $total_records ?> résultats)</h3>
                </div>
                
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>
                                <a href="<?= getSortLink('transaction_id', $sort_field, $sort_order, $search, $status_filter, $method_filter, $date_from, $date_to) ?>" class="sort-header">
                                    Transaction <?= getSortIcon('transaction_id', $sort_field, $sort_order) ?>
                                </a>
                            </th>
                            <th>Réservation</th>
                            <th>Client</th>
                            <th>
                                <a href="<?= getSortLink('amount', $sort_field, $sort_order, $search, $status_filter, $method_filter, $date_from, $date_to) ?>" class="sort-header">
                                    Montant <?= getSortIcon('amount', $sort_field, $sort_order) ?>
                                </a>
                            </th>
                            <th>
                                <a href="<?= getSortLink('payment_method', $sort_field, $sort_order, $search, $status_filter, $method_filter, $date_from, $date_to) ?>" class="sort-header">
                                    Méthode <?= getSortIcon('payment_method', $sort_field, $sort_order) ?>
                                </a>
                            </th>
                            <th>
                                <a href="<?= getSortLink('status', $sort_field, $sort_order, $search, $status_filter, $method_filter, $date_from, $date_to) ?>" class="sort-header">
                                    Statut <?= getSortIcon('status', $sort_field, $sort_order) ?>
                                </a>
                            </th>
                            <th>
                                <a href="<?= getSortLink('created_at', $sort_field, $sort_order, $search, $status_filter, $method_filter, $date_from, $date_to) ?>" class="sort-header">
                                    Date <?= getSortIcon('created_at', $sort_field, $sort_order) ?>
                                </a>
                            </th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($payments)): ?>
                            <tr>
                                <td colspan="8" style="text-align: center; padding: 2rem; color: var(--text-muted);">
                                    Aucun paiement trouvé
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($payments as $payment): ?>
                                <tr>
                                    <td>
                                        <strong><?= htmlspecialchars($payment['transaction_id']) ?></strong>
                                        <br>
                                        <small>ID: <?= $payment['payment_id'] ?></small>
                                    </td>
                                    <td>
                                        <strong><?= htmlspecialchars($payment['reservation_code']) ?></strong>
                                        <br>
                                        <small>Place <?= htmlspecialchars($payment['spot_number']) ?></small>
                                    </td>
                                    <td>
                                        <?= htmlspecialchars($payment['first_name'] . ' ' . $payment['last_name']) ?>
                                        <br>
                                        <small style="color: var(--text-muted);"><?= htmlspecialchars($payment['email']) ?></small>
                                    </td>
                                    <td>
                                        <strong><?= formatCurrency($payment['amount']) ?></strong>
                                        <?php if ($payment['total_refunded'] > 0): ?>
                                            <br>
                                            <small style="color: #dc2626;">-<?= formatCurrency($payment['total_refunded']) ?> remboursé</small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="method-badge method-<?= $payment['payment_method'] ?>">
                                            <?= getMethodLabel($payment['payment_method']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="status-badge <?= getStatusBadgeClass($payment['status']) ?>">
                                            <?= getStatusLabel($payment['status']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?= formatDate($payment['created_at']) ?>
                                    </td>
                                    <td class="actions-cell">
                                        <button class="btn btn-small" onclick="viewPaymentDetails(<?= htmlspecialchars(json_encode($payment)) ?>)">
                                            👁️ Détails
                                        </button>
                                        <?php if ($payment['status'] === 'completed' && $payment['total_refunded'] < $payment['amount']): ?>
                                            <button class="btn btn-small btn-warning" onclick="openRefundModal(<?= htmlspecialchars(json_encode($payment)) ?>)">
                                                💰 Rembourser
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

    <!-- Modal de remboursement -->
    <div id="refundModal" class="modal">
        <div class="modal-content">
            <h2>Traiter un remboursement</h2>
            <div id="refund-payment-info"></div>
            <form method="POST" id="refundForm">
                <input type="hidden" name="action" value="process_refund">
                <input type="hidden" name="payment_id" id="refund_payment_id">
                
                <div class="form-group">
                    <label for="refund_amount">Montant à rembourser (€) *</label>
                    <input type="number" id="refund_amount" name="refund_amount" step="0.01" min="0" required>
                    <small id="max-refund-info"></small>
                </div>
                
                <div class="form-group">
                    <label for="refund_reason">Raison du remboursement *</label>
                    <textarea id="refund_reason" name="refund_reason" required rows="3" placeholder="Expliquez la raison du remboursement..."></textarea>
                </div>
                
                <div class="modal-actions">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('refundModal')">Annuler</button>
                    <button type="submit" class="btn btn-warning">Traiter le remboursement</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal de paiement manuel -->
    <div id="manualPaymentModal" class="modal">
        <div class="modal-content">
            <h2>Enregistrer un paiement manuel</h2>
            <form method="POST" id="manualPaymentForm">
                <input type="hidden" name="action" value="mark_as_paid">
                
                <div class="form-group">
                    <label for="reservation_search">Rechercher une réservation</label>
                    <input type="text" id="reservation_search" placeholder="Code de réservation ou email client...">
                    <div id="reservation_results" class="search-results"></div>
                    <input type="hidden" name="reservation_id" id="selected_reservation_id" required>
                </div>
                
                <div class="form-group">
                    <label for="manual_amount">Montant payé (€) *</label>
                    <input type="number" id="manual_amount" name="amount" step="0.01" min="0" required>
                </div>
                
                <div class="form-group">
                    <label for="manual_payment_method">Méthode de paiement *</label>
                    <select id="manual_payment_method" name="payment_method" required>
                        <option value="cash">Espèces</option>
                        <option value="bank_transfer">Virement bancaire</option>
                        <option value="manual">Autre (manuel)</option>
                    </select>
                </div>
                
                <div class="modal-actions">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('manualPaymentModal')">Annuler</button>
                    <button type="submit" class="btn">Enregistrer le paiement</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal de détails -->
    <div id="detailsModal" class="modal">
        <div class="modal-content">
            <h2>Détails du paiement</h2>
            <div id="payment-details-content"></div>
            <div class="modal-actions">
                <button type="button" class="btn btn-secondary" onclick="closeModal('detailsModal')">Fermer</button>
            </div>
        </div>
    </div>

    <!-- Scripts JavaScript -->
    <script src="assets/js/admin-payments.js"></script>
</body>
</html>