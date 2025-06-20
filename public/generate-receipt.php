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
$format = $_GET['format'] ?? 'pdf'; // pdf ou html
$download = $_GET['download'] ?? '1'; // forcer le téléchargement

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
    
    // Générer le reçu selon le format demandé
    if ($format === 'pdf') {
        generateReceiptPDF($reservation, $download);
    } else {
        generateReceiptHTML($reservation);
    }
    
} catch (Exception $e) {
    error_log("Erreur génération reçu: " . $e->getMessage());
    http_response_code(500);
    exit('Erreur lors de la génération du reçu: ' . $e->getMessage());
}

/**
 * Génère et affiche le reçu PDF
 */
function generateReceiptPDF($reservation, $forceDownload = true) {
    // Vérifier si TCPDF est disponible
    if (!class_exists('TCPDF')) {
        // Essayer d'inclure TCPDF depuis différents emplacements possibles
        $tcpdfPaths = [
            __DIR__ . '/vendor/tecnickcom/tcpdf/tcpdf.php',
            __DIR__ . '/../vendor/tecnickcom/tcpdf/tcpdf.php',
            __DIR__ . '/tcpdf/tcpdf.php'
        ];
        
        $tcpdfLoaded = false;
        foreach ($tcpdfPaths as $path) {
            if (file_exists($path)) {
                require_once $path;
                $tcpdfLoaded = true;
                break;
            }
        }
        
        if (!$tcpdfLoaded) {
            // Si TCPDF n'est pas disponible, générer un HTML avec message
            generateReceiptHTML($reservation, true);
            return;
        }
    }
    
    try {
        // Configuration TCPDF
        $pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
        
        // Métadonnées du PDF
        $pdf->SetCreator('ParkFinder');
        $pdf->SetAuthor('ParkFinder System');
        $pdf->SetTitle('Reçu de parking - ' . $reservation['reservation_code']);
        $pdf->SetSubject('Reçu de paiement');
        
        // Supprimer header et footer par défaut
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        
        // Marges
        $pdf->SetMargins(15, 15, 15);
        $pdf->SetAutoPageBreak(true, 15);
        
        // Ajouter une page
        $pdf->AddPage();
        
        // Contenu HTML du reçu
        $html = generatePDFContent($reservation);
        
        // CSS pour le PDF
        $css = getPDFStyles();
        
        // Écrire le HTML avec CSS
        $pdf->writeHTML('<style>' . $css . '</style>' . $html, true, false, true, false, '');
        
        // Nom du fichier
        $filename = 'Recu_ParkFinder_' . $reservation['reservation_code'] . '.pdf';
        
        // Sortie du PDF
        if ($forceDownload) {
            $pdf->Output($filename, 'D'); // Force le téléchargement
        } else {
            $pdf->Output($filename, 'I'); // Affichage dans le navigateur
        }
        
    } catch (Exception $e) {
        error_log("Erreur génération PDF: " . $e->getMessage());
        
        // Fallback vers HTML en cas d'erreur
        generateReceiptHTML($reservation, true);
    }
}

/**
 * Génère le contenu HTML pour PDF
 */
function generatePDFContent($reservation) {
    // Types de places
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
    $paymentDate = $reservation['processed_at'] ? 
        date('d/m/Y à H:i', strtotime($reservation['processed_at'])) : 
        date('d/m/Y à H:i', strtotime($reservation['created_at']));
    
    // Calculs financiers
    $montantHT = $reservation['total_amount'];
    $montantTVA = $montantHT * 0.2;
    $montantTTC = $montantHT + $montantTVA;
    
    $html = '
    <div class="receipt-container">
        <!-- En-tête -->
        <div class="header">
            <div class="logo">
                <div class="logo-icon">P</div>
                <div class="logo-text">ParkFinder</div>
            </div>
            <h1 class="receipt-title">REÇU DE PAIEMENT</h1>
            <p class="header-subtitle">Système de gestion de parking intelligent</p>
        </div>
        
        <!-- Informations de la réservation -->
        <div class="section">
            <h2 class="section-title">🎫 Informations de la réservation</h2>
            <table class="detail-table">
                <tr>
                    <td class="detail-label">Code de réservation:</td>
                    <td class="detail-value"><strong>' . htmlspecialchars($reservation['reservation_code']) . '</strong></td>
                </tr>
                <tr>
                    <td class="detail-label">Date d\'émission:</td>
                    <td class="detail-value">' . $currentDate . '</td>
                </tr>
                <tr>
                    <td class="detail-label">Statut:</td>
                    <td class="detail-value success-text">✅ Payé et confirmé</td>
                </tr>
            </table>
        </div>
        
        <!-- Informations client -->
        <div class="section">
            <h2 class="section-title">👤 Informations client</h2>
            <table class="detail-table">
                <tr>
                    <td class="detail-label">Nom complet:</td>
                    <td class="detail-value">' . htmlspecialchars($reservation['first_name'] . ' ' . $reservation['last_name']) . '</td>
                </tr>
                <tr>
                    <td class="detail-label">Email:</td>
                    <td class="detail-value">' . htmlspecialchars($reservation['email']) . '</td>
                </tr>
                <tr>
                    <td class="detail-label">Téléphone:</td>
                    <td class="detail-value">' . htmlspecialchars($reservation['phone']) . '</td>
                </tr>
            </table>
        </div>
        
        <!-- Détails de la place -->
        <div class="section">
            <h2 class="section-title">🅿️ Détails de la place de parking</h2>
            <table class="detail-table">
                <tr>
                    <td class="detail-label">Numéro de place:</td>
                    <td class="detail-value"><strong>' . htmlspecialchars($reservation['spot_number']) . '</strong></td>
                </tr>
                <tr>
                    <td class="detail-label">Type de place:</td>
                    <td class="detail-value">' . $spotTypeLabel . '</td>
                </tr>
                <tr>
                    <td class="detail-label">Zone:</td>
                    <td class="detail-value">Zone ' . htmlspecialchars($reservation['zone_section']) . ', Niveau ' . $reservation['floor_level'] . '</td>
                </tr>
            </table>
        </div>
        
        <!-- Période de stationnement -->
        <div class="section">
            <h2 class="section-title">⏰ Période de stationnement</h2>
            <table class="detail-table">
                <tr>
                    <td class="detail-label">Date et heure de début:</td>
                    <td class="detail-value">' . $startDate . '</td>
                </tr>
                <tr>
                    <td class="detail-label">Date et heure de fin:</td>
                    <td class="detail-value">' . $endDate . '</td>
                </tr>
                <tr>
                    <td class="detail-label">Durée totale:</td>
                    <td class="detail-value"><strong>' . $reservation['duration_hours'] . ' heure(s)</strong></td>
                </tr>';
    
    if ($reservation['vehicle_plate']) {
        $html .= '<tr>
                    <td class="detail-label">Véhicule:</td>
                    <td class="detail-value">' . htmlspecialchars($reservation['vehicle_plate']) . '</td>
                </tr>';
    }
    
    $html .= '
            </table>
        </div>
        
        <!-- Détails du paiement -->
        <div class="section">
            <h2 class="section-title">💰 Détails du paiement</h2>
            <table class="detail-table">
                <tr>
                    <td class="detail-label">Moyen de paiement:</td>
                    <td class="detail-value">' . ucfirst($reservation['payment_method'] ?? 'PayPal') . '</td>
                </tr>
                <tr>
                    <td class="detail-label">ID de transaction:</td>
                    <td class="detail-value">' . htmlspecialchars($reservation['transaction_id'] ?? 'N/A') . '</td>
                </tr>
                <tr>
                    <td class="detail-label">Date de paiement:</td>
                    <td class="detail-value">' . $paymentDate . '</td>
                </tr>
            </table>
            
            <!-- Totaux -->
            <div class="total-section">
                <table class="total-table">
                    <tr>
                        <td class="total-label">Montant HT:</td>
                        <td class="total-value">' . number_format($montantHT, 2, ',', ' ') . ' €</td>
                    </tr>
                    <tr>
                        <td class="total-label">TVA (20%):</td>
                        <td class="total-value">' . number_format($montantTVA, 2, ',', ' ') . ' €</td>
                    </tr>
                    <tr class="total-final">
                        <td class="total-label"><strong>TOTAL TTC:</strong></td>
                        <td class="total-value"><strong>' . number_format($montantTTC, 2, ',', ' ') . ' €</strong></td>
                    </tr>
                </table>
            </div>
        </div>
        
        <!-- Tampon de validation -->
        <div class="stamp">
            <div class="stamp-icon">✅</div>
            <div class="stamp-text">DOCUMENT PAYÉ ET VALIDÉ</div>
            <div class="stamp-subtitle">Ce reçu fait foi de paiement pour la réservation ' . htmlspecialchars($reservation['reservation_code']) . '</div>
        </div>
        
        <!-- Pied de page -->
        <div class="footer">
            <div class="footer-logo">
                <div class="footer-logo-icon">P</div>
                <strong>ParkFinder - Système de gestion de parking</strong>
            </div>
            <p>📧 support@parkfinder.com | 📞 +33 1 23 45 67 89</p>
            <p>Document généré automatiquement le ' . $currentDate . '</p>
            <p style="margin-top: 10px; font-size: 10px;">Ce document est un reçu officiel de paiement.</p>
        </div>
    </div>';
    
    return $html;
}

/**
 * Retourne les styles CSS pour PDF
 */
function getPDFStyles() {
    return '
    body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 11px; color: #333; line-height: 1.4; }
    .receipt-container { max-width: 100%; }
    .header { text-align: center; border-bottom: 3px solid #10B981; padding-bottom: 15px; margin-bottom: 20px; }
    .logo { margin-bottom: 10px; }
    .logo-icon { display: inline-block; width: 30px; height: 30px; background: #10B981; color: white; text-align: center; line-height: 30px; font-weight: bold; border-radius: 6px; margin-right: 8px; }
    .logo-text { display: inline-block; font-size: 24px; font-weight: bold; color: #10B981; vertical-align: top; }
    .receipt-title { font-size: 20px; font-weight: bold; color: #111827; margin: 10px 0; }
    .header-subtitle { font-size: 12px; color: #6B7280; font-style: italic; }
    .section { margin-bottom: 20px; border: 1px solid #E5E7EB; border-radius: 6px; padding: 12px; }
    .section-title { font-size: 14px; font-weight: bold; color: #059669; margin-bottom: 10px; border-bottom: 2px solid #D1D5DB; padding-bottom: 5px; }
    .detail-table { width: 100%; border-collapse: collapse; }
    .detail-table td { padding: 5px 0; border-bottom: 1px dotted #E5E7EB; }
    .detail-label { width: 50%; font-weight: 500; color: #4B5563; }
    .detail-value { width: 50%; font-weight: 600; color: #111827; text-align: right; }
    .success-text { color: #059669; }
    .total-section { background: #ECFDF5; padding: 12px; border-radius: 6px; margin-top: 10px; border: 2px solid #10B981; }
    .total-table { width: 100%; border-collapse: collapse; }
    .total-table td { padding: 4px 0; }
    .total-label { font-weight: 500; color: #4B5563; }
    .total-value { text-align: right; font-weight: 600; color: #111827; }
    .total-final { border-top: 2px solid #10B981; padding-top: 6px; }
    .total-final td { font-size: 14px; color: #059669; }
    .stamp { background: #ECFDF5; border: 3px solid #10B981; padding: 15px; text-align: center; margin: 20px 0; border-radius: 8px; }
    .stamp-icon { font-size: 24px; margin-bottom: 5px; }
    .stamp-text { font-size: 14px; font-weight: bold; color: #059669; }
    .stamp-subtitle { font-size: 10px; color: #4B5563; margin-top: 5px; }
    .footer { text-align: center; font-size: 10px; color: #6B7280; border-top: 2px solid #D1D5DB; padding-top: 15px; margin-top: 20px; }
    .footer-logo { margin-bottom: 5px; }
    .footer-logo-icon { display: inline-block; width: 12px; height: 12px; background: #10B981; color: white; text-align: center; line-height: 12px; font-size: 8px; border-radius: 2px; margin-right: 4px; }
    ';
}

/**
 * Génère et affiche le reçu HTML (fallback)
 */
function generateReceiptHTML($reservation, $showTcpdfError = false) {
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
    $paymentDate = $reservation['processed_at'] ? 
        date('d/m/Y à H:i', strtotime($reservation['processed_at'])) : 
        date('d/m/Y à H:i', strtotime($reservation['created_at']));
    
    // Headers pour affichage HTML
    header('Content-Type: text/html; charset=UTF-8');
    
    echo '<!DOCTYPE html>
    <html lang="fr">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Reçu ParkFinder - ' . htmlspecialchars($reservation['reservation_code']) . '</title>
        <link rel="stylesheet" href="assets/css/receipt.css">
    </head>
    <body>';
    
    if ($showTcpdfError) {
        echo '<div class="print-info" style="background: #FEF3C7; border-color: #F59E0B; color: #92400E;">
            <div class="print-info-title">⚠️ Génération PDF non disponible</div>
            <div class="print-info-subtitle">TCPDF n\'est pas installé. Utilisez Ctrl+P pour imprimer cette page.</div>
        </div>';
    } else {
        echo '<div class="print-info">
            <div class="print-info-title">📄 Reçu généré avec succès !</div>
            <div class="print-info-subtitle">Vous pouvez imprimer ce document en utilisant Ctrl+P ou le sauvegarder en PDF.</div>
        </div>';
    }
    
    echo generatePDFContent($reservation);
    
    echo '<script src="assets/js/receipt.js"></script>
    </body>
    </html>';
}
?>

