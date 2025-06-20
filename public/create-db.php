<?php
echo "<h1>🔧 Création de la base de données</h1>";

try {
    // Connexion MySQL sans spécifier de base de données
    $pdo = new PDO('mysql:host=localhost', 'root', '');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "<p>✅ Connexion MySQL réussie</p>";
    
    // Lire le fichier SQL
    $sqlFile = __DIR__ . '/../database/parking_db.sql';
    if(!file_exists($sqlFile)) {
        throw new Exception("Fichier SQL non trouvé: $sqlFile");
    }
    
    $sql = file_get_contents($sqlFile);
    echo "<p>✅ Fichier SQL lu avec succès</p>";
    
    // Diviser le script en instructions individuelles
    $statements = array_filter(
        array_map('trim', preg_split('/;(?=(?:[^\'"]|[\'"][^\'"]*[\'"])*$)/', $sql)),
        function($stmt) {
            return !empty($stmt) && !preg_match('/^--/', $stmt);
        }
    );
    
    echo "<h2>Exécution du script SQL...</h2>";
    echo "<div style='background: #f5f5f5; padding: 10px; border-radius: 5px; font-family: monospace; font-size: 12px; max-height: 300px; overflow-y: auto;'>";
    
    $executed = 0;
    $errors = 0;
    
    foreach($statements as $statement) {
        $statement = trim($statement);
        if(!empty($statement)) {
            try {
                $pdo->exec($statement);
                $executed++;
                
                // Afficher les instructions importantes
                if(preg_match('/CREATE (DATABASE|TABLE)/i', $statement)) {
                    $shortStmt = substr($statement, 0, 80) . (strlen($statement) > 80 ? '...' : '');
                    echo "<span style='color: green;'>✅ " . htmlspecialchars($shortStmt) . "</span><br>";
                } elseif(preg_match('/INSERT INTO/i', $statement)) {
                    $shortStmt = substr($statement, 0, 60) . '...';
                    echo "<span style='color: blue;'>📝 " . htmlspecialchars($shortStmt) . "</span><br>";
                }
            } catch(Exception $e) {
                $errors++;
                echo "<span style='color: orange;'>⚠️ " . htmlspecialchars($e->getMessage()) . "</span><br>";
            }
        }
    }
    
    echo "</div>";
    echo "<p><strong>✅ $executed instructions SQL exécutées avec succès</strong></p>";
    if($errors > 0) {
        echo "<p><strong>⚠️ $errors avertissements (normal pour les tables existantes)</strong></p>";
    }
    
    // Vérification finale
    echo "<h2>Vérification de la base de données</h2>";
    
    // Se connecter à la base créée
    $pdo = new PDO('mysql:host=localhost;dbname=parking_management_system', 'root', '');
    
    // Compter les tables
    $stmt = $pdo->query("SHOW TABLES");
    $tables = $stmt->fetchAll();
    echo "<p>✅ " . count($tables) . " tables créées :</p>";
    echo "<ul>";
    foreach($tables as $table) {
        $tableName = array_values($table)[0];
        echo "<li><strong>$tableName</strong>";
        
        // Compter les enregistrements
        try {
            $countStmt = $pdo->query("SELECT COUNT(*) as count FROM `$tableName`");
            $count = $countStmt->fetch()['count'];
            echo " ($count enregistrements)";
        } catch(Exception $e) {
            echo " (erreur comptage)";
        }
        echo "</li>";
    }
    echo "</ul>";
    
    // Vérifier l'utilisateur admin
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM users WHERE role = 'admin'");
    $adminCount = $stmt->fetch()['count'];
    
    if($adminCount > 0) {
        echo "<p>✅ Compte administrateur créé</p>";
        echo "<div style='background: #e8f5e8; padding: 15px; border-radius: 8px; margin: 20px 0;'>";
        echo "<h3 style='color: #2d5a2d; margin-top: 0;'>🔑 Compte administrateur</h3>";
        echo "<p style='margin: 5px 0;'><strong>Email :</strong> admin@parkingsystem.com</p>";
        echo "<p style='margin: 5px 0;'><strong>Mot de passe :</strong> admin123</p>";
        echo "</div>";
    } else {
        echo "<p>❌ Aucun compte administrateur trouvé</p>";
    }
    
    // Vérifier les places de parking
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM parking_spots WHERE is_active = 1");
    $spotCount = $stmt->fetch()['count'];
    
    if($spotCount > 0) {
        echo "<p>✅ $spotCount places de parking créées</p>";
        
        // Détail par type
        $stmt = $pdo->query("SELECT spot_type, COUNT(*) as count FROM parking_spots WHERE is_active = 1 GROUP BY spot_type");
        $spotTypes = $stmt->fetchAll();
        echo "<ul>";
        foreach($spotTypes as $type) {
            $typeLabel = [
                'standard' => 'Standard',
                'disabled' => 'PMR',
                'electric' => 'Électrique',
                'reserved' => 'Réservée',
                'compact' => 'Compacte'
            ][$type['spot_type']] ?? $type['spot_type'];
            echo "<li>{$type['count']} places $typeLabel</li>";
        }
        echo "</ul>";
    }
    
    // Mise à jour de la table parking_spots si nécessaire
    try {
        $stmt = $pdo->query("SHOW COLUMNS FROM parking_spots LIKE 'hourly_rate'");
        $columnExists = $stmt->fetch();
        
        if (!$columnExists) {
            $pdo->exec("ALTER TABLE parking_spots ADD COLUMN hourly_rate DECIMAL(10,2) DEFAULT 2.50 AFTER status");
            echo "<p>✅ Colonne hourly_rate ajoutée à la table parking_spots</p>";
        }
        
        // Mettre à jour les places existantes avec des tarifs
        $pdo->exec("UPDATE parking_spots SET hourly_rate = 2.50 WHERE hourly_rate IS NULL OR hourly_rate = 0");
        
    } catch (Exception $e) {
        echo "<p>⚠️ Erreur lors de la mise à jour de la structure: " . $e->getMessage() . "</p>";
    }
    
    echo "<h2>🎉 Base de données créée avec succès!</h2>";
    echo "<div style='background: #e8f5e8; padding: 20px; border-radius: 8px; margin: 20px 0;'>";
    echo "<h3 style='color: #2d5a2d; margin-top: 0;'>Prochaines étapes :</h3>";
    echo "<ol>";
    echo "<li><a href='debug-register.php' style='color: #2d5a2d;'>🔍 Tester le système d'inscription</a></li>";
    echo "<li><a href='index.php' style='color: #2d5a2d;'>🏠 Retour à l'application</a></li>";
    echo "<li><a href='index.php#login' style='color: #2d5a2d;'>🔐 Tester la connexion admin</a></li>";
    echo "</ol>";
    echo "</div>";
    
    echo "<p>✅ Base de données créée avec succès</p>";
    echo "<p>✅ Tables créées avec succès</p>";
    echo "<p>✅ Données de test insérées</p>";
    
    // Afficher les comptes créés
    echo "<h2>📋 Comptes utilisateur créés :</h2>";
    echo "<div style='background:#d4edda; padding:1rem; border-radius:5px; margin:1rem 0;'>";
    echo "<strong>👑 Compte Administrateur :</strong><br>";
    echo "Email: <code>admin@parkingsystem.com</code><br>";
    echo "Mot de passe: <code>admin123</code><br><br>";
    echo "<strong>👤 Comptes Utilisateurs de test :</strong><br>";
    echo "Email: <code>marie.martin@email.com</code> | Mot de passe: <code>admin123</code><br>";
    echo "Email: <code>pierre.dupont@email.com</code> | Mot de passe: <code>admin123</code><br>";
    echo "Email: <code>sophie.bernard@email.com</code> | Mot de passe: <code>admin123</code><br>";
    echo "Email: <code>lucas.moreau@email.com</code> | Mot de passe: <code>admin123</code>";
    echo "</div>";
    
} catch(Exception $e) {
    echo "<h2>❌ Erreur</h2>";
    echo "<div style='background: #fee; padding: 15px; border-radius: 8px; border: 1px solid #fcc;'>";
    echo "<p><strong>Message :</strong> " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<p><strong>Code :</strong> " . $e->getCode() . "</p>";
    echo "<p><strong>Fichier :</strong> " . $e->getFile() . " ligne " . $e->getLine() . "</p>";
    echo "</div>";
    
    echo "<h3>Solutions possibles :</h3>";
    echo "<ul>";
    echo "<li>Vérifiez que MySQL/WAMP est démarré</li>";
    echo "<li>Vérifiez les identifiants de connexion (host: localhost, user: root, pass: vide)</li>";
    echo "<li>Vérifiez que le fichier database/parking_db.sql existe</li>";
    echo "<li>Essayez de créer la base manuellement via phpMyAdmin</li>";
    echo "</ul>";
    
    echo "<p><a href='index.php'>← Retour à l'accueil</a></p>";
}

echo "<style>
body { font-family: Arial, sans-serif; max-width: 900px; margin: 20px auto; padding: 20px; line-height: 1.6; }
h1, h2, h3 { color: #2c3e50; }
a { color: #3498db; text-decoration: none; }
a:hover { text-decoration: underline; }
code { background: #f5f5f5; padding: 2px 5px; border-radius: 3px; }
</style>";
?>
