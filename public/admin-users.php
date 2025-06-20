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
        case 'create':
            $first_name = trim($_POST['first_name'] ?? '');
            $last_name = trim($_POST['last_name'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $phone = trim($_POST['phone'] ?? '');
            $password = $_POST['password'] ?? '';
            $role = $_POST['role'] ?? 'customer';
            $is_active = isset($_POST['is_active']) ? 1 : 0;
            
            if (!empty($first_name) && !empty($last_name) && !empty($email) && !empty($password)) {
                // Vérifier si l'email existe déjà
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE email = ?");
                $stmt->execute([$email]);
                
                if ($stmt->fetchColumn() > 0) {
                    $message = "Cette adresse email est déjà utilisée.";
                    $messageType = 'error';
                } else {
                    try {
                        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                        $stmt = $pdo->prepare("
                            INSERT INTO users (first_name, last_name, email, phone, password_hash, role, is_active, created_at) 
                            VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
                        ");
                        $stmt->execute([$first_name, $last_name, $email, $phone, $hashedPassword, $role, $is_active]);
                        $message = "Utilisateur créé avec succès.";
                        $messageType = 'success';
                    } catch (Exception $e) {
                        $message = "Erreur lors de la création: " . $e->getMessage();
                        $messageType = 'error';
                    }
                }
            } else {
                $message = "Veuillez remplir tous les champs obligatoires.";
                $messageType = 'error';
            }
            break;
            
        case 'update':
            $user_id = intval($_POST['user_id'] ?? 0);
            $first_name = trim($_POST['first_name'] ?? '');
            $last_name = trim($_POST['last_name'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $phone = trim($_POST['phone'] ?? '');
            $role = $_POST['role'] ?? 'customer';
            $is_active = isset($_POST['is_active']) ? 1 : 0;
            
            if ($user_id > 0 && !empty($first_name) && !empty($last_name) && !empty($email)) {
                // Vérifier si l'email existe déjà pour un autre utilisateur
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE email = ? AND user_id != ?");
                $stmt->execute([$email, $user_id]);
                
                if ($stmt->fetchColumn() > 0) {
                    $message = "Cette adresse email est déjà utilisée par un autre utilisateur.";
                    $messageType = 'error';
                } else {
                    try {
                        $stmt = $pdo->prepare("
                            UPDATE users 
                            SET first_name = ?, last_name = ?, email = ?, phone = ?, role = ?, is_active = ?, updated_at = NOW()
                            WHERE user_id = ?
                        ");
                        $stmt->execute([$first_name, $last_name, $email, $phone, $role, $is_active, $user_id]);
                        $message = "Utilisateur mis à jour avec succès.";
                        $messageType = 'success';
                    } catch (Exception $e) {
                        $message = "Erreur lors de la mise à jour: " . $e->getMessage();
                        $messageType = 'error';
                    }
                }
            } else {
                $message = "Données invalides pour la mise à jour.";
                $messageType = 'error';
            }
            break;
            
        case 'update_password':
            $user_id = intval($_POST['user_id'] ?? 0);
            $new_password = $_POST['new_password'] ?? '';
            
            if ($user_id > 0 && !empty($new_password)) {
                try {
                    $hashedPassword = password_hash($new_password, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("UPDATE users SET password_hash = ?, updated_at = NOW() WHERE user_id = ?");
                    $stmt->execute([$hashedPassword, $user_id]);
                    $message = "Mot de passe mis à jour avec succès.";
                    $messageType = 'success';
                } catch (Exception $e) {
                    $message = "Erreur lors de la mise à jour du mot de passe: " . $e->getMessage();
                    $messageType = 'error';
                }
            } else {
                $message = "Données invalides pour la mise à jour du mot de passe.";
                $messageType = 'error';
            }
            break;
            
        case 'delete':
            $user_id = intval($_POST['user_id'] ?? 0);
            if ($user_id > 0) {
                try {
                    // Vérifier s'il y a des réservations actives
                    $stmt = $pdo->prepare("SELECT COUNT(*) FROM reservations WHERE user_id = ? AND status IN ('confirmed', 'active')");
                    $stmt->execute([$user_id]);
                    $activeReservations = $stmt->fetchColumn();
                    
                    if ($activeReservations > 0) {
                        $message = "Impossible de supprimer: cet utilisateur a des réservations actives.";
                        $messageType = 'error';
                    } else {
                        // Vérifier si c'est un admin (protection)
                        $stmt = $pdo->prepare("SELECT role FROM users WHERE user_id = ?");
                        $stmt->execute([$user_id]);
                        $userRole = $stmt->fetchColumn();
                        
                        if ($userRole === 'admin') {
                            $message = "Impossible de supprimer un compte administrateur.";
                            $messageType = 'error';
                        } else {
                            $stmt = $pdo->prepare("DELETE FROM users WHERE user_id = ?");
                            $stmt->execute([$user_id]);
                            $message = "Utilisateur supprimé avec succès.";
                            $messageType = 'success';
                        }
                    }
                } catch (Exception $e) {
                    $message = "Erreur lors de la suppression: " . $e->getMessage();
                    $messageType = 'error';
                }
            }
            break;
            
        case 'toggle_status':
            $user_id = intval($_POST['user_id'] ?? 0);
            if ($user_id > 0) {
                try {
                    $stmt = $pdo->prepare("UPDATE users SET is_active = NOT is_active, updated_at = NOW() WHERE user_id = ?");
                    $stmt->execute([$user_id]);
                    $message = "Statut de l'utilisateur mis à jour.";
                    $messageType = 'success';
                } catch (Exception $e) {
                    $message = "Erreur lors de la mise à jour du statut: " . $e->getMessage();
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
$role_filter = $_GET['role'] ?? '';
$status_filter = $_GET['status'] ?? '';
$page = max(1, intval($_GET['page'] ?? 1));
$per_page = 20;
$offset = ($page - 1) * $per_page;

// Champs autorisés pour le tri
$allowed_sort_fields = ['first_name', 'last_name', 'email', 'role', 'is_active', 'created_at', 'last_login'];
if (!in_array($sort_field, $allowed_sort_fields)) {
    $sort_field = 'created_at';
}

$sort_order = strtoupper($sort_order) === 'DESC' ? 'DESC' : 'ASC';

// Construction de la requête
$where_conditions = ['1=1'];
$params = [];

if (!empty($search)) {
    $where_conditions[] = "(first_name LIKE ? OR last_name LIKE ? OR email LIKE ? OR phone LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
}

if (!empty($role_filter)) {
    $where_conditions[] = "role = ?";
    $params[] = $role_filter;
}

if (!empty($status_filter)) {
    if ($status_filter === 'active') {
        $where_conditions[] = "is_active = 1";
    } elseif ($status_filter === 'inactive') {
        $where_conditions[] = "is_active = 0";
    }
}

$where_clause = implode(' AND ', $where_conditions);

// Récupération des données
try {
    // Compter le total
    $count_query = "SELECT COUNT(*) FROM users WHERE $where_clause";
    $stmt = $pdo->prepare($count_query);
    $stmt->execute($params);
    $total_records = $stmt->fetchColumn();
    $total_pages = ceil($total_records / $per_page);
    
    // Récupérer les données paginées
    $query = "
        SELECT u.*, 
               COUNT(r.reservation_id) as total_reservations,
               SUM(CASE WHEN r.status IN ('confirmed', 'active') THEN 1 ELSE 0 END) as active_reservations,
               MAX(r.created_at) as last_reservation_date
        FROM users u
        LEFT JOIN reservations r ON u.user_id = r.user_id
        WHERE $where_clause
        GROUP BY u.user_id
        ORDER BY $sort_field $sort_order
        LIMIT $per_page OFFSET $offset
    ";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
      // Statistiques générales
    $stats_query = "
        SELECT 
            COUNT(*) as total_users,
            SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) as active_users,
            SUM(CASE WHEN is_active = 0 THEN 1 ELSE 0 END) as inactive_users,
            SUM(CASE WHEN role = 'admin' THEN 1 ELSE 0 END) as admin_users,
            SUM(CASE WHEN role = 'customer' THEN 1 ELSE 0 END) as customer_users,
            SUM(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 1 ELSE 0 END) as new_users_month
        FROM users
    ";
    $stmt = $pdo->query($stats_query);
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    $message = "Erreur lors de la récupération des données: " . $e->getMessage();
    $messageType = 'error';
    $users = [];
    $stats = [];
}

// Fonctions utilitaires
function getSortLink($field, $current_field, $current_order, $search, $role_filter, $status_filter) {
    $new_order = ($field === $current_field && $current_order === 'ASC') ? 'DESC' : 'ASC';
    $params = [
        'sort' => $field,
        'order' => $new_order,
        'search' => $search,
        'role' => $role_filter,
        'status' => $status_filter
    ];
    return 'admin-users.php?' . http_build_query(array_filter($params));
}

function getSortIcon($field, $current_field, $current_order) {
    if ($field !== $current_field) return '↕️';
    return $current_order === 'ASC' ? '↑' : '↓';
}

function formatDate($date) {
    return $date ? date('d/m/Y H:i', strtotime($date)) : 'Jamais';
}

function getRoleBadgeClass($role) {
    return $role === 'admin' ? 'role-admin' : 'role-customer';
}

function getRoleLabel($role) {
    return $role === 'admin' ? 'Administrateur' : 'Client';
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des Utilisateurs - ParkFinder Admin</title>
    
    <!-- Styles CSS -->
    <link rel="stylesheet" href="assets/css/admin-dashboard.css">
    <link rel="stylesheet" href="assets/css/admin-users.css">
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
                    <a href="admin-users.php" class="nav-item active">
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
                <h1>Gestion des Utilisateurs</h1>
                <div class="admin-actions">
                    <button class="btn btn-secondary" onclick="location.reload()">
                        🔄 Actualiser
                    </button>
                    <button class="btn" onclick="openModal('createModal')">
                        ➕ Nouvel utilisateur
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
                    <div class="stat-number"><?= $stats['total_users'] ?? 0 ?></div>
                    <div class="stat-label">Total utilisateurs</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?= $stats['active_users'] ?? 0 ?></div>
                    <div class="stat-label">Utilisateurs actifs</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?= $stats['customer_users'] ?? 0 ?></div>
                    <div class="stat-label">Clients</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?= $stats['admin_users'] ?? 0 ?></div>
                    <div class="stat-label">Administrateurs</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?= $stats['new_users_month'] ?? 0 ?></div>
                    <div class="stat-label">Nouveaux ce mois</div>
                </div>
            </div>

            <!-- Filtres et recherche -->
            <div class="filters-section">
                <form method="GET" class="filters-grid">
                    <div class="form-group">
                        <label for="search">Rechercher</label>
                        <input type="text" id="search" name="search" value="<?= htmlspecialchars($search) ?>" 
                               placeholder="Nom, email, téléphone...">
                    </div>
                    <div class="form-group">
                        <label for="role">Rôle</label>
                        <select id="role" name="role">
                            <option value="">Tous les rôles</option>
                            <option value="customer" <?= $role_filter === 'customer' ? 'selected' : '' ?>>Client</option>
                            <option value="admin" <?= $role_filter === 'admin' ? 'selected' : '' ?>>Administrateur</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="status">Statut</label>
                        <select id="status" name="status">
                            <option value="">Tous les statuts</option>
                            <option value="active" <?= $status_filter === 'active' ? 'selected' : '' ?>>Actif</option>
                            <option value="inactive" <?= $status_filter === 'inactive' ? 'selected' : '' ?>>Inactif</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <button type="submit" class="btn">🔍 Filtrer</button>
                        <a href="admin-users.php" class="btn btn-secondary">Réinitialiser</a>
                    </div>
                </form>
            </div>

            <!-- Tableau des utilisateurs -->
            <div class="table-container">
                <div class="table-header">
                    <h3>Utilisateurs (<?= $total_records ?> résultats)</h3>
                </div>
                
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>
                                <a href="<?= getSortLink('first_name', $sort_field, $sort_order, $search, $role_filter, $status_filter) ?>" class="sort-header">
                                    Nom <?= getSortIcon('first_name', $sort_field, $sort_order) ?>
                                </a>
                            </th>
                            <th>
                                <a href="<?= getSortLink('email', $sort_field, $sort_order, $search, $role_filter, $status_filter) ?>" class="sort-header">
                                    Email <?= getSortIcon('email', $sort_field, $sort_order) ?>
                                </a>
                            </th>
                            <th>Téléphone</th>
                            <th>
                                <a href="<?= getSortLink('role', $sort_field, $sort_order, $search, $role_filter, $status_filter) ?>" class="sort-header">
                                    Rôle <?= getSortIcon('role', $sort_field, $sort_order) ?>
                                </a>
                            </th>
                            <th>Réservations</th>
                            <th>
                                <a href="<?= getSortLink('is_active', $sort_field, $sort_order, $search, $role_filter, $status_filter) ?>" class="sort-header">
                                    Statut <?= getSortIcon('is_active', $sort_field, $sort_order) ?>
                                </a>
                            </th>
                            <th>
                                <a href="<?= getSortLink('created_at', $sort_field, $sort_order, $search, $role_filter, $status_filter) ?>" class="sort-header">
                                    Inscrit le <?= getSortIcon('created_at', $sort_field, $sort_order) ?>
                                </a>
                            </th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($users)): ?>
                            <tr>
                                <td colspan="8" style="text-align: center; padding: 2rem; color: var(--text-muted);">
                                    Aucun utilisateur trouvé
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($users as $user): ?>
                                <tr>
                                    <td>
                                        <div class="user-info">
                                            <div class="user-avatar">
                                                <?= strtoupper(substr($user['first_name'], 0, 1) . substr($user['last_name'], 0, 1)) ?>
                                            </div>
                                            <div>
                                                <strong><?= htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) ?></strong>
                                                <br>
                                                <small>ID: <?= $user['user_id'] ?></small>
                                            </div>
                                        </div>
                                    </td>
                                    <td><?= htmlspecialchars($user['email']) ?></td>
                                    <td><?= htmlspecialchars($user['phone'] ?? 'Non renseigné') ?></td>
                                    <td>
                                        <span class="role-badge <?= getRoleBadgeClass($user['role']) ?>">
                                            <?= getRoleLabel($user['role']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span><?= $user['active_reservations'] ?? 0 ?> actives</span><br>
                                        <small style="color: var(--text-muted);"><?= $user['total_reservations'] ?? 0 ?> total</small>
                                    </td>
                                    <td>
                                        <span class="status-badge status-<?= $user['is_active'] ? 'active' : 'inactive' ?>">
                                            <?= $user['is_active'] ? 'Actif' : 'Inactif' ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span><?= formatDate($user['created_at']) ?></span>
                                        <?php if ($user['last_reservation_date']): ?>
                                            <br><small style="color: var(--text-muted);">
                                                Dernière réservation: <?= formatDate($user['last_reservation_date']) ?>
                                            </small>
                                        <?php endif; ?>
                                    </td>
                                    <td class="actions-cell">
                                        <button class="btn btn-small" onclick="editUser(<?= htmlspecialchars(json_encode($user)) ?>)">
                                            ✏️ Modifier
                                        </button>
                                        <button class="btn btn-small btn-warning" onclick="openPasswordModal(<?= $user['user_id'] ?>, '<?= htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) ?>')">
                                            🔑 MDP
                                        </button>
                                        <?php if ($user['role'] !== 'admin'): ?>
                                            <button class="btn btn-small btn-danger" onclick="deleteUser(<?= $user['user_id'] ?>, '<?= htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) ?>')">
                                                🗑️ Supprimer
                                            </button>
                                        <?php endif; ?>
                                        <button class="btn btn-small <?= $user['is_active'] ? 'btn-secondary' : 'btn-success' ?>" 
                                                onclick="toggleUserStatus(<?= $user['user_id'] ?>, '<?= htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) ?>', <?= $user['is_active'] ? 'true' : 'false' ?>)">
                                            <?= $user['is_active'] ? '🚫 Désactiver' : '✅ Activer' ?>
                                        </button>
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
            <h2>Nouvel utilisateur</h2>
            <form method="POST">
                <input type="hidden" name="action" value="create">
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="create_first_name">Prénom *</label>
                        <input type="text" id="create_first_name" name="first_name" required>
                    </div>
                    <div class="form-group">
                        <label for="create_last_name">Nom *</label>
                        <input type="text" id="create_last_name" name="last_name" required>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="create_email">Email *</label>
                    <input type="email" id="create_email" name="email" required>
                </div>
                
                <div class="form-group">
                    <label for="create_phone">Téléphone</label>
                    <input type="tel" id="create_phone" name="phone" placeholder="+33 6 12 34 56 78">
                </div>
                
                <div class="form-group">
                    <label for="create_password">Mot de passe *</label>
                    <input type="password" id="create_password" name="password" required minlength="6">
                    <small>Minimum 6 caractères</small>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="create_role">Rôle *</label>
                        <select id="create_role" name="role" required>
                            <option value="customer">Client</option>
                            <option value="admin">Administrateur</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <div class="checkbox-group">
                            <input type="checkbox" id="create_is_active" name="is_active" checked>
                            <label for="create_is_active">Compte actif</label>
                        </div>
                    </div>
                </div>
                
                <div class="modal-actions">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('createModal')">Annuler</button>
                    <button type="submit" class="btn">Créer l'utilisateur</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal de modification -->
    <div id="editModal" class="modal">
        <div class="modal-content">
            <h2>Modifier l'utilisateur</h2>
            <form method="POST" id="editForm">
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="user_id" id="edit_user_id">
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="edit_first_name">Prénom *</label>
                        <input type="text" id="edit_first_name" name="first_name" required>
                    </div>
                    <div class="form-group">
                        <label for="edit_last_name">Nom *</label>
                        <input type="text" id="edit_last_name" name="last_name" required>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="edit_email">Email *</label>
                    <input type="email" id="edit_email" name="email" required>
                </div>
                
                <div class="form-group">
                    <label for="edit_phone">Téléphone</label>
                    <input type="tel" id="edit_phone" name="phone">
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="edit_role">Rôle *</label>
                        <select id="edit_role" name="role" required>
                            <option value="customer">Client</option>
                            <option value="admin">Administrateur</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <div class="checkbox-group">
                            <input type="checkbox" id="edit_is_active" name="is_active">
                            <label for="edit_is_active">Compte actif</label>
                        </div>
                    </div>
                </div>
                
                <div class="modal-actions">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('editModal')">Annuler</button>
                    <button type="submit" class="btn">Mettre à jour</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal de changement de mot de passe -->
    <div id="passwordModal" class="modal">
        <div class="modal-content">
            <h2>Changer le mot de passe</h2>
            <p id="password-user-info"></p>
            <form method="POST" id="passwordForm">
                <input type="hidden" name="action" value="update_password">
                <input type="hidden" name="user_id" id="password_user_id">
                
                <div class="form-group">
                    <label for="new_password">Nouveau mot de passe *</label>
                    <input type="password" id="new_password" name="new_password" required minlength="6">
                    <small>Minimum 6 caractères</small>
                </div>
                
                <div class="form-group">
                    <label for="confirm_password">Confirmer le mot de passe *</label>
                    <input type="password" id="confirm_password" required minlength="6">
                    <small>Veuillez confirmer le nouveau mot de passe</small>
                </div>
                
                <div class="modal-actions">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('passwordModal')">Annuler</button>
                    <button type="submit" class="btn">Changer le mot de passe</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Forms cachés pour actions -->
    <form method="POST" id="deleteForm" style="display: none;">
        <input type="hidden" name="action" value="delete">
        <input type="hidden" name="user_id" id="delete_user_id">
    </form>
    
    <form method="POST" id="toggleStatusForm" style="display: none;">
        <input type="hidden" name="action" value="toggle_status">
        <input type="hidden" name="user_id" id="toggle_user_id">
    </form>

    <!-- Scripts JavaScript -->
    <script src="assets/js/admin-users.js"></script>
</body>
</html>