<?php
/**
 * ParkFinder - Classe utilitaire pour l'administration
 * Fichier: AdminHelper.php
 */

class AdminHelper {
    
    private $pdo;
    
    /**
     * Constructeur
     */
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    /**
     * Vérifie si l'utilisateur est administrateur
     */
    public static function isAdmin($userId, $userRole = null) {
        if ($userRole) {
            return $userRole === 'admin';
        }
        
        // Si pas de rôle fourni, vérifier en base
        global $pdo;
        $stmt = $pdo->prepare("SELECT role FROM users WHERE user_id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch();
        
        return $user && $user['role'] === 'admin';
    }
    
    /**
     * Vérifie les permissions d'accès admin
     */
    public static function requireAdmin() {
        if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
            header('Location: index.php#login');
            exit;
        }
    }
    
    /**
     * Génère des statistiques de tableau de bord
     */
    public function getDashboardStatistics($period = 'month') {
        $stats = [];
        
        try {
            // Statistiques de base
            $stats['reservations'] = $this->getReservationStats($period);
            $stats['revenue'] = $this->getRevenueStats($period);
            $stats['occupancy'] = $this->getOccupancyStats();
            $stats['users'] = $this->getUserStats($period);
            
        } catch (Exception $e) {
            error_log("Erreur statistiques admin: " . $e->getMessage());
            $stats = $this->getDefaultStats();
        }
        
        return $stats;
    }
    
    /**
     * Statistiques des réservations
     */
    private function getReservationStats($period) {
        $whereClause = $this->getPeriodWhereClause($period);
        
        $stmt = $this->pdo->prepare("
            SELECT 
                COUNT(*) as total,
                COUNT(CASE WHEN status = 'confirmed' THEN 1 END) as confirmed,
                COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending,
                COUNT(CASE WHEN status = 'cancelled' THEN 1 END) as cancelled,
                COUNT(CASE WHEN payment_status = 'paid' THEN 1 END) as paid
            FROM reservations 
            WHERE $whereClause
        ");
        $stmt->execute();
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * Statistiques des revenus
     */
    private function getRevenueStats($period) {
        $whereClause = $this->getPeriodWhereClause($period);
        
        $stmt = $this->pdo->prepare("
            SELECT 
                COALESCE(SUM(total_amount), 0) as total_revenue,
                COALESCE(AVG(total_amount), 0) as avg_reservation_value,
                COUNT(DISTINCT DATE(created_at)) as days_with_revenue
            FROM reservations 
            WHERE $whereClause AND payment_status = 'paid'
        ");
        $stmt->execute();
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * Statistiques d'occupation
     */
    private function getOccupancyStats() {
        // Places totales
        $stmt = $this->pdo->query("
            SELECT COUNT(*) as total FROM parking_spots WHERE is_active = 1
        ");
        $totalSpots = $stmt->fetch()['total'];
        
        // Places occupées maintenant
        $stmt = $this->pdo->query("
            SELECT COUNT(DISTINCT spot_id) as occupied 
            FROM reservations 
            WHERE NOW() BETWEEN start_datetime AND end_datetime 
            AND status = 'confirmed'
        ");
        $occupiedSpots = $stmt->fetch()['occupied'];
        
        // Places en maintenance
        $stmt = $this->pdo->query("
            SELECT COUNT(*) as maintenance 
            FROM parking_spots 
            WHERE status = 'maintenance' AND is_active = 1
        ");
        $maintenanceSpots = $stmt->fetch()['maintenance'];
        
        return [
            'total_spots' => $totalSpots,
            'occupied_spots' => $occupiedSpots,
            'maintenance_spots' => $maintenanceSpots,
            'available_spots' => $totalSpots - $occupiedSpots - $maintenanceSpots,
            'occupancy_rate' => $totalSpots > 0 ? round(($occupiedSpots / $totalSpots) * 100, 1) : 0
        ];
    }
    
    /**
     * Statistiques des utilisateurs
     */
    private function getUserStats($period) {
        $whereClause = $this->getPeriodWhereClause($period, 'created_at');
        
        $stmt = $this->pdo->prepare("
            SELECT 
                COUNT(*) as new_users,
                COUNT(CASE WHEN is_active = 1 THEN 1 END) as active_users
            FROM users 
            WHERE $whereClause
        ");
        $stmt->execute();
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * Génère la clause WHERE pour une période donnée
     */
    private function getPeriodWhereClause($period, $dateColumn = 'created_at') {
        switch ($period) {
            case 'today':
                return "DATE($dateColumn) = CURDATE()";
            case 'week':
                return "$dateColumn >= DATE_SUB(CURDATE(), INTERVAL 1 WEEK)";
            case 'month':
                return "MONTH($dateColumn) = MONTH(CURDATE()) AND YEAR($dateColumn) = YEAR(CURDATE())";
            case 'year':
                return "YEAR($dateColumn) = YEAR(CURDATE())";
            default:
                return "1=1";
        }
    }
    
    /**
     * Statistiques par défaut en cas d'erreur
     */
    private function getDefaultStats() {
        return [
            'reservations' => [
                'total' => 0,
                'confirmed' => 0,
                'pending' => 0,
                'cancelled' => 0,
                'paid' => 0
            ],
            'revenue' => [
                'total_revenue' => 0,
                'avg_reservation_value' => 0,
                'days_with_revenue' => 0
            ],
            'occupancy' => [
                'total_spots' => 0,
                'occupied_spots' => 0,
                'maintenance_spots' => 0,
                'available_spots' => 0,
                'occupancy_rate' => 0
            ],
            'users' => [
                'new_users' => 0,
                'active_users' => 0
            ]
        ];
    }
    
    /**
     * Obtient les réservations avec filtres
     */
    public function getReservations($filters = [], $limit = 50, $offset = 0) {
        $whereConditions = ['1=1'];
        $params = [];
        
        // Filtres
        if (!empty($filters['status'])) {
            $whereConditions[] = "r.status = ?";
            $params[] = $filters['status'];
        }
        
        if (!empty($filters['payment_status'])) {
            $whereConditions[] = "r.payment_status = ?";
            $params[] = $filters['payment_status'];
        }
        
        if (!empty($filters['date_from'])) {
            $whereConditions[] = "DATE(r.start_datetime) >= ?";
            $params[] = $filters['date_from'];
        }
        
        if (!empty($filters['date_to'])) {
            $whereConditions[] = "DATE(r.end_datetime) <= ?";
            $params[] = $filters['date_to'];
        }
        
        if (!empty($filters['search'])) {
            $whereConditions[] = "(r.reservation_code LIKE ? OR u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ?)";
            $searchTerm = '%' . $filters['search'] . '%';
            $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm, $searchTerm]);
        }
        
        $whereClause = implode(' AND ', $whereConditions);
        
        $stmt = $this->pdo->prepare("
            SELECT 
                r.*,
                u.first_name,
                u.last_name,
                u.email,
                u.phone,
                ps.spot_number,
                ps.spot_type,
                ps.zone_section,
                ps.floor_level,
                p.payment_method,
                p.transaction_id
            FROM reservations r
            JOIN users u ON r.user_id = u.user_id
            JOIN parking_spots ps ON r.spot_id = ps.spot_id
            LEFT JOIN payments p ON r.reservation_id = p.reservation_id
            WHERE $whereClause
            ORDER BY r.created_at DESC
            LIMIT ? OFFSET ?
        ");
        
        $params[] = $limit;
        $params[] = $offset;
        
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Compte le nombre total de réservations (pour pagination)
     */
    public function countReservations($filters = []) {
        $whereConditions = ['1=1'];
        $params = [];
        
        // Même logique de filtres que getReservations
        if (!empty($filters['status'])) {
            $whereConditions[] = "status = ?";
            $params[] = $filters['status'];
        }
        
        if (!empty($filters['payment_status'])) {
            $whereConditions[] = "payment_status = ?";
            $params[] = $filters['payment_status'];
        }
        
        if (!empty($filters['date_from'])) {
            $whereConditions[] = "DATE(start_datetime) >= ?";
            $params[] = $filters['date_from'];
        }
        
        if (!empty($filters['date_to'])) {
            $whereConditions[] = "DATE(end_datetime) <= ?";
            $params[] = $filters['date_to'];
        }
        
        $whereClause = implode(' AND ', $whereConditions);
        
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM reservations WHERE $whereClause");
        $stmt->execute($params);
        
        return $stmt->fetchColumn();
    }
    
    /**
     * Met à jour le statut d'une réservation
     */
    public function updateReservationStatus($reservationId, $status, $adminId) {
        try {
            $this->pdo->beginTransaction();
            
            // Mettre à jour la réservation
            $stmt = $this->pdo->prepare("
                UPDATE reservations 
                SET status = ?, updated_at = NOW() 
                WHERE reservation_id = ?
            ");
            $stmt->execute([$status, $reservationId]);
            
            // Logger l'action
            $this->logAdminAction($adminId, 'reservation_status_update', [
                'reservation_id' => $reservationId,
                'new_status' => $status
            ]);
            
            $this->pdo->commit();
            return true;
            
        } catch (Exception $e) {
            $this->pdo->rollback();
            error_log("Erreur mise à jour statut réservation: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Obtient les utilisateurs avec filtres
     */
    public function getUsers($filters = [], $limit = 50, $offset = 0) {
        $whereConditions = ['1=1'];
        $params = [];
        
        if (!empty($filters['role'])) {
            $whereConditions[] = "role = ?";
            $params[] = $filters['role'];
        }
        
        if (!empty($filters['is_active'])) {
            $whereConditions[] = "is_active = ?";
            $params[] = $filters['is_active'];
        }
        
        if (!empty($filters['search'])) {
            $whereConditions[] = "(first_name LIKE ? OR last_name LIKE ? OR email LIKE ?)";
            $searchTerm = '%' . $filters['search'] . '%';
            $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm]);
        }
        
        $whereClause = implode(' AND ', $whereConditions);
        
        $stmt = $this->pdo->prepare("
            SELECT 
                user_id,
                first_name,
                last_name,
                email,
                phone,
                role,
                is_active,
                created_at,
                last_login,
                (SELECT COUNT(*) FROM reservations WHERE user_id = users.user_id) as total_reservations
            FROM users
            WHERE $whereClause
            ORDER BY created_at DESC
            LIMIT ? OFFSET ?
        ");
        
        $params[] = $limit;
        $params[] = $offset;
        
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Génère un rapport de revenus
     */
    public function generateRevenueReport($startDate, $endDate, $groupBy = 'day') {
        $dateFormat = $this->getDateFormatForGrouping($groupBy);
        
        $stmt = $this->pdo->prepare("
            SELECT 
                $dateFormat as period,
                COUNT(*) as reservations_count,
                SUM(total_amount) as total_revenue,
                AVG(total_amount) as avg_revenue,
                COUNT(DISTINCT user_id) as unique_customers
            FROM reservations
            WHERE DATE(created_at) BETWEEN ? AND ?
            AND payment_status = 'paid'
            GROUP BY $dateFormat
            ORDER BY period DESC
        ");
        
        $stmt->execute([$startDate, $endDate]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Obtient le format de date pour le groupement
     */
    private function getDateFormatForGrouping($groupBy) {
        switch ($groupBy) {
            case 'hour':
                return "DATE_FORMAT(created_at, '%Y-%m-%d %H:00:00')";
            case 'day':
                return "DATE(created_at)";
            case 'week':
                return "YEARWEEK(created_at)";
            case 'month':
                return "DATE_FORMAT(created_at, '%Y-%m')";
            case 'year':
                return "YEAR(created_at)";
            default:
                return "DATE(created_at)";
        }
    }
    
    /**
     * Log une action administrateur
     */
    public function logAdminAction($adminId, $action, $details = null) {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO admin_logs (admin_id, action, details, ip_address, user_agent, created_at)
                VALUES (?, ?, ?, ?, ?, NOW())
            ");
            
            $stmt->execute([
                $adminId,
                $action,
                json_encode($details),
                $_SERVER['REMOTE_ADDR'] ?? null,
                $_SERVER['HTTP_USER_AGENT'] ?? null
            ]);
            
        } catch (Exception $e) {
            error_log("Erreur log admin: " . $e->getMessage());
        }
    }
    
    /**
     * Obtient les logs d'administration
     */
    public function getAdminLogs($limit = 100, $offset = 0) {
        $stmt = $this->pdo->prepare("
            SELECT 
                al.*,
                u.first_name,
                u.last_name,
                u.email
            FROM admin_logs al
            JOIN users u ON al.admin_id = u.user_id
            ORDER BY al.created_at DESC
            LIMIT ? OFFSET ?
        ");
        
        $stmt->execute([$limit, $offset]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Obtient les alertes système
     */
    public function getSystemAlerts() {
        $alerts = [];
        
        // Réservations avec paiement en attente depuis plus d'1h
        $stmt = $this->pdo->query("
            SELECT COUNT(*) as count 
            FROM reservations 
            WHERE payment_status = 'pending' 
            AND created_at < DATE_SUB(NOW(), INTERVAL 1 HOUR)
        ");
        $pendingPayments = $stmt->fetch()['count'];
        
        if ($pendingPayments > 0) {
            $alerts[] = [
                'level' => 'warning',
                'message' => "$pendingPayments réservation(s) avec paiement en attente",
                'action' => 'admin-payments.php'
            ];
        }
        
        // Places en maintenance
        $stmt = $this->pdo->query("
            SELECT COUNT(*) as count 
            FROM parking_spots 
            WHERE status = 'maintenance' AND is_active = 1
        ");
        $maintenanceSpots = $stmt->fetch()['count'];
        
        if ($maintenanceSpots > 0) {
            $alerts[] = [
                'level' => 'info',
                'message' => "$maintenanceSpots place(s) en maintenance",
                'action' => 'admin-parking-spots.php'
            ];
        }
        
        // Vérifier les erreurs récentes
        $stmt = $this->pdo->query("
            SELECT COUNT(*) as count 
            FROM admin_logs 
            WHERE action LIKE '%error%' 
            AND created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)
        ");
        $recentErrors = $stmt->fetch()['count'];
        
        if ($recentErrors > 5) {
            $alerts[] = [
                'level' => 'error',
                'message' => "$recentErrors erreur(s) dans les dernières 24h",
                'action' => 'admin-logs.php'
            ];
        }
        
        return $alerts;
    }
    
    /**
     * Exporte des données en CSV
     */
    public function exportToCSV($data, $headers, $filename) {
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: no-cache, must-revalidate');
        
        $output = fopen('php://output', 'w');
        
        // En-têtes
        fputcsv($output, $headers, ';');
        
        // Données
        foreach ($data as $row) {
            fputcsv($output, $row, ';');
        }
        
        fclose($output);
        exit;
    }
    
    /**
     * Formate une valeur pour l'affichage
     */
    public static function formatValue($value, $type = 'text') {
        switch ($type) {
            case 'currency':
                return number_format($value, 2, ',', ' ') . ' €';
            case 'percentage':
                return number_format($value, 1, ',', ' ') . '%';
            case 'date':
                return date('d/m/Y', strtotime($value));
            case 'datetime':
                return date('d/m/Y H:i', strtotime($value));
            case 'number':
                return number_format($value, 0, ',', ' ');
            default:
                return htmlspecialchars($value);
        }
    }
}
?>