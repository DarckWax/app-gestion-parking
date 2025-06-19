<?php

namespace App\Models;

use App\Core\Model;
use App\Utils\Security;

/**
 * User Model - Adapté pour la nouvelle structure de base de données
 */
class User extends Model
{
    protected $table = 'users';
    protected $primaryKey = 'user_id';
    
    public function findByEmail($email)
    {
        return $this->db->fetch(
            "SELECT * FROM {$this->table} WHERE email = ? AND status = 'active'",
            [$email]
        );
    }
    
    public function createUser($data)
    {
        $userData = [
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'email' => $data['email'],
            'phone' => $data['phone'],
            'password_hash' => Security::hashPassword($data['password']),
            'role' => $data['role'] ?? 'user',
            'status' => 'active',
            'email_verified' => false,
            'phone_verified' => false
        ];
        
        return $this->create($userData);
    }
    
    public function updateProfile($userId, $data)
    {
        $updateData = [];
        
        $allowedFields = ['first_name', 'last_name', 'phone', 'profile_image'];
        foreach ($allowedFields as $field) {
            if (isset($data[$field])) {
                $updateData[$field] = $data[$field];
            }
        }
        
        if (!empty($updateData)) {
            return $this->update($userId, $updateData);
        }
        
        return false;
    }
    
    public function changePassword($userId, $newPassword)
    {
        return $this->update($userId, [
            'password_hash' => Security::hashPassword($newPassword)
        ]);
    }
    
    public function updateLastLogin($userId)
    {
        return $this->update($userId, [
            'last_login' => date('Y-m-d H:i:s')
        ]);
    }
    
    public function toggleStatus($userId)
    {
        $user = $this->find($userId);
        if (!$user) return false;
        
        $newStatus = $user['status'] === 'active' ? 'inactive' : 'active';
        return $this->update($userId, ['status' => $newStatus]);
    }
    
    public function getUsersWithStats($limit = null, $offset = 0)
    {
        $sql = "
            SELECT 
                u.*,
                COUNT(DISTINCT r.reservation_id) as total_reservations,
                COALESCE(SUM(p.amount), 0) as total_spent
            FROM {$this->table} u
            LEFT JOIN reservations r ON u.user_id = r.user_id
            LEFT JOIN payments p ON r.reservation_id = p.reservation_id AND p.payment_status = 'completed'
            WHERE u.status != 'deleted'
            GROUP BY u.user_id
            ORDER BY u.created_at DESC
        ";
        
        if ($limit) {
            $sql .= " LIMIT {$limit} OFFSET {$offset}";
        }
        
        return $this->db->fetchAll($sql);
    }
    
    public function searchUsers($query, $limit = 20)
    {
        $searchTerm = "%{$query}%";
        return $this->db->fetchAll(
            "SELECT * FROM {$this->table} 
             WHERE (first_name LIKE ? OR last_name LIKE ? OR email LIKE ?) 
             AND status != 'deleted'
             ORDER BY first_name, last_name
             LIMIT ?",
            [$searchTerm, $searchTerm, $searchTerm, $limit]
        );
    }
}
