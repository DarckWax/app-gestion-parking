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

// Récupérer l'ID de la réservation
$reservationId = $_GET['id'] ?? null;

if (!$reservationId) {
    header('Location: my-reservations.php');
    exit;
}

// Connexion base de données
try {
    $pdo = new PDO("mysql:host={$config['db_host']};dbname={$config['db_name']}", $config['db_user'], $config['db_pass']);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Récupérer les détails de la réservation
    $stmt = $pdo->prepare("
        SELECT r.*, ps.spot_number, ps.spot_type, ps.zone_section, ps.floor_level
        FROM reservations r
        JOIN parking_spots ps ON r.spot_id = ps.spot_id
        WHERE r.reservation_id = ? AND r.user_id = ?
    ");
    $stmt->execute([$reservationId, $_SESSION['user_id']]);
    $reservation = $stmt->fetch();
    
    if (!$reservation) {
        header('Location: my-reservations.php');
        exit;
    }
    
    // Vérifier que la réservation peut être modifiée
    $canModify = false;
    $modifyError = '';
    
    if ($reservation['status'] === 'cancelled') {
        $modifyError = "Cette réservation a été annulée et ne peut plus être modifiée.";
    } elseif ($reservation['status'] === 'completed') {
        $modifyError = "Cette réservation est terminée et ne peut plus être modifiée.";
    } elseif (strtotime($reservation['start_datetime']) <= time() && $reservation['payment_status'] === 'paid') {
        $modifyError = "Cette réservation a déjà commencé et ne peut plus être modifiée.";
    } else {
        // Permettre la modification si :
        // 1. La réservation n'a pas encore commencé
        // 2. OU si elle est en attente de paiement (même si la date a commencé)
        $canModify = true;
    }
    
    if (!$canModify) {
        $error = $modifyError;
    }
    
} catch(Exception $e) {
    $error = "Erreur lors de la récupération de la réservation.";
}

// Traitement de la modification
if ($_POST && !isset($error)) {
    try {
        $newStartDatetime = $_POST['start_datetime'];
        $newEndDatetime = $_POST['end_datetime'];
        $newVehiclePlate = $_POST['vehicle_plate'] ?? null;
        
        // Validation des dates
        if (strtotime($newStartDatetime) <= time()) {
            throw new Exception("La nouvelle date de début doit être dans le futur.");
        }
        
        if (strtotime($newEndDatetime) <= strtotime($newStartDatetime)) {
            throw new Exception("La date de fin doit être postérieure à la date de début.");
        }
        
        // Vérifier la disponibilité de la place pour les nouvelles dates
        $checkAvailability = $pdo->prepare("
            SELECT COUNT(*) as conflicts
            FROM reservations 
            WHERE spot_id = ? 
            AND reservation_id != ?
            AND status IN ('confirmed', 'active')
            AND (
                (start_datetime <= ? AND end_datetime > ?) OR
                (start_datetime < ? AND end_datetime >= ?) OR
                (start_datetime >= ? AND end_datetime <= ?)
            )
        ");
        $checkAvailability->execute([
            $reservation['spot_id'], $reservationId,
            $newStartDatetime, $newStartDatetime,
            $newEndDatetime, $newEndDatetime,
            $newStartDatetime, $newEndDatetime
        ]);
        
        if ($checkAvailability->fetch()['conflicts'] > 0) {
            throw new Exception("La place n'est pas disponible pour ces nouvelles dates.");
        }
        
        // Recalculer le prix (optionnel - pour simplifier, on garde le même montant)
        // En production, il faudrait recalculer le prix selon les nouvelles dates
        
        $pdo->beginTransaction();
        
        // Mettre à jour la réservation
        $updateReservation = $pdo->prepare("
            UPDATE reservations 
            SET start_datetime = ?, 
                end_datetime = ?, 
                vehicle_plate = ?,
                updated_at = NOW()
            WHERE reservation_id = ?
        ");
        $updateReservation->execute([$newStartDatetime, $newEndDatetime, $newVehiclePlate, $reservationId]);
        
        // Log de la modification
        $insertLog = $pdo->prepare("
            INSERT INTO system_logs (user_id, log_level, action, message, ip_address)
            VALUES (?, 'info', 'modify_reservation', ?, ?)
        ");
        $logMessage = "Modification réservation ID: $reservationId, Code: {$reservation['reservation_code']}";
        $insertLog->execute([$_SESSION['user_id'], $logMessage, $_SERVER['REMOTE_ADDR'] ?? 'unknown']);
        
        $pdo->commit();
        
        $success = "Réservation modifiée avec succès!";
        
        // Recharger les données
        $stmt->execute([$reservationId, $_SESSION['user_id']]);
        $reservation = $stmt->fetch();
        
    } catch (Exception $e) {
        if (isset($pdo)) {
            $pdo->rollback();
        }
        $error = $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Modifier la réservation - ParkFinder</title>    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Space+Grotesk:wght@400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Styles CSS -->
    <link rel="stylesheet" href="assets/css/modify-reservation.css">
</head>
<body>
    <div class="container">        <div class="form-card">
            <div class="header-nav">
                <a href="index.php" class="logo">
                    <div class="logo-icon">P</div>
                    <span class="logo-text">ParkFinder</span>
                </a>
                <a href="my-reservations.php" class="back-link">← Retour aux réservations</a>
            </div>
            
            <h1 class="form-title">Modifier la réservation</h1>
            
            <?php if (isset($success)): ?>
                <div class="message success"><?= htmlspecialchars($success) ?></div>
            <?php endif; ?>
            
            <?php if (isset($error)): ?>
                <div class="message error"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>
            
            <div class="reservation-info">
                <h3>Réservation actuelle</h3>
                <p><strong>Code:</strong> <?= htmlspecialchars($reservation['reservation_code']) ?></p>
                <p><strong>Place:</strong> <?= htmlspecialchars($reservation['spot_number']) ?> (<?= ucfirst($reservation['spot_type']) ?>)</p>
                <p><strong>Zone:</strong> <?= htmlspecialchars($reservation['zone_section']) ?>, Niveau <?= $reservation['floor_level'] ?></p>
            </div>
            
            <?php if ($canModify): ?>
            <form method="POST">
                <div class="form-group">
                    <label for="start_datetime" class="form-label">Nouvelle date et heure de début</label>
                    <input type="datetime-local" 
                           id="start_datetime" 
                           name="start_datetime" 
                           class="form-input"
                           value="<?= date('Y-m-d\TH:i', strtotime($reservation['start_datetime'])) ?>"
                           min="<?= date('Y-m-d\TH:i') ?>"
                           required>
                </div>
                
                <div class="form-group">
                    <label for="end_datetime" class="form-label">Nouvelle date et heure de fin</label>
                    <input type="datetime-local" 
                           id="end_datetime" 
                           name="end_datetime" 
                           class="form-input"
                           value="<?= date('Y-m-d\TH:i', strtotime($reservation['end_datetime'])) ?>"
                           min="<?= date('Y-m-d\TH:i') ?>"
                           required>
                </div>
                
                <div class="form-group">
                    <label for="vehicle_plate" class="form-label">Plaque d'immatriculation (optionnel)</label>
                    <input type="text" 
                           id="vehicle_plate" 
                           name="vehicle_plate" 
                           class="form-input"
                           value="<?= htmlspecialchars($reservation['vehicle_plate'] ?? '') ?>"
                           placeholder="AB-123-CD">
                </div>
                  <?php if ($reservation['payment_status'] === 'pending'): ?>
                    <div class="payment-warning">
                        <strong>⚠️ Attention :</strong> Cette réservation est en attente de paiement. 
                        Après modification, vous devrez procéder au paiement pour confirmer la réservation.
                    </div>
                <?php endif; ?>
                
                <div class="btn-group">
                    <button type="submit" class="btn btn-primary">Confirmer les modifications</button>
                    <a href="my-reservations.php" class="btn btn-secondary">Annuler</a>
                </div>
            </form>
            <?php else: ?>
                <div style="text-align: center;">
                    <a href="my-reservations.php" class="btn btn-primary">Retour à mes réservations</a>
                </div>
            <?php endif; ?>
        </div>
    </div>    
    <!-- Scripts JavaScript -->
    <script src="assets/js/modify-reservation.js"></script>
</body>
</html>