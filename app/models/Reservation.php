<?php

namespace App\Models;

use App\Core\Model;
use App\Utils\Security;

/**
 * Reservation Model - Adapté pour la nouvelle structure
 */
class Reservation extends Model
{
    protected $table = 'reservations';
    protected $primaryKey = 'reservation_id';
    
    public function createReservation($data)
    {
        $reservationData = [
            'user_id' => $data['user_id'],
            'spot_id' => $data['spot_id'],
            'reservation_code' => $this->generateReservationCode(),
            'start_datetime' => $data['start_datetime'],
            'end_datetime' => $data['end_datetime'],
            'total_amount' => $data['total_amount'],
            'status' => 'pending',
            'payment_status' => 'pending',
            'vehicle_plate' => $data['vehicle_plate'] ?? null,
            'vehicle_model' => $data['vehicle_model'] ?? null,
            'special_requests' => $data['special_requests'] ?? null
        ];
        
        return $this->create($reservationData);
    }
    
    private function generateReservationCode()
    {
        do {
            $code = 'PK' . date('Y') . strtoupper(Security::generateRandomString(6));
            $exists = $this->db->fetch(
                "SELECT reservation_id FROM {$this->table} WHERE reservation_code = ?",
                [$code]
            );
        } while ($exists);
        
        return $code;
    }
    
    public function getUserReservations($userId, $status = null, $limit = null)
    {
        $sql = "
            SELECT 
                r.*,
                ps.spot_number,
                ps.spot_type,
                ps.zone_section,
                ps.floor_level,
                CASE ps.spot_type
                    WHEN 'standard' THEN 'Standard'
                    WHEN 'disabled' THEN 'PMR'
                    WHEN 'reserved' THEN 'Réservée'
                    WHEN 'electric' THEN 'Électrique'
                    WHEN 'compact' THEN 'Compacte'
                    ELSE ps.spot_type
                END as spot_type_label
            FROM {$this->table} r
            JOIN parking_spots ps ON r.spot_id = ps.spot_id
            WHERE r.user_id = ?
        ";
        
        $params = [$userId];
        
        if ($status) {
            $sql .= " AND r.status = ?";
            $params[] = $status;
        }
        
        $sql .= " ORDER BY r.created_at DESC";
        
        if ($limit) {
            $sql .= " LIMIT {$limit}";
        }
        
        return $this->db->fetchAll($sql, $params);
    }
    
    public function getReservationWithDetails($reservationId)
    {
        return $this->db->fetch(
            "SELECT 
                r.*,
                u.first_name,
                u.last_name,
                u.email,
                u.phone,
                ps.spot_number,
                ps.spot_type,
                ps.zone_section,
                ps.floor_level,
                CASE ps.spot_type
                    WHEN 'standard' THEN 'Standard'
                    WHEN 'disabled' THEN 'PMR'
                    WHEN 'reserved' THEN 'Réservée'
                    WHEN 'electric' THEN 'Électrique'
                    WHEN 'compact' THEN 'Compacte'
                    ELSE ps.spot_type
                END as spot_type_label,
                p.payment_id,
                p.payment_status as payment_current_status,
                p.transaction_id
            FROM {$this->table} r
            JOIN users u ON r.user_id = u.user_id
            JOIN parking_spots ps ON r.spot_id = ps.spot_id
            LEFT JOIN payments p ON r.reservation_id = p.reservation_id
            WHERE r.reservation_id = ?",
            [$reservationId]
        );
    }
    
    public function cancelReservation($reservationId, $reason = null)
    {
        $updateData = [
            'status' => 'cancelled',
            'cancellation_reason' => $reason
        ];
        
        return $this->update($reservationId, $updateData);
    }
    
    public function confirmReservation($reservationId)
    {
        return $this->update($reservationId, ['status' => 'confirmed']);
    }
    
    public function activateReservation($reservationId)
    {
        return $this->update($reservationId, [
            'status' => 'active',
            'actual_start_datetime' => date('Y-m-d H:i:s')
        ]);
    }
    
    public function completeReservation($reservationId)
    {
        return $this->update($reservationId, [
            'status' => 'completed',
            'actual_end_datetime' => date('Y-m-d H:i:s')
        ]);
    }
    
    public function updatePaymentStatus($reservationId, $status)
    {
        return $this->update($reservationId, ['payment_status' => $status]);
    }
    
    public function getActiveReservations($limit = null)
    {
        $sql = "
            SELECT 
                r.*,
                u.first_name,
                u.last_name,
                u.email,
                ps.spot_number,
                ps.zone_section
            FROM {$this->table} r
            JOIN users u ON r.user_id = u.user_id
            JOIN parking_spots ps ON r.spot_id = ps.spot_id
            WHERE r.status IN ('confirmed', 'active')
            ORDER BY r.start_datetime ASC
        ";
        
        if ($limit) {
            $sql .= " LIMIT {$limit}";
        }
        
        return $this->db->fetchAll($sql);
    }
    
    public function getReservationsForReminder($minutes = 30)
    {
        return $this->db->fetchAll(
            "SELECT 
                r.*,
                u.email,
                u.first_name
            FROM {$this->table} r
            JOIN users u ON r.user_id = u.user_id
            WHERE r.status = 'confirmed'
            AND r.start_datetime BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL ? MINUTE)
            AND r.reservation_id NOT IN (
                SELECT n.reservation_id 
                FROM notifications n 
                WHERE n.type = 'reminder' 
                AND n.status = 'sent'
                AND n.reservation_id = r.reservation_id
            )",
            [$minutes]
        );
    }
    
    public function getReservationStats($startDate = null, $endDate = null)
    {
        $sql = "
            SELECT 
                COUNT(*) as total_reservations,
                SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
                SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled,
                SUM(CASE WHEN status = 'no_show' THEN 1 ELSE 0 END) as no_shows,
                AVG(total_amount) as avg_amount,
                SUM(total_amount) as total_revenue,
                AVG(TIMESTAMPDIFF(HOUR, start_datetime, end_datetime)) as avg_duration
            FROM {$this->table}
            WHERE 1=1
        ";
        
        $params = [];
        
        if ($startDate) {
            $sql .= " AND created_at >= ?";
            $params[] = $startDate;
        }
        
        if ($endDate) {
            $sql .= " AND created_at <= ?";
            $params[] = $endDate;
        }
        
        return $this->db->fetch($sql, $params);
    }
}
