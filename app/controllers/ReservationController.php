<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Models\Reservation;
use App\Models\ParkingSpot;
use App\Models\User;
use App\Utils\Security;
use App\Utils\Validator;
use App\Utils\Logger;

/**
 * Reservation Controller - Handle parking reservations
 */
class ReservationController extends Controller
{
    private $reservationModel;
    private $parkingSpotModel;
    
    public function __construct()
    {
        parent::__construct();
        $this->reservationModel = new Reservation();
        $this->parkingSpotModel = new ParkingSpot();
    }
    
    public function bookingForm()
    {
        $this->requireAuth();
        
        // Get available spot types
        $spotTypes = $this->parkingSpotModel->getSpotTypes();
        
        $this->view('reservations/book', [
            'title' => 'Book Parking Spot',
            'spotTypes' => $spotTypes,
            'csrf_token' => Security::generateCSRFToken()
        ]);
    }
    
    public function createReservation()
    {
        $this->requireAuth();
        
        // Validation
        $validator = new Validator($_POST);
        $validator->required('spot_id')->numeric('spot_id')
                 ->required('start_datetime')->datetime('start_datetime')
                 ->required('end_datetime')->datetime('end_datetime')
                 ->max('vehicle_plate', 20)
                 ->max('vehicle_model', 50);
        
        if ($validator->fails()) {
            $this->flash('error', $validator->getFirstError());
            $this->redirect('/book');
        }
        
        $startDateTime = $_POST['start_datetime'];
        $endDateTime = $_POST['end_datetime'];
        $spotId = (int)$_POST['spot_id'];
        
        // Validate datetime logic
        if (strtotime($startDateTime) >= strtotime($endDateTime)) {
            $this->flash('error', 'End time must be after start time');
            $this->redirect('/book');
        }
        
        if (strtotime($startDateTime) < time()) {
            $this->flash('error', 'Start time cannot be in the past');
            $this->redirect('/book');
        }
        
        try {
            $this->db->beginTransaction();
            
            // Check spot availability
            $availableSpots = $this->parkingSpotModel->getAvailableSpots($startDateTime, $endDateTime);
            $isAvailable = false;
            foreach ($availableSpots as $spot) {
                if ($spot['spot_id'] == $spotId) {
                    $isAvailable = true;
                    break;
                }
            }
            
            if (!$isAvailable) {
                throw new \Exception('Selected parking spot is not available for the requested time');
            }
            
            // Calculate total amount
            $totalAmount = $this->calculateParkingFee($spotId, $startDateTime, $endDateTime);
            
            // Create reservation
            $reservationData = [
                'user_id' => $_SESSION['user_id'],
                'spot_id' => $spotId,
                'start_datetime' => $startDateTime,
                'end_datetime' => $endDateTime,
                'total_amount' => $totalAmount,
                'vehicle_plate' => Security::sanitizeInput($_POST['vehicle_plate'] ?? ''),
                'vehicle_model' => Security::sanitizeInput($_POST['vehicle_model'] ?? ''),
                'special_requests' => Security::sanitizeInput($_POST['special_requests'] ?? '')
            ];
            
            $reservationId = $this->reservationModel->createReservation($reservationData);
            
            $this->db->commit();
            
            Logger::info('Reservation created', [
                'user_id' => $_SESSION['user_id'],
                'reservation_id' => $reservationId,
                'spot_id' => $spotId
            ]);
            
            $this->flash('success', 'Reservation created successfully! Please proceed to payment.');
            $this->redirect("/payment/{$reservationId}");
            
        } catch (\Exception $e) {
            $this->db->rollback();
            Logger::error('Reservation creation failed: ' . $e->getMessage());
            $this->flash('error', $e->getMessage());
            $this->redirect('/book');
        }
    }
    
    public function userReservations()
    {
        $this->requireAuth();
        
        $page = (int)($_GET['page'] ?? 1);
        $limit = 10;
        $offset = ($page - 1) * $limit;
        
        $reservations = $this->reservationModel->getUserReservations($_SESSION['user_id'], null, $limit);
        
        $this->view('reservations/list', [
            'title' => 'My Reservations',
            'reservations' => $reservations,
            'currentPage' => $page
        ]);
    }
    
    public function viewReservation($reservationId)
    {
        $this->requireAuth();
        
        $reservation = $this->reservationModel->getReservationWithDetails($reservationId);
        
        if (!$reservation) {
            $this->flash('error', 'Reservation not found');
            $this->redirect('/reservations');
        }
        
        // Check if user owns this reservation or is admin
        if ($reservation['user_id'] != $_SESSION['user_id'] && !$this->isAdmin()) {
            $this->flash('error', 'Access denied');
            $this->redirect('/reservations');
        }
        
        $this->view('reservations/view', [
            'title' => 'Reservation Details',
            'reservation' => $reservation,
            'csrf_token' => Security::generateCSRFToken()
        ]);
    }
    
    public function cancelReservation($reservationId)
    {
        $this->requireAuth();
        
        $reservation = $this->reservationModel->find($reservationId);
        
        if (!$reservation) {
            $this->json(['error' => 'Reservation not found'], 404);
        }
        
        // Check ownership
        if ($reservation['user_id'] != $_SESSION['user_id'] && !$this->isAdmin()) {
            $this->json(['error' => 'Access denied'], 403);
        }
        
        // Check if cancellation is allowed
        if (!in_array($reservation['status'], ['pending', 'confirmed'])) {
            $this->json(['error' => 'Reservation cannot be cancelled'], 400);
        }
        
        // Check cancellation time limit (e.g., 2 hours before start time)
        $startTime = strtotime($reservation['start_datetime']);
        $currentTime = time();
        $timeDiff = $startTime - $currentTime;
        
        if ($timeDiff < 7200) { // 2 hours
            $this->json(['error' => 'Cancellation not allowed less than 2 hours before start time'], 400);
        }
        
        try {
            $this->db->beginTransaction();
            
            $reason = $_POST['reason'] ?? 'User cancellation';
            $this->reservationModel->cancelReservation($reservationId, $reason);
            
            // Update spot status
            $this->parkingSpotModel->updateSpotStatus($reservation['spot_id'], 'available');
            
            $this->db->commit();
            
            Logger::info('Reservation cancelled', [
                'user_id' => $_SESSION['user_id'],
                'reservation_id' => $reservationId,
                'reason' => $reason
            ]);
            
            $this->json(['success' => true, 'message' => 'Reservation cancelled successfully']);
            
        } catch (\Exception $e) {
            $this->db->rollback();
            Logger::error('Reservation cancellation failed: ' . $e->getMessage());
            $this->json(['error' => 'Failed to cancel reservation'], 500);
        }
    }
    
    private function calculateParkingFee($spotId, $startDateTime, $endDateTime)
    {
        // Get spot details
        $spot = $this->parkingSpotModel->find($spotId);
        if (!$spot) {
            throw new \Exception('Parking spot not found');
        }
        
        // Calculate duration in hours
        $startTime = strtotime($startDateTime);
        $endTime = strtotime($endDateTime);
        $durationHours = ceil(($endTime - $startTime) / 3600);
        
        // Determine time period
        $dayOfWeek = date('N', $startTime); // 1 = Monday, 7 = Sunday
        $hour = date('H', $startTime);
        
        $timePeriod = 'weekday_day';
        if ($dayOfWeek >= 6) { // Weekend
            $timePeriod = ($hour >= 6 && $hour < 20) ? 'weekend_day' : 'weekend_night';
        } else { // Weekday
            $timePeriod = ($hour >= 6 && $hour < 18) ? 'weekday_day' : 'weekday_night';
        }
        
        // Get pricing rule
        $pricingRule = $this->db->fetch(
            "SELECT * FROM pricing_rules 
             WHERE spot_type = ? AND time_period = ? AND is_active = 1
             ORDER BY created_at DESC
             LIMIT 1",
            [$spot['spot_type'], $timePeriod]
        );
        
        if (!$pricingRule) {
            // Default pricing based on spot type
            $defaultPrices = [
                'standard' => ['base' => 2.00, 'hourly' => 3.00, 'daily' => 25.00],
                'disabled' => ['base' => 1.00, 'hourly' => 1.50, 'daily' => 12.50],
                'electric' => ['base' => 3.00, 'hourly' => 4.50, 'daily' => 35.00],
                'reserved' => ['base' => 5.00, 'hourly' => 6.00, 'daily' => 50.00],
                'compact' => ['base' => 1.50, 'hourly' => 2.50, 'daily' => 20.00]
            ];
            
            $prices = $defaultPrices[$spot['spot_type']] ?? $defaultPrices['standard'];
            $basePrice = $prices['base'];
            $hourlyRate = $prices['hourly'];
            $dailyRate = $prices['daily'];
        } else {
            $basePrice = $pricingRule['base_price'];
            $hourlyRate = $pricingRule['hourly_rate'];
            $dailyRate = $pricingRule['daily_rate'];
        }
        
        // Calculate total fee
        $totalFee = $basePrice + ($hourlyRate * $durationHours);
        
        // Apply daily rate if applicable
        if ($durationHours >= 8 && $dailyRate) {
            $totalFee = min($totalFee, $dailyRate);
        }
        
        return round($totalFee, 2);
    }
}
