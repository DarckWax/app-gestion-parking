<?php
session_start();

// Vérifier la connexion
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Utilisateur non connecté']);
    exit;
}

// Vérifier que c'est une requête POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Méthode non autorisée']);
    exit;
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
    
    // Récupérer les données de la requête
    $input = json_decode(file_get_contents('php://input'), true);
    $reservationId = $input['reservation_id'] ?? null;
    $reason = $input['reason'] ?? 'Annulation à la demande du client';
    
    if (!$reservationId) {
        throw new Exception('ID de réservation manquant');
    }
    
    $pdo->beginTransaction();
    
    // Vérifier que la réservation appartient à l'utilisateur et peut être annulée
    $stmt = $pdo->prepare("
        SELECT r.*, ps.spot_id, ps.status as spot_status
        FROM reservations r
        JOIN parking_spots ps ON r.spot_id = ps.spot_id
        WHERE r.reservation_id = ? AND r.user_id = ?
    ");
    $stmt->execute([$reservationId, $_SESSION['user_id']]);
    $reservation = $stmt->fetch();
    
    if (!$reservation) {
        throw new Exception('Réservation non trouvée ou non autorisée');
    }
    
    // Vérifier que la réservation peut être annulée
    if ($reservation['status'] === 'cancelled') {
        throw new Exception('Cette réservation est déjà annulée');
    }
    
    if ($reservation['status'] === 'completed') {
        throw new Exception('Cette réservation est terminée et ne peut plus être annulée');
    }
    
    // Permettre l'annulation si :
    // 1. La réservation n'a pas encore commencé (upcoming)
    // 2. OU si elle est en attente de paiement (pending payment)
    $canCancel = false;
    
    if (strtotime($reservation['start_datetime']) > time()) {
        // Réservation future - toujours annulable
        $canCancel = true;
    } elseif ($reservation['status'] === 'pending' && $reservation['payment_status'] === 'pending') {
        // Réservation en attente de paiement - annulable même si la date a commencé
        $canCancel = true;
    }
    
    if (!$canCancel) {
        throw new Exception('Cette réservation ne peut plus être annulée (déjà commencée et payée)');
    }
    
    // Mettre à jour la réservation
    $updateReservation = $pdo->prepare("
        UPDATE reservations 
        SET status = 'cancelled', 
            cancellation_reason = ?, 
            updated_at = NOW()
        WHERE reservation_id = ?
    ");
    $updateReservation->execute([$reason, $reservationId]);
    
    // Libérer la place de parking
    $updateSpot = $pdo->prepare("
        UPDATE parking_spots 
        SET status = 'available' 
        WHERE spot_id = ?
    ");
    $updateSpot->execute([$reservation['spot_id']]);
    
    // Gérer le remboursement selon le statut de paiement
    $refundMessage = '';
    if ($reservation['payment_status'] === 'paid') {
        // Paiement effectué - traiter le remboursement
        $updatePayment = $pdo->prepare("
            UPDATE payments 
            SET payment_status = 'refunded', 
                refunded_at = NOW(),
                refund_amount = amount
            WHERE reservation_id = ?
        ");
        $updatePayment->execute([$reservationId]);
        
        $refundMessage = "Un remboursement de {$reservation['total_amount']}€ sera traité sous 3-5 jours ouvrés.";
        
        // Créer une notification pour le remboursement
        $insertNotification = $pdo->prepare("
            INSERT INTO notifications (user_id, reservation_id, type, title, message, status)
            VALUES (?, ?, 'cancellation', 'Réservation annulée', ?, 'pending')
        ");
        $notificationMessage = "Votre réservation {$reservation['reservation_code']} a été annulée. Un remboursement de {$reservation['total_amount']}€ sera traité sous 3-5 jours ouvrés.";
        $insertNotification->execute([$_SESSION['user_id'], $reservationId, $notificationMessage]);
    } elseif ($reservation['payment_status'] === 'pending') {
        // Paiement en attente - pas de remboursement nécessaire
        $refundMessage = "Aucun paiement n'ayant été effectué, aucun remboursement n'est nécessaire.";
        
        // Créer une notification simple
        $insertNotification = $pdo->prepare("
            INSERT INTO notifications (user_id, reservation_id, type, title, message, status)
            VALUES (?, ?, 'cancellation', 'Réservation annulée', ?, 'pending')
        ");
        $notificationMessage = "Votre réservation {$reservation['reservation_code']} a été annulée avec succès. Aucun paiement n'était en cours.";
        $insertNotification->execute([$_SESSION['user_id'], $reservationId, $notificationMessage]);
    }
    
    // Log de l'annulation
    $insertLog = $pdo->prepare("
        INSERT INTO system_logs (user_id, log_level, action, message, ip_address)
        VALUES (?, 'info', 'cancel_reservation', ?, ?)
    ");
    $logMessage = "Annulation réservation ID: $reservationId, Code: {$reservation['reservation_code']}, Raison: $reason";
    $insertLog->execute([$_SESSION['user_id'], $logMessage, $_SERVER['REMOTE_ADDR'] ?? 'unknown']);
    
    $pdo->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'Réservation annulée avec succès',
        'refund_info' => $refundMessage
    ]);
    
} catch (Exception $e) {
    if (isset($pdo)) {
        $pdo->rollback();
    }
    
    error_log("Erreur annulation réservation: " . $e->getMessage());
    
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>
