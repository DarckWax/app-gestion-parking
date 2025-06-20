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
    <title>Modifier la réservation - ParkFinder</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Space+Grotesk:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        // ...existing code... (mêmes styles que les autres pages)
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        :root {
            --primary-green: #10B981;
            --dark-green: #059669;
            --pale-green: #ECFDF5;
            --primary-black: #111827;
            --gray-900: #1F2937;
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
        
        .container {
            max-width: 800px;
            margin: 0 auto;
            padding: 2rem;
        }
        
        .form-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-radius: 1.5rem;
            padding: 2rem;
            color: var(--primary-black);
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
        }
        
        .form-title {
            font-family: var(--font-display);
            font-size: 2rem;
            font-weight: 800;
            text-align: center;
            margin-bottom: 2rem;
            color: var(--primary-green);
        }
        
        .form-group {
            margin-bottom: 1.5rem;
        }
        
        .form-label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: var(--gray-700);
        }
        
        .form-input {
            width: 100%;
            padding: 1rem;
            border: 2px solid var(--gray-300);
            border-radius: 0.75rem;
            font-size: 1rem;
            transition: border-color 0.3s ease;
        }
        
        .form-input:focus {
            outline: none;
            border-color: var(--primary-green);
            box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.1);
        }
        
        .btn {
            padding: 1rem 2rem;
            border: none;
            border-radius: 0.75rem;
            font-weight: 600;
            font-size: 1rem;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
            text-align: center;
        }
        
        .btn-primary {
            background: var(--primary-green);
            color: var(--white);
        }
        
        .btn-primary:hover {
            background: var(--dark-green);
            transform: translateY(-2px);
        }
        
        .btn-secondary {
            background: var(--gray-600);
            color: var(--white);
        }
        
        .message {
            padding: 1rem;
            border-radius: 0.75rem;
            margin-bottom: 1rem;
            font-weight: 500;
        }
        
        .message.success {
            background: var(--pale-green);
            color: var(--dark-green);
            border: 1px solid var(--primary-green);
        }
        
        .message.error {
            background: #FEE2E2;
            color: #DC2626;
            border: 1px solid #F87171;
        }
        
        .reservation-info {
            background: var(--gray-100);
            padding: 1.5rem;
            border-radius: 1rem;
            margin-bottom: 2rem;
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
    </style>
</head>
<body>
    <div class="container">
        <div class="form-card">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem;">
                <a href="index.php" style="font-family: var(--font-display); font-size: 1.5rem; font-weight: 700; color: var(--primary-green); text-decoration: none;">🚗 ParkFinder</a>
                <a href="my-reservations.php" style="color: var(--gray-700); text-decoration: none; font-weight: 500;">← Retour aux réservations</a>
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
                    <div style="background: #FEF3C7; color: #92400E; padding: 1rem; border-radius: 0.5rem; margin: 1rem 0;">
                        <strong>⚠️ Attention :</strong> Cette réservation est en attente de paiement. 
                        Après modification, vous devrez procéder au paiement pour confirmer la réservation.
                    </div>
                <?php endif; ?>
                
                <div style="display: flex; gap: 1rem; justify-content: center; flex-wrap: wrap;">
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
    
    <script>
        // Validation des dates côté client
        document.getElementById('start_datetime').addEventListener('change', function() {
            const endInput = document.getElementById('end_datetime');
            const startValue = this.value;
            
            if (startValue) {
                const minEndDate = new Date(startValue);
                minEndDate.setHours(minEndDate.getHours() + 1);
                endInput.min = minEndDate.toISOString().slice(0, 16);
                
                if (endInput.value && new Date(endInput.value) <= new Date(startValue)) {
                    endInput.value = minEndDate.toISOString().slice(0, 16);
                }
            }
        });
    </script>
</body>
</html>