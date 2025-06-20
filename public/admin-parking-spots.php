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
    
    switch ($action) {        case 'create':
            $spot_number = trim($_POST['spot_number'] ?? '');
            $spot_type = $_POST['spot_type'] ?? 'standard';
            $zone_section = trim($_POST['zone_section'] ?? 'A');
            $floor_level = intval($_POST['floor_level'] ?? 1);
            $description = trim($_POST['description'] ?? '');
            
            if (!empty($spot_number)) {
                try {
                    $stmt = $pdo->prepare("
                        INSERT INTO parking_spots (spot_number, spot_type, zone_section, floor_level, description, status, is_active, created_at) 
                        VALUES (?, ?, ?, ?, ?, 'available', 1, NOW())
                    ");
                    $stmt->execute([$spot_number, $spot_type, $zone_section, $floor_level, $description]);
                    $message = "Place de parking créée avec succès.";
                    $messageType = 'success';
                } catch (Exception $e) {
                    $message = "Erreur lors de la création: " . $e->getMessage();
                    $messageType = 'error';
                }
            } else {
                $message = "Veuillez remplir tous les champs obligatoires.";
                $messageType = 'error';
            }
            break;
              case 'update':
            $spot_id = intval($_POST['spot_id'] ?? 0);
            $spot_number = trim($_POST['spot_number'] ?? '');
            $spot_type = $_POST['spot_type'] ?? 'standard';
            $zone_section = trim($_POST['zone_section'] ?? 'A');
            $floor_level = intval($_POST['floor_level'] ?? 1);
            $description = trim($_POST['description'] ?? '');
            $status = $_POST['status'] ?? 'available';
            $is_active = isset($_POST['is_active']) ? 1 : 0;
            
            if ($spot_id > 0 && !empty($spot_number)) {
                try {
                    $stmt = $pdo->prepare("
                        UPDATE parking_spots 
                        SET spot_number = ?, spot_type = ?, zone_section = ?, floor_level = ?, description = ?, status = ?, is_active = ?, updated_at = NOW()
                        WHERE spot_id = ?
                    ");
                    $stmt->execute([$spot_number, $spot_type, $zone_section, $floor_level, $description, $status, $is_active, $spot_id]);
                    $message = "Place de parking mise à jour avec succès.";
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
            
        case 'delete':
            $spot_id = intval($_POST['spot_id'] ?? 0);
            if ($spot_id > 0) {
                try {
                    // Vérifier s'il y a des réservations actives
                    $stmt = $pdo->prepare("SELECT COUNT(*) FROM reservations WHERE spot_id = ? AND status IN ('active', 'confirmed')");
                    $stmt->execute([$spot_id]);
                    $activeReservations = $stmt->fetchColumn();
                    
                    if ($activeReservations > 0) {
                        $message = "Impossible de supprimer: des réservations actives existent pour cette place.";
                        $messageType = 'error';
                    } else {
                        $stmt = $pdo->prepare("DELETE FROM parking_spots WHERE spot_id = ?");
                        $stmt->execute([$spot_id]);
                        $message = "Place de parking supprimée avec succès.";
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
$sort_field = $_GET['sort'] ?? 'spot_number';
$sort_order = $_GET['order'] ?? 'ASC';
$status_filter = $_GET['status'] ?? '';
$type_filter = $_GET['type'] ?? '';
$page = max(1, intval($_GET['page'] ?? 1));
$per_page = 20;
$offset = ($page - 1) * $per_page;

// Champs autorisés pour le tri
$allowed_sort_fields = ['spot_number', 'spot_type', 'zone_section', 'floor_level', 'status', 'is_active', 'created_at'];
if (!in_array($sort_field, $allowed_sort_fields)) {
    $sort_field = 'spot_number';
}

$sort_order = strtoupper($sort_order) === 'DESC' ? 'DESC' : 'ASC';

// Construction de la requête
$where_conditions = ['1=1'];
$params = [];

if (!empty($search)) {
    $where_conditions[] = "(spot_number LIKE ? OR zone_section LIKE ? OR description LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
}

if (!empty($status_filter)) {
    $where_conditions[] = "status = ?";
    $params[] = $status_filter;
}

if (!empty($type_filter)) {
    $where_conditions[] = "spot_type = ?";
    $params[] = $type_filter;
}

$where_clause = implode(' AND ', $where_conditions);

// Récupération des données
try {
    // Compter le total
    $count_query = "SELECT COUNT(*) FROM parking_spots WHERE $where_clause";
    $stmt = $pdo->prepare($count_query);
    $stmt->execute($params);
    $total_records = $stmt->fetchColumn();
    $total_pages = ceil($total_records / $per_page);
    
    // Récupérer les données paginées
    $query = "
        SELECT ps.*, 
               COUNT(r.reservation_id) as total_reservations,
               SUM(CASE WHEN r.status IN ('active', 'confirmed') THEN 1 ELSE 0 END) as active_reservations
        FROM parking_spots ps
        LEFT JOIN reservations r ON ps.spot_id = r.spot_id
        WHERE $where_clause
        GROUP BY ps.spot_id
        ORDER BY $sort_field $sort_order
        LIMIT $per_page OFFSET $offset
    ";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $parking_spots = $stmt->fetchAll(PDO::FETCH_ASSOC);
      // Statistiques générales
    $stats_query = "
        SELECT 
            COUNT(*) as total_spots,
            SUM(CASE WHEN status = 'available' AND is_active = 1 THEN 1 ELSE 0 END) as available_spots,
            SUM(CASE WHEN status = 'occupied' THEN 1 ELSE 0 END) as occupied_spots,
            SUM(CASE WHEN status = 'maintenance' THEN 1 ELSE 0 END) as maintenance_spots,
            SUM(CASE WHEN is_active = 0 THEN 1 ELSE 0 END) as inactive_spots
        FROM parking_spots
    ";
    $stmt = $pdo->query($stats_query);
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    $message = "Erreur lors de la récupération des données: " . $e->getMessage();
    $messageType = 'error';
    $parking_spots = [];
    $stats = [];
}

// Fonction pour générer les liens de tri
function getSortLink($field, $current_field, $current_order, $search, $status_filter, $type_filter) {
    $new_order = ($field === $current_field && $current_order === 'ASC') ? 'DESC' : 'ASC';
    $params = [
        'sort' => $field,
        'order' => $new_order,
        'search' => $search,
        'status' => $status_filter,
        'type' => $type_filter
    ];
    return 'admin-parking-spots.php?' . http_build_query(array_filter($params));
}

// Fonction pour afficher l'icône de tri
function getSortIcon($field, $current_field, $current_order) {
    if ($field !== $current_field) return '↕️';
    return $current_order === 'ASC' ? '↑' : '↓';
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des Places - ParkFinder Admin</title>
    
    <!-- Styles CSS -->
    <link rel="stylesheet" href="assets/css/admin-dashboard.css">
    <link rel="stylesheet" href="assets/css/admin-parking-spots.css">
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
            </div>            <nav class="sidebar-nav">
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
                    <a href="admin-parking-spots.php" class="nav-item active">
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
                <h1>Gestion des Places de Parking</h1>
                <div class="admin-actions">
                    <button class="btn btn-secondary" onclick="location.reload()">
                        🔄 Actualiser
                    </button>
                    <button class="btn" onclick="openModal('createModal')">
                        ➕ Nouvelle place
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
                    <div class="stat-number"><?= $stats['total_spots'] ?? 0 ?></div>
                    <div class="stat-label">Total des places</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?= $stats['available_spots'] ?? 0 ?></div>
                    <div class="stat-label">Places disponibles</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?= $stats['occupied_spots'] ?? 0 ?></div>
                    <div class="stat-label">Places occupées</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?= $stats['maintenance_spots'] ?? 0 ?></div>
                    <div class="stat-label">En maintenance</div>
                </div>                <div class="stat-card">
                    <div class="stat-number"><?= $stats['inactive_spots'] ?? 0 ?></div>
                    <div class="stat-label">Places inactives</div>
                </div>
            </div>

            <!-- Filtres et recherche -->
            <div class="filters-section">
                <form method="GET" class="filters-grid">
                    <div class="form-group">
                        <label for="search">Rechercher</label>
                        <input type="text" id="search" name="search" value="<?= htmlspecialchars($search) ?>" 
                               placeholder="Numéro, emplacement, description...">
                    </div>
                    <div class="form-group">
                        <label for="status">Statut</label>
                        <select id="status" name="status">
                            <option value="">Tous les statuts</option>
                            <option value="available" <?= $status_filter === 'available' ? 'selected' : '' ?>>Disponible</option>
                            <option value="occupied" <?= $status_filter === 'occupied' ? 'selected' : '' ?>>Occupée</option>
                            <option value="maintenance" <?= $status_filter === 'maintenance' ? 'selected' : '' ?>>Maintenance</option>
                            <option value="reserved" <?= $status_filter === 'reserved' ? 'selected' : '' ?>>Réservée</option>
                        </select>
                    </div>
                    <div class="form-group">                        <label for="type">Type</label>
                        <select id="type" name="type">
                            <option value="">Tous les types</option>
                            <option value="standard" <?= $type_filter === 'standard' ? 'selected' : '' ?>>Standard</option>
                            <option value="disabled" <?= $type_filter === 'disabled' ? 'selected' : '' ?>>PMR</option>
                            <option value="electric" <?= $type_filter === 'electric' ? 'selected' : '' ?>>Électrique</option>
                            <option value="compact" <?= $type_filter === 'compact' ? 'selected' : '' ?>>Compact</option>
                            <option value="reserved" <?= $type_filter === 'reserved' ? 'selected' : '' ?>>Réservée</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <button type="submit" class="btn">🔍 Filtrer</button>
                        <a href="admin-parking-spots.php" class="btn btn-secondary">Réinitialiser</a>
                    </div>
                </form>
            </div>

            <!-- Tableau des places -->
            <div class="table-container">
                <div class="table-header">
                    <h3>Places de parking (<?= $total_records ?> résultats)</h3>
                </div>
                
                <table class="data-table">
                    <thead>
                        <tr>                            <th>
                                <a href="<?= getSortLink('spot_number', $sort_field, $sort_order, $search, $status_filter, $type_filter) ?>" class="sort-header">
                                    Numéro <?= getSortIcon('spot_number', $sort_field, $sort_order) ?>
                                </a>
                            </th>
                            <th>
                                <a href="<?= getSortLink('zone_section', $sort_field, $sort_order, $search, $status_filter, $type_filter) ?>" class="sort-header">
                                    Zone <?= getSortIcon('zone_section', $sort_field, $sort_order) ?>
                                </a>
                            </th>
                            <th>
                                <a href="<?= getSortLink('spot_type', $sort_field, $sort_order, $search, $status_filter, $type_filter) ?>" class="sort-header">
                                    Type <?= getSortIcon('spot_type', $sort_field, $sort_order) ?>
                                </a>
                            </th>
                            <th>
                                <a href="<?= getSortLink('floor_level', $sort_field, $sort_order, $search, $status_filter, $type_filter) ?>" class="sort-header">
                                    Étage <?= getSortIcon('floor_level', $sort_field, $sort_order) ?>
                                </a>
                            </th>
                            <th>
                                <a href="<?= getSortLink('status', $sort_field, $sort_order, $search, $status_filter, $type_filter) ?>" class="sort-header">
                                    Statut <?= getSortIcon('status', $sort_field, $sort_order) ?>
                                </a>
                            </th>
                            <th>Réservations</th>
                            <th>
                                <a href="<?= getSortLink('is_active', $sort_field, $sort_order, $search, $status_filter, $type_filter) ?>" class="sort-header">
                                    Actif <?= getSortIcon('is_active', $sort_field, $sort_order) ?>
                                </a>
                            </th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($parking_spots)): ?>                            <tr>
                                <td colspan="8" style="text-align: center; padding: 2rem; color: var(--text-muted);">
                                    Aucune place de parking trouvée
                                </td>
                            </tr><?php else: ?>                            <?php foreach ($parking_spots as $spot): ?>
                                <tr>
                                    <td><strong><?= htmlspecialchars($spot['spot_number'] ?? 'N/A') ?></strong></td>
                                    <td><?= htmlspecialchars($spot['zone_section'] ?? 'N/A') ?> - Étage <?= $spot['floor_level'] ?? 1 ?></td>
                                    <td><span class="type-badge type-<?= $spot['spot_type'] ?? 'standard' ?>"><?= ucfirst($spot['spot_type'] ?? 'standard') ?></span></td>
                                    <td><?= $spot['floor_level'] ?? 1 ?></td>
                                    <td>
                                        <span class="status-badge status-<?= $spot['status'] ?? 'available' ?>">
                                            <?= ucfirst($spot['status'] ?? 'available') ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span><?= $spot['active_reservations'] ?? 0 ?> actives</span><br>
                                        <small style="color: var(--text-muted);"><?= $spot['total_reservations'] ?? 0 ?> total</small>
                                    </td>
                                    <td>
                                        <span class="active-badge active-<?= ($spot['is_active'] ?? 1) ? 'yes' : 'no' ?>">
                                            <?= ($spot['is_active'] ?? 1) ? 'Oui' : 'Non' ?>
                                        </span>
                                    </td>
                                    <td class="actions-cell">
                                        <button class="btn btn-small" onclick="editSpot(<?= htmlspecialchars(json_encode($spot)) ?>)">
                                            ✏️ Modifier
                                        </button>
                                        <button class="btn btn-small btn-danger" onclick="deleteSpot(<?= $spot['spot_id'] ?? 0 ?>, '<?= htmlspecialchars($spot['spot_number'] ?? 'N/A') ?>')">
                                            🗑️ Supprimer
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
            <h2>Nouvelle place de parking</h2>
            <form method="POST">
                <input type="hidden" name="action" value="create">
                  <div class="form-row">
                    <div class="form-group">
                        <label for="create_spot_number">Numéro de place *</label>
                        <input type="text" id="create_spot_number" name="spot_number" required>
                    </div>
                    <div class="form-group">
                        <label for="create_spot_type">Type *</label>
                        <select id="create_spot_type" name="spot_type" required>
                            <option value="standard">Standard</option>
                            <option value="disabled">PMR</option>
                            <option value="electric">Électrique</option>
                            <option value="compact">Compact</option>
                            <option value="reserved">Réservée</option>
                        </select>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="create_zone_section">Zone *</label>
                        <input type="text" id="create_zone_section" name="zone_section" required placeholder="Ex: A, B, C">
                    </div>
                    <div class="form-group">
                        <label for="create_floor_level">Étage *</label>
                        <input type="number" id="create_floor_level" name="floor_level" value="1" min="1" required>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="create_description">Description</label>
                    <textarea id="create_description" name="description" placeholder="Description optionnelle"></textarea>
                </div>
                
                <div class="modal-actions">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('createModal')">Annuler</button>
                    <button type="submit" class="btn">Créer la place</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal de modification -->
    <div id="editModal" class="modal">
        <div class="modal-content">
            <h2>Modifier la place de parking</h2>
            <form method="POST" id="editForm">
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="spot_id" id="edit_spot_id">
                  <div class="form-row">
                    <div class="form-group">
                        <label for="edit_spot_number">Numéro de place *</label>
                        <input type="text" id="edit_spot_number" name="spot_number" required>
                    </div>
                    <div class="form-group">
                        <label for="edit_spot_type">Type *</label>
                        <select id="edit_spot_type" name="spot_type" required>
                            <option value="standard">Standard</option>
                            <option value="disabled">PMR</option>
                            <option value="electric">Électrique</option>
                            <option value="compact">Compact</option>
                            <option value="reserved">Réservée</option>
                        </select>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="edit_zone_section">Zone *</label>
                        <input type="text" id="edit_zone_section" name="zone_section" required>
                    </div>
                    <div class="form-group">
                        <label for="edit_floor_level">Étage *</label>
                        <input type="number" id="edit_floor_level" name="floor_level" min="1" required>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="edit_status">Statut</label>
                    <select id="edit_status" name="status">
                        <option value="available">Disponible</option>
                        <option value="occupied">Occupée</option>
                        <option value="maintenance">Maintenance</option>
                        <option value="reserved">Réservée</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="edit_description">Description</label>
                    <textarea id="edit_description" name="description"></textarea>
                </div>
                
                <div class="form-group">
                    <div class="checkbox-group">
                        <input type="checkbox" id="edit_is_active" name="is_active">
                        <label for="edit_is_active">Place active</label>
                    </div>
                </div>
                
                <div class="modal-actions">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('editModal')">Annuler</button>
                    <button type="submit" class="btn">Mettre à jour</button>
                </div>
            </form>
        </div>
    </div>    <!-- Form de suppression -->
    <form method="POST" id="deleteForm" style="display: none;">
        <input type="hidden" name="action" value="delete">
        <input type="hidden" name="spot_id" id="delete_spot_id">
    </form>

    <!-- Scripts JavaScript -->
    <script src="assets/js/admin-parking-spots.js"></script>
</body>
</html>