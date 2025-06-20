<?php
/**
 * ParkFinder - Configuration et utilitaires administrateur
 * Fichier: admin-config.php
 */

// Fonction pour vérifier si l'utilisateur est admin
function checkAdminAccess($requireAdmin = true) {
    global $pdo;
    
    // Vérifier si connecté
    if (!isset($_SESSION['user_id'])) {
        if ($requireAdmin) {
            header('Location: index.php#login');
            exit;
        }
        return false;
    }
    
    // Récupérer le rôle actuel en base
    try {
        $stmt = $pdo->prepare("SELECT role FROM users WHERE user_id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $userRole = $stmt->fetchColumn();
        
        // Mettre à jour la session si nécessaire
        if ($userRole) {
            $_SESSION['role'] = $userRole;
        }
        
        // Vérifier le rôle admin
        $isAdmin = ($userRole === 'admin');
        
        if ($requireAdmin && !$isAdmin) {
            // Rediriger vers le diagnostic au lieu de bloquer
            header('Location: admin-diagnostic.php');
            exit;
        }
        
        return $isAdmin;
        
    } catch (Exception $e) {
        error_log("Erreur vérification admin: " . $e->getMessage());
        if ($requireAdmin) {
            header('Location: admin-diagnostic.php');
            exit;
        }
        return false;
    }
}

/**
 * Fonction pour créer un utilisateur admin par défaut
 */
function createDefaultAdmin($pdo) {
    try {
        // Vérifier s'il existe déjà un admin
        $stmt = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'admin'");
        $adminCount = $stmt->fetchColumn();
        
        if ($adminCount == 0) {
            // Créer un admin par défaut
            $stmt = $pdo->prepare("
                INSERT INTO users (first_name, last_name, email, password_hash, role, is_active, created_at) 
                VALUES (?, ?, ?, ?, 'admin', 1, NOW())
            ");
            
            $defaultPassword = password_hash('admin123', PASSWORD_DEFAULT);
            
            $stmt->execute([
                'Admin',
                'ParkFinder',
                'admin@parkfinder.com',
                $defaultPassword
            ]);
            
            return [
                'created' => true,
                'email' => 'admin@parkfinder.com',
                'password' => 'admin123'
            ];
        }
        
        return ['created' => false];
        
    } catch (Exception $e) {
        error_log("Erreur création admin: " . $e->getMessage());
        return ['created' => false, 'error' => $e->getMessage()];
    }
}

/**
 * Fonction pour promouvoir un utilisateur existant en admin
 */
function promoteToAdmin($pdo, $userId) {
    try {
        $stmt = $pdo->prepare("UPDATE users SET role = 'admin' WHERE user_id = ?");
        $stmt->execute([$userId]);
        
        // Mettre à jour la session si c'est l'utilisateur actuel
        if (isset($_SESSION['user_id']) && $_SESSION['user_id'] == $userId) {
            $_SESSION['role'] = 'admin';
        }
        
        return true;
        
    } catch (Exception $e) {
        error_log("Erreur promotion admin: " . $e->getMessage());
        return false;
    }
}

/**
 * Fonction pour vérifier la configuration de la base de données
 */
function checkDatabaseConfig($pdo) {
    $tables = ['users', 'parking_spots', 'reservations', 'pricing_rules'];
    $missingTables = [];
    
    foreach ($tables as $table) {
        try {
            $stmt = $pdo->query("SHOW TABLES LIKE '$table'");
            if (!$stmt->fetch()) {
                $missingTables[] = $table;
            }
        } catch (Exception $e) {
            $missingTables[] = $table;
        }
    }
    
    return [
        'complete' => empty($missingTables),
        'missing_tables' => $missingTables
    ];
}

/**
 * Fonction pour initialiser les données de test
 */
function initializeTestData($pdo) {
    try {
        // Créer des places de parking de test
        $stmt = $pdo->prepare("
            INSERT IGNORE INTO parking_spots (spot_number, spot_type, status, floor_level, zone_section, is_active) 
            VALUES (?, ?, 'available', ?, ?, 1)
        ");
        
        $testSpots = [
            ['A01', 'standard', 1, 'A'],
            ['A02', 'standard', 1, 'A'],
            ['A03', 'disabled', 1, 'A'],
            ['A04', 'electric', 1, 'A'],
            ['B01', 'standard', 2, 'B'],
            ['B02', 'compact', 2, 'B'],
            ['B03', 'reserved', 2, 'B']
        ];
        
        foreach ($testSpots as $spot) {
            $stmt->execute($spot);
        }
        
        // Créer des règles de tarification de base
        $stmt = $pdo->prepare("
            INSERT IGNORE INTO pricing_rules (spot_type, time_period, base_price, hourly_rate, daily_rate, is_active) 
            VALUES (?, ?, ?, ?, ?, 1)
        ");
        
        $testRules = [
            ['standard', 'weekday_day', 2.00, 3.00, 25.00],
            ['standard', 'weekday_night', 1.50, 2.00, 20.00],
            ['standard', 'weekend_day', 2.50, 3.50, 30.00],
            ['standard', 'weekend_night', 2.00, 2.50, 25.00],
            ['electric', 'weekday_day', 3.00, 4.00, 35.00],
            ['disabled', 'weekday_day', 2.00, 3.00, 25.00],
            ['reserved', 'weekday_day', 5.00, 6.00, 50.00],
            ['compact', 'weekday_day', 1.50, 2.50, 20.00]
        ];
        
        foreach ($testRules as $rule) {
            $stmt->execute($rule);
        }
        
        return true;
        
    } catch (Exception $e) {
        error_log("Erreur initialisation données: " . $e->getMessage());
        return false;
    }
}

/**
 * Affiche une page d'erreur admin simple
 */
function showAdminError($title, $message, $redirectUrl = 'index.php') {
    ?>
    <!DOCTYPE html>
    <html lang="fr">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title><?= htmlspecialchars($title) ?> - ParkFinder</title>
        <style>
            body {
                font-family: 'Segoe UI', sans-serif;
                background: linear-gradient(135deg, #111827, #1F2937);
                min-height: 100vh;
                display: flex;
                align-items: center;
                justify-content: center;
                color: white;
                margin: 0;
            }
            .error-container {
                background: rgba(255, 255, 255, 0.95);
                color: #111827;
                padding: 2rem;
                border-radius: 1rem;
                text-align: center;
                max-width: 500px;
                box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
            }
            .error-icon {
                font-size: 3rem;
                margin-bottom: 1rem;
            }
            .error-title {
                font-size: 1.5rem;
                font-weight: bold;
                margin-bottom: 1rem;
                color: #DC2626;
            }
            .error-message {
                margin-bottom: 2rem;
                color: #6B7280;
            }
            .btn {
                background: #10B981;
                color: white;
                padding: 0.75rem 1.5rem;
                border: none;
                border-radius: 0.5rem;
                text-decoration: none;
                display: inline-block;
                margin: 0.5rem;
                transition: background 0.3s ease;
            }
            .btn:hover {
                background: #059669;
            }
            .btn-secondary {
                background: #6B7280;
            }
            .btn-secondary:hover {
                background: #4B5563;
            }
        </style>
    </head>
    <body>
        <div class="error-container">
            <div class="error-icon">🚫</div>
            <div class="error-title"><?= htmlspecialchars($title) ?></div>
            <div class="error-message"><?= htmlspecialchars($message) ?></div>
            <a href="admin-diagnostic.php" class="btn">🔧 Diagnostic Admin</a>
            <a href="<?= htmlspecialchars($redirectUrl) ?>" class="btn btn-secondary">🏠 Retour</a>
        </div>
    </body>
    </html>
    <?php
    exit;
}
?>