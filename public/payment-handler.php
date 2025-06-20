<?php
/**
 * Gestionnaire des paiements PayPal
 * Simule un contrôleur MVC pour traiter les paiements
 */

// Configuration
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type, X-Requested-With');

// Activer la gestion d'erreurs
error_reporting(E_ALL);
ini_set('display_errors', 0); // Ne pas afficher les erreurs en production

/**
 * Classe PaymentController
 * Simule un contrôleur MVC pour la gestion des paiements
 */
class PaymentController 
{
    private $logPath;
    
    public function __construct() {
        // Chemin vers le fichier de log des paiements
        $this->logPath = dirname(__DIR__) . '/logs/payments.log';
        
        // Créer le dossier logs s'il n'existe pas
        $logsDir = dirname($this->logPath);
        if (!is_dir($logsDir)) {
            mkdir($logsDir, 0755, true);
        }
        
        // Créer le fichier s'il n'existe pas
        if (!file_exists($this->logPath)) {
            file_put_contents($this->logPath, '');
        }
    }
    
    /**
     * Enregistre le succès d'un paiement dans le fichier de log
     * 
     * @param array $details Détails du paiement PayPal
     * @return array Réponse JSON
     */
    public function storeSuccess($details) {
        try {
            // Validation des données requises
            $requiredFields = ['order_id', 'transaction_id', 'amount', 'reservation_id'];
            foreach ($requiredFields as $field) {
                if (!isset($details[$field])) {
                    throw new Exception("Champ requis manquant: $field");
                }
            }
            
            // Préparer les données à enregistrer
            $paymentRecord = [
                'timestamp' => date('Y-m-d H:i:s'),
                'order_id' => $details['order_id'],
                'transaction_id' => $details['transaction_id'],
                'reservation_id' => $details['reservation_id'],
                'reservation_code' => $details['reservation_code'] ?? 'N/A',
                'customer_name' => $details['customer_name'] ?? 'N/A',
                'spot_number' => $details['spot_number'] ?? 'N/A',
                'amount' => [
                    'value' => $details['amount']['value'] ?? $details['amount'],
                    'currency' => $details['amount']['currency_code'] ?? 'EUR'
                ],
                'payer' => [
                    'email' => $details['payer']['email_address'] ?? 'N/A',
                    'name' => $details['payer']['name']['given_name'] ?? 'N/A',
                    'payer_id' => $details['payer']['payer_id'] ?? 'N/A'
                ],
                'status' => $details['status'] ?? 'COMPLETED',
                'payment_source' => 'paypal_sandbox',
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
            ];
            
            // Convertir en JSON et ajouter au fichier de log
            $jsonRecord = json_encode($paymentRecord, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            $logEntry = "\n" . str_repeat('=', 80) . "\n";
            $logEntry .= "PAIEMENT REÇU - " . $paymentRecord['timestamp'] . "\n";
            $logEntry .= str_repeat('=', 80) . "\n";
            $logEntry .= $jsonRecord . "\n";
            
            // Écrire dans le fichier (thread-safe)
            if (file_put_contents($this->logPath, $logEntry, FILE_APPEND | LOCK_EX) === false) {
                throw new Exception("Impossible d'écrire dans le fichier de log");
            }
            
            // Log de succès
            error_log("PayPal payment stored successfully: " . $details['transaction_id']);
            
            return [
                'success' => true,
                'message' => 'Paiement enregistré avec succès',
                'transaction_id' => $details['transaction_id'],
                'reservation_id' => $details['reservation_id']
            ];
            
        } catch (Exception $e) {
            // Log de l'erreur
            error_log("PayPal payment storage error: " . $e->getMessage());
            
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'details' => 'Erreur lors de l\'enregistrement du paiement'
            ];
        }
    }
    
    /**
     * Récupère l'historique des paiements (optionnel)
     * 
     * @param int $limit Nombre de paiements à récupérer
     * @return array Liste des paiements
     */
    public function getPaymentHistory($limit = 10) {
        try {
            if (!file_exists($this->logPath)) {
                return ['success' => true, 'payments' => []];
            }
            
            $content = file_get_contents($this->logPath);
            $payments = [];
            
            // Parser le fichier de log (simple parsing)
            $entries = explode(str_repeat('=', 80), $content);
            
            foreach (array_reverse(array_slice($entries, -$limit)) as $entry) {
                if (strpos($entry, '{') !== false) {
                    $jsonStart = strpos($entry, '{');
                    $jsonContent = substr($entry, $jsonStart);
                    $payment = json_decode(trim($jsonContent), true);
                    
                    if ($payment) {
                        $payments[] = $payment;
                    }
                }
            }
            
            return [
                'success' => true,
                'payments' => $payments,
                'total' => count($payments)
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
}

// === TRAITEMENT DE LA REQUÊTE ===

try {
    // Vérifier que c'est une requête POST
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Méthode non autorisée');
    }
    
    // Récupérer les données JSON
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
    
    if (!$data) {
        throw new Exception('Données JSON invalides');
    }
    
    // Vérifier l'action demandée
    $action = $data['action'] ?? '';
    
    // Instancier le contrôleur
    $paymentController = new PaymentController();
    
    // Router vers la bonne méthode
    switch ($action) {
        case 'store_payment':
            if (!isset($data['payment_details'])) {
                throw new Exception('Détails de paiement manquants');
            }
            
            $result = $paymentController->storeSuccess($data['payment_details']);
            echo json_encode($result);
            break;
            
        case 'get_history':
            $limit = $data['limit'] ?? 10;
            $result = $paymentController->getPaymentHistory($limit);
            echo json_encode($result);
            break;
            
        default:
            throw new Exception('Action non reconnue: ' . $action);
    }
    
} catch (Exception $e) {
    // Réponse d'erreur
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'timestamp' => date('Y-m-d H:i:s')
    ]);
}
?>
