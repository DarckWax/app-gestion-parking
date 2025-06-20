<?php
/**
 * ParkFinder - Classe utilitaire pour les calculs de prix
 * Fichier: PricingCalculator.php
 */

class PricingCalculator {
    
    private $pricingRules;
    
    /**
     * Constructeur
     */
    public function __construct($pricingRules = []) {
        $this->pricingRules = $pricingRules;
    }
    
    /**
     * Calcule le prix total d'une réservation
     */
    public function calculatePrice($startDatetime, $endDatetime, $spotType) {
        $start = new DateTime($startDatetime);
        $end = new DateTime($endDatetime);
        
        // Validation des dates
        $this->validateDates($start, $end);
        
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
            $timePeriod = $this->getTimePeriod($current);
            
            // Trouver la règle de tarification
            $rule = $this->findPricingRule($spotType, $timePeriod);
            
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
                    $basePrice = $this->getDefaultBasePrice();
                    $totalPrice += $basePrice;
                    $baseAdded = true;
                }
                $totalPrice += $this->getDefaultHourlyRate() * $periodDuration;
            }
            
            $totalDuration += $periodDuration;
            $current = $nextHour;
        }
        
        // Vérifier si un tarif journalier serait plus avantageux
        $dailyPrice = $this->calculateDailyPrice($totalDuration, $spotType);
        if ($dailyPrice > 0 && $dailyPrice < $totalPrice) {
            $totalPrice = $dailyPrice;
        }
        
        return round($totalPrice, 2);
    }
    
    /**
     * Détermine la période tarifaire selon l'heure et le jour
     */
    public function getTimePeriod($dateTime) {
        $dayOfWeek = intval($dateTime->format('w')); // 0 = dimanche, 6 = samedi
        $hour = intval($dateTime->format('H'));
        
        // Weekend
        if ($dayOfWeek === 0 || $dayOfWeek === 6) {
            return ($hour >= 6 && $hour < 20) ? 'weekend_day' : 'weekend_night';
        }
        
        // Jours de semaine
        return ($hour >= 6 && $hour < 18) ? 'weekday_day' : 'weekday_night';
    }
    
    /**
     * Trouve la règle de tarification appropriée
     */
    private function findPricingRule($spotType, $timePeriod) {
        foreach ($this->pricingRules as $rule) {
            if ($rule['spot_type'] === $spotType && $rule['time_period'] === $timePeriod) {
                return $rule;
            }
        }
        return null;
    }
    
    /**
     * Calcule le prix journalier si applicable
     */
    private function calculateDailyPrice($totalDuration, $spotType) {
        if ($totalDuration < 8) {
            return 0; // Pas de tarif journalier pour moins de 8h
        }
        
        $dailyRules = array_filter($this->pricingRules, function($rule) use ($spotType) {
            return $rule['spot_type'] === $spotType && 
                   !empty($rule['daily_rate']) && 
                   floatval($rule['daily_rate']) > 0;
        });
        
        if (empty($dailyRules)) {
            return 0;
        }
        
        // Trouver le meilleur tarif journalier
        $bestDailyRate = min(array_map(function($rule) { 
            return floatval($rule['daily_rate']); 
        }, $dailyRules));
        
        $numberOfDays = max(1, ceil($totalDuration / 24));
        return $bestDailyRate * $numberOfDays;
    }
    
    /**
     * Valide les dates de début et de fin
     */
    private function validateDates($start, $end) {
        $now = new DateTime();
        
        if ($start <= $now) {
            throw new InvalidArgumentException("La date de début doit être dans le futur");
        }
        
        if ($end <= $start) {
            throw new InvalidArgumentException("La date de fin doit être postérieure à la date de début");
        }
        
        // Durée maximum de 7 jours
        $maxEnd = clone $start;
        $maxEnd->add(new DateInterval('P7D'));
        
        if ($end > $maxEnd) {
            throw new InvalidArgumentException("La durée maximum est de 7 jours");
        }
        
        // Durée minimum de 30 minutes
        $minEnd = clone $start;
        $minEnd->add(new DateInterval('PT30M'));
        
        if ($end < $minEnd) {
            throw new InvalidArgumentException("La durée minimum est de 30 minutes");
        }
    }
    
    /**
     * Retourne le prix de base par défaut
     */
    private function getDefaultBasePrice() {
        return 2.00;
    }
    
    /**
     * Retourne le tarif horaire par défaut
     */
    private function getDefaultHourlyRate() {
        return 3.00;
    }
    
    /**
     * Génère un détail du calcul de prix
     */
    public function getPriceBredown($startDatetime, $endDatetime, $spotType) {
        $start = new DateTime($startDatetime);
        $end = new DateTime($endDatetime);
        
        $breakdown = [
            'periods' => [],
            'base_price' => 0,
            'hourly_total' => 0,
            'daily_rate_applied' => false,
            'total_duration' => 0,
            'total_price' => 0
        ];
        
        $totalPrice = 0;
        $totalDuration = 0;
        $basePrice = 0;
        $baseAdded = false;
        $current = clone $start;
        
        while ($current < $end) {
            $nextHour = clone $current;
            $nextHour->add(new DateInterval('PT1H'));
            
            $periodEnd = $nextHour > $end ? $end : $nextHour;
            
            $interval = $current->diff($periodEnd);
            $periodDuration = $interval->h + ($interval->i / 60) + ($interval->s / 3600);
            
            $timePeriod = $this->getTimePeriod($current);
            $rule = $this->findPricingRule($spotType, $timePeriod);
            
            $periodData = [
                'start' => $current->format('Y-m-d H:i:s'),
                'end' => $periodEnd->format('Y-m-d H:i:s'),
                'duration' => $periodDuration,
                'time_period' => $timePeriod,
                'hourly_rate' => 0,
                'period_cost' => 0
            ];
            
            if ($rule) {
                if (!$baseAdded) {
                    $basePrice = floatval($rule['base_price']);
                    $totalPrice += $basePrice;
                    $breakdown['base_price'] = $basePrice;
                    $baseAdded = true;
                }
                
                $hourlyRate = floatval($rule['hourly_rate']);
                $periodCost = $hourlyRate * $periodDuration;
                $totalPrice += $periodCost;
                
                $periodData['hourly_rate'] = $hourlyRate;
                $periodData['period_cost'] = $periodCost;
            } else {
                if (!$baseAdded) {
                    $basePrice = $this->getDefaultBasePrice();
                    $totalPrice += $basePrice;
                    $breakdown['base_price'] = $basePrice;
                    $baseAdded = true;
                }
                
                $hourlyRate = $this->getDefaultHourlyRate();
                $periodCost = $hourlyRate * $periodDuration;
                $totalPrice += $periodCost;
                
                $periodData['hourly_rate'] = $hourlyRate;
                $periodData['period_cost'] = $periodCost;
            }
            
            $breakdown['periods'][] = $periodData;
            $breakdown['hourly_total'] += $periodData['period_cost'];
            $totalDuration += $periodDuration;
            $current = $nextHour;
        }
        
        // Vérifier le tarif journalier
        $dailyPrice = $this->calculateDailyPrice($totalDuration, $spotType);
        if ($dailyPrice > 0 && $dailyPrice < $totalPrice) {
            $breakdown['daily_rate_applied'] = true;
            $breakdown['daily_price'] = $dailyPrice;
            $totalPrice = $dailyPrice;
        }
        
        $breakdown['total_duration'] = $totalDuration;
        $breakdown['total_price'] = round($totalPrice, 2);
        
        return $breakdown;
    }
    
    /**
     * Convertit la durée en format lisible
     */
    public static function formatDuration($hours) {
        $days = floor($hours / 24);
        $remainingHours = $hours % 24;
        $minutes = ($remainingHours - floor($remainingHours)) * 60;
        
        $parts = [];
        
        if ($days > 0) {
            $parts[] = $days . ' jour' . ($days > 1 ? 's' : '');
        }
        
        if (floor($remainingHours) > 0) {
            $parts[] = floor($remainingHours) . 'h';
        }
        
        if ($minutes >= 1) {
            $parts[] = round($minutes) . 'min';
        }
        
        return empty($parts) ? '0min' : implode(' ', $parts);
    }
    
    /**
     * Met à jour les règles de tarification
     */
    public function setPricingRules($pricingRules) {
        $this->pricingRules = $pricingRules;
    }
    
    /**
     * Obtient les règles de tarification actuelles
     */
    public function getPricingRules() {
        return $this->pricingRules;
    }
}
?>