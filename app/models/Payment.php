<?php

namespace App\Models;

use App\Core\Model;

/**
 * Payment Model - Handle payment transactions
 */
class Payment extends Model
{
    protected $table = 'payments';
    protected $primaryKey = 'payment_id';
    
    public function createPayment($data)
    {
        $paymentData = [
            'reservation_id' => $data['reservation_id'],
            'user_id' => $data['user_id'],
            'amount' => $data['amount'],
            'payment_method' => $data['payment_method'],
            'payment_status' => $data['payment_status'] ?? 'pending',
            'transaction_id' => $data['transaction_id'] ?? null,
            'gateway_response' => $data['gateway_response'] ?? null,
            'currency' => $data['currency'] ?? 'USD',
            'fee_amount' => $data['fee_amount'] ?? 0.00,
            'net_amount' => $data['net_amount'] ?? $data['amount'],
            'processed_at' => $data['processed_at'] ?? null
        ];
        
        return $this->create($paymentData);
    }
    
    public function updatePaymentStatus($paymentId, $status, $transactionId = null)
    {
        $updateData = ['payment_status' => $status];
        
        if ($transactionId) {
            $updateData['transaction_id'] = $transactionId;
        }
        
        if ($status === 'completed') {
            $updateData['processed_at'] = date('Y-m-d H:i:s');
        }
        
        return $this->update($paymentId, $updateData);
    }
    
    public function getPaymentsByUser($userId, $limit = null)
    {
        $sql = "
            SELECT 
                p.*,
                r.reservation_code,
                r.start_datetime,
                r.end_datetime,
                ps.spot_number
            FROM {$this->table} p
            JOIN reservations r ON p.reservation_id = r.reservation_id
            JOIN parking_spots ps ON r.spot_id = ps.spot_id
            WHERE p.user_id = ?
            ORDER BY p.created_at DESC
        ";
        
        if ($limit) {
            $sql .= " LIMIT {$limit}";
        }
        
        return $this->db->fetchAll($sql, [$userId]);
    }
    
    public function getPaymentWithDetails($paymentId)
    {
        return $this->db->fetch("
            SELECT 
                p.*,
                r.reservation_code,
                r.start_datetime,
                r.end_datetime,
                u.first_name,
                u.last_name,
                u.email,
                ps.spot_number,
                ps.spot_type,
                ps.zone_section
            FROM {$this->table} p
            JOIN reservations r ON p.reservation_id = r.reservation_id
            JOIN users u ON p.user_id = u.user_id
            JOIN parking_spots ps ON r.spot_id = ps.spot_id
            WHERE p.payment_id = ?
        ", [$paymentId]);
    }
    
    public function processRefund($paymentId, $refundAmount, $reason = null)
    {
        $updateData = [
            'payment_status' => 'refunded',
            'refund_amount' => $refundAmount,
            'refunded_at' => date('Y-m-d H:i:s')
        ];
        
        return $this->update($paymentId, $updateData);
    }
    
    public function getPaymentStats($startDate = null, $endDate = null)
    {
        $sql = "
            SELECT 
                COUNT(*) as total_payments,
                SUM(CASE WHEN payment_status = 'completed' THEN 1 ELSE 0 END) as successful_payments,
                SUM(CASE WHEN payment_status = 'failed' THEN 1 ELSE 0 END) as failed_payments,
                SUM(CASE WHEN payment_status = 'completed' THEN amount ELSE 0 END) as total_revenue,
                SUM(CASE WHEN payment_status = 'completed' THEN fee_amount ELSE 0 END) as total_fees,
                SUM(CASE WHEN payment_status = 'completed' THEN net_amount ELSE 0 END) as net_revenue,
                payment_method
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
        
        $sql .= " GROUP BY payment_method";
        
        return $this->db->fetchAll($sql, $params);
    }
    
    public function getDailyRevenue($days = 30)
    {
        return $this->db->fetchAll("
            SELECT 
                DATE(created_at) as date,
                COUNT(*) as transactions,
                SUM(CASE WHEN payment_status = 'completed' THEN amount ELSE 0 END) as revenue
            FROM {$this->table}
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
            GROUP BY DATE(created_at)
            ORDER BY date DESC
        ", [$days]);
    }
}
