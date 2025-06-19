<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Models\Payment;
use App\Models\Reservation;
use App\Models\User;
use App\Utils\Security;
use App\Utils\Validator;
use App\Utils\Logger;

/**
 * Payment Controller - Handle payment processing with PayPal DEMO MODE
 */
class PaymentController extends Controller
{
    private $paymentModel;
    private $reservationModel;
    private $paypalConfig;
    
    public function __construct()
    {
        parent::__construct();
        $this->paymentModel = new Payment();
        $this->reservationModel = new Reservation();
        
        // PayPal Demo Configuration
        $this->paypalConfig = [
            'client_id' => $_ENV['PAYPAL_CLIENT_ID'] ?? 'AZDxjDScFpQtjWTOUtWKbyN_bDt4OgqaF4eYXlewfBP4-8aqX3PiV8e1GWU6liB2CUXlkA59kJXE7M6R',
            'client_secret' => $_ENV['PAYPAL_CLIENT_SECRET'] ?? 'demo_secret',
            'mode' => 'sandbox', // Force sandbox mode for demo
            'api_url' => 'https://api.sandbox.paypal.com', // Always use sandbox
            'web_url' => 'https://www.sandbox.paypal.com', // Sandbox web URL
            'demo_mode' => true
        ];
    }
    
    public function paymentForm($reservationId)
    {
        $this->requireAuth();
        
        $reservation = $this->reservationModel->getReservationWithDetails($reservationId);
        
        if (!$reservation) {
            $this->flash('error', 'Reservation not found');
            $this->redirect('/reservations');
        }
        
        // Check if user owns this reservation
        if ($reservation['user_id'] != $_SESSION['user_id'] && !$this->isAdmin()) {
            $this->flash('error', 'Access denied');
            $this->redirect('/reservations');
        }
        
        // Check if payment is still pending
        if ($reservation['payment_status'] !== 'pending') {
            $this->flash('error', 'Payment already processed');
            $this->redirect("/reservation/{$reservationId}");
        }
        
        $this->view('payments/form', [
            'title' => 'Payment - Reservation #' . $reservation['reservation_code'],
            'reservation' => $reservation,
            'paypal_client_id' => $this->paypalConfig['client_id'],
            'paypal_mode' => 'sandbox', // Always sandbox for demo
            'is_demo' => true,
            'demo_accounts' => [
                'buyer_email' => $_ENV['PAYPAL_DEMO_BUYER_EMAIL'] ?? 'sb-buyer@business.example.com',
                'buyer_password' => $_ENV['PAYPAL_DEMO_BUYER_PASSWORD'] ?? 'demo123456'
            ],
            'csrf_token' => Security::generateCSRFToken(),
            'customJS' => ['/assets/js/payment.js']
        ]);
    }
    
    public function processPayment()
    {
        $this->requireAuth();
        $this->validateCSRF();
        
        // Validation
        $validator = new Validator($_POST);
        $validator->required('reservation_id')->numeric('reservation_id')
                 ->required('payment_method')
                 ->required('amount')->numeric('amount');
        
        if ($validator->fails()) {
            $this->json(['error' => $validator->getFirstError()], 400);
        }
        
        $reservationId = (int)$_POST['reservation_id'];
        $paymentMethod = $_POST['payment_method'];
        $amount = (float)$_POST['amount'];
        
        try {
            $this->db->beginTransaction();
            
            // Get reservation details
            $reservation = $this->reservationModel->find($reservationId);
            if (!$reservation || $reservation['user_id'] != $_SESSION['user_id']) {
                throw new \Exception('Invalid reservation');
            }
            
            // Verify amount matches reservation
            if (abs($amount - $reservation['total_amount']) > 0.01) {
                throw new \Exception('Payment amount mismatch');
            }
            
            // Process payment based on method
            $paymentResult = null;
            switch ($paymentMethod) {
                case 'paypal':
                    $paymentResult = $this->processPayPalPayment($_POST);
                    break;
                case 'credit_card':
                    $paymentResult = $this->processCreditCardPayment($_POST);
                    break;
                default:
                    throw new \Exception('Unsupported payment method');
            }
            
            // Create payment record
            $paymentData = [
                'reservation_id' => $reservationId,
                'user_id' => $_SESSION['user_id'],
                'amount' => $amount,
                'payment_method' => $paymentMethod,
                'payment_status' => $paymentResult['status'],
                'transaction_id' => $paymentResult['transaction_id'],
                'gateway_response' => json_encode($paymentResult['response']),
                'currency' => 'USD',
                'fee_amount' => $paymentResult['fee'] ?? 0.00,
                'net_amount' => $amount - ($paymentResult['fee'] ?? 0.00),
                'processed_at' => $paymentResult['status'] === 'completed' ? date('Y-m-d H:i:s') : null
            ];
            
            $paymentId = $this->paymentModel->create($paymentData);
            
            // Update reservation status
            if ($paymentResult['status'] === 'completed') {
                $this->reservationModel->updatePaymentStatus($reservationId, 'paid');
                $this->reservationModel->confirmReservation($reservationId);
            }
            
            $this->db->commit();
            
            Logger::info('Payment processed', [
                'user_id' => $_SESSION['user_id'],
                'payment_id' => $paymentId,
                'reservation_id' => $reservationId,
                'amount' => $amount,
                'method' => $paymentMethod,
                'status' => $paymentResult['status']
            ]);
            
            if ($paymentResult['status'] === 'completed') {
                $this->json([
                    'success' => true,
                    'message' => 'Payment successful',
                    'redirect' => '/payment/success?payment_id=' . $paymentId
                ]);
            } else {
                $this->json([
                    'success' => false,
                    'message' => 'Payment failed: ' . ($paymentResult['error'] ?? 'Unknown error'),
                    'redirect' => '/payment/failed?payment_id=' . $paymentId
                ]);
            }
            
        } catch (\Exception $e) {
            $this->db->rollback();
            Logger::error('Payment processing failed: ' . $e->getMessage());
            $this->json(['error' => $e->getMessage()], 500);
        }
    }
    
    private function processPayPalPayment($postData)
    {
        try {
            $paypalOrderId = $postData['paypal_order_id'] ?? null;
            if (!$paypalOrderId) {
                throw new \Exception('PayPal order ID missing');
            }
            
            // For demo mode, we'll simulate successful payments more often
            if ($this->paypalConfig['demo_mode']) {
                Logger::info('PayPal Demo Mode: Processing payment', [
                    'order_id' => $paypalOrderId,
                    'mode' => 'sandbox'
                ]);
            }
            
            // Capture PayPal payment using sandbox API
            $accessToken = $this->getPayPalAccessToken();
            $captureUrl = $this->paypalConfig['api_url'] . "/v2/checkout/orders/{$paypalOrderId}/capture";
            
            $curl = curl_init();
            curl_setopt_array($curl, [
                CURLOPT_URL => $captureUrl,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER => [
                    'Content-Type: application/json',
                    'Authorization: Bearer ' . $accessToken,
                    'PayPal-Request-Id: ' . uniqid(),
                    'PayPal-Partner-Attribution-Id: ParkFinder_Cart_Demo'
                ],
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => '{}',
                CURLOPT_SSL_VERIFYPEER => false, // For demo/development only
                CURLOPT_TIMEOUT => 30
            ]);
            
            $response = curl_exec($curl);
            $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
            $curlError = curl_error($curl);
            curl_close($curl);
            
            if ($curlError) {
                throw new \Exception('PayPal API connection error: ' . $curlError);
            }
            
            if ($httpCode !== 201) {
                // For demo mode, simulate success even if API fails
                if ($this->paypalConfig['demo_mode'] && $httpCode === 0) {
                    Logger::info('PayPal Demo Mode: Simulating successful payment');
                    return [
                        'status' => 'completed',
                        'transaction_id' => 'DEMO_' . $paypalOrderId,
                        'response' => [
                            'demo_mode' => true,
                            'simulated_success' => true,
                            'original_order_id' => $paypalOrderId
                        ],
                        'fee' => 0.00
                    ];
                }
                throw new \Exception('PayPal capture failed: HTTP ' . $httpCode . ' - ' . $response);
            }
            
            $responseData = json_decode($response, true);
            
            if ($responseData && $responseData['status'] === 'COMPLETED') {
                $captureDetails = $responseData['purchase_units'][0]['payments']['captures'][0];
                
                return [
                    'status' => 'completed',
                    'transaction_id' => $captureDetails['id'],
                    'response' => $responseData,
                    'fee' => 0.00
                ];
            } else {
                return [
                    'status' => 'failed',
                    'transaction_id' => $paypalOrderId,
                    'response' => $responseData,
                    'error' => 'Payment not completed'
                ];
            }
            
        } catch (\Exception $e) {
            Logger::error('PayPal payment processing error: ' . $e->getMessage());
            
            // For demo mode, return success if it's a connection issue
            if ($this->paypalConfig['demo_mode'] && strpos($e->getMessage(), 'connection') !== false) {
                return [
                    'status' => 'completed',
                    'transaction_id' => 'DEMO_OFFLINE_' . time(),
                    'response' => [
                        'demo_mode' => true,
                        'offline_simulation' => true,
                        'original_error' => $e->getMessage()
                    ],
                    'fee' => 0.00
                ];
            }
            
            return [
                'status' => 'failed',
                'transaction_id' => $paypalOrderId ?? 'unknown',
                'response' => ['error' => $e->getMessage()],
                'error' => $e->getMessage()
            ];
        }
    }
    
    private function processCreditCardPayment($postData)
    {
        // Simulate credit card processing (implement with Stripe, Square, etc.)
        try {
            $cardNumber = $postData['card_number'] ?? '';
            $cardExpiry = $postData['card_expiry'] ?? '';
            $cardCvv = $postData['card_cvv'] ?? '';
            $cardName = $postData['card_name'] ?? '';
            
            // Basic validation
            if (empty($cardNumber) || empty($cardExpiry) || empty($cardCvv)) {
                throw new \Exception('Missing card details');
            }
            
            // Simulate processing (replace with actual gateway integration)
            $transactionId = 'CC_' . time() . '_' . rand(1000, 9999);
            $success = rand(1, 10) > 2; // 80% success rate for simulation
            
            if ($success) {
                return [
                    'status' => 'completed',
                    'transaction_id' => $transactionId,
                    'response' => ['simulated' => true, 'card_last4' => substr($cardNumber, -4)],
                    'fee' => $postData['amount'] * 0.029 + 0.30 // Typical credit card fee
                ];
            } else {
                return [
                    'status' => 'failed',
                    'transaction_id' => $transactionId,
                    'response' => ['simulated' => true, 'error' => 'Card declined'],
                    'error' => 'Card declined'
                ];
            }
            
        } catch (\Exception $e) {
            return [
                'status' => 'failed',
                'transaction_id' => 'CC_ERROR_' . time(),
                'response' => ['error' => $e->getMessage()],
                'error' => $e->getMessage()
            ];
        }
    }
    
    private function getPayPalAccessToken()
    {
        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL => $this->paypalConfig['api_url'] . '/v1/oauth2/token',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_USERPWD => $this->paypalConfig['client_id'] . ':' . $this->paypalConfig['client_secret'],
            CURLOPT_HTTPHEADER => [
                'Accept: application/json',
                'Accept-Language: en_US'
            ],
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => 'grant_type=client_credentials',
            CURLOPT_SSL_VERIFYPEER => false, // For demo/development only
            CURLOPT_TIMEOUT => 30
        ]);
        
        $response = curl_exec($curl);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $curlError = curl_error($curl);
        curl_close($curl);
        
        if ($curlError) {
            // For demo mode, return a dummy token if connection fails
            if ($this->paypalConfig['demo_mode']) {
                Logger::warning('PayPal Demo Mode: Using dummy access token due to connection error');
                return 'DEMO_ACCESS_TOKEN_' . time();
            }
            throw new \Exception('Failed to connect to PayPal API: ' . $curlError);
        }
        
        if ($httpCode !== 200) {
            throw new \Exception('Failed to get PayPal access token: HTTP ' . $httpCode);
        }
        
        $responseData = json_decode($response, true);
        if (!$responseData || !isset($responseData['access_token'])) {
            throw new \Exception('Invalid PayPal token response');
        }
        
        return $responseData['access_token'];
    }
    
    public function createPayPalOrder()
    {
        $this->requireAuth();
        
        $reservationId = $this->getInput('reservation_id');
        $reservation = $this->reservationModel->find($reservationId);
        
        if (!$reservation || $reservation['user_id'] != $_SESSION['user_id']) {
            $this->json(['error' => 'Réservation invalide'], 400);
        }
        
        try {
            $accessToken = $this->getPayPalAccessToken();
            
            $orderData = [
                'intent' => 'CAPTURE',
                'purchase_units' => [[
                    'reference_id' => 'RESERVATION_' . $reservationId,
                    'amount' => [
                        'currency_code' => 'EUR', // Changé pour EUR
                        'value' => number_format($reservation['total_amount'], 2, '.', '')
                    ],
                    'description' => 'Réservation parking #' . $reservation['reservation_code'] . ' (MODE DÉMO)'
                ]],
                'application_context' => [
                    'brand_name' => 'ParkFinder Démo',
                    'landing_page' => 'BILLING',
                    'user_action' => 'PAY_NOW',
                    'return_url' => $_ENV['APP_URL'] . '/payment/paypal-return',
                    'cancel_url' => $_ENV['APP_URL'] . '/payment/paypal-cancel',
                    'locale' => 'fr_FR' // Ajout locale française
                ]
            ];
            
            // For demo mode, add special handling
            if ($this->paypalConfig['demo_mode']) {
                Logger::info('PayPal Demo Mode: Creating order', [
                    'reservation_id' => $reservationId,
                    'amount' => $reservation['total_amount']
                ]);
            }
            
            $curl = curl_init();
            curl_setopt_array($curl, [
                CURLOPT_URL => $this->paypalConfig['api_url'] . '/v2/checkout/orders',
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER => [
                    'Content-Type: application/json',
                    'Authorization: Bearer ' . $accessToken,
                    'PayPal-Request-Id: ' . uniqid(),
                    'PayPal-Partner-Attribution-Id: ParkFinder_Cart_Demo'
                ],
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => json_encode($orderData),
                CURLOPT_SSL_VERIFYPEER => false, // For demo/development only
                CURLOPT_TIMEOUT => 30
            ]);
            
            $response = curl_exec($curl);
            $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
            $curlError = curl_error($curl);
            curl_close($curl);
            
            if ($curlError) {
                throw new \Exception('PayPal API connection error: ' . $curlError);
            }
            
            if ($httpCode !== 201) {
                throw new \Exception('PayPal order creation failed: HTTP ' . $httpCode . ' - ' . $response);
            }
            
            $responseData = json_decode($response, true);
            
            if ($responseData && isset($responseData['id'])) {
                return [
                    'status' => 'created',
                    'order_id' => $responseData['id'],
                    'response' => $responseData
                ];
            } else {
                throw new \Exception('Invalid PayPal order response');
            }
            
        } catch (\Exception $e) {
            Logger::error('Échec création commande PayPal: ' . $e->getMessage());
            $this->json(['error' => 'Échec de création de la commande de paiement: ' . $e->getMessage()], 500);
        }
    }
}
