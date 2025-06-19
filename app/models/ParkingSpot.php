<?php

namespace App\Models;

use App\Core\Model;

/**
 * Parking Spot Model - Adapté pour la nouvelle structure
 */
class ParkingSpot extends Model
{
    protected $table = 'parking_spots';
    protected $primaryKey = 'spot_id';
    
    public function getAvailableSpots($startDate = null, $endDate = null, $spotType = null)
    {
        $sql = "
            SELECT ps.* 
            FROM {$this->table} ps
            WHERE ps.status = 'available' 
            AND ps.is_active = 1
        ";
        
        $params = [];
        
        if ($spotType) {
            $sql .= " AND ps.spot_type = ?";
            $params[] = $spotType;
        }
        
        if ($startDate && $endDate) {
            $sql .= "
                AND ps.spot_id NOT IN (
                    SELECT r.spot_id 
                    FROM reservations r 
                    WHERE r.status IN ('confirmed', 'active')
                    AND (
                        (? BETWEEN r.start_datetime AND r.end_datetime) OR
                        (? BETWEEN r.start_datetime AND r.end_datetime) OR
                        (r.start_datetime BETWEEN ? AND ?)
                    )
                )
            ";
            $params = array_merge($params, [$startDate, $endDate, $startDate, $endDate]);
        }
        
        $sql .= " ORDER BY ps.zone_section, ps.spot_number";
        
        return $this->db->fetchAll($sql, $params);
    }
    
    public function createSpot($data)
    {
        $spotData = [
            'spot_number' => $data['spot_number'],
            'spot_type' => $data['spot_type'],
            'status' => $data['status'] ?? 'available',
            'floor_level' => $data['floor_level'] ?? 1,
            'zone_section' => $data['zone_section'] ?? 'A',
            'length_cm' => $data['length_cm'] ?? 500,
            'width_cm' => $data['width_cm'] ?? 250,
            'description' => $data['description'] ?? null,
            'is_active' => true
        ];
        
        return $this->create($spotData);
    }
    
    public function updateSpotStatus($spotId, $status)
    {
        return $this->update($spotId, ['status' => $status]);
    }
    
    public function getSpotsByZone()
    {
        return $this->db->fetchAll(
            "SELECT zone_section, COUNT(*) as total_spots,
                    SUM(CASE WHEN status = 'available' THEN 1 ELSE 0 END) as available_spots,
                    SUM(CASE WHEN status = 'occupied' THEN 1 ELSE 0 END) as occupied_spots
             FROM {$this->table} 
             WHERE is_active = 1
             GROUP BY zone_section
             ORDER BY zone_section"
        );
    }
    
    public function getSpotTypes()
    {
        return $this->db->fetchAll(
            "SELECT spot_type, COUNT(*) as count,
                    CASE spot_type
                        WHEN 'standard' THEN 'Standard'
                        WHEN 'disabled' THEN 'PMR'
                        WHEN 'reserved' THEN 'Réservée'
                        WHEN 'electric' THEN 'Électrique'
                        WHEN 'compact' THEN 'Compacte'
                        ELSE spot_type
                    END as type_label
             FROM {$this->table}
             WHERE is_active = 1
             GROUP BY spot_type
             ORDER BY spot_type"
        );
    }
    
    public function searchSpots($query)
    {
        $searchTerm = "%{$query}%";
        return $this->db->fetchAll(
            "SELECT * FROM {$this->table}
             WHERE (spot_number LIKE ? OR description LIKE ? OR zone_section LIKE ?)
             AND is_active = 1
             ORDER BY spot_number",
            [$searchTerm, $searchTerm, $searchTerm]
        );
    }
    
    public function getSpotsWithReservationCount($limit = null)
    {
        $sql = "
            SELECT 
                ps.*,
                COUNT(r.reservation_id) as total_reservations,
                SUM(CASE WHEN r.status = 'active' THEN 1 ELSE 0 END) as active_reservations
            FROM {$this->table} ps
            LEFT JOIN reservations r ON ps.spot_id = r.spot_id
            WHERE ps.is_active = 1
            GROUP BY ps.spot_id
            ORDER BY ps.zone_section, ps.spot_number
        ";
        
        if ($limit) {
            $sql .= " LIMIT {$limit}";
        }
        
        return $this->db->fetchAll($sql);
    }
}
