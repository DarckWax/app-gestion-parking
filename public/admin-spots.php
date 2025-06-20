<?php
// Contrôle d'accès administrateur obligatoire
require_once '../app/middlewares/AdminMiddleware.php';
use App\Middlewares\AdminMiddleware;

AdminMiddleware::requireAdmin();

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

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
    $dbConnected = true;
} catch(Exception $e) {
    $dbConnected = false;
}

$message = '';
$messageType = '';

// Traitement des actions
if ($_POST && $dbConnected) {
    $action = $_POST['action'] ?? '';
    $spotId = $_POST['spot_id'] ?? '';
    
    try {
        switch ($action) {
            case 'toggle_status':
                $newStatus = $_POST['new_status'] ?? '';
                if (in_array($newStatus, ['available', 'occupied', 'maintenance', 'reserved'])) {
                    $stmt = $pdo->prepare("UPDATE parking_spots SET status = ? WHERE spot_id = ?");
                    $stmt->execute([$newStatus, $spotId]);
                    $message = "Statut de la place modifié avec succès";
                    $messageType = 'success';
                }
                break;
                
            case 'toggle_active':
                $stmt = $pdo->prepare("UPDATE parking_spots SET is_active = NOT is_active WHERE spot_id = ?");
                $stmt->execute([$spotId]);
                $message = "État d'activation modifié avec succès";
                $messageType = 'success';
                break;
                
            case 'delete_spot':
                // Vérifier qu'il n'y a pas de réservations actives
                $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM reservations WHERE spot_id = ? AND status IN ('active', 'pending')");
                $stmt->execute([$spotId]);
                $activeReservations = $stmt->fetch()['count'];
                
                if ($activeReservations > 0) {
                    throw new Exception("Impossible de supprimer cette place car elle a des réservations actives");
                }
                
                $stmt = $pdo->prepare("DELETE FROM parking_spots WHERE spot_id = ?");
                $stmt->execute([$spotId]);
                $message = "Place supprimée avec succès";
                $messageType = 'success';
                break;
                
            case 'add_spot':
                $spotNumber = trim($_POST['spot_number'] ?? '');
                $spotType = $_POST['spot_type'] ?? 'standard';
                $zoneSection = trim($_POST['zone_section'] ?? '');
                $hourlyRate = floatval($_POST['hourly_rate'] ?? 2.50);
                $description = trim($_POST['description'] ?? '');
                
                if (empty($spotNumber)) {
                    throw new Exception("Le numéro de place est obligatoire");
                }
                
                // Vérifier si le numéro de place existe déjà
                $stmt = $pdo->prepare("SELECT spot_id FROM parking_spots WHERE spot_number = ?");
                $stmt->execute([$spotNumber]);
                if ($stmt->fetch()) {
                    throw new Exception("Ce numéro de place existe déjà");
                }
                
                // Vérifier d'abord si la colonne hourly_rate existe
                $stmt = $pdo->query("SHOW COLUMNS FROM parking_spots LIKE 'hourly_rate'");
                $columnExists = $stmt->fetch();
                
                if ($columnExists) {
                    $stmt = $pdo->prepare("INSERT INTO parking_spots (spot_number, spot_type, zone_section, status, hourly_rate, description, is_active, created_at) VALUES (?, ?, ?, 'available', ?, ?, TRUE, NOW())");
                    $stmt->execute([$spotNumber, $spotType, $zoneSection, $hourlyRate, $description]);
                } else {
                    // Si la colonne n'existe pas, ajouter la colonne d'abord
                    $pdo->exec("ALTER TABLE parking_spots ADD COLUMN hourly_rate DECIMAL(10,2) DEFAULT 2.50 AFTER status");
                    
                    $stmt = $pdo->prepare("INSERT INTO parking_spots (spot_number, spot_type, zone_section, status, hourly_rate, description, is_active, created_at) VALUES (?, ?, ?, 'available', ?, ?, TRUE, NOW())");
                    $stmt->execute([$spotNumber, $spotType, $zoneSection, $hourlyRate, $description]);
                }
                
                $message = "Place créée avec succès";
                $messageType = 'success';
                break;
                
            case 'update_spot':
                $spotType = $_POST['spot_type'] ?? '';
                $zoneSection = trim($_POST['zone_section'] ?? '');
                $hourlyRate = floatval($_POST['hourly_rate'] ?? 2.50);
                $description = trim($_POST['description'] ?? '');
                
                // Vérifier si la colonne hourly_rate existe
                $stmt = $pdo->query("SHOW COLUMNS FROM parking_spots LIKE 'hourly_rate'");
                $columnExists = $stmt->fetch();
                
                if ($columnExists) {
                    $stmt = $pdo->prepare("UPDATE parking_spots SET spot_type = ?, zone_section = ?, hourly_rate = ?, description = ? WHERE spot_id = ?");
                    $stmt->execute([$spotType, $zoneSection, $hourlyRate, $description, $spotId]);
                } else {
                    // Si la colonne n'existe pas, l'ajouter d'abord
                    $pdo->exec("ALTER TABLE parking_spots ADD COLUMN hourly_rate DECIMAL(10,2) DEFAULT 2.50 AFTER status");
                    
                    $stmt = $pdo->prepare("UPDATE parking_spots SET spot_type = ?, zone_section = ?, hourly_rate = ?, description = ? WHERE spot_id = ?");
                    $stmt->execute([$spotType, $zoneSection, $hourlyRate, $description, $spotId]);
                }
                
                $message = "Place modifiée avec succès";
                $messageType = 'success';
                break;
        }
    } catch (Exception $e) {
        $message = "Erreur: " . $e->getMessage();
        $messageType = 'error';
    }
}

// Récupération des places de parking avec gestion des valeurs nulles
$spots = [];
$totalSpots = 0;
$availableSpots = 0;
$occupiedSpots = 0;
$maintenanceSpots = 0;

if ($dbConnected) {
    try {
        // Vérifier si la colonne hourly_rate existe avant de faire la requête
        $stmt = $pdo->query("SHOW COLUMNS FROM parking_spots LIKE 'hourly_rate'");
        $columnExists = $stmt->fetch();
        
        if ($columnExists) {
            $stmt = $pdo->query("
                SELECT ps.*, 
                       COALESCE(ps.hourly_rate, 2.50) as hourly_rate,
                       COUNT(r.reservation_id) as total_reservations,
                       MAX(r.end_datetime) as last_reservation
                FROM parking_spots ps
                LEFT JOIN reservations r ON ps.spot_id = r.spot_id
                GROUP BY ps.spot_id
                ORDER BY ps.spot_number ASC
            ");
        } else {
            // Si la colonne n'existe pas, l'ajouter
            $pdo->exec("ALTER TABLE parking_spots ADD COLUMN hourly_rate DECIMAL(10,2) DEFAULT 2.50 AFTER status");
            
            $stmt = $pdo->query("
                SELECT ps.*, 
                       COALESCE(ps.hourly_rate, 2.50) as hourly_rate,
                       COUNT(r.reservation_id) as total_reservations,
                       MAX(r.end_datetime) as last_reservation
                FROM parking_spots ps
                LEFT JOIN reservations r ON ps.spot_id = r.spot_id
                GROUP BY ps.spot_id
                ORDER BY ps.spot_number ASC
            ");
        }
        
        $spots = $stmt->fetchAll();
        
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM parking_spots WHERE is_active = 1");
        $totalSpots = $stmt->fetch()['count'];
        
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM parking_spots WHERE status = 'available' AND is_active = 1");
        $availableSpots = $stmt->fetch()['count'];
        
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM parking_spots WHERE status = 'occupied' AND is_active = 1");
        $occupiedSpots = $stmt->fetch()['count'];
        
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM parking_spots WHERE status = 'maintenance' AND is_active = 1");
        $maintenanceSpots = $stmt->fetch()['count'];
        
    } catch (Exception $e) {
        // Garder les valeurs par défaut
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des Places - ParkFinder Admin</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Space+Grotesk:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        :root {
            --primary-green: #10B981;
            --dark-green: #059669;
            --light-green: #34D399;
            --accent-green: #6EE7B7;
            --pale-green: #ECFDF5;
            
            --primary-black: #111827;
            --gray-900: #1F2937;
            --gray-800: #374151;
            --gray-700: #4B5563;
            --gray-600: #6B7280;
            --gray-300: #D1D5DB;
            --gray-100: #F3F4F6;
            --white: #FFFFFF;
            
            --font-primary: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            --font-display: 'Space Grotesk', -apple-system, BlinkMacSystemFont, sans-serif;
        }
        
        body {
            font-family: var(--font-primary);
            background: linear-gradient(135deg, var(--primary-black) 0%, var(--gray-900) 50%, var(--dark-green) 100%);
            min-height: 100vh;
            color: var(--white);
            line-height: 1.6;
            margin: 0;
        }
        
        /* Header identique au dashboard */
        .header {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-bottom: 1px solid var(--gray-300);
            padding: 1rem 0;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            position: sticky;
            top: 0;
            z-index: 50;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 1.5rem;
        }
        
        .navbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .logo {
            display: flex;
            align-items: center;
            font-family: var(--font-display);
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--primary-black);
            text-decoration: none;
        }
        
        .logo-icon {
            width: 32px;
            height: 32px;
            margin-right: 0.75rem;
            background: linear-gradient(135deg, var(--primary-green), var(--dark-green));
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--white);
            font-size: 18px;
            font-weight: 800;
            box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
        }
        
        .logo-text {
            font-weight: 800;
            background: linear-gradient(135deg, var(--primary-black), var(--gray-800));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        
        .admin-nav {
            display: flex;
            gap: 2rem;
            align-items: center;
        }
        
        .admin-nav a {
            color: var(--gray-700);
            text-decoration: none;
            font-weight: 500;
            font-size: 0.9rem;
            padding: 0.5rem 1rem;
            border-radius: 0.75rem;
            transition: all 0.3s ease;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .admin-nav a:hover, .admin-nav a.active {
            color: var(--primary-green);
            background: var(--pale-green);
            transform: translateY(-1px);
        }
        
        .admin-badge {
            background: linear-gradient(135deg, #dc3545, #c82333);
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 1.5rem;
            font-size: 0.8rem;
            font-weight: 600;
            letter-spacing: 0.5px;
        }
        
        /* Contenu principal */
        .main-content {
            padding: 3rem 0;
        }
        
        .page-header {
            text-align: center;
            margin-bottom: 3rem;
        }
        
        .page-title {
            font-family: var(--font-display);
            font-size: 2.5rem;
            font-weight: 800;
            margin-bottom: 1rem;
            background: linear-gradient(135deg, var(--white) 0%, var(--accent-green) 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        
        .page-subtitle {
            font-size: 1.125rem;
            opacity: 0.9;
            font-weight: 300;
        }
        
        /* Messages */
        .message {
            padding: 1rem;
            border-radius: 0.75rem;
            margin-bottom: 2rem;
            text-align: center;
            font-weight: 500;
        }
        
        .message.success {
            background: var(--pale-green);
            color: var(--dark-green);
            border: 1px solid var(--primary-green);
        }
        
        .message.error {
            background: #FEF2F2;
            color: #DC2626;
            border: 1px solid #F87171;
        }
        
        /* Statistiques */
        .stats-overview {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
            margin-bottom: 3rem;
        }
        
        .stat-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            padding: 1.5rem;
            border-radius: 1rem;
            text-align: center;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            border-left: 4px solid var(--primary-green);
        }
        
        .stat-number {
            font-family: var(--font-display);
            font-size: 2rem;
            font-weight: 800;
            color: var(--primary-green);
            margin-bottom: 0.5rem;
        }
        
        .stat-label {
            font-weight: 600;
            color: var(--gray-800);
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        /* Section principale */
        .users-section {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-radius: 1.5rem;
            padding: 2rem;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
            margin-bottom: 2rem;
        }
        
        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
            flex-wrap: wrap;
            gap: 1rem;
        }
        
        .section-title {
            font-family: var(--font-display);
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--primary-black);
        }
        
        .btn {
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 0.5rem;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.3s ease;
            font-size: 0.9rem;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, var(--primary-green), var(--dark-green));
            color: white;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(16, 185, 129, 0.4);
        }
        
        .btn-danger {
            background: #dc3545;
            color: white;
        }
        
        .btn-warning {
            background: #ffc107;
            color: var(--gray-800);
        }
        
        .btn-small {
            padding: 0.5rem 0.75rem;
            font-size: 0.8rem;
        }
        
        /* Styles spécifiques aux places */
        .spot-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-radius: 1rem;
            padding: 1.5rem;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
            border-left: 4px solid var(--primary-green);
        }
        
        .spot-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
        }
        
        .spot-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 1rem;
            flex-wrap: wrap;
            gap: 0.5rem;
        }
        
        .spot-number {
            font-family: var(--font-display);
            font-size: 1.5rem;
            font-weight: 800;
            color: var(--primary-black);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .spot-badges {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
            align-items: flex-end;
        }
        
        .spot-status {
            padding: 0.35rem 0.75rem;
            border-radius: 1rem;
            font-size: 0.8rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .status-available {
            background: var(--pale-green);
            color: var(--dark-green);
        }
        
        .status-occupied {
            background: #FEF2F2;
            color: #DC2626;
        }
        
        .status-maintenance {
            background: #FFF7ED;
            color: #EA580C;
        }
        
        .status-reserved {
            background: #EFF6FF;
            color: #2563EB;
        }
        
        .spot-type {
            padding: 0.25rem 0.75rem;
            border-radius: 1rem;
            font-size: 0.8rem;
            font-weight: 600;
            background: var(--gray-100);
            color: var(--gray-800);
        }
        
        .spot-info {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
            margin-bottom: 1rem;
            padding: 1rem;
            background: var(--gray-100);
            border-radius: 0.5rem;
        }
        
        .spot-info div {
            color: var(--gray-700);
            font-size: 0.9rem;
        }
        
        .spot-info strong {
            color: var(--gray-800);
            font-weight: 600;
        }
        
        .spots-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
            gap: 1.5rem;
            margin-top: 2rem;
        }
        
        .inactive-spot {
            opacity: 0.6;
            filter: grayscale(50%);
        }
        
        .spot-description {
            margin: 1rem 0;
            color: var(--gray-600);
            font-size: 0.9rem;
            font-style: italic;
            padding: 0.75rem;
            background: var(--pale-green);
            border-radius: 0.5rem;
            border-left: 3px solid var(--primary-green);
        }
        
        .spot-actions {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
            margin-top: 1rem;
            padding-top: 1rem;
            border-top: 1px solid var(--gray-300);
        }
        
        .quick-status-change {
            margin-bottom: 1rem;
            padding: 1rem;
            background: var(--white);
            border-radius: 0.5rem;
            border: 1px solid var(--gray-300);
        }
        
        .quick-status-change strong {
            display: block;
            color: var(--gray-700);
            font-size: 0.9rem;
            margin-bottom: 0.75rem;
        }
        
        .status-buttons {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
        }
        
        .status-btn {
            padding: 0.5rem 1rem;
            border: none;
            border-radius: 0.5rem;
            font-size: 0.8rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .status-btn:hover {
            transform: translateY(-1px);
        }
        
        .status-btn.available {
            background: var(--pale-green);
            color: var(--dark-green);
        }
        
        .status-btn.occupied {
            background: #FEF2F2;
            color: #DC2626;
        }
        
        .status-btn.maintenance {
            background: #FFF7ED;
            color: #EA580C;
        }
        
        .status-btn.reserved {
            background: #EFF6FF;
            color: #2563EB;
        }
        
        /* Modal styles */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }
        
        .modal.show {
            display: flex;
        }
        
        .modal-content {
            background: white;
            border-radius: 1rem;
            padding: 2rem;
            max-width: 500px;
            width: 90%;
            max-height: 90vh;
            overflow-y: auto;
        }
        
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
        }
        
        .modal-title {
            font-family: var(--font-display);
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--primary-black);
        }
        
        .close {
            background: none;
            border: none;
            font-size: 1.5rem;
            cursor: pointer;
            color: var(--gray-600);
        }
        
        .form-group {
            margin-bottom: 1.5rem;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: var(--gray-800);
        }
        
        .form-group input,
        .form-group select {
            width: 100%;
            padding: 0.75rem;
            border: 2px solid var(--gray-300);
            border-radius: 0.5rem;
            font-size: 1rem;
            transition: border-color 0.3s ease;
        }
        
        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            border-color: var(--primary-green);
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .admin-nav {
                gap: 1rem;
                flex-wrap: wrap;
            }
            
            .page-title {
                font-size: 2rem;
            }
            
            .stats-overview {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .spots-grid {
                grid-template-columns: 1fr;
            }
            
            .spot-info {
                grid-template-columns: 1fr;
                gap: 0.5rem;
            }
            
            .spot-header {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .spot-badges {
                align-items: flex-start;
                flex-direction: row;
                flex-wrap: wrap;
            }
        }
        
        @media (max-width: 480px) {
            .stats-overview {
                grid-template-columns: 1fr;
            }
            
            .status-buttons {
                justify-content: center;
            }
        }
    </style>
</head>
<body>
    <!-- Header identique -->
    <header class="header">
        <div class="container">
            <nav class="navbar">
                <a href="index.php" class="logo">
                    <div class="logo-icon">P</div>
                    <span class="logo-text">ParkFinder</span>
                </a>
                
                <div class="admin-nav">
                    <a href="dashboard.php">Tableau de bord</a>
                    <a href="admin-users.php">Utilisateurs</a>
                    <a href="admin-spots.php" class="active">Places</a>
                    <a href="admin-reservations.php">Réservations</a>
                    <a href="index.php">Retour site</a>
                </div>
                
                <div class="admin-badge">
                    👑 ADMIN
                </div>
            </nav>
        </div>
    </header>

    <!-- Contenu principal -->
    <main class="main-content">
        <div class="container">
            <!-- En-tête de page -->
            <div class="page-header">
                <h1 class="page-title">Gestion des Places de Parking</h1>
                <p class="page-subtitle">Administration des places de stationnement</p>
            </div>

            <!-- Message -->
            <?php if ($message): ?>
                <div class="message <?= $messageType ?>">
                    <?= htmlspecialchars($message) ?>
                </div>
            <?php endif; ?>

            <!-- Statistiques -->
            <div class="stats-overview">
                <div class="stat-card">
                    <div class="stat-number"><?= $totalSpots ?></div>
                    <div class="stat-label">Places totales</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?= $availableSpots ?></div>
                    <div class="stat-label">Places disponibles</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?= $occupiedSpots ?></div>
                    <div class="stat-label">Places occupées</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?= $maintenanceSpots ?></div>
                    <div class="stat-label">En maintenance</div>
                </div>
            </div>

            <!-- Section places -->
            <div class="users-section">
                <div class="section-header">
                    <h2 class="section-title">Gestion des Places</h2>
                    <button class="btn btn-primary" onclick="openAddSpotModal()">
                        ➕ Ajouter une place
                    </button>
                </div>

                <?php if ($dbConnected && !empty($spots)): ?>
                    <div class="spots-grid">
                        <?php foreach ($spots as $spot): ?>
                            <div class="spot-card <?= $spot['is_active'] ? '' : 'inactive-spot' ?>">
                                <div class="spot-header">
                                    <div class="spot-number">🅿️ <?= htmlspecialchars($spot['spot_number']) ?></div>
                                    <div class="spot-badges">
                                        <span class="spot-status status-<?= $spot['status'] ?>">
                                            <?= ucfirst($spot['status']) ?>
                                        </span>
                                        <?php if (!$spot['is_active']): ?>
                                            <span class="spot-status" style="background: #6c757d; color: white;">Inactive</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <div class="spot-info">
                                    <div><strong>Type:</strong> <?= htmlspecialchars($spot['spot_type']) ?></div>
                                    <div><strong>Zone:</strong> <?= htmlspecialchars($spot['zone_section'] ?? 'Non définie') ?></div>
                                    <div><strong>Tarif:</strong> <?= number_format(floatval($spot['hourly_rate'] ?? 0), 2) ?>€/h</div>
                                    <div><strong>Réservations:</strong> <?= $spot['total_reservations'] ?></div>
                                </div>
                                
                                <?php if (!empty($spot['description'])): ?>
                                    <div class="spot-description">
                                        💬 <?= htmlspecialchars($spot['description']) ?>
                                    </div>
                                <?php endif; ?>
                                
                                <div class="quick-status-change">
                                    <strong>Changer le statut :</strong>
                                    <div class="status-buttons">
                                        <?php 
                                        $statuses = [
                                            'available' => '✅ Disponible',
                                            'occupied' => '🚗 Occupée',
                                            'maintenance' => '🔧 Maintenance',
                                            'reserved' => '📅 Réservée'
                                        ];
                                        ?>
                                        <?php foreach ($statuses as $status => $label): ?>
                                            <?php if ($status !== $spot['status']): ?>
                                                <form method="post" style="display: inline;">
                                                    <input type="hidden" name="action" value="toggle_status">
                                                    <input type="hidden" name="spot_id" value="<?= $spot['spot_id'] ?>">
                                                    <input type="hidden" name="new_status" value="<?= $status ?>">
                                                    <button type="submit" class="status-btn <?= $status ?>" title="<?= $label ?>">
                                                        <?= explode(' ', $label)[1] ?>
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                                
                                <div class="spot-actions">
                                    <button class="btn btn-small btn-primary" onclick="openEditSpotModal(<?= htmlspecialchars(json_encode($spot)) ?>)">
                                        ✏️ Modifier
                                    </button>
                                    
                                    <form method="post" style="display: inline;">
                                        <input type="hidden" name="action" value="toggle_active">
                                        <input type="hidden" name="spot_id" value="<?= $spot['spot_id'] ?>">
                                        <button type="submit" class="btn btn-small btn-warning">
                                            <?= $spot['is_active'] ? '⏸️ Désactiver' : '▶️ Activer' ?>
                                        </button>
                                    </form>
                                    
                                    <form method="post" style="display: inline;" onsubmit="return confirm('Êtes-vous sûr de vouloir supprimer cette place ?')">
                                        <input type="hidden" name="action" value="delete_spot">
                                        <input type="hidden" name="spot_id" value="<?= $spot['spot_id'] ?>">
                                        <button type="submit" class="btn btn-small btn-danger">
                                            🗑️ Supprimer
                                        </button>
                                    </form>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p style="text-align: center; color: var(--gray-600); margin: 2rem 0;">
                        <?= $dbConnected ? 'Aucune place de parking trouvée.' : 'Erreur de connexion à la base de données.' ?>
                    </p>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <!-- Modal d'ajout de place -->
    <div id="addSpotModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Ajouter une place de parking</h3>
                <button class="close" onclick="closeAddSpotModal()">&times;</button>
            </div>
            
            <form method="post">
                <input type="hidden" name="action" value="add_spot">
                
                <div class="form-group">
                    <label for="spot_number">Numéro de place *</label>
                    <input type="text" id="spot_number" name="spot_number" required placeholder="Ex: A01, B15, C22">
                </div>
                
                <div class="form-group">
                    <label for="spot_type">Type de place *</label>
                    <select id="spot_type" name="spot_type" required>
                        <option value="standard">Standard</option>
                        <option value="compact">Compact</option>
                        <option value="large">Large</option>
                        <option value="electric">Électrique</option>
                        <option value="handicapped">PMR</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="zone_section">Zone/Section</label>
                    <input type="text" id="zone_section" name="zone_section" placeholder="Ex: Zone A, Niveau 1, Secteur Nord">
                </div>
                
                <div class="form-group">
                    <label for="hourly_rate">Tarif horaire (€) *</label>
                    <input type="number" id="hourly_rate" name="hourly_rate" step="0.01" min="0" required placeholder="2.50">
                </div>
                
                <div class="form-group">
                    <label for="description">Description</label>
                    <input type="text" id="description" name="description" placeholder="Informations supplémentaires">
                </div>
                
                <div style="display: flex; gap: 1rem; justify-content: flex-end;">
                    <button type="button" class="btn" onclick="closeAddSpotModal()" style="background: var(--gray-300); color: var(--gray-800);">
                        Annuler
                    </button>
                    <button type="submit" class="btn btn-primary">
                        Créer la place
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal de modification de place -->
    <div id="editSpotModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Modifier la place</h3>
                <button class="close" onclick="closeEditSpotModal()">&times;</button>
            </div>
            
            <form method="post" id="editSpotForm">
                <input type="hidden" name="action" value="update_spot">
                <input type="hidden" name="spot_id" id="edit_spot_id">
                
                <div class="form-group">
                    <label for="edit_spot_type">Type de place *</label>
                    <select id="edit_spot_type" name="spot_type" required>
                        <option value="standard">Standard</option>
                        <option value="compact">Compact</option>
                        <option value="large">Large</option>
                        <option value="electric">Électrique</option>
                        <option value="handicapped">PMR</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="edit_zone_section">Zone/Section</label>
                    <input type="text" id="edit_zone_section" name="zone_section">
                </div>
                
                <div class="form-group">
                    <label for="edit_hourly_rate">Tarif horaire (€) *</label>
                    <input type="number" id="edit_hourly_rate" name="hourly_rate" step="0.01" min="0" required>
                </div>
                
                <div class="form-group">
                    <label for="edit_description">Description</label>
                    <input type="text" id="edit_description" name="description">
                </div>
                
                <div style="display: flex; gap: 1rem; justify-content: flex-end;">
                    <button type="button" class="btn" onclick="closeEditSpotModal()" style="background: var(--gray-300); color: var(--gray-800);">
                        Annuler
                    </button>
                    <button type="submit" class="btn btn-primary">
                        Modifier la place
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openAddSpotModal() {
            document.getElementById('addSpotModal').classList.add('show');
        }
        
        function closeAddSpotModal() {
            document.getElementById('addSpotModal').classList.remove('show');
        }
        
        function openEditSpotModal(spot) {
            document.getElementById('edit_spot_id').value = spot.spot_id;
            document.getElementById('edit_spot_type').value = spot.spot_type;
            document.getElementById('edit_zone_section').value = spot.zone_section || '';
            document.getElementById('edit_hourly_rate').value = spot.hourly_rate;
            document.getElementById('edit_description').value = spot.description || '';
            
            document.getElementById('editSpotModal').classList.add('show');
        }
        
        function closeEditSpotModal() {
            document.getElementById('editSpotModal').classList.remove('show');
        }
        
        // Fermer les modals en cliquant à l'extérieur
        document.addEventListener('click', function(e) {
            if (e.target.classList.contains('modal')) {
                e.target.classList.remove('show');
            }
        });
    </script>
</body>
</html>