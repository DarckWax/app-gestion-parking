<?php
echo "<h1>🔧 Création de la base de données</h1>";

try {
    // Connexion MySQL
    $pdo = new PDO('mysql:host=localhost', 'root', '');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Lire le fichier SQL
    $sqlFile = __DIR__ . '/../database/parking_db.sql';
    if(!file_exists($sqlFile)) {
        throw new Exception("Fichier SQL non trouvé: $sqlFile");
    }
    
    $sql = file_get_contents($sqlFile);
    
    // Exécuter le script SQL
    echo "<h2>Exécution du script SQL...</h2>";
    $statements = explode(';', $sql);
    $executed = 0;
    
    foreach($statements as $statement) {
        $statement = trim($statement);
        if(!empty($statement) && !str_starts_with($statement, '--')) {
            try {
                $pdo->exec($statement);
                $executed++;
            } catch(Exception $e) {
                echo "<p>⚠️ Avertissement: " . htmlspecialchars($e->getMessage()) . "</p>";
            }
        }
    }
    
    echo "<p>✅ $executed instructions SQL exécutées</p>";
    
    // Vérification
    $pdo = new PDO('mysql:host=localhost;dbname=parking_management_system', 'root', '');
    
    // Compter les tables
    $stmt = $pdo->query("SHOW TABLES");
    $tables = $stmt->rowCount();
    echo "<p>✅ $tables tables créées</p>";
    
    // Compter les utilisateurs
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM users");
    $users = $stmt->fetch()['count'];
    echo "<p>✅ $users utilisateurs créés</p>";
    
    // Compter les places
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM parking_spots");
    $spots = $stmt->fetch()['count'];
    echo "<p>✅ $spots places de parking créées</p>";
    
    echo "<h2>🎉 Base de données créée avec succès!</h2>";
    echo "<p><a href='start.php'>← Retour aux tests</a></p>";
    echo "<p><a href='simple-app.php'>→ Tester l'application</a></p>";
    
} catch(Exception $e) {
    echo "<h2>❌ Erreur</h2>";
    echo "<p>" . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<p><a href='start.php'>← Retour</a></p>";
}

echo "<style>body{font-family:Arial;max-width:800px;margin:20px auto;padding:20px;}h1{color:#2c3e50;}a{color:#3498db;}</style>";
?>
