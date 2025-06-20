<?php
session_start();

// Rediriger si non connecté
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php#login');
    exit;
}

// Inclure les classes utilitaires
require_once 'classes/PricingCalculator.php';

// Configuration de base
$config = [
    'db_host' => 'localhost',
    'db_name' => 'parking_management_system', 
    'db_user' => 'root',
    'db_pass' => ''
];

$error = null;
$success = null;
$reservationDetails = null;

try {
    $pdo = new PDO("mysql:host={$config['db_host']};dbname={$config['db_name']}", $config['db_user'], $config['db_pass']);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    if ($_POST) {
        // Validation et nettoyage des données
        $postData = validateAndSanitizeInput($_POST);
        
        // Traitement de la réservation
        $reservationId = processReservation($pdo, $postData);
        
        if ($reservationId) {
            // Rediriger vers la page de paiement avec animation
            header("Location: payment.php?reservation_id=$reservationId&from=process");
            exit;
        }
    }
    
} catch (Exception $e) {
    if (isset($pdo)) {
        $pdo->rollback();
    }
    $error = $e->getMessage();
    error_log("Erreur process-reservation: " . $e->getMessage());
}

/**
 * Valide et nettoie les données d'entrée
 */
function validateAndSanitizeInput($post) {
    $data = [];
    
    // Validation des champs requis
    $requiredFields = ['spot_id', 'start_datetime', 'end_datetime'];
    foreach ($requiredFields as $field) {
        if (empty($post[$field])) {
            throw new InvalidArgumentException("Le champ $field est requis");
        }
        $data[$field] = trim($post[$field]);
    }
    
    // Validation des formats de date
    if (!DateTime::createFromFormat('Y-m-d\TH:i', $data['start_datetime'])) {
        throw new InvalidArgumentException("Format de date de début invalide");
    }
    
    if (!DateTime::createFromFormat('Y-m-d\TH:i', $data['end_datetime'])) {
        throw new InvalidArgumentException("Format de date de fin invalide");
    }
    
    // Nettoyer et valider la plaque d'immatriculation si fournie
    if (!empty($post['vehicle_plate'])) {
        $data['vehicle_plate'] = strtoupper(trim($post['vehicle_plate']));
        
        // Validation du format de plaque française (optionnel)
        if (!preg_match('/^[A-Z]{2}-[0-9]{3}-[A-Z]{2}$|^[0-9]{1,4}\s?[A-Z]{1,3}\s?[0-9]{2}$/', $data['vehicle_plate'])) {
            // Format flexible accepté, pas d'exception levée
        }
    } else {
        $data['vehicle_plate'] = null;
    }
    
    return $data;
}

/**
 * Traite une nouvelle réservation
 */
function processReservation($pdo, $data) {
    $pdo->beginTransaction();
    
    try {
        // 1. Vérifier la disponibilité de la place
        $spot = checkSpotAvailability($pdo, $data['spot_id'], $data['start_datetime'], $data['end_datetime']);
        
        // 2. Récupérer les règles de tarification
        $pricingRules = getPricingRules($pdo);
        
        // 3. Calculer le prix avec la classe utilitaire
        $calculator = new PricingCalculator($pricingRules);
        $totalPrice = $calculator->calculatePrice(
            $data['start_datetime'], 
            $data['end_datetime'], 
            $spot['spot_type']
        );
        
        // 4. Générer un code de réservation unique
        $reservationCode = generateUniqueReservationCode($pdo);
        
        // 5. Créer la réservation
        $reservationId = createReservation($pdo, $data, $spot, $reservationCode, $totalPrice);
        
        $pdo->commit();
        
        return $reservationId;
        
    } catch (Exception $e) {
        $pdo->rollback();
        throw $e;
    }
}

/**
 * Vérifie la disponibilité d'une place
 */
function checkSpotAvailability($pdo, $spotId, $startDatetime, $endDatetime) {
    // Récupérer les informations de la place
    $spotStmt = $pdo->prepare("
        SELECT spot_id, spot_number, spot_type, zone_section, floor_level 
        FROM parking_spots 
        WHERE spot_id = ? AND is_active = 1
    ");
    $spotStmt->execute([$spotId]);
    $spot = $spotStmt->fetch();
    
    if (!$spot) {
        throw new Exception("Place de parking non trouvée ou inactive");
    }
    
    // Vérifier les conflits de réservation
    $conflictStmt = $pdo->prepare("
        SELECT COUNT(*) as conflicts 
        FROM reservations 
        WHERE spot_id = ? 
        AND status NOT IN ('cancelled', 'no_show')
        AND (
            (start_datetime <= ? AND end_datetime > ?) OR
            (start_datetime < ? AND end_datetime >= ?) OR
            (start_datetime >= ? AND end_datetime <= ?)
        )
    ");
    
    $conflictStmt->execute([
        $spotId,
        $startDatetime, $startDatetime,
        $endDatetime, $endDatetime,
        $startDatetime, $endDatetime
    ]);
    
    $conflictResult = $conflictStmt->fetch();
    
    if ($conflictResult['conflicts'] > 0) {
        throw new Exception("Cette place n'est pas disponible pour la période sélectionnée");
    }
    
    return $spot;
}

/**
 * Récupère les règles de tarification actives
 */
function getPricingRules($pdo) {
    $stmt = $pdo->prepare("
        SELECT spot_type, time_period, base_price, hourly_rate, daily_rate, weekly_rate
        FROM pricing_rules 
        WHERE is_active = 1
        ORDER BY spot_type, time_period
    ");
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Génère un code de réservation unique
 */
function generateUniqueReservationCode($pdo) {
    $maxAttempts = 10;
    $attempts = 0;
    
    do {
        $code = 'PK' . date('Y') . strtoupper(substr(str_shuffle('ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789'), 0, 6));
        
        $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM reservations WHERE reservation_code = ?");
        $stmt->execute([$code]);
        $result = $stmt->fetch();
        
        $attempts++;
        
        if ($attempts >= $maxAttempts) {
            throw new Exception("Impossible de générer un code de réservation unique");
        }
        
    } while ($result['count'] > 0);
    
    return $code;
}

/**
 * Crée la réservation en base de données
 */
function createReservation($pdo, $data, $spot, $reservationCode, $totalPrice) {
    $stmt = $pdo->prepare("
        INSERT INTO reservations (
            user_id, spot_id, reservation_code, start_datetime, end_datetime, 
            total_amount, vehicle_plate, status, payment_status, created_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, 'pending', 'pending', NOW())
    ");
    
    $stmt->execute([
        $_SESSION['user_id'],
        $data['spot_id'],
        $reservationCode,
        $data['start_datetime'],
        $data['end_datetime'],
        $totalPrice,
        $data['vehicle_plate']
    ]);
    
    $reservationId = $pdo->lastInsertId();
    
    // Optionnel : Log de l'activité
    logReservationActivity($pdo, $reservationId, 'created', "Réservation créée: $reservationCode");
    
    return $reservationId;
}

/**
 * Log une activité de réservation (optionnel)
 */
function logReservationActivity($pdo, $reservationId, $action, $details = null) {
    try {
        $stmt = $pdo->prepare("
            INSERT INTO reservation_logs (reservation_id, action, details, created_at) 
            VALUES (?, ?, ?, NOW())
        ");
        $stmt->execute([$reservationId, $action, $details]);
    } catch (Exception $e) {
        // Log silencieux - ne pas faire échouer la réservation pour un problème de log
        error_log("Erreur log réservation: " . $e->getMessage());
    }
}

// Si on arrive ici, c'est qu'il y a eu une erreur ou que c'est un accès direct
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Traitement de la réservation - ParkFinder</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Space+Grotesk:wght@400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Styles CSS -->
    <link rel="stylesheet" href="assets/css/process-reservation.css">
</head>
<body>
    <!-- Header -->
    <header class="header">
        <div class="navbar">
            <a href="index.php" class="logo">
                <div class="logo-icon">P</div>
                <span class="logo-text">ParkFinder</span>
            </a>
            <a href="reservation.php" class="back-link">
                ← Retour à la réservation
            </a>
        </div>
    </header>

    <!-- Contenu principal -->
    <div class="container">
        <?php if ($error): ?>
            <div class="status-message error">
                <span>❌</span>
                <span><strong>Erreur :</strong> <?= htmlspecialchars($error) ?></span>
            </div>
            
            <div class="processing-container">
                <div class="processing-icon" style="background: #DC2626;">
                    ❌
                </div>
                <h1 class="processing-title">Erreur lors du traitement</h1>
                <p class="processing-message"><?= htmlspecialchars($error) ?></p>
                
                <div class="btn-group">
                    <a href="reservation.php" class="btn btn-secondary">
                        ← Retour à la réservation
                    </a>
                    <a href="index.php" class="btn">
                        🏠 Accueil
                    </a>
                </div>
            </div>
            
        <?php elseif ($success): ?>
            <div class="status-message success">
                <span>✅</span>
                <span><?= htmlspecialchars($success) ?></span>
            </div>
            
        <?php else: ?>
            <!-- Accès direct sans données -->
            <div class="processing-container">
                <div class="processing-icon" style="background: #F59E0B;">
                    ⚠️
                </div>
                <h1 class="processing-title">Accès non autorisé</h1>
                <p class="processing-message">
                    Cette page ne peut être accédée que lors du processus de réservation.
                </p>
                
                <div class="btn-group">
                    <a href="reservation.php" class="btn">
                        🎫 Faire une réservation
                    </a>
                    <a href="index.php" class="btn btn-secondary">
                        🏠 Retour à l'accueil
                    </a>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- Scripts JavaScript -->
    <script src="assets/js/process-reservation.js"></script>
</body>
</html>
