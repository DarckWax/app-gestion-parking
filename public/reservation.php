<?php
session_start();

// Rediriger si non connecté
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php#login');
    exit;
}

// Configuration de base
$config = [
    'db_host' => 'localhost',
    'db_name' => 'parking_management_system', 
    'db_user' => 'root',
    'db_pass' => ''
];

// Connexion base de données
try {
    $pdo = new PDO("mysql:host={$config['db_host']};dbname={$config['db_name']}", $config['db_user'], $config['db_pass']);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $dbConnected = true;
} catch(Exception $e) {
    $dbConnected = false;
}

// Récupérer les places de parking
$parkingSpots = [];
if ($dbConnected) {
    try {
        $stmt = $pdo->query("
            SELECT spot_id, spot_number, spot_type, status, floor_level, zone_section, description
            FROM parking_spots 
            WHERE is_active = 1 
            ORDER BY zone_section, spot_number
        ");
        $parkingSpots = $stmt->fetchAll();
    } catch(Exception $e) {
        // Garder le tableau vide
    }
}

// Récupérer les règles de tarification
$pricingRules = [];
if ($dbConnected) {
    try {
        $stmt = $pdo->query("
            SELECT * FROM pricing_rules 
            WHERE is_active = 1 
            ORDER BY spot_type, time_period
        ");
        $pricingRules = $stmt->fetchAll();
    } catch(Exception $e) {
        // Garder le tableau vide
    }
}

// Ajouter le middleware pour les vérifications
require_once '../app/middlewares/AdminMiddleware.php';
use App\Middlewares\AdminMiddleware;
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Réserver une place - ParkFinder</title>
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
            background: linear-gradient(135deg, var(--primary-black) 0%, var(--gray-900) 100%);
            min-height: 100vh;
            color: var(--white);
        }
        
        .header {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            padding: 1rem 0;
            position: sticky;
            top: 0;
            z-index: 100;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        }
        
        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 0 1.5rem;
        }
        
        .navbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .logo {
            font-family: var(--font-display);
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--primary-black);
            text-decoration: none;
            display: flex;
            align-items: center;
            transition: all 0.3s ease;
        }
        
        .logo-icon {
            width: 28px;
            height: 28px;
            margin-right: 0.75rem;
            background: linear-gradient(135deg, var(--primary-green), var(--dark-green));
            border-radius: 6px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--white);
            font-size: 16px;
            font-weight: 800;
            box-shadow: 0 3px 10px rgba(16, 185, 129, 0.3);
            position: relative;
            overflow: hidden;
        }
        
        .logo-icon::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: linear-gradient(45deg, transparent, rgba(255,255,255,0.1), transparent);
            transform: rotate(45deg);
            transition: all 0.5s ease;
            opacity: 0;
        }
        
        .logo:hover .logo-icon::before {
            opacity: 1;
            animation: shine 0.8s ease-in-out;
        }
        
        .logo-text {
            font-weight: 800;
            background: linear-gradient(135deg, var(--primary-black), var(--gray-700));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        
        .logo:hover {
            transform: translateY(-1px);
        }
        
        .logo:hover .logo-icon {
            transform: scale(1.05);
            box-shadow: 0 5px 15px rgba(16, 185, 129, 0.4);
        }
        
        @keyframes shine {
            0% { transform: translateX(-100%) translateY(-100%) rotate(45deg); }
            100% { transform: translateX(100%) translateY(100%) rotate(45deg); }
        }
        
        body {
            font-family: var(--font-primary);
            background: linear-gradient(135deg, var(--primary-black) 0%, var(--gray-900) 100%);
            min-height: 100vh;
            color: var(--white);
        }
        
        .header {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            padding: 1rem 0;
            position: sticky;
            top: 0;
            z-index: 100;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        }
        
        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 0 1.5rem;
        }
        
        .navbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .logo {
            font-family: var(--font-display);
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--primary-black);
            text-decoration: none;
            display: flex;
            align-items: center;
            transition: all 0.3s ease;
        }
        
        .logo-icon {
            width: 28px;
            height: 28px;
            margin-right: 0.75rem;
            background: linear-gradient(135deg, var(--primary-green), var(--dark-green));
            border-radius: 6px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--white);
            font-size: 16px;
            font-weight: 800;
            box-shadow: 0 3px 10px rgba(16, 185, 129, 0.3);
            position: relative;
            overflow: hidden;
        }
        
        .logo-icon::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: linear-gradient(45deg, transparent, rgba(255,255,255,0.1), transparent);
            transform: rotate(45deg);
            transition: all 0.5s ease;
            opacity: 0;
        }
        
        .logo:hover .logo-icon::before {
            opacity: 1;
            animation: shine 0.8s ease-in-out;
        }
        
        .logo-text {
            font-weight: 800;
            background: linear-gradient(135deg, var(--primary-black), var(--gray-700));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        
        .logo:hover {
            transform: translateY(-1px);
        }
        
        .logo:hover .logo-icon {
            transform: scale(1.05);
            box-shadow: 0 5px 15px rgba(16, 185, 129, 0.4);
        }
        
        @keyframes shine {
            0% { transform: translateX(-100%) translateY(-100%) rotate(45deg); }
            100% { transform: translateX(100%) translateY(100%) rotate(45deg); }
        }
        
        /* Écran de sélection de dates */
        .date-selection-screen {
            min-height: calc(100vh - 80px);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem;
        }
        
        .date-selection-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-radius: 1.5rem;
            padding: 3rem;
            max-width: 600px;
            width: 100%;
            color: var(--primary-black);
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
            text-align: center;
        }
        
        .date-selection-title {
            font-family: var(--font-display);
            font-size: 2.5rem;
            font-weight: 800;
            margin-bottom: 1rem;
            background: linear-gradient(135deg, var(--primary-green), var(--dark-green));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        
        .date-selection-subtitle {
            font-size: 1.125rem;
            color: var(--gray-600);
            margin-bottom: 2rem;
            font-weight: 300;
        }
        
        .date-form {
            display: grid;
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .form-group {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
            text-align: left;
        }
        
        .form-label {
            font-weight: 600;
            color: var(--gray-800);
            font-size: 0.875rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .form-input {
            padding: 1rem;
            border: 2px solid var(--gray-300);
            border-radius: 0.75rem;
            font-size: 1rem;
            transition: all 0.3s ease;
            font-family: var(--font-primary);
        }
        
        .form-input:focus {
            outline: none;
            border-color: var(--primary-green);
            box-shadow: 0 0 0 4px rgba(16, 185, 129, 0.1);
            transform: translateY(-1px);
        }
        
        .btn {
            padding: 1rem 2rem;
            background: var(--primary-green);
            color: var(--white);
            border: none;
            border-radius: 0.75rem;
            font-weight: 600;
            font-size: 1rem;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
        }
        
        .btn:hover {
            background: var(--dark-green);
            transform: translateY(-2px);
            box-shadow: 0 10px 25px -5px rgba(16, 185, 129, 0.3);
        }
        
        .btn:disabled {
            background: var(--gray-300);
            cursor: not-allowed;
            transform: none;
        }
        
        /* Interface principale de réservation */
        .reservation-interface {
            display: none;
            height: calc(100vh - 80px);
            padding: 1.5rem;
            gap: 1.5rem;
        }
        
        .reservation-interface.active {
            display: grid;
            grid-template-columns: 2fr 1fr;
        }
        
        /* Section des places (2/3 gauche) */
        .parking-section {
            background: var(--white);
            border-radius: 1.5rem;
            padding: 2rem;
            color: var(--primary-black);
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }
        
        .parking-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
            flex-wrap: wrap;
            gap: 1rem;
        }
        
        .parking-title {
            font-family: var(--font-display);
            font-size: 1.875rem;
            font-weight: 700;
            color: var(--primary-black);
        }
        
        .selected-date-info {
            background: var(--pale-green);
            padding: 0.75rem 1rem;
            border-radius: 0.5rem;
            font-size: 0.875rem;
            font-weight: 500;
            color: var(--dark-green);
        }
        
        .parking-legend {
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
            margin-bottom: 1.5rem;
        }
        
        .legend-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.875rem;
            font-weight: 500;
        }
        
        .legend-color {
            width: 16px;
            height: 16px;
            border-radius: 4px;
        }
        
        .parking-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(120px, 1fr));
            gap: 1rem;
            max-height: calc(100% - 200px);
            overflow-y: auto;
            padding: 1rem;
            background: var(--gray-100);
            border-radius: 1rem;
        }
        
        .parking-spot {
            aspect-ratio: 1;
            border: 2px solid var(--gray-300);
            border-radius: 0.75rem;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            cursor: pointer;
            transition: all 0.3s ease;
            position: relative;
            font-weight: 600;
            font-size: 0.875rem;
        }
        
        .parking-spot:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        }
        
        .parking-spot.available {
            background: var(--pale-green);
            border-color: var(--primary-green);
        }
        
        .parking-spot.available:hover {
            background: var(--light-green);
        }
        
        .parking-spot.occupied {
            background: #FEE2E2;
            border-color: #EF4444;
            cursor: not-allowed;
            opacity: 0.6;
        }
        
        .parking-spot.maintenance {
            background: #FEF3C7;
            border-color: #F59E0B;
            cursor: not-allowed;
            opacity: 0.6;
        }
        
        .parking-spot.selected {
            background: var(--primary-green);
            border-color: var(--dark-green);
            color: var(--white);
            transform: scale(1.05);
            box-shadow: 0 0 0 4px rgba(16, 185, 129, 0.3);
        }
        
        .spot-number {
            font-size: 1rem;
            font-weight: 700;
            margin-bottom: 0.25rem;
        }
        
        .spot-type {
            font-size: 0.75rem;
            opacity: 0.8;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .spot-icon {
            position: absolute;
            top: 4px;
            right: 4px;
            font-size: 0.75rem;
        }
        
        /* Section droite (1/3 droite) */
        .booking-sidebar {
            display: flex;
            flex-direction: column;
            gap: 1.5rem;
        }
        
        .sidebar-section {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-radius: 1.5rem;
            padding: 1.5rem;
            color: var(--primary-black);
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1);
        }
        
        .sidebar-title {
            font-family: var(--font-display);
            font-size: 1.25rem;
            font-weight: 700;
            color: var(--primary-black);
            margin-bottom: 1rem;
            border-bottom: 2px solid var(--primary-green);
            padding-bottom: 0.5rem;
        }
        
        .selected-spot-info {
            background: var(--pale-green);
            padding: 1rem;
            border-radius: 0.75rem;
            border: 1px solid var(--primary-green);
            margin-bottom: 1rem;
        }
        
        .info-item {
            display: flex;
            justify-content: space-between;
            margin-bottom: 0.5rem;
            font-size: 0.875rem;
        }
        
        .info-label {
            color: var(--gray-600);
            font-weight: 500;
        }
        
        .info-value {
            color: var(--primary-black);
            font-weight: 600;
        }
        
        .pricing-display {
            background: var(--gray-100);
            padding: 1rem;
            border-radius: 0.75rem;
            border: 2px solid var(--gray-300);
        }
        
        .price-item {
            display: flex;
            justify-content: space-between;
            margin-bottom: 0.5rem;
            font-size: 0.875rem;
        }
        
        .price-total {
            display: flex;
            justify-content: space-between;
            font-weight: 700;
            font-size: 1.125rem;
            color: var(--primary-green);
            border-top: 2px solid var(--gray-300);
            padding-top: 0.75rem;
            margin-top: 0.75rem;
        }
        
        .empty-state {
            text-align: center;
            padding: 2rem;
            color: var(--gray-600);
        }
        
        .change-date-btn {
            background: var(--gray-600);
            font-size: 0.875rem;
            padding: 0.75rem 1rem;
        }
        
        .change-date-btn:hover {
            background: var(--gray-700);
        }
        
        /* Responsive Design */
        @media (max-width: 1200px) {
            .parking-grid {
                grid-template-columns: repeat(auto-fill, minmax(100px, 1fr));
            }
        }
        
        @media (max-width: 1024px) {
            .reservation-interface.active {
                grid-template-columns: 1fr;
                grid-template-rows: 1fr auto;
            }
            
            .parking-section {
                min-height: 60vh;
            }
            
            .booking-sidebar {
                flex-direction: row;
                overflow-x: auto;
                gap: 1rem;
            }
            
            .sidebar-section {
                min-width: 300px;
                flex-shrink: 0;
            }
        }
        
        @media (max-width: 768px) {
            .container {
                padding: 0 1rem;
            }
            
            .reservation-interface {
                padding: 1rem;
                gap: 1rem;
            }
            
            .date-selection-card {
                padding: 2rem;
                margin: 1rem;
            }
            
            .date-selection-title {
                font-size: 2rem;
            }
            
            .parking-grid {
                grid-template-columns: repeat(auto-fill, minmax(80px, 1fr));
                gap: 0.75rem;
            }
            
            .parking-spot {
                font-size: 0.75rem;
            }
            
            .booking-sidebar {
                flex-direction: column;
            }
            
            .sidebar-section {
                min-width: auto;
            }
            
            .parking-header {
                flex-direction: column;
                align-items: flex-start;
            }
        }
        
        @media (max-width: 480px) {
            .parking-grid {
                grid-template-columns: repeat(auto-fill, minmax(70px, 1fr));
                gap: 0.5rem;
            }
            
            .date-form {
                gap: 1rem;
            }
            
            .form-input {
                padding: 0.75rem;
            }
            
            .btn {
                padding: 0.75rem 1.5rem;
                font-size: 0.875rem;
            }
        }
    </style>
</head>
<body>
    <!-- Header -->
    <header class="header">
        <div class="container">
            <nav class="navbar">
                <a href="index.php" class="logo">🚗 ParkFinder</a>
                <a href="index.php" class="back-link">
                    ← Retour à l'accueil
                </a>
            </nav>
        </div>
    </header>

    <!-- Écran de sélection de dates -->
    <div id="dateSelectionScreen" class="date-selection-screen">
        <div class="date-selection-card">
            <h1 class="date-selection-title">Réserver une place</h1>
            <p class="date-selection-subtitle">
                Sélectionnez votre période de stationnement pour voir les places disponibles
            </p>
            
            <form class="date-form" id="dateForm">
                <div class="form-group">
                    <label for="startDateTime" class="form-label">Date et heure de début</label>
                    <input type="datetime-local" 
                           id="startDateTime" 
                           name="startDateTime" 
                           class="form-input"
                           min="<?= date('Y-m-d\TH:i') ?>"
                           required>
                </div>
                
                <div class="form-group">
                    <label for="endDateTime" class="form-label">Date et heure de fin</label>
                    <input type="datetime-local" 
                           id="endDateTime" 
                           name="endDateTime" 
                           class="form-input"
                           min="<?= date('Y-m-d\TH:i') ?>"
                           required>
                </div>
            </form>
            
            <button type="button" onclick="proceedToSpotSelection()" class="btn" id="proceedToSpotSelectionBtn">
                🔍 Voir les places disponibles
            </button>
        </div>
    </div>

    <!-- Interface principale de réservation -->
    <div class="container">
        <div id="reservationInterface" class="reservation-interface">
            <!-- Section des places (2/3 gauche) -->
            <div class="parking-section">
                <div class="parking-header">
                    <h1 class="parking-title">Places de parking</h1>
                    <div class="selected-date-info" id="selectedDateInfo">
                        -
                    </div>
                    <button onclick="changeDates()" class="btn change-date-btn">
                        📅 Changer les dates
                    </button>
                </div>
                
                <div class="parking-legend">
                    <div class="legend-item">
                        <div class="legend-color" style="background: var(--pale-green); border: 1px solid var(--primary-green);"></div>
                        <span>Disponible</span>
                    </div>
                    <div class="legend-item">
                        <div class="legend-color" style="background: #FEE2E2; border: 1px solid #EF4444;"></div>
                        <span>Occupée</span>
                    </div>
                    <div class="legend-item">
                        <div class="legend-color" style="background: #FEF3C7; border: 1px solid #F59E0B;"></div>
                        <span>Maintenance</span>
                    </div>
                    <div class="legend-item">
                        <div class="legend-color" style="background: var(--primary-green);"></div>
                        <span>Sélectionnée</span>
                    </div>
                </div>
                
                <div class="parking-grid" id="parkingGrid">
                    <?php if (empty($parkingSpots)): ?>
                        <div class="empty-state">
                            <h3>Aucune place disponible</h3>
                            <p>La base de données n'est pas configurée.</p>
                            <a href="create-db.php" style="color: var(--primary-green);">Créer la base de données</a>
                        </div>
                    <?php else: ?>
                        <?php foreach ($parkingSpots as $spot): ?>
                            <div class="parking-spot <?= $spot['status'] ?>" 
                                 data-spot-id="<?= $spot['spot_id'] ?>"
                                 data-spot-number="<?= htmlspecialchars($spot['spot_number']) ?>"
                                 data-spot-type="<?= $spot['spot_type'] ?>"
                                 data-zone="<?= htmlspecialchars($spot['zone_section']) ?>"
                                 data-floor="<?= $spot['floor_level'] ?>"
                                 onclick="selectSpot(this)">
                                <div class="spot-number"><?= htmlspecialchars($spot['spot_number']) ?></div>
                                <div class="spot-type">
                                    <?php
                                    $typeLabels = [
                                        'standard' => 'Standard',
                                        'disabled' => 'PMR',
                                        'electric' => 'Électrique',
                                        'reserved' => 'VIP',
                                        'compact' => 'Compact'
                                    ];
                                    echo $typeLabels[$spot['spot_type']] ?? $spot['spot_type'];
                                    ?>
                                </div>
                                <div class="spot-icon">
                                    <?php
                                    $icons = [
                                        'standard' => '🚗',
                                        'disabled' => '♿',
                                        'electric' => '⚡',
                                        'reserved' => '⭐',
                                        'compact' => '🚙'
                                    ];
                                    echo $icons[$spot['spot_type']] ?? '🚗';
                                    ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Sidebar droite (1/3 droite) -->
            <div class="booking-sidebar">
                <!-- Informations de la place -->
                <div class="sidebar-section">
                    <h2 class="sidebar-title">📍 Place sélectionnée</h2>
                    
                    <div id="selectedSpotInfo" style="display: none;">
                        <div class="selected-spot-info">
                            <div class="info-item">
                                <span class="info-label">Numéro:</span>
                                <span class="info-value" id="spotNumber">-</span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Type:</span>
                                <span class="info-value" id="spotType">-</span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Zone:</span>
                                <span class="info-value" id="spotZone">-</span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Étage:</span>
                                <span class="info-value" id="spotFloor">-</span>
                            </div>
                        </div>
                    </div>
                    
                    <div id="noSpotSelected" class="empty-state">
                        <p>👆 Sélectionnez une place disponible</p>
                    </div>
                </div>

                <!-- Tarification -->
                <div class="sidebar-section">
                    <h2 class="sidebar-title">💰 Tarification</h2>
                    
                    <div class="pricing-display" id="pricingDisplay">
                        <div class="price-item">
                            <span>Durée:</span>
                            <span id="duration">-</span>
                        </div>
                        <div class="price-item">
                            <span>Tarif de base:</span>
                            <span id="basePrice">-</span>
                        </div>
                        <div class="price-item">
                            <span>Tarif horaire:</span>
                            <span id="hourlyRate">-</span>
                        </div>
                        <div class="price-total">
                            <span>Total:</span>
                            <span id="totalPrice">0,00 €</span>
                        </div>
                    </div>
                    
                    <button type="button" 
                            id="proceedPayment" 
                            class="btn" 
                            onclick="proceedToPayment()"
                            disabled
                            style="width: 100%; margin-top: 1rem;">
                        💳 Procéder au paiement
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Variables globales
        var selectedSpot = null;
        var selectedDates = null;
        var pricingRules = <?= json_encode($pricingRules) ?>;
        
        // Fonction pour procéder à la sélection de place
        function proceedToSpotSelection() {
            var startInput = document.getElementById('startDateTime');
            var endInput = document.getElementById('endDateTime');
            
            if (!startInput || !endInput) {
                alert('Erreur: Champs de date non trouvés.');
                return false;
            }
            
            var startDate = startInput.value;
            var endDate = endInput.value;
            
            if (!startDate || !endDate) {
                alert('Veuillez sélectionner les dates de début et de fin.');
                return false;
            }
            
            if (new Date(endDate) <= new Date(startDate)) {
                alert('La date de fin doit être postérieure à la date de début.');
                return false;
            }
            
            selectedDates = { start: startDate, end: endDate };
            
            var dateScreen = document.getElementById('dateSelectionScreen');
            var reservationInterface = document.getElementById('reservationInterface');
            
            if (dateScreen && reservationInterface) {
                dateScreen.style.display = 'none';
                reservationInterface.classList.add('active');
                updateSelectedDateDisplay();
                filterAvailableSpots();
                return true;
            } else {
                alert('Erreur: Éléments de l\'interface non trouvés.');
                return false;
            }
        }
        
        // Fonction pour changer les dates
        function changeDates() {
            var dateScreen = document.getElementById('dateSelectionScreen');
            var reservationInterface = document.getElementById('reservationInterface');
            
            if (dateScreen && reservationInterface) {
                dateScreen.style.display = 'flex';
                reservationInterface.classList.remove('active');
                selectedSpot = null;
                updateSelectedSpotDisplay();
            }
        }
        
        // Fonction pour mettre à jour l'affichage des dates sélectionnées
        function updateSelectedDateDisplay() {
            if (!selectedDates) return;
            
            var start = new Date(selectedDates.start);
            var end = new Date(selectedDates.end);
            
            var formatDate = function(date) {
                return date.toLocaleDateString('fr-FR', {
                    day: '2-digit',
                    month: '2-digit',
                    year: 'numeric',
                    hour: '2-digit',
                    minute: '2-digit'
                });
            };
            
            var dateInfoElement = document.getElementById('selectedDateInfo');
            if (dateInfoElement) {
                dateInfoElement.textContent = formatDate(start) + ' → ' + formatDate(end);
            }
        }
        
        // Fonction pour filtrer les places disponibles
        function filterAvailableSpots() {
            var spots = document.querySelectorAll('.parking-spot');
            
            for (var i = 0; i < spots.length; i++) {
                var spot = spots[i];
                var random = Math.random();
                if (random < 0.15) {
                    spot.classList.remove('available');
                    spot.classList.add('occupied');
                    spot.onclick = null;
                } else if (random < 0.18) {
                    spot.classList.remove('available', 'occupied');
                    spot.classList.add('maintenance');
                    spot.onclick = null;
                } else {
                    spot.classList.remove('occupied', 'maintenance');
                    spot.classList.add('available');
                    spot.onclick = function() { selectSpot(this); };
                }
            }
        }
        
        // Sélection d'une place
        function selectSpot(spotElement) {
            if (!spotElement.classList.contains('available')) {
                return;
            }
            
            var selectedSpots = document.querySelectorAll('.parking-spot.selected');
            for (var i = 0; i < selectedSpots.length; i++) {
                selectedSpots[i].classList.remove('selected');
            }
            
            spotElement.classList.add('selected');
            
            selectedSpot = {
                id: spotElement.dataset.spotId,
                number: spotElement.dataset.spotNumber,
                type: spotElement.dataset.spotType,
                zone: spotElement.dataset.spotZone,
                floor: spotElement.dataset.spotFloor
            };
            
            updateSelectedSpotDisplay();
            calculatePrice();
        }
        
        // Mettre à jour l'affichage de la place sélectionnée
        function updateSelectedSpotDisplay() {
            var infoDiv = document.getElementById('selectedSpotInfo');
            var noSpotDiv = document.getElementById('noSpotSelected');
            
            if (selectedSpot) {
                document.getElementById('spotNumber').textContent = selectedSpot.number;
                document.getElementById('spotType').textContent = getSpotTypeLabel(selectedSpot.type);
                document.getElementById('spotZone').textContent = selectedSpot.zone;
                document.getElementById('spotFloor').textContent = selectedSpot.floor;
                
                infoDiv.style.display = 'block';
                noSpotDiv.style.display = 'none';
            } else {
                infoDiv.style.display = 'none';
                noSpotDiv.style.display = 'block';
            }
        }
        
        // Obtenir le libellé du type de place
        function getSpotTypeLabel(type) {
            var labels = {
                'standard': 'Standard',
                'disabled': 'PMR',
                'electric': 'Électrique',
                'reserved': 'VIP',
                'compact': 'Compact'
            };
            return labels[type] || type;
        }
        
        // Calculer le prix
        function calculatePrice() {
            if (!selectedSpot || !selectedDates) {
                updatePricingDisplay(null);
                return;
            }
            
            var start = new Date(selectedDates.start);
            var end = new Date(selectedDates.end);
            var totalCost = calculateDetailedPricing(start, end, selectedSpot.type);
            updatePricingDisplay(totalCost);
        }
        
        // Calcul détaillé de la tarification
        function calculateDetailedPricing(startDate, endDate, spotType) {
            var totalPrice = 0;
            var totalDuration = 0;
            var basePrice = 0;
            var firstRule = null;
            var currentDate = new Date(startDate);
            
            while (currentDate < endDate) {
                var nextHour = new Date(currentDate);
                nextHour.setHours(nextHour.getHours() + 1);
                
                var periodEnd = nextHour > endDate ? endDate : nextHour;
                var periodDuration = (periodEnd - currentDate) / (1000 * 60 * 60);
                var timePeriod = getTimePeriod(currentDate);
                
                var rule = null;
                for (var i = 0; i < pricingRules.length; i++) {
                    if (pricingRules[i].spot_type === spotType && pricingRules[i].time_period === timePeriod) {
                        rule = pricingRules[i];
                        break;
                    }
                }
                
                if (!rule) {
                    rule = { base_price: 2.00, hourly_rate: 3.00, daily_rate: 25.00 };
                }
                
                if (!firstRule) {
                    firstRule = rule;
                    basePrice = parseFloat(rule.base_price);
                    totalPrice += basePrice;
                }
                
                var hourlyRate = parseFloat(rule.hourly_rate);
                var periodCost = hourlyRate * periodDuration;
                totalPrice += periodCost;
                totalDuration += periodDuration;
                currentDate = nextHour;
            }
            
            // Vérifier tarif journalier
            var durationMs = endDate - startDate;
            var durationHours = durationMs / (1000 * 60 * 60);
            var isDailyRate = false;
            
            if (durationHours >= 8 && firstRule && firstRule.daily_rate) {
                var numberOfDays = Math.max(1, Math.ceil(durationHours / 24));
                var dailyPrice = parseFloat(firstRule.daily_rate) * numberOfDays;
                
                if (dailyPrice < totalPrice) {
                    totalPrice = dailyPrice;
                    isDailyRate = true;
                }
            }
            
            var displayedHourlyRate = firstRule ? parseFloat(firstRule.hourly_rate) : 0;
            
            return {
                duration: Math.round(totalDuration * 100) / 100,
                basePrice: basePrice,
                hourlyRate: displayedHourlyRate,
                totalPrice: Math.round(totalPrice * 100) / 100,
                isDailyRate: isDailyRate
            };
        }
        
        // Fonction pour obtenir la période tarifaire
        function getTimePeriod(date) {
            var dayOfWeek = date.getDay();
            var hour = date.getHours();
            
            if (dayOfWeek === 0 || dayOfWeek === 6) {
                return (hour >= 6 && hour < 20) ? 'weekend_day' : 'weekend_night';
            }
            
            return (hour >= 6 && hour < 18) ? 'weekday_day' : 'weekday_night';
        }
        
        // Mettre à jour l'affichage de la tarification
        function updatePricingDisplay(pricing) {
            var paymentBtn = document.getElementById('proceedPayment');
            
            if (!pricing) {
                document.getElementById('duration').textContent = '-';
                document.getElementById('basePrice').textContent = '-';
                document.getElementById('hourlyRate').textContent = '-';
                document.getElementById('totalPrice').textContent = '0,00 €';
                if (paymentBtn) paymentBtn.disabled = true;
                return;
            }
            
            document.getElementById('duration').textContent = pricing.duration + 'h';
            document.getElementById('basePrice').textContent = pricing.basePrice.toFixed(2) + ' €';
            
            if (pricing.isDailyRate) {
                document.getElementById('hourlyRate').textContent = 'Tarif journalier appliqué';
            } else {
                document.getElementById('hourlyRate').textContent = pricing.hourlyRate.toFixed(2) + ' €/h';
            }
            
            document.getElementById('totalPrice').textContent = pricing.totalPrice.toFixed(2) + ' €';
            
            if (paymentBtn) paymentBtn.disabled = false;
        }
        
        // Procéder au paiement
        function proceedToPayment() {
            if (!selectedSpot || !selectedDates) {
                alert('Veuillez sélectionner une place et vérifier les dates.');
                return;
            }
            
            var totalPriceElement = document.getElementById('totalPrice');
            if (!totalPriceElement) {
                alert('Erreur: Prix non calculé.');
                return;
            }
            
            var totalAmount = totalPriceElement.textContent.replace(' €', '').replace(',', '.');
            
            var form = document.createElement('form');
            form.method = 'POST';
            form.action = 'process-reservation.php';
            form.style.display = 'none';
            
            var fields = {
                spot_id: selectedSpot.id,
                start_datetime: selectedDates.start,
                end_datetime: selectedDates.end,
                total_amount: totalAmount
            };
            
            for (var fieldName in fields) {
                if (fields.hasOwnProperty(fieldName)) {
                    var input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = fieldName;
                    input.value = fields[fieldName];
                    form.appendChild(input);
                }
            }
            
            document.body.appendChild(form);
            form.submit();
        }
        
        // Fonction pour valider les dates
        function validateDates() {
            var startInput = document.getElementById('startDateTime');
            var endInput = document.getElementById('endDateTime');
            
            if (startInput && startInput.value) {
                var minEndDate = new Date(startInput.value);
                minEndDate.setHours(minEndDate.getHours() + 1);
                endInput.min = minEndDate.toISOString().slice(0, 16);
                
                if (endInput.value && new Date(endInput.value) <= new Date(startInput.value)) {
                    endInput.value = minEndDate.toISOString().slice(0, 16);
                }
            }
        }
        
        // Initialisation unique au chargement du DOM
        document.addEventListener('DOMContentLoaded', function() {
            var startInput = document.getElementById('startDateTime');
            var endInput = document.getElementById('endDateTime');
            
            if (startInput && endInput) {
                startInput.addEventListener('change', validateDates);
                endInput.addEventListener('change', validateDates);
            }
        });
    </script>
</body>
</html>
<script>
    // Fonction pour soumettre le formulaire de paiement
    function proceedToPayment() {
        if (!selectedSpot || !selectedDates) {
            alert('Veuillez sélectionner une place et vérifier les dates.');
            return;
        }
        
        const startDate = selectedDates.start;
        const endDate = selectedDates.end;
        
        if (!startDate || !endDate) {
            alert('Veuillez sélectionner des dates valides.');
            return;
        }
        
        const totalPriceElement = document.getElementById('totalPrice');
        if (!totalPriceElement) {
            alert('Erreur: Prix non calculé.');
            return;
        }
        
        const totalAmount = totalPriceElement.textContent.replace(' €', '').replace(',', '.');
        
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = 'process-reservation.php';
        form.style.display = 'none';
        
        const fields = {
            spot_id: selectedSpot.id,
            start_datetime: startDate,
            end_datetime: endDate,
            total_amount: totalAmount
        };
        
        for (const fieldName in fields) {
            if (fields.hasOwnProperty(fieldName)) {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = fieldName;
                input.value = fields[fieldName];
                form.appendChild(input);
            }
        }
        
        document.body.appendChild(form);
        form.submit();
    }

    // Fonction pour mettre à jour l'affichage des prix et détails
    function updatePricingDisplay(pricing) {
        const paymentBtn = document.getElementById('proceedPayment');
        
        if (!pricing) {
            document.getElementById('duration').textContent = '-';
            document.getElementById('basePrice').textContent = '-';
            document.getElementById('hourlyRate').textContent = '-';
            document.getElementById('totalPrice').textContent = '0,00 €';
            if (paymentBtn) paymentBtn.disabled = true;
            removePeriodBreakdown();
            return;
        }
        
        document.getElementById('duration').textContent = `${pricing.duration}h`;
        document.getElementById('basePrice').textContent = `${pricing.basePrice.toFixed(2)} €`;
        
        if (pricing.isDailyRate) {
            document.getElementById('hourlyRate').innerHTML = '<span style="color: var(--primary-green); font-weight: bold;">Tarif journalier appliqué</span>';
        } else if (pricing.hasMultiplePeriods) {
            document.getElementById('hourlyRate').textContent = `${pricing.hourlyRate.toFixed(2)} €/h (moyenne)`;
        } else {
            document.getElementById('hourlyRate').textContent = `${pricing.hourlyRate.toFixed(2)} €/h`;
        }
        
        document.getElementById('totalPrice').textContent = `${pricing.totalPrice.toFixed(2)} €`;
        if (paymentBtn) paymentBtn.disabled = false;
        
        // Ajouter le détail des périodes tarifaires si applicable
        addPeriodBreakdown(pricing.periodBreakdown, pricing.hasMultiplePeriods);
    }
    
    // Fonction pour supprimer le détail des périodes s'il existe
    function removePeriodBreakdown() {
        const existingBreakdown = document.getElementById('periodBreakdown');
        if (existingBreakdown) {
            existingBreakdown.remove();
        }
    }

    // Fonction pour afficher le détail des périodes tarifaires
    function addPeriodBreakdown(periodBreakdown, hasMultiplePeriods) {
        removePeriodBreakdown();
        
        if (!hasMultiplePeriods) {
            return; // Pas besoin d'afficher le détail s'il n'y a qu'une seule période
        }
        
        const breakdownDiv = document.createElement('div');
        breakdownDiv.id = 'periodBreakdown';
        breakdownDiv.style.cssText = `
            margin-top: 1rem;
            padding: 1rem;
            background: var(--pale-green);
            border-radius: 0.5rem;
            font-size: 0.75rem;
            border: 1px solid var(--primary-green);
        `;
        breakdownDiv.innerHTML = '<strong>Détail par période tarifaire :</strong>';
        
        const periodLabels = {
            'weekday_day': 'Semaine jour (6h-18h)',
            'weekday_night': 'Semaine nuit (18h-6h)',
            'weekend_day': 'Weekend jour (6h-20h)',
            'weekend_night': 'Weekend nuit (20h-6h)'
        };
        
        for (const [period, data] of Object.entries(periodBreakdown)) {
            const periodDiv = document.createElement('div');
            periodDiv.style.cssText = `
                display: flex;
                justify-content: space-between;
                margin: 0.5rem 0;
                padding: 0.25rem 0;
                border-bottom: 1px solid rgba(16, 185, 129, 0.2);
            `;
            periodDiv.innerHTML = `
                <span style="color: var(--gray-700);">${periodLabels[period] || period} :</span>
                <span style="font-weight: 600; color: var(--dark-green);">
                    ${data.duration.toFixed(1)}h × ${data.rate.toFixed(2)}€ = ${data.cost.toFixed(2)}€
                </span>
            `;
            breakdownDiv.appendChild(periodDiv);
        }
        
        const pricingDisplay = document.getElementById('pricingDisplay');
        if (pricingDisplay) {
            pricingDisplay.parentNode.insertBefore(breakdownDiv, pricingDisplay.nextSibling);
        }
    }

    // Exporter la fonction pour pouvoir l'appeler depuis HTML si besoin
    window.proceedToPayment = proceedToPayment;
</script>
</body>
</html>
