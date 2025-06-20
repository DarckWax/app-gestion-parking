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
    <title>Mes réservations - ParkFinder</title>
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
            font-family: var(--font-display);
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--primary-black);
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            transition: all 0.3s ease;
        }
        
        .logo:hover {
            transform: translateY(-1px);
        }
        
        .logo-icon {
            width: 32px;
            height: 32px;
            background: linear-gradient(135deg, var(--primary-green), var(--dark-green));
            border-radius: 6px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: var(--font-display);
            font-size: 1.2rem;
            font-weight: 800;
            color: var(--white);
            box-shadow: 0 3px 10px rgba(16, 185, 129, 0.3);
            transition: all 0.3s ease;
        }
        
        .logo:hover .logo-icon {
            transform: scale(1.05);
            box-shadow: 0 5px 15px rgba(16, 185, 129, 0.4);
        }
        
        .logo-text {
            background: linear-gradient(135deg, var(--primary-black), var(--gray-800));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            letter-spacing: -0.025em;
            font-weight: 700;
        }
        
        .back-link {
            color: var(--gray-700);
            text-decoration: none;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            transition: color 0.3s ease;
        }
        
        .back-link:hover {
            color: var(--primary-green);
        }
        
        .page-header {
            padding: 2rem 0;
            text-align: center;
        }
        
        .page-title {
            font-family: var(--font-display);
            font-size: 2.5rem;
            font-weight: 800;
            margin-bottom: 1rem;
            background: linear-gradient(135deg, var(--white), var(--accent-green));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        
        .page-subtitle {
            font-size: 1.1rem;
            opacity: 0.8;
            margin-bottom: 2rem;
        }
        
        .stats-grid {
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
            color: var(--primary-black);
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1);
            border: 1px solid rgba(16, 185, 129, 0.2);
        }
        
        .stat-number {
            font-family: var(--font-display);
            font-size: 2rem;
            font-weight: 800;
            color: var(--primary-green);
            margin-bottom: 0.5rem;
        }
        
        .stat-label {
            font-size: 0.9rem;
            font-weight: 600;
            color: var(--gray-700);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .error-message {
            background: rgba(239, 68, 68, 0.1);
            color: #EF4444;
            padding: 1rem;
            border-radius: 0.5rem;
            margin: 1rem 0;
            border: 1px solid #EF4444;
        }
        
        .reservations-container {
            padding-bottom: 3rem;
        }
        
        .reservation-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-radius: 1.5rem;
            padding: 2rem;
            margin-bottom: 1.5rem;
            color: var(--primary-black);
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1);
            border-left: 4px solid var(--primary-green);
            transition: all 0.3s ease;
        }
        
        .reservation-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 20px 40px -10px rgba(0, 0, 0, 0.15);
        }
        
        .reservation-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 1.5rem;
            flex-wrap: wrap;
            gap: 1rem;
        }
        
        .reservation-title {
            font-family: var(--font-display);
            font-size: 1.3rem;
            font-weight: 700;
            color: var(--primary-black);
            flex: 1;
        }
        
        .status-badge {
            padding: 0.5rem 1rem;
            border-radius: 2rem;
            font-size: 0.8rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .status-pending {
            background: #FEF3C7;
            color: #92400E;
        }
        
        .status-confirmed {
            background: var(--pale-green);
            color: var(--dark-green);
        }
        
        .status-active {
            background: #DBEAFE;
            color: #1E40AF;
        }
        
        .status-completed {
            background: #F3E8FF;
            color: #7C3AED;
        }
        
        .status-cancelled {
            background: #FEE2E2;
            color: #DC2626;
        }
        
        .time-badge {
            padding: 0.3rem 0.8rem;
            border-radius: 1rem;
            font-size: 0.75rem;
            font-weight: 500;
            margin-left: 0.5rem;
        }
        
        .time-upcoming {
            background: #EBF8FF;
            color: #2563EB;
        }
        
        .time-active {
            background: #DCFCE7;
            color: #16A34A;
        }
        
        .time-past {
            background: #F1F5F9;
            color: #64748B;
        }
        
        .reservation-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 2rem;
        }
        
        .detail-section {
            background: var(--gray-100);
            padding: 1.5rem;
            border-radius: 1rem;
        }
        
        .section-title {
            font-weight: 700;
            color: var(--gray-800);
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .detail-item {
            display: flex;
            justify-content: space-between;
            margin-bottom: 0.75rem;
            padding-bottom: 0.5rem;
            border-bottom: 1px solid var(--gray-300);
        }
        
        .detail-item:last-child {
            margin-bottom: 0;
            border-bottom: none;
        }
        
        .detail-label {
            color: var(--gray-600);
            font-weight: 500;
        }
        
        .detail-value {
            color: var(--gray-800);
            font-weight: 600;
            text-align: right;
        }
        
        .amount-highlight {
            color: var(--primary-green);
            font-size: 1.1rem;
        }
        
        .empty-state {
            text-align: center;
            padding: 4rem 2rem;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 1.5rem;
            color: var(--white);
        }
        
        .empty-icon {
            font-size: 4rem;
            margin-bottom: 1rem;
            opacity: 0.6;
        }
        
        .empty-title {
            font-family: var(--font-display);
            font-size: 1.5rem;
            font-weight: 700;
            margin-bottom: 1rem;
        }
        
        .empty-subtitle {
            opacity: 0.8;
            margin-bottom: 2rem;
        }
        
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 1rem 2rem;
            background: var(--primary-green);
            color: var(--white);
            text-decoration: none;
            border-radius: 0.75rem;
            font-weight: 600;
            transition: all 0.3s ease;
            border: none;
            cursor: pointer;
        }
        
        .btn:hover {
            background: var(--dark-green);
            transform: translateY(-2px);
            box-shadow: 0 10px 25px -5px rgba(16, 185, 129, 0.3);
        }
        
        .action-buttons {
            margin-top: 1.5rem;
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
        }
        
        .btn-secondary {
            background: var(--gray-600);
            font-size: 0.9rem;
            padding: 0.75rem 1.5rem;
        }
        
        .btn-secondary:hover {
            background: var(--gray-700);
        }
        
        .btn-danger {
            background: #DC2626;
        }
        
        .btn-danger:hover {
            background: #B91C1C;
        }
        
        .btn-primary {
            background: var(--primary-green);
            color: var(--white);
        }
        
        .btn-primary:hover {
            background: var(--dark-green);
        }
        
        /* Styles pour le modal */
        .modal {
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            z-index: 1001;
            background: var(--white);
            border-radius: 1rem;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
            min-width: 400px;
            max-width: 90vw;
            display: none;
        }
        
        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 1000;
            display: none;
        }
        
        .modal.show {
            display: block;
            animation: modalFadeIn 0.3s ease;
        }
        
        .modal-overlay.show {
            display: block;
            animation: overlayFadeIn 0.3s ease;
        }
        
        @keyframes modalFadeIn {
            from {
                opacity: 0;
                transform: translate(-50%, -50%) scale(0.9);
            }
            to {
                opacity: 1;
                transform: translate(-50%, -50%) scale(1);
            }
        }
        
        @keyframes overlayFadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        
        .modal-content {
            padding: 0;
        }
        
        .modal-header {
            padding: 1.5rem 2rem 0 2rem;
            color: var(--primary-black);
        }
        
        .modal-header h3 {
            margin: 0;
            font-family: var(--font-display);
            color: var(--primary-green);
            font-size: 1.25rem;
        }
        
        .modal-body {
            padding: 1.5rem 2rem;
            color: var(--gray-700);
        }
        
        .modal-body p {
            margin-bottom: 1rem;
            line-height: 1.5;
        }
        
        .modal-body p:last-child {
            margin-bottom: 0;
        }
        
        .modal-body small {
            color: var(--gray-600);
            font-style: italic;
        }
        
        .modal-footer {
            padding: 0 2rem 2rem 2rem;
            display: flex;
            gap: 1rem;
            justify-content: flex-end;
        }
        
        .modal-footer .btn {
            min-width: 120px;
            text-align: center;
            font-size: 0.9rem;
            padding: 0.75rem 1.5rem;
        }
        
        /* Loading state */
        .modal-loading {
            text-align: center;
            padding: 2rem;
        }
        
        .spinner {
            width: 40px;
            height: 40px;
            border: 4px solid var(--gray-300);
            border-top: 4px solid var(--primary-green);
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin: 0 auto 1rem auto;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        @media (max-width: 768px) {
            .container {
                padding: 0 1rem;
            }
            
            .page-title {
                font-size: 2rem;
            }
            
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .reservation-card {
                padding: 1.5rem;
            }
            
            .reservation-details {
                grid-template-columns: 1fr;
                gap: 1rem;
            }
            
            .reservation-header {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .modal {
                min-width: 320px;
                margin: 1rem;
                max-width: calc(100vw - 2rem);
            }
            
            .modal-footer {
                flex-direction: column;
            }
            
            .modal-footer .btn {
                width: 100%;
            }
            
            .modal-header,
            .modal-body,
            .modal-footer {
                padding-left: 1.5rem;
                padding-right: 1.5rem;
            }
        }
    </style>
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

    <script>
        let currentReservationId = null;
        
        function confirmDownloadReceipt(reservationId) {
            console.log('Opening modal for reservation:', reservationId);
            currentReservationId = reservationId;
            
            const overlay = document.getElementById('modalOverlay');
            const modal = document.getElementById('confirmModal');
            
            overlay.classList.add('show');
            modal.classList.add('show');
            
            document.body.style.overflow = 'hidden';
            
            overlay.onclick = closeConfirmModal;
        }
        
        function closeConfirmModal() {
            console.log('Closing modal');
            const overlay = document.getElementById('modalOverlay');
            const modal = document.getElementById('confirmModal');
            
            overlay.classList.remove('show');
            modal.classList.remove('show');
            
            document.body.style.overflow = '';
            
            currentReservationId = null;
            
            overlay.onclick = null;
        }
        
        function downloadReceipt() {
            console.log('Starting download for reservation:', currentReservationId);
            
            if (!currentReservationId) {
                alert('Erreur: Aucune réservation sélectionnée');
                return;
            }
            
            showLoadingState();
            
            const downloadUrl = 'generate-receipt.php?reservation_id=' + currentReservationId;
            console.log('Download URL:', downloadUrl);
            
            const downloadWindow = window.open(downloadUrl, '_blank');
            
            if (!downloadWindow) {
                console.log('Popup blocked, using direct link');
                window.location.href = downloadUrl;
            }
            
            setTimeout(function() {
                closeConfirmModal();
                
                if (downloadWindow && !downloadWindow.closed) {
                    alert('📄 Reçu téléchargé avec succès !');
                } else {
                    alert('📄 Le téléchargement du reçu a été initié. Si rien ne se passe, vérifiez vos téléchargements ou autorisez les popups.');
                }
            }, 2000);
        }
        
        function showLoadingState() {
            const modal = document.getElementById('confirmModal');
            
            modal.innerHTML = '<div class="modal-content"><div class="modal-header"><h3>📄 Génération du reçu</h3></div><div class="modal-loading"><div class="spinner"></div><p><strong>Génération de votre reçu en cours...</strong></p><small>Veuillez patienter, le téléchargement va commencer automatiquement.</small></div></div>';
        }
        
        function cancelReservation(reservationId) {
            const reason = prompt('Veuillez indiquer la raison de l\'annulation (optionnel):');
            
            if (reason !== null) {
                if (confirm('Êtes-vous sûr de vouloir annuler cette réservation ?')) {
                    const button = event.target;
                    const originalText = button.innerHTML;
                    button.innerHTML = '⏳ Annulation...';
                    button.disabled = true;
                    
                    fetch('cancel-reservation.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify({
                            reservation_id: reservationId,
                            reason: reason || 'Annulation à la demande du client'
                        })
                    })
                    .then(function(response) {
                        return response.json();
                    })
                    .then(function(data) {
                        if (data.success) {
                            alert('Réservation annulée avec succès!' + 
                                  (data.refund_info ? '\n\n' + data.refund_info : ''));
                            window.location.reload();
                        } else {
                            alert('Erreur lors de l\'annulation: ' + data.error);
                            button.innerHTML = originalText;
                            button.disabled = false;
                        }
                    })
                    .catch(function(error) {
                        console.error('Erreur:', error);
                        alert('Une erreur est survenue lors de l\'annulation.');
                        button.innerHTML = originalText;
                        button.disabled = false;
                    });
                }
            }
        }
        
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                closeConfirmModal();
            }
        });
        
        document.addEventListener('DOMContentLoaded', function() {
            const modal = document.getElementById('confirmModal');
            const overlay = document.getElementById('modalOverlay');
            
            console.log('Modal element:', modal);
            console.log('Overlay element:', overlay);
            
            if (!modal || !overlay) {
                console.error('Modal or overlay element not found!');
            }
        });
    </script>
</body>
</html>