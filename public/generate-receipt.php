<?php
session_start();

// Vérifier la connexion
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    exit('Accès non autorisé - Veuillez vous connecter');
}

// Configuration
$config = [
    'db_host' => 'localhost',
    'db_name' => 'parking_management_system', 
    'db_user' => 'root',
    'db_pass' => ''
];

$reservationId = $_GET['reservation_id'] ?? null;

if (!$reservationId) {
    http_response_code(400);
    exit('ID de réservation manquant');
}

try {
    $pdo = new PDO("mysql:host={$config['db_host']};dbname={$config['db_name']}", $config['db_user'], $config['db_pass']);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Requête optimisée unique avec tous les JOIN nécessaires
    $stmt = $pdo->prepare("
        SELECT 
            r.reservation_code, r.start_datetime, r.end_datetime, r.total_amount, 
            r.vehicle_plate, r.created_at,
            ps.spot_number, ps.spot_type, ps.zone_section, ps.floor_level,
            u.first_name, u.last_name, u.email, u.phone,
            p.payment_method, p.transaction_id, p.processed_at,
            TIMESTAMPDIFF(HOUR, r.start_datetime, r.end_datetime) as duration_hours
        FROM reservations r
        JOIN parking_spots ps ON r.spot_id = ps.spot_id
        JOIN users u ON r.user_id = u.user_id
        LEFT JOIN payments p ON r.reservation_id = p.reservation_id
        WHERE r.reservation_id = ? AND r.user_id = ? AND r.payment_status = 'paid'
    ");
    $stmt->execute([$reservationId, $_SESSION['user_id']]);
    $reservation = $stmt->fetch();
    
    if (!$reservation) {
        http_response_code(404);
        exit('Réservation non trouvée ou non payée');
    }
    
    // Générer le reçu directement
    generateReceiptHTML($reservation);
    
} catch (Exception $e) {
    error_log("Erreur génération reçu: " . $e->getMessage());
    http_response_code(500);
    exit('Erreur lors de la génération du reçu: ' . $e->getMessage());
}

/**
 * Génère et affiche le reçu HTML optimisé
 */
function generateReceiptHTML($reservation) {
    // Types de places pré-définis pour éviter les conditions
    $typeLabels = [
        'standard' => 'Standard',
        'disabled' => 'PMR',
        'electric' => 'Électrique', 
        'reserved' => 'VIP',
        'compact' => 'Compact'
    ];
    
    $spotTypeLabel = $typeLabels[$reservation['spot_type']] ?? $reservation['spot_type'];
    $currentDate = date('d/m/Y à H:i');
    $startDate = date('d/m/Y à H:i', strtotime($reservation['start_datetime']));
    $endDate = date('d/m/Y à H:i', strtotime($reservation['end_datetime']));
    $paymentDate = $reservation['processed_at'] ? date('d/m/Y à H:i', strtotime($reservation['processed_at'])) : date('d/m/Y à H:i', strtotime($reservation['created_at']));
    
    // Headers pour téléchargement
    header('Content-Type: text/html; charset=UTF-8');
    header('Content-Disposition: attachment; filename="Recu_ParkFinder_' . $reservation['reservation_code'] . '.html"');
    header('Cache-Control: no-cache, must-revalidate');
    header('Content-Length: ' . strlen(getReceiptContent($reservation, $spotTypeLabel, $currentDate, $startDate, $endDate, $paymentDate)));
    
    echo getReceiptContent($reservation, $spotTypeLabel, $currentDate, $startDate, $endDate, $paymentDate);
}

/**
 * Génère le contenu HTML du reçu (optimisé)
 */
function getReceiptContent($r, $spotTypeLabel, $currentDate, $startDate, $endDate, $paymentDate) {
    $htAmount = number_format($r['total_amount'], 2, ',', ' ');
    $tvaAmount = number_format($r['total_amount'] * 0.2, 2, ',', ' ');
    $ttcAmount = number_format($r['total_amount'] * 1.2, 2, ',', ' ');
    $vehiclePlate = $r['vehicle_plate'] ? '<div class="row"><span>Véhicule:</span><span>' . htmlspecialchars($r['vehicle_plate']) . '</span></div>' : '';
    
    return '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Reçu ParkFinder</title><style>
*{margin:0;padding:0;box-sizing:border-box}
body{font:14px Arial,sans-serif;color:#333;max-width:800px;margin:0 auto;padding:20px;line-height:1.4}
.header{text-align:center;border-bottom:3px solid #10B981;padding-bottom:20px;margin-bottom:30px}
.logo{display:flex;align-items:center;justify-content:center;gap:12px;margin-bottom:15px}
.logo-icon{width:48px;height:48px;background:linear-gradient(135deg,#10B981,#059669);border-radius:8px;display:flex;align-items:center;justify-content:center;font-size:28px;font-weight:bold;color:white;box-shadow:0 4px 12px rgba(16,185,129,0.3)}
.logo-text{font-size:32px;font-weight:bold;color:#10B981}
.title{font-size:24px;color:#111827;font-weight:bold}
.section{margin-bottom:25px;page-break-inside:avoid}
.section-title{font-size:16px;font-weight:bold;color:#059669;margin-bottom:15px;border-bottom:2px solid #D1D5DB;padding-bottom:5px}
.row{display:flex;justify-content:space-between;margin-bottom:8px;padding:5px 0}
.row span:first-child{font-weight:500;color:#4B5563}
.row span:last-child{font-weight:600;color:#111827;text-align:right}
.total{background:#ECFDF5;padding:15px;border-radius:8px;margin-top:15px;border:2px solid #10B981}
.total .amount{font-size:20px;font-weight:bold;color:#059669}
.stamp{background:#ECFDF5;border:3px solid #10B981;padding:15px;text-align:center;margin:20px 0;border-radius:8px;font-weight:bold}
.footer{margin-top:40px;text-align:center;font-size:12px;color:#6B7280;border-top:1px solid #D1D5DB;padding-top:20px}
.print-info{background:#F3F4F6;padding:15px;border-radius:8px;margin:20px 0;text-align:center;font-size:14px}
@media print{.print-info{display:none}body{margin:0}}
</style></head><body>
<div class="print-info"><strong>📄 Reçu téléchargé avec succès !</strong><br><small>Vous pouvez imprimer ce document en utilisant Ctrl+P ou le sauvegarder en PDF.</small></div>
<div class="header">
<div class="logo"><div class="logo-icon">P</div><div class="logo-text">ParkFinder</div></div>
<div class="title">REÇU DE PAIEMENT</div>
<div style="font-size:14px;margin-top:10px;color:#6B7280">Système de gestion de parking intelligent</div>
</div>
<div class="section">
<div class="section-title">🎫 Informations de la réservation</div>
<div class="row"><span>Code de réservation:</span><span>' . htmlspecialchars($r['reservation_code']) . '</span></div>
<div class="row"><span>Date d\'émission:</span><span>' . $currentDate . '</span></div>
<div class="row"><span>Statut:</span><span style="color:#059669">✅ Payé et confirmé</span></div>
</div>
<div class="section">
<div class="section-title">Informations client</div>
<div class="row"><span>Nom complet:</span><span>' . htmlspecialchars($r['first_name'] . ' ' . $r['last_name']) . '</span></div>
<div class="row"><span>Email:</span><span>' . htmlspecialchars($r['email']) . '</span></div>
<div class="row"><span>Téléphone:</span><span>' . htmlspecialchars($r['phone']) . '</span></div>
</div>
<div class="section">
<div class="section-title">Détails de la place de parking</div>
<div class="row"><span>Numéro de place:</span><span>' . htmlspecialchars($r['spot_number']) . '</span></div>
<div class="row"><span>Type de place:</span><span>' . $spotTypeLabel . '</span></div>
<div class="row"><span>Zone:</span><span>Zone ' . htmlspecialchars($r['zone_section']) . ', Niveau ' . $r['floor_level'] . '</span></div>
</div>
<div class="section">
<div class="section-title">Période de stationnement</div>
<div class="row"><span>Date et heure de début:</span><span>' . $startDate . '</span></div>
<div class="row"><span>Date et heure de fin:</span><span>' . $endDate . '</span></div>
<div class="row"><span>Durée totale:</span><span>' . $r['duration_hours'] . ' heure(s)</span></div>
' . $vehiclePlate . '
</div>
<div class="section">
<div class="section-title">Détails du paiement</div>
<div class="row"><span>Moyen de paiement:</span><span>' . ucfirst($r['payment_method'] ?? 'PayPal') . '</span></div>
<div class="row"><span>ID de transaction:</span><span>' . htmlspecialchars($r['transaction_id'] ?? 'N/A') . '</span></div>
<div class="row"><span>Date de paiement:</span><span>' . $paymentDate . '</span></div>
<div class="total">
<div class="row"><span>Montant HT:</span><span>' . $htAmount . ' €</span></div>
<div class="row"><span>TVA (20%):</span><span>' . $tvaAmount . ' €</span></div>
<div class="row amount"><span>TOTAL TTC:</span><span>' . $ttcAmount . ' €</span></div>
</div>
</div>
<div class="stamp">✅ DOCUMENT PAYÉ ET VALIDÉ<br><small style="font-weight:normal">Ce reçu fait foi de paiement pour la réservation ' . htmlspecialchars($r['reservation_code']) . '</small></div>
<div class="footer">
<p><strong>ParkFinder - Système de gestion de parking</strong></p>
<p>📧 support@parkfinder.com | 📞 +33 1 23 45 67 89</p>
<p>Document généré automatiquement le ' . $currentDate . '</p>
</div>
<script>window.onload=function(){if(confirm("Voulez-vous imprimer ce reçu maintenant ?")){window.print()}}</script>
</body></html>';
}
?>
            .total-amount { 
                font-size: 20px; 
                font-weight: bold; 
                color: #059669; 
            }
            .footer { 
                margin-top: 40px; 
                text-align: center; 
                font-size: 12px; 
                color: #6B7280; 
                border-top: 1px solid #D1D5DB; 
                padding-top: 20px; 
            }
            .stamp { 
                background: #ECFDF5; 
                border: 3px solid #10B981; 
                padding: 15px; 
                text-align: center; 
                margin: 20px 0; 
                border-radius: 8px; 
                font-weight: bold;
            }
            .print-info {
                background: #F3F4F6;
                padding: 15px;
                border-radius: 8px;
                margin: 20px 0;
                text-align: center;
                font-size: 14px;
            }
            @media print {
                .print-info { display: none; }
                body { margin: 0; }
            }
        </style>
    </head>
    <body>
        <div class="print-info">
            <strong>📄 Reçu téléchargé avec succès !</strong><br>
            <small>Vous pouvez imprimer ce document en utilisant Ctrl+P ou le sauvegarder en PDF.</small>
        </div>
        
        <div class="header">
            <div class="logo">
                <div class="logo-icon">P</div>
                <div class="logo-text">ParkFinder</div>
            </div>
            <div class="receipt-title">REÇU DE PAIEMENT</div>
            <div style="font-size: 14px; margin-top: 10px; color: #6B7280;">Système de gestion de parking intelligent</div>
        </div>
        
        <div class="section">
            <div class="section-title">🎫 Informations de la réservation</div>
            <div class="detail-row">
                <span class="detail-label">Code de réservation:</span>
                <span class="detail-value">' . htmlspecialchars($reservation['reservation_code']) . '</span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Date d\'émission:</span>
                <span class="detail-value">' . date('d/m/Y à H:i') . '</span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Statut:</span>
                <span class="detail-value" style="color: #059669;">✅ Payé et confirmé</span>
            </div>
        </div>
        
        <div class="section">
            <div class="section-title">👤 Informations client</div>
            <div class="detail-row">
                <span class="detail-label">Nom complet:</span>
                <span class="detail-value">' . htmlspecialchars($reservation['first_name'] . ' ' . $reservation['last_name']) . '</span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Email:</span>
                <span class="detail-value">' . htmlspecialchars($reservation['email']) . '</span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Téléphone:</span>
                <span class="detail-value">' . htmlspecialchars($reservation['phone']) . '</span>
            </div>
        </div>
        
        <div class="section">
            <div class="section-title">🅿️ Détails de la place de parking</div>
            <div class="detail-row">
                <span class="detail-label">Numéro de place:</span>
                <span class="detail-value">' . htmlspecialchars($reservation['spot_number']) . '</span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Type de place:</span>
                <span class="detail-value">' . $spotTypeLabel . '</span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Zone:</span>
                <span class="detail-value">Zone ' . htmlspecialchars($reservation['zone_section']) . ', Niveau ' . $reservation['floor_level'] . '</span>
            </div>
        </div>
        
        <div class="section">
            <div class="section-title">⏰ Période de stationnement</div>
            <div class="detail-row">
                <span class="detail-label">Date et heure de début:</span>
                <span class="detail-value">' . date('d/m/Y à H:i', strtotime($reservation['start_datetime'])) . '</span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Date et heure de fin:</span>
                <span class="detail-value">' . date('d/m/Y à H:i', strtotime($reservation['end_datetime'])) . '</span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Durée totale:</span>
                <span class="detail-value">' . $reservation['duration_hours'] . ' heure(s)</span>
            </div>';
    
    if ($reservation['vehicle_plate']) {
        echo '<div class="detail-row">
                <span class="detail-label">Véhicule:</span>
                <span class="detail-value">' . htmlspecialchars($reservation['vehicle_plate']) . '</span>
            </div>';
    }
    
    echo '</div>
        
        <div class="section">
            <div class="section-title">💰 Détails du paiement</div>
            <div class="detail-row">
                <span class="detail-label">Moyen de paiement:</span>
                <span class="detail-value">' . ucfirst($reservation['payment_method'] ?? 'PayPal') . '</span>
            </div>
            <div class="detail-row">
                <span class="detail-label">ID de transaction:</span>
                <span class="detail-value">' . htmlspecialchars($reservation['transaction_id'] ?? 'N/A') . '</span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Date de paiement:</span>
                <span class="detail-value">' . ($reservation['processed_at'] ? date('d/m/Y à H:i', strtotime($reservation['processed_at'])) : date('d/m/Y à H:i', strtotime($reservation['created_at']))) . '</span>
            </div>
            
            <div class="total-row">
                <div class="detail-row">
                    <span class="detail-label">Montant HT:</span>
                    <span class="detail-value">' . number_format($reservation['total_amount'], 2, ',', ' ') . ' €</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">TVA (20%):</span>
                    <span class="detail-value">' . number_format($reservation['total_amount'] * 0.2, 2, ',', ' ') . ' €</span>
                </div>
                <div class="detail-row total-amount">
                    <span>TOTAL TTC:</span>
                    <span>' . number_format($reservation['total_amount'] * 1.2, 2, ',', ' ') . ' €</span>
                </div>
            </div>
        </div>
        
        <div class="stamp">
            ✅ DOCUMENT PAYÉ ET VALIDÉ<br>
            <small style="font-weight: normal;">Ce reçu fait foi de paiement pour la réservation ' . htmlspecialchars($reservation['reservation_code']) . '</small>
        </div>
        
        <div class="footer">
            <p><strong>
                <span style="display: inline-flex; align-items: center; justify-content: center;">
                    <span style="
                        display: inline-flex;
                        width: 20px;
                        height: 20px;
                        background: linear-gradient(135deg, #10B981, #059669);
                        border-radius: 4px;
                        align-items: center;
                        justify-content: center;
                        color: white;
                        font-size: 12px;
                        font-weight: 800;
                        margin-right: 8px;
                    ">P</span>
                    ParkFinder - Système de gestion de parking
                </span>
            </strong></p>
            <p>📧 support@parkfinder.com | 📞 +33 1 23 45 67 89</p>
            <p>Document généré automatiquement le ' . date('d/m/Y à H:i:s') . '</p>
        </div>
        
        <script>
            // Proposer l\'impression automatiquement
            window.onload = function() {
                if (confirm("Voulez-vous imprimer ce reçu maintenant ?")) {
                    window.print();
                }
            }
        </script>
    </body>
    </html>';
}
?>
