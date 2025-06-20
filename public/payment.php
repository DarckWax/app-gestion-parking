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

$reservationId = $_GET['reservation_id'] ?? null;

if (!$reservationId) {
    header('Location: reservation.php');
    exit;
}

// Connexion base de données
try {
    $pdo = new PDO("mysql:host={$config['db_host']};dbname={$config['db_name']}", $config['db_user'], $config['db_pass']);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Récupérer les détails de la réservation
    $stmt = $pdo->prepare("
        SELECT r.*, ps.spot_number, ps.spot_type, ps.zone_section, ps.floor_level,
               u.first_name, u.last_name, u.email
        FROM reservations r
        JOIN parking_spots ps ON r.spot_id = ps.spot_id
        JOIN users u ON r.user_id = u.user_id
        WHERE r.reservation_id = ? AND r.user_id = ?
    ");
    $stmt->execute([$reservationId, $_SESSION['user_id']]);
    $reservation = $stmt->fetch();
    
    if (!$reservation) {
        header('Location: reservation.php');
        exit;
    }
    
} catch(Exception $e) {
    header('Location: reservation.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Paiement - ParkFinder</title>
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
        
        .payment-container {
            padding: 2rem 0;
            min-height: calc(100vh - 80px);
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .payment-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-radius: 1.5rem;
            padding: 2rem;
            max-width: 800px;
            width: 100%;
            color: var(--primary-black);
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
        }
        
        .payment-title {
            font-family: var(--font-display);
            font-size: 2rem;
            font-weight: 800;
            text-align: center;
            margin-bottom: 2rem;
            color: var(--primary-green);
        }
        
        .reservation-summary {
            background: var(--gray-100);
            padding: 1.5rem;
            border-radius: 1rem;
            margin-bottom: 2rem;
        }
        
        .summary-title {
            font-weight: 700;
            margin-bottom: 1rem;
            color: var(--gray-800);
        }
        
        .summary-item {
            display: flex;
            justify-content: space-between;
            margin-bottom: 0.5rem;
            padding: 0.25rem 0;
        }
        
        .summary-label {
            color: var(--gray-600);
        }
        
        .summary-value {
            font-weight: 600;
            color: var(--gray-800);
        }
        
        .total-amount {
            border-top: 2px solid var(--gray-300);
            padding-top: 1rem;
            margin-top: 1rem;
            font-size: 1.25rem;
            font-weight: 700;
            color: var(--primary-green);
        }
        
        .payment-methods {
            margin-bottom: 2rem;
        }
        
        .methods-title {
            font-weight: 700;
            margin-bottom: 1rem;
            color: var(--gray-800);
        }
        
        .payment-method {
            background: var(--white);
            border: 2px solid var(--gray-300);
            border-radius: 0.75rem;
            padding: 1rem;
            margin-bottom: 1rem;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        
        .payment-method:hover {
            border-color: var(--primary-green);
        }
        
        .payment-method.selected {
            border-color: var(--primary-green);
            background: var(--pale-green);
        }
        
        .method-icon {
            font-size: 1.5rem;
        }
        
        .method-info {
            flex: 1;
        }
        
        .method-name {
            font-weight: 600;
            color: var(--gray-800);
        }
        
        .method-description {
            font-size: 0.875rem;
            color: var(--gray-600);
        }
        
        .btn {
            width: 100%;
            padding: 1rem 2rem;
            background: var(--primary-green);
            color: var(--white);
            border: none;
            border-radius: 0.75rem;
            font-weight: 600;
            font-size: 1rem;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
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
        
        @media (max-width: 768px) {
            .payment-card {
                margin: 1rem;
                padding: 1.5rem;
            }
            
            .payment-title {
                font-size: 1.5rem;
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
                    <span>ParkFinder</span>
                </a>
                <a href="reservation.php" class="back-link">
                    ← Retour à la réservation
                </a>
            </nav>
        </div>
    </header>

    <div class="payment-container">
        <div class="container">
            <div class="payment-card">
                <h1 class="payment-title">💳 Finaliser le paiement</h1>
                
                <!-- Résumé de la réservation -->
                <div class="reservation-summary">
                    <h2 class="summary-title">📋 Résumé de votre réservation</h2>
                    
                    <div class="summary-item">
                        <span class="summary-label">Code de réservation:</span>
                        <span class="summary-value"><?= htmlspecialchars($reservation['reservation_code']) ?></span>
                    </div>
                    
                    <div class="summary-item">
                        <span class="summary-label">Place:</span>
                        <span class="summary-value"><?= htmlspecialchars($reservation['spot_number']) ?> (Zone <?= htmlspecialchars($reservation['zone_section']) ?>)</span>
                    </div>
                    
                    <div class="summary-item">
                        <span class="summary-label">Date de début:</span>
                        <span class="summary-value"><?= date('d/m/Y à H:i', strtotime($reservation['start_datetime'])) ?></span>
                    </div>
                    
                    <div class="summary-item">
                        <span class="summary-label">Date de fin:</span>
                        <span class="summary-value"><?= date('d/m/Y à H:i', strtotime($reservation['end_datetime'])) ?></span>
                    </div>
                    
                    <div class="summary-item">
                        <span class="summary-label">Durée:</span>
                        <span class="summary-value">
                            <?php
                            $start = new DateTime($reservation['start_datetime']);
                            $end = new DateTime($reservation['end_datetime']);
                            $duration = $start->diff($end);
                            echo $duration->format('%h heures %i minutes');
                            ?>
                        </span>
                    </div>
                    
                    <div class="summary-item total-amount">
                        <span class="summary-label">Total à payer:</span>
                        <span class="summary-value"><?= number_format($reservation['total_amount'], 2, ',', ' ') ?> €</span>
                    </div>
                </div>
                
                <!-- Méthodes de paiement -->
                <div class="payment-methods">
                    <h2 class="methods-title">💰 Choisir une méthode de paiement</h2>
                    
                    <div class="payment-method selected" onclick="selectPaymentMethod(this)" data-method="paypal">
                        <div class="method-icon">💳</div>
                        <div class="method-info">
                            <div class="method-name">PayPal</div>
                            <div class="method-description">Paiement sécurisé via PayPal</div>
                        </div>
                    </div>
                    
                    <div class="payment-method" onclick="selectPaymentMethod(this)" data-method="card">
                        <div class="method-icon">💎</div>
                        <div class="method-info">
                            <div class="method-name">Carte bancaire</div>
                            <div class="method-description">Visa, Mastercard, American Express</div>
                        </div>
                    </div>
                </div>
                
                <!-- Bouton de paiement -->
                <form id="paymentForm" method="POST" action="payment-success.php">
                    <input type="hidden" name="reservation_id" value="<?= $reservationId ?>">
                    <input type="hidden" name="payment_method" id="selectedMethod" value="paypal">
                    <input type="hidden" name="amount" value="<?= $reservation['total_amount'] ?>">
                    
                    <button type="button" class="btn" id="proceedPaymentBtn" onclick="showPayPalButton()">
                        🔒 Procéder au paiement - <?= number_format($reservation['total_amount'], 2, ',', ' ') ?> €
                    </button>
                </form>
                
                <!-- Container pour le bouton PayPal (caché initialement) -->
                <div id="paypal-button-container" style="display: none; margin-top: 1rem;">
                    <div style="background: var(--pale-green); padding: 1rem; border-radius: 0.75rem; margin-bottom: 1rem; text-align: center;">
                        <p style="color: var(--dark-green); font-weight: 600;">💳 Finalisez votre paiement avec PayPal</p>
                        <p style="font-size: 0.875rem; color: var(--gray-600);">Mode sandbox - Aucun vrai paiement ne sera effectué</p>
                    </div>
                </div>
                
                <!-- Message de retour au paiement classique -->
                <div id="back-to-classic" style="display: none; margin-top: 1rem; text-align: center;">
                    <button type="button" class="btn" style="background: var(--gray-600);" onclick="hidePayPalButton()">
                        ← Retour aux méthodes de paiement
                    </button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- SDK PayPal -->
    <script src="https://www.paypal.com/sdk/js?client-id=AU_d4_uDrLX2CzYvvKneqz0Ew752P-xt4RgSu4qz366x2wDP-JSSCrAeqB-Elg1sUu2f6Wp_nhxDLGti&currency=EUR&intent=capture"></script>
    
    <script>
        // Variables globales pour la gestion PayPal
        let paypalButtonRendered = false;
        let reservationData = {
            id: <?= $reservationId ?>,
            amount: <?= $reservation['total_amount'] ?>,
            code: '<?= htmlspecialchars($reservation['reservation_code']) ?>',
            customer: '<?= htmlspecialchars($reservation['first_name'] . ' ' . $reservation['last_name']) ?>',
            spot: '<?= htmlspecialchars($reservation['spot_number']) ?>'
        };
        
        /**
         * Affiche le bouton PayPal et masque le bouton classique
         */
        function showPayPalButton() {
            console.log('Affichage du bouton PayPal...');
            
            // Masquer le bouton initial
            document.getElementById('proceedPaymentBtn').style.display = 'none';
            
            // Afficher le container PayPal
            document.getElementById('paypal-button-container').style.display = 'block';
            document.getElementById('back-to-classic').style.display = 'block';
            
            // Rendre le bouton PayPal une seule fois
            if (!paypalButtonRendered) {
                renderPayPalButton();
                paypalButtonRendered = true;
            }
        }
        
        /**
         * Masque le bouton PayPal et réaffiche le bouton classique
         */
        function hidePayPalButton() {
            console.log('Masquage du bouton PayPal...');
            
            // Réafficher le bouton initial
            document.getElementById('proceedPaymentBtn').style.display = 'flex';
            
            // Masquer le container PayPal
            document.getElementById('paypal-button-container').style.display = 'none';
            document.getElementById('back-to-classic').style.display = 'none';
        }
        
        /**
         * Initialise et rend le bouton PayPal Smart Button
         */
        function renderPayPalButton() {
            console.log('Rendu du bouton PayPal...', reservationData);
            
            paypal.Buttons({
                // Style du bouton PayPal
                style: {
                    layout: 'vertical',
                    color: 'blue',
                    shape: 'rect',
                    label: 'paypal',
                    height: 45
                },
                
                /**
                 * Création de la commande PayPal
                 */
                createOrder: function(data, actions) {
                    console.log('Création de la commande PayPal...');
                    
                    return actions.order.create({
                        purchase_units: [{
                            reference_id: reservationData.code,
                            description: `Réservation parking ${reservationData.spot} - ${reservationData.code}`,
                            amount: {
                                currency_code: 'EUR',
                                value: reservationData.amount.toFixed(2)
                            },
                            payee: {
                                merchant_id: 'sandbox_merchant_parkfinder'
                            }
                        }],
                        application_context: {
                            brand_name: 'ParkFinder',
                            landing_page: 'NO_PREFERENCE',
                            user_action: 'PAY_NOW',
                            return_url: window.location.origin + '/Parkfinder/public/payment-success.php',
                            cancel_url: window.location.href
                        }
                    });
                },
                
                /**
                 * Approbation du paiement (avant capture)
                 */
                onApprove: function(data, actions) {
                    console.log('Paiement approuvé, capture en cours...', data);
                    
                    // Afficher un loader
                    showPaymentLoader();
                    
                    return actions.order.capture().then(function(orderData) {
                        console.log('Paiement capturé avec succès:', orderData);
                        
                        // Traiter le succès du paiement
                        handlePaymentSuccess(orderData);
                    });
                },
                
                /**
                 * Gestion des erreurs PayPal
                 */
                onError: function(err) {
                    console.error('Erreur PayPal:', err);
                    hidePaymentLoader();
                    
                    alert('❌ Erreur lors du paiement PayPal. Veuillez réessayer ou choisir une autre méthode de paiement.');
                    
                    // Retourner au bouton classique
                    hidePayPalButton();
                },
                
                /**
                 * Annulation du paiement par l'utilisateur
                 */
                onCancel: function(data) {
                    console.log('Paiement annulé par l\'utilisateur:', data);
                    hidePaymentLoader();
                    
                    alert('⚠️ Paiement annulé. Vous pouvez réessayer ou choisir une autre méthode.');
                    
                    // Retourner au bouton classique
                    hidePayPalButton();
                }
                
            }).render('#paypal-button-container');
            
            console.log('Bouton PayPal rendu avec succès');
        }
        
        /**
         * Traite le succès du paiement PayPal
         */
        function handlePaymentSuccess(orderData) {
            console.log('Traitement du succès de paiement...', orderData);
            
            // Extraire les informations importantes
            const paymentDetails = {
                order_id: orderData.id,
                payer: orderData.payer,
                status: orderData.status,
                amount: orderData.purchase_units[0].amount,
                transaction_id: orderData.purchase_units[0].payments.captures[0].id,
                timestamp: new Date().toISOString(),
                reservation_id: reservationData.id,
                reservation_code: reservationData.code,
                customer_name: reservationData.customer,
                spot_number: reservationData.spot
            };
            
            // Appeler le backend PHP pour enregistrer le paiement
            storePaymentSuccess(paymentDetails)
                .then(function(response) {
                    hidePaymentLoader();
                    
                    if (response.success) {
                        // Afficher le message de succès
                        alert(`✅ Paiement réussi par ${reservationData.customer} !\n\nMontant: ${reservationData.amount.toFixed(2)}€\nTransation: ${paymentDetails.transaction_id}`);
                        
                        // Rediriger vers la page de succès après 2 secondes
                        setTimeout(function() {
                            window.location.href = `payment-success.php?reservation_id=${reservationData.id}&transaction_id=${paymentDetails.transaction_id}`;
                        }, 2000);
                    } else {
                        throw new Error(response.error || 'Erreur lors de l\'enregistrement');
                    }
                })
                .catch(function(error) {
                    console.error('Erreur lors de l\'enregistrement:', error);
                    hidePaymentLoader();
                    
                    alert('⚠️ Paiement effectué mais erreur d\'enregistrement. Contactez le support avec cette référence: ' + paymentDetails.transaction_id);
                });
        }
        
        /**
         * Envoie les détails du paiement au backend PHP
         */
        function storePaymentSuccess(paymentDetails) {
            console.log('Envoi des données au backend...', paymentDetails);
            
            return fetch('payment-handler.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({
                    action: 'store_payment',
                    payment_details: paymentDetails
                })
            })
            .then(function(response) {
                if (!response.ok) {
                    throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                }
                return response.json();
            })
            .catch(function(error) {
                console.error('Erreur réseau:', error);
                throw error;
            });
        }
        
        /**
         * Affiche un loader pendant le traitement du paiement
         */
        function showPaymentLoader() {
            const container = document.getElementById('paypal-button-container');
            
            container.innerHTML = `
                <div style="text-align: center; padding: 2rem;">
                    <div style="display: inline-block; width: 40px; height: 40px; border: 4px solid #f3f3f3; border-top: 4px solid #10B981; border-radius: 50%; animation: spin 1s linear infinite;"></div>
                    <p style="margin-top: 1rem; color: var(--dark-green); font-weight: 600;">💳 Traitement du paiement en cours...</p>
                    <p style="font-size: 0.875rem; color: var(--gray-600);">Veuillez patienter, ne fermez pas cette page</p>
                </div>
                <style>
                    @keyframes spin {
                        0% { transform: rotate(0deg); }
                        100% { transform: rotate(360deg); }
                    }
                </style>
            `;
        }
        
        /**
         * Masque le loader de paiement
         */
        function hidePaymentLoader() {
            const container = document.getElementById('paypal-button-container');
            container.innerHTML = '';
            
            // Re-rendre le bouton PayPal si nécessaire
            if (paypalButtonRendered) {
                paypalButtonRendered = false;
                renderPayPalButton();
            }
        }
        
        // Gestion des méthodes de paiement existantes
        function selectPaymentMethod(element) {
            // Désélectionner toutes les méthodes
            document.querySelectorAll('.payment-method').forEach(function(method) {
                method.classList.remove('selected');
            });
            
            // Sélectionner la méthode cliquée
            element.classList.add('selected');
            
            // Mettre à jour le champ caché
            const method = element.dataset.method;
            document.getElementById('selectedMethod').value = method;
            
            // Mettre à jour le texte du bouton selon la méthode
            const btnElement = document.getElementById('proceedPaymentBtn');
            if (method === 'paypal') {
                btnElement.innerHTML = '🔒 Procéder au paiement PayPal - <?= number_format($reservation['total_amount'], 2, ',', ' ') ?> €';
                btnElement.onclick = showPayPalButton;
            } else {
                btnElement.innerHTML = '🔒 Payer par carte bancaire - <?= number_format($reservation['total_amount'], 2, ',', ' ') ?> €';
                btnElement.onclick = function() {
                    alert('💳 Paiement par carte bancaire non implémenté dans cette démo. Utilisez PayPal.');
                };
            }
            
            // Masquer PayPal si une autre méthode est sélectionnée
            if (method !== 'paypal') {
                hidePayPalButton();
            }
        }
        
        // Initialisation au chargement de la page
        document.addEventListener('DOMContentLoaded', function() {
            console.log('Page de paiement chargée, données:', reservationData);
            
            // Vérifier que PayPal SDK est chargé
            if (typeof paypal === 'undefined') {
                console.error('PayPal SDK non chargé !');
                alert('❌ Erreur de chargement PayPal. Veuillez actualiser la page.');
            } else {
                console.log('PayPal SDK chargé avec succès');
            }
        });
    </script>
</body>
</html>