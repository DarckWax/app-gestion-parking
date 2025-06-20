<?php
echo "<h1>🔧 Correction des règles de tarification</h1>";

try {
    $pdo = new PDO('mysql:host=localhost;dbname=parking_management_system', 'root', '');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "<p>✅ Connexion à la base de données réussie</p>";
    
    // Vérifier les règles existantes
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM pricing_rules WHERE is_active = 1");
    $currentRules = $stmt->fetch()['count'];
    echo "<p>📊 Règles existantes: $currentRules</p>";
    
    // Supprimer les anciennes règles
    $pdo->exec("DELETE FROM pricing_rules");
    echo "<p>🗑️ Anciennes règles supprimées</p>";
    
    // Insérer les nouvelles règles complètes
    $rules = [
        // Places STANDARD
        ['Standard Jour Semaine', 'standard', 'weekday_day', '06:00:00', '18:00:00', 2.00, 3.00, 25.00, 12],
        ['Standard Nuit Semaine', 'standard', 'weekday_night', '18:00:00', '06:00:00', 1.50, 2.00, 15.00, 12],
        ['Standard Jour Weekend', 'standard', 'weekend_day', '06:00:00', '20:00:00', 2.50, 4.00, 30.00, 24],
        ['Standard Nuit Weekend', 'standard', 'weekend_night', '20:00:00', '06:00:00', 1.50, 2.00, 15.00, 12],
        
        // Places PMR
        ['PMR Jour Semaine', 'disabled', 'weekday_day', '06:00:00', '18:00:00', 1.00, 1.50, 12.50, 12],
        ['PMR Nuit Semaine', 'disabled', 'weekday_night', '18:00:00', '06:00:00', 0.50, 1.00, 8.00, 12],
        ['PMR Jour Weekend', 'disabled', 'weekend_day', '06:00:00', '20:00:00', 1.50, 2.00, 15.00, 24],
        ['PMR Nuit Weekend', 'disabled', 'weekend_night', '20:00:00', '06:00:00', 0.50, 1.00, 8.00, 12],
        
        // Places ÉLECTRIQUES
        ['Électrique Jour Semaine', 'electric', 'weekday_day', '06:00:00', '18:00:00', 3.00, 4.50, 35.00, 8],
        ['Électrique Nuit Semaine', 'electric', 'weekday_night', '18:00:00', '06:00:00', 2.50, 3.50, 25.00, 12],
        ['Électrique Jour Weekend', 'electric', 'weekend_day', '06:00:00', '20:00:00', 3.50, 5.00, 40.00, 8],
        ['Électrique Nuit Weekend', 'electric', 'weekend_night', '20:00:00', '06:00:00', 2.50, 3.50, 25.00, 12],
        
        // Places RÉSERVÉES
        ['Réservée Jour Semaine', 'reserved', 'weekday_day', '06:00:00', '18:00:00', 5.00, 6.00, 50.00, 24],
        ['Réservée Nuit Semaine', 'reserved', 'weekday_night', '18:00:00', '06:00:00', 4.00, 5.00, 40.00, 24],
        ['Réservée Jour Weekend', 'reserved', 'weekend_day', '06:00:00', '20:00:00', 6.00, 7.00, 60.00, 24],
        ['Réservée Nuit Weekend', 'reserved', 'weekend_night', '20:00:00', '06:00:00', 4.00, 5.00, 40.00, 24],
        
        // Places COMPACTES
        ['Compact Jour Semaine', 'compact', 'weekday_day', '06:00:00', '18:00:00', 1.50, 2.50, 20.00, 12],
        ['Compact Nuit Semaine', 'compact', 'weekday_night', '18:00:00', '06:00:00', 1.00, 1.50, 12.00, 12],
        ['Compact Jour Weekend', 'compact', 'weekend_day', '06:00:00', '20:00:00', 2.00, 3.00, 25.00, 24],
        ['Compact Nuit Weekend', 'compact', 'weekend_night', '20:00:00', '06:00:00', 1.00, 1.50, 12.00, 12]
    ];
    
    $stmt = $pdo->prepare("
        INSERT INTO pricing_rules (rule_name, spot_type, time_period, start_time, end_time, base_price, hourly_rate, daily_rate, max_duration_hours, is_active) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1)
    ");
    
    $inserted = 0;
    foreach ($rules as $rule) {
        $stmt->execute($rule);
        $inserted++;
    }
    
    echo "<p>✅ $inserted nouvelles règles de tarification ajoutées</p>";
    
    // Vérification finale
    $stmt = $pdo->query("
        SELECT spot_type, time_period, base_price, hourly_rate, daily_rate 
        FROM pricing_rules 
        WHERE is_active = 1 
        ORDER BY spot_type, time_period
    ");
    $allRules = $stmt->fetchAll();
    
    echo "<h2>📋 Règles de tarification configurées :</h2>";
    echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr style='background: #f0f0f0;'>";
    echo "<th>Type de place</th><th>Période</th><th>Base</th><th>Horaire</th><th>Journalier</th>";
    echo "</tr>";
    
    foreach ($allRules as $rule) {
        echo "<tr>";
        echo "<td>{$rule['spot_type']}</td>";
        echo "<td>{$rule['time_period']}</td>";
        echo "<td>{$rule['base_price']} €</td>";
        echo "<td>{$rule['hourly_rate']} €/h</td>";
        echo "<td>{$rule['daily_rate']} €/jour</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    echo "<div style='background: #e8f5e8; padding: 20px; border-radius: 8px; margin: 20px 0;'>";
    echo "<h3>🎉 Correction terminée avec succès!</h3>";
    echo "<p>Toutes les règles de tarification sont maintenant configurées.</p>";
    echo "<p><a href='reservation.php'>Tester la réservation</a></p>";
    echo "</div>";
    
} catch (Exception $e) {
    echo "<div style='background: #fee; padding: 15px; border-radius: 8px;'>";
    echo "<h2>❌ Erreur</h2>";
    echo "<p>" . htmlspecialchars($e->getMessage()) . "</p>";
    echo "</div>";
}

echo "<style>
body { font-family: Arial, sans-serif; max-width: 1000px; margin: 20px auto; padding: 20px; }
table { border-collapse: collapse; margin: 20px 0; }
th, td { padding: 8px; text-align: left; border: 1px solid #ddd; }
th { background-color: #f2f2f2; }
</style>";
?>
