<?php
// Activer l'affichage des erreurs pour debug
error_reporting(E_ALL);
ini_set('display_errors', 1);

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
$dbConnected = false;
$reservations = [];
$stats = ['total' => 0, 'active' => 0, 'completed' => 0, 'cancelled' => 0];
$error = null;

try {
    $pdo = new PDO("mysql:host={$config['db_host']};dbname={$config['db_name']}", $config['db_user'], $config['db_pass']);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $dbConnected = true;
    
    // Récupérer les réservations de l'utilisateur
    $stmt = $pdo->prepare("
        SELECT 
            r.*,
            ps.spot_number,
            ps.spot_type,
            ps.zone_section,
            ps.floor_level,
            ps.description as spot_description,
            p.payment_method,
            p.transaction_id,
            p.processed_at as payment_date,
            TIMESTAMPDIFF(HOUR, r.start_datetime, r.end_datetime) as duration_hours,
            CASE 
                WHEN NOW() < r.start_datetime THEN 'upcoming'
                WHEN NOW() BETWEEN r.start_datetime AND r.end_datetime THEN 'active'
                WHEN NOW() > r.end_datetime THEN 'past'
                ELSE 'unknown'
            END as time_status
        FROM reservations r
        LEFT JOIN parking_spots ps ON r.spot_id = ps.spot_id
        LEFT JOIN payments p ON r.reservation_id = p.reservation_id
        WHERE r.user_id = ?
        ORDER BY r.created_at DESC
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $reservations = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Calculer les statistiques
    $stats['total'] = count($reservations);
    foreach ($reservations as $reservation) {
        switch ($reservation['status']) {
            case 'confirmed':
            case 'active':
                $stats['active']++;
                break;
            case 'completed':
                $stats['completed']++;
                break;
            case 'cancelled':
                $stats['cancelled']++;
                break;
        }
    }
    
} catch(Exception $e) {
    $error = "Erreur lors de la récupération des réservations.";
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mes réservations - ParkFinder</title>    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Space+Grotesk:wght@400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Styles CSS -->
    <link rel="stylesheet" href="assets/css/my-reservations.css">
</head>
<body>
    <!-- Header -->
    <header class="header">
        <div class="container">
            <nav class="navbar">
                <a href="index.php" class="logo">
                    <div class="logo-icon">P</div>
                    <span class="logo-text">ParkFinder</span>
                </a>
                <a href="index.php" class="back-link">
                    ← Retour à l'accueil
                </a>
            </nav>
        </div>
    </header>

    <!-- Contenu principal -->
    <div class="container">
        <!-- En-tête de page -->
        <div class="page-header">
            <h1 class="page-title">Mes réservations</h1>
            <p class="page-subtitle">
                Gérez et consultez l'historique de toutes vos réservations de parking
            </p>
            
            <!-- Message d'erreur si nécessaire -->
            <?php if ($error): ?>
                <div class="error-message">
                    <strong>Erreur:</strong> <?= htmlspecialchars($error) ?>
                    <br><a href="create-db.php" style="color: #6EE7B7;">Configurer la base de données</a>
                </div>
            <?php endif; ?>
            
            <!-- Statistiques -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-number"><?= $stats['total'] ?></div>
                    <div class="stat-label">Total</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?= $stats['active'] ?></div>
                    <div class="stat-label">Actives</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?= $stats['completed'] ?></div>
                    <div class="stat-label">Terminées</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?= $stats['cancelled'] ?></div>
                    <div class="stat-label">Annulées</div>
                </div>
            </div>
        </div>

        <!-- Liste des réservations -->
        <div class="reservations-container">
            <?php if (empty($reservations)): ?>
                <div class="empty-state">
                    <div class="empty-icon">🅿️</div>
                    <h2 class="empty-title">Aucune réservation</h2>
                    <p class="empty-subtitle">
                        Vous n'avez pas encore effectué de réservation de place de parking.
                    </p>
                    <a href="reservation.php" class="btn">
                        🚗 Réserver une place
                    </a>
                </div>
            <?php else: ?>
                <?php foreach ($reservations as $reservation): ?>
                    <div class="reservation-card">
                        <div class="reservation-header">
                            <div>
                                <h2 class="reservation-title">
                                    Place <?= htmlspecialchars($reservation['spot_number']) ?>
                                    <span class="time-badge time-<?= $reservation['time_status'] ?>">
                                        <?php
                                        switch ($reservation['time_status']) {
                                            case 'upcoming': echo 'À venir'; break;
                                            case 'active': echo 'En cours'; break;
                                            case 'past': echo 'Terminée'; break;
                                            default: echo 'Statut inconnu';
                                        }
                                        ?>
                                    </span>
                                </h2>
                                <p style="color: var(--gray-600); margin-top: 0.5rem;">
                                    Code: <strong><?= htmlspecialchars($reservation['reservation_code']) ?></strong>
                                </p>
                            </div>
                            <div class="status-badge status-<?= $reservation['status'] ?>">
                                <?php
                                $statusLabels = [
                                    'pending' => 'En attente',
                                    'confirmed' => 'Confirmée',
                                    'active' => 'Active',
                                    'completed' => 'Terminée',
                                    'cancelled' => 'Annulée',
                                    'no_show' => 'Non présentée'
                                ];
                                echo $statusLabels[$reservation['status']] ?? $reservation['status'];
                                ?>
                            </div>
                        </div>

                        <div class="reservation-details">
                            <!-- Informations de la place -->
                            <div class="detail-section">
                                <h3 class="section-title">
                                    🅿️ Détails de la place
                                </h3>
                                <div class="detail-item">
                                    <span class="detail-label">Numéro de place:</span>
                                    <span class="detail-value"><?= htmlspecialchars($reservation['spot_number']) ?></span>
                                </div>
                                <div class="detail-item">
                                    <span class="detail-label">Type:</span>
                                    <span class="detail-value">
                                        <?php
                                        $typeLabels = [
                                            'standard' => 'Standard',
                                            'disabled' => 'PMR',
                                            'electric' => 'Électrique',
                                            'reserved' => 'VIP',
                                            'compact' => 'Compact'
                                        ];
                                        echo $typeLabels[$reservation['spot_type']] ?? $reservation['spot_type'];
                                        ?>
                                    </span>
                                </div>
                                <div class="detail-item">
                                    <span class="detail-label">Zone:</span>
                                    <span class="detail-value">Zone <?= htmlspecialchars($reservation['zone_section']) ?></span>
                                </div>
                                <div class="detail-item">
                                    <span class="detail-label">Étage:</span>
                                    <span class="detail-value">Niveau <?= $reservation['floor_level'] ?></span>
                                </div>
                            </div>

                            <!-- Informations temporelles -->
                            <div class="detail-section">
                                <h3 class="section-title">
                                    ⏰ Période de réservation
                                </h3>
                                <div class="detail-item">
                                    <span class="detail-label">Date de début:</span>
                                    <span class="detail-value"><?= date('d/m/Y', strtotime($reservation['start_datetime'])) ?></span>
                                </div>
                                <div class="detail-item">
                                    <span class="detail-label">Heure de début:</span>
                                    <span class="detail-value"><?= date('H:i', strtotime($reservation['start_datetime'])) ?></span>
                                </div>
                                <div class="detail-item">
                                    <span class="detail-label">Date de fin:</span>
                                    <span class="detail-value"><?= date('d/m/Y', strtotime($reservation['end_datetime'])) ?></span>
                                </div>
                                <div class="detail-item">
                                    <span class="detail-label">Heure de fin:</span>
                                    <span class="detail-value"><?= date('H:i', strtotime($reservation['end_datetime'])) ?></span>
                                </div>
                                <div class="detail-item">
                                    <span class="detail-label">Durée totale:</span>
                                    <span class="detail-value"><?= $reservation['duration_hours'] ?>h</span>
                                </div>
                            </div>

                            <!-- Informations financières -->
                            <div class="detail-section">
                                <h3 class="section-title">
                                    💰 Informations financières
                                </h3>
                                <div class="detail-item">
                                    <span class="detail-label">Montant total:</span>
                                    <span class="detail-value amount-highlight">
                                        <?= number_format($reservation['total_amount'], 2, ',', ' ') ?> €
                                    </span>
                                </div>
                                <div class="detail-item">
                                    <span class="detail-label">Statut paiement:</span>
                                    <span class="detail-value">
                                        <?php
                                        $paymentLabels = [
                                            'pending' => 'En attente',
                                            'paid' => 'Payé',
                                            'refunded' => 'Remboursé',
                                            'failed' => 'Échoué'
                                        ];
                                        echo $paymentLabels[$reservation['payment_status']] ?? $reservation['payment_status'];
                                        ?>
                                    </span>
                                </div>
                                <?php if ($reservation['payment_method']): ?>
                                <div class="detail-item">
                                    <span class="detail-label">Moyen de paiement:</span>
                                    <span class="detail-value">
                                        <?= ucfirst($reservation['payment_method']) ?>
                                    </span>
                                </div>
                                <?php endif; ?>
                                <?php if ($reservation['payment_date']): ?>
                                <div class="detail-item">
                                    <span class="detail-label">Date de paiement:</span>
                                    <span class="detail-value">
                                        <?= date('d/m/Y H:i', strtotime($reservation['payment_date'])) ?>
                                    </span>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Boutons d'action -->
                        <div class="action-buttons">
                            <?php if (($reservation['status'] === 'confirmed' || $reservation['status'] === 'pending') && $reservation['time_status'] === 'upcoming'): ?>
                                <a href="modify-reservation.php?id=<?= $reservation['reservation_id'] ?>" class="btn btn-secondary">
                                    ✏️ Modifier
                                </a>
                                <button class="btn btn-danger" onclick="cancelReservation(<?= $reservation['reservation_id'] ?>)">
                                    ❌ Annuler
                                </button>
                            <?php endif; ?>
                            
                            <?php if ($reservation['payment_status'] === 'paid'): ?>
                                <button class="btn btn-secondary" onclick="confirmDownloadReceipt(<?= $reservation['reservation_id'] ?>)">
                                    📄 Télécharger reçu
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Modal de confirmation pour le téléchargement -->
    <div id="confirmModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>📄 Téléchargement du reçu</h3>
            </div>
            <div class="modal-body">
                <p>Êtes-vous sûr de vouloir télécharger le reçu de cette réservation ?</p>
                <p><small>Le reçu sera généré au format PDF et téléchargé automatiquement.</small></p>
            </div>
            <div class="modal-footer">
                <button class="btn btn-primary" onclick="downloadReceipt()">
                    ✅ Oui, télécharger
                </button>
                <button class="btn btn-secondary" onclick="closeConfirmModal()">
                    ❌ Non, annuler
                </button>
            </div>
        </div>
    </div>
    <div id="modalOverlay" class="modal-overlay"></div>    
    <!-- Scripts JavaScript -->
    <script src="assets/js/my-reservations.js"></script>
</body>
</html>