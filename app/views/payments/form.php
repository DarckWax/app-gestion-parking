<?php
$bodyClass = 'payment-page';
ob_start();
?>

<div class="container">
    <div class="payment-container">
        <!-- Demo Mode Notice -->
        <?php if (isset($is_demo) && $is_demo): ?>
        <div class="demo-notice">
            <div class="demo-alert">
                <h4><i class="icon-info"></i> Mode Démo Actif</h4>
                <p>Ceci est l'environnement de test PayPal. Aucun vrai paiement ne sera effectué.</p>
                <div class="demo-credentials">
                    <strong>Compte PayPal de test :</strong><br>
                    Email : <?= htmlspecialchars($demo_accounts['buyer_email']) ?><br>
                    Mot de passe : <?= htmlspecialchars($demo_accounts['buyer_password']) ?>
                </div>
                <p><small>Utilisez ces identifiants quand la popup PayPal apparaît, ou créez un nouveau compte sandbox.</small></p>
            </div>
        </div>
        <?php endif; ?>
        
        <div class="payment-header">
            <h1>Finaliser votre paiement</h1>
            <p>Paiement sécurisé pour votre réservation de parking</p>
        </div>
        
        <div class="payment-content">
            <!-- Reservation Summary -->
            <div class="reservation-summary">
                <h3>Récapitulatif de la réservation</h3>
                <div class="summary-details">
                    <div class="detail-row">
                        <span class="label">Code de réservation :</span>
                        <span class="value"><?= htmlspecialchars($reservation['reservation_code']) ?></span>
                    </div>
                    <div class="detail-row">
                        <span class="label">Place de parking :</span>
                        <span class="value"><?= htmlspecialchars($reservation['spot_number']) ?> (<?= ucfirst($reservation['spot_type_label'] ?? $reservation['spot_type']) ?>)</span>
                    </div>
                    <div class="detail-row">
                        <span class="label">Emplacement :</span>
                        <span class="value">Zone <?= htmlspecialchars($reservation['zone_section']) ?>, Niveau <?= $reservation['floor_level'] ?></span>
                    </div>
                    <div class="detail-row">
                        <span class="label">Début :</span>
                        <span class="value"><?= date('d/m/Y H:i', strtotime($reservation['start_datetime'])) ?></span>
                    </div>
                    <div class="detail-row">
                        <span class="label">Fin :</span>
                        <span class="value"><?= date('d/m/Y H:i', strtotime($reservation['end_datetime'])) ?></span>
                    </div>
                    <div class="detail-row total">
                        <span class="label">Montant total :</span>
                        <span class="value"><?= number_format($reservation['total_amount'], 2, ',', ' ') ?> €</span>
                    </div>
                </div>
            </div>
            
            <!-- Payment Form -->
            <div class="payment-form-container">
                <form id="paymentForm" 
                      data-reservation-id="<?= $reservation['reservation_id'] ?>"
                      data-amount="<?= $reservation['total_amount'] ?>">
                    
                    <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                    
                    <!-- Payment Method Selection -->
                    <div class="payment-methods">
                        <h3>Choisir le mode de paiement</h3>
                        
                        <div class="payment-method-option">
                            <label class="payment-method-label">
                                <input type="radio" name="payment_method" value="paypal" checked>
                                <span class="payment-method-content">
                                    <img src="/assets/images/paypal-logo.png" alt="PayPal" class="payment-logo">
                                    <span class="payment-method-text">
                                        PayPal 
                                        <?php if (isset($is_demo) && $is_demo): ?>
                                            <span class="demo-badge">DÉMO</span>
                                        <?php endif; ?>
                                    </span>
                                </span>
                            </label>
                        </div>
                        
                        <div class="payment-method-option">
                            <label class="payment-method-label">
                                <input type="radio" name="payment_method" value="credit_card">
                                <span class="payment-method-content">
                                    <div class="card-logos">
                                        <img src="/assets/images/visa-logo.png" alt="Visa" class="payment-logo">
                                        <img src="/assets/images/mastercard-logo.png" alt="MasterCard" class="payment-logo">
                                        <img src="/assets/images/amex-logo.png" alt="American Express" class="payment-logo">
                                    </div>
                                    <span class="payment-method-text">
                                        Carte Bancaire
                                        <?php if (isset($is_demo) && $is_demo): ?>
                                            <span class="demo-badge">DÉMO</span>
                                        <?php endif; ?>
                                    </span>
                                </span>
                            </label>
                        </div>
                    </div>
                    
                    <!-- PayPal Payment Form -->
                    <div id="paypal-form" class="payment-form" style="display: block;">
                        <div class="paypal-container">
                            <?php if (isset($is_demo) && $is_demo): ?>
                                <div class="demo-instructions">
                                    <h4>Instructions Mode Démo :</h4>
                                    <ol>
                                        <li>Cliquez sur le bouton PayPal ci-dessous</li>
                                        <li>Utilisez les identifiants de test affichés ci-dessus</li>
                                        <li>Complétez le processus de paiement</li>
                                        <li>Vous serez redirigé pour confirmer le paiement</li>
                                    </ol>
                                </div>
                            <?php endif; ?>
                            <p class="payment-description">Cliquez sur le bouton PayPal ci-dessous pour effectuer votre paiement en toute sécurité.</p>
                            <div id="paypal-button-container"></div>
                        </div>
                    </div>
                    
                    <!-- Credit Card Payment Form -->
                    <div id="credit_card-form" class="payment-form" style="display: none;">
                        <div class="card-form">
                            <?php if (isset($is_demo) && $is_demo): ?>
                                <div class="demo-card-info">
                                    <h4>Numéros de carte de test :</h4>
                                    <ul>
                                        <li><strong>Visa :</strong> 4111 1111 1111 1111</li>
                                        <li><strong>MasterCard :</strong> 5555 5555 5555 4444</li>
                                        <li><strong>Amex :</strong> 3714 496353 98431</li>
                                    </ul>
                                    <p><small>Utilisez n'importe quelle date d'expiration future et n'importe quel code CVV à 3-4 chiffres</small></p>
                                </div>
                            <?php endif; ?>
                            
                            <div class="form-group">
                                <label for="card_number" class="form-label">Numéro de carte</label>
                                <input type="text" 
                                       id="card_number" 
                                       name="card_number" 
                                       class="form-control" 
                                       placeholder="1234 5678 9012 3456"
                                       maxlength="19"
                                       required>
                            </div>
                            
                            <div class="form-row">
                                <div class="form-group col-md-6">
                                    <label for="card_expiry" class="form-label">Date d'expiration</label>
                                    <input type="text" 
                                           id="card_expiry" 
                                           name="card_expiry" 
                                           class="form-control" 
                                           placeholder="MM/AA"
                                           maxlength="5"
                                           required>
                                </div>
                                
                                <div class="form-group col-md-6">
                                    <label for="card_cvv" class="form-label">CVV</label>
                                    <input type="text" 
                                           id="card_cvv" 
                                           name="card_cvv" 
                                           class="form-control" 
                                           placeholder="123"
                                           maxlength="4"
                                           required>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label for="card_name" class="form-label">Nom du porteur</label>
                                <input type="text" 
                                       id="card_name" 
                                       name="card_name" 
                                       class="form-control" 
                                       placeholder="Jean Dupont"
                                       required>
                            </div>
                            
                            <button type="submit" class="btn btn-primary btn-lg btn-block">
                                Payer <?= number_format($reservation['total_amount'], 2, ',', ' ') ?> € (Démo)
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Security Notice -->
        <div class="security-notice">
            <div class="security-icons">
                <i class="icon-shield"></i>
                <i class="icon-lock"></i>
            </div>
            <p>
                <?php if (isset($is_demo) && $is_demo): ?>
                    Environnement de démonstration - Aucun vrai paiement ne sera effectué. Toutes les transactions sont simulées à des fins de test.
                <?php else: ?>
                    Vos informations de paiement sont chiffrées et sécurisées. Nous ne stockons jamais les détails de votre carte.
                <?php endif; ?>
            </p>
        </div>
    </div>
</div>

<!-- PayPal SDK Configuration -->
<meta name="paypal-client-id" content="<?= htmlspecialchars($paypal_client_id) ?>">
<meta name="paypal-mode" content="<?= htmlspecialchars($paypal_mode ?? 'sandbox') ?>">

<style>
.payment-container {
    max-width: 800px;
    margin: 0 auto;
    padding: 2rem;
}

.payment-header {
    text-align: center;
    margin-bottom: 3rem;
}

.payment-header h1 {
    color: #2c3e50;
    margin-bottom: 0.5rem;
}

.payment-content {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 3rem;
    margin-bottom: 2rem;
}

.reservation-summary {
    background: #f8f9fa;
    padding: 2rem;
    border-radius: 12px;
    border: 1px solid #e9ecef;
}

.reservation-summary h3 {
    color: #2c3e50;
    margin-bottom: 1.5rem;
    border-bottom: 2px solid #3498db;
    padding-bottom: 0.5rem;
}

.detail-row {
    display: flex;
    justify-content: space-between;
    padding: 0.75rem 0;
    border-bottom: 1px solid #e9ecef;
}

.detail-row:last-child {
    border-bottom: none;
}

.detail-row.total {
    font-weight: 600;
    font-size: 1.1rem;
    color: #2c3e50;
    margin-top: 1rem;
    padding-top: 1rem;
    border-top: 2px solid #3498db;
}

.label {
    color: #666;
}

.value {
    color: #2c3e50;
    font-weight: 500;
}

.payment-form-container {
    background: white;
    padding: 2rem;
    border-radius: 12px;
    box-shadow: 0 5px 20px rgba(0,0,0,0.1);
}

.payment-methods {
    margin-bottom: 2rem;
}

.payment-methods h3 {
    color: #2c3e50;
    margin-bottom: 1.5rem;
}

.payment-method-option {
    margin-bottom: 1rem;
}

.payment-method-label {
    display: block;
    padding: 1rem;
    border: 2px solid #e9ecef;
    border-radius: 8px;
    cursor: pointer;
    transition: all 0.3s ease;
}

.payment-method-label:hover {
    border-color: #3498db;
}

.payment-method-label input:checked + .payment-method-content,
.payment-method-label:has(input:checked) {
    border-color: #3498db;
    background-color: #f0f8ff;
}

.payment-method-content {
    display: flex;
    align-items: center;
    gap: 1rem;
}

.payment-logo {
    height: 30px;
    width: auto;
}

.card-logos {
    display: flex;
    gap: 0.5rem;
}

.card-logos .payment-logo {
    height: 24px;
}

.payment-method-text {
    font-weight: 500;
    color: #2c3e50;
}

.payment-form {
    margin-top: 2rem;
}

.paypal-container {
    text-align: center;
}

.payment-description {
    color: #666;
    margin-bottom: 2rem;
}

#paypal-button-container {
    max-width: 400px;
    margin: 0 auto;
}

.form-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1rem;
}

.btn-block {
    width: 100%;
    margin-top: 1.5rem;
}

.security-notice {
    text-align: center;
    padding: 1.5rem;
    background: #f8f9fa;
    border-radius: 8px;
    border: 1px solid #e9ecef;
}

.security-icons {
    margin-bottom: 1rem;
    color: #27ae60;
}

.security-notice p {
    color: #666;
    margin: 0;
    font-size: 0.9rem;
}

.demo-notice {
    margin-bottom: 2rem;
}

.demo-alert {
    background: linear-gradient(135deg, #ff7b54, #ff6b35);
    color: white;
    padding: 1.5rem;
    border-radius: 12px;
    box-shadow: 0 4px 15px rgba(255, 107, 53, 0.3);
}

.demo-alert h4 {
    margin-bottom: 1rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.demo-credentials {
    background: rgba(255, 255, 255, 0.1);
    padding: 1rem;
    border-radius: 8px;
    margin: 1rem 0;
    font-family: monospace;
}

.demo-badge {
    background: #ff6b35;
    color: white;
    padding: 0.2rem 0.5rem;
    border-radius: 4px;
    font-size: 0.7rem;
    font-weight: bold;
    margin-left: 0.5rem;
}

.demo-instructions {
    background: #e8f4f8;
    padding: 1rem;
    border-radius: 8px;
    margin-bottom: 1rem;
    border-left: 4px solid #3498db;
}

.demo-instructions h4 {
    color: #2c3e50;
    margin-bottom: 0.5rem;
}

.demo-instructions ol {
    margin: 0;
    padding-left: 1.5rem;
}

.demo-instructions li {
    margin-bottom: 0.5rem;
    color: #34495e;
}

.demo-card-info {
    background: #fff3cd;
    padding: 1rem;
    border-radius: 8px;
    margin-bottom: 1.5rem;
    border: 1px solid #ffeaa7;
}

.demo-card-info h4 {
    color: #856404;
    margin-bottom: 0.5rem;
}

.demo-card-info ul {
    margin: 0.5rem 0;
    padding-left: 1.5rem;
}

.demo-card-info li {
    margin-bottom: 0.3rem;
    color: #856404;
}

.demo-card-info p {
    margin-top: 0.5rem;
    color: #6c757d;
}

/* Responsive Design */
@media (max-width: 768px) {
    .payment-content {
        grid-template-columns: 1fr;
        gap: 2rem;
    }
    
    .form-row {
        grid-template-columns: 1fr;
    }
    
    .payment-container {
        padding: 1rem;
    }
}

/* PayPal button customization */
.paypal-buttons {
    margin-top: 1rem;
}

/* Loading state */
.payment-form.loading {
    opacity: 0.6;
    pointer-events: none;
}

.payment-form.loading::after {
    content: '';
    position: absolute;
    top: 50%;
    left: 50%;
    width: 30px;
    height: 30px;
    border: 3px solid #f3f3f3;
    border-top: 3px solid #3498db;
    border-radius: 50%;
    animation: spin 1s linear infinite;
    transform: translate(-50%, -50%);
}
</style>

<?php
$content = ob_get_clean();
include __DIR__ . '/../layouts/main.php';
?>
