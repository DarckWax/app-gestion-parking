<?php
session_start();

// Rediriger si non connecté
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php#login');
    exit;
}

// Configuration de base
$config = [
    'db_host' => 'localhost',
    'db_name' => 'parking_management_system', 
    'db_user' => 'root',
    'db_pass' => ''
];

try {
    $pdo = new PDO("mysql:host={$config['db_host']};dbname={$config['db_name']}", $config['db_user'], $config['db_pass']);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    if ($_POST) {
        // Récupérer les informations de la place
        $spotId = $_POST['spot_id'];
        $startDatetime = $_POST['start_datetime'];
        $endDatetime = $_POST['end_datetime'];
        $vehiclePlate = $_POST['vehicle_plate'] ?? null;
        
        // Récupérer les informations de la place
        $spotStmt = $pdo->prepare("SELECT spot_type FROM parking_spots WHERE spot_id = ? AND is_active = 1");
        $spotStmt->execute([$spotId]);
        $spot = $spotStmt->fetch();
        
        if (!$spot) {
            throw new Exception("Place de parking non trouvée");
        }
        
        // Récupérer les règles de tarification
        $rulesStmt = $pdo->query("SELECT * FROM pricing_rules WHERE is_active = 1");
        $pricingRules = $rulesStmt->fetchAll();
        
        // Calculer le prix précis
        $calculatedPrice = calculateServerSidePrice($startDatetime, $endDatetime, $spot['spot_type'], $pricingRules);
        
        $pdo->beginTransaction();
        
        // Générer un code de réservation unique
        do {
            $reservationCode = 'PK' . date('Y') . strtoupper(substr(str_shuffle('ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789'), 0, 6));
            $stmt = $pdo->prepare("SELECT reservation_id FROM reservations WHERE reservation_code = ?");
            $stmt->execute([$reservationCode]);
        } while ($stmt->fetch());
        
        // Insérer la réservation
        $stmt = $pdo->prepare("
            INSERT INTO reservations (user_id, spot_id, reservation_code, start_datetime, end_datetime, total_amount, vehicle_plate, status, payment_status) 
            VALUES (?, ?, ?, ?, ?, ?, ?, 'pending', 'pending')
        ");
        
        $stmt->execute([
            $_SESSION['user_id'],
            $spotId,
            $reservationCode,
            $startDatetime,
            $endDatetime,
            $calculatedPrice,
            $vehiclePlate
        ]);
        
        $reservationId = $pdo->lastInsertId();
        $pdo->commit();
        
        // Rediriger vers la page de paiement
        header("Location: payment.php?reservation_id=$reservationId");
        exit;
    }
    
} catch (Exception $e) {
    if (isset($pdo)) {
        $pdo->rollback();
    }
    die('Erreur: ' . $e->getMessage());
}

function calculateServerSidePrice($startDatetime, $endDatetime, $spotType, $pricingRules) {
    $start = new DateTime($startDatetime);
    $end = new DateTime($endDatetime);
    
    $totalPrice = 0;
    $totalDuration = 0;
    $basePrice = 0;
    $baseAdded = false;
    
    // Calculer heure par heure
    $current = clone $start;
    
    while ($current < $end) {
        $nextHour = clone $current;
        $nextHour->add(new DateInterval('PT1H'));
        
        // Si on dépasse la fin, ajuster
        $periodEnd = $nextHour > $end ? $end : $nextHour;
        
        // Calculer la durée de cette période en heures
        $interval = $current->diff($periodEnd);
        $periodDuration = $interval->h + ($interval->i / 60) + ($interval->s / 3600);
        
        // Déterminer la période tarifaire
        $timePeriod = getServerTimePeriod($current);
        
        // Trouver la règle de tarification
        $rule = null;
        foreach ($pricingRules as $r) {
            if ($r['spot_type'] === $spotType && $r['time_period'] === $timePeriod) {
                $rule = $r;
                break;
            }
        }
        
        if ($rule) {
            // Ajouter le prix de base une seule fois
            if (!$baseAdded) {
                $basePrice = floatval($rule['base_price']);
                $totalPrice += $basePrice;
                $baseAdded = true;
            }
            
            // Ajouter le coût horaire
            $hourlyRate = floatval($rule['hourly_rate']);
            $totalPrice += $hourlyRate * $periodDuration;
        } else {
            // Règle par défaut si aucune trouvée
            if (!$baseAdded) {
                $basePrice = 2.00;
                $totalPrice += $basePrice;
                $baseAdded = true;
            }
            $totalPrice += 3.00 * $periodDuration;
        }
        
        $totalDuration += $periodDuration;
        $current = $nextHour;
    }
    
    // Vérifier si un tarif journalier serait plus avantageux
    if ($totalDuration >= 8) {
        $dailyRules = array_filter($pricingRules, function($r) use ($spotType) {
            return $r['spot_type'] === $spotType && !empty($r['daily_rate']) && floatval($r['daily_rate']) > 0;
        });
        
        if (!empty($dailyRules)) {
            $bestDailyRate = min(array_map(function($r) { return floatval($r['daily_rate']); }, $dailyRules));
            $numberOfDays = max(1, ceil($totalDuration / 24));
            $dailyPrice = $bestDailyRate * $numberOfDays;
            
            if ($dailyPrice < $totalPrice) {
                $totalPrice = $dailyPrice;
            }
        }
    }
    
    return round($totalPrice, 2);
}

function getServerTimePeriod($dateTime) {
    $dayOfWeek = intval($dateTime->format('w')); // 0 = dimanche, 6 = samedi
    $hour = intval($dateTime->format('H'));
    
    // Weekend
    if ($dayOfWeek === 0 || $dayOfWeek === 6) {
        return ($hour >= 6 && $hour < 20) ? 'weekend_day' : 'weekend_night';
    }
    
    // Jours de semaine
    return ($hour >= 6 && $hour < 18) ? 'weekday_day' : 'weekday_night';
}
?>
