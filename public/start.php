<?php
// Test ultra-minimal - Aucune dépendance
echo "<!DOCTYPE html>";
echo "<html><head><title>ParkFinder Test</title></head><body>";
echo "<h1>🚗 ParkFinder - Test Minimal</h1>";
echo "<p>✅ PHP Version: " . phpversion() . "</p>";
echo "<p>✅ Date: " . date('Y-m-d H:i:s') . "</p>";
echo "<p>✅ Serveur: " . $_SERVER['SERVER_SOFTWARE'] . "</p>";

// Test extensions
$ext = ['pdo', 'pdo_mysql', 'json', 'session'];
echo "<h2>Extensions PHP:</h2><ul>";
foreach($ext as $e) {
    $status = extension_loaded($e) ? "✅" : "❌";
    echo "<li>$status $e</li>";
}
echo "</ul>";

// Test MySQL sans classe
echo "<h2>Test MySQL direct:</h2>";
try {
    $pdo = new PDO('mysql:host=localhost', 'root', '');
    echo "<p>✅ Connexion MySQL réussie</p>";
    
    // Tester si la base existe
    $stmt = $pdo->query("SHOW DATABASES LIKE 'parking_management_system'");
    if($stmt->rowCount() > 0) {
        echo "<p>✅ Base 'parking_management_system' trouvée</p>";
        
        // Se connecter à la base
        $pdo = new PDO('mysql:host=localhost;dbname=parking_management_system', 'root', '');
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM users");
        $result = $stmt->fetch();
        echo "<p>✅ " . $result['count'] . " utilisateurs dans la base</p>";
    } else {
        echo "<p>❌ Base 'parking_management_system' non trouvée</p>";
        echo "<p>💡 <a href='create-db.php'>Créer la base de données</a></p>";
    }
} catch(Exception $e) {
    echo "<p>❌ Erreur MySQL: " . htmlspecialchars($e->getMessage()) . "</p>";
}

echo "<hr>";
echo "<h2>Tests disponibles:</h2>";
echo "<ul>";
echo "<li><a href='create-db.php'>🔧 Créer/Importer la base de données</a></li>";
echo "<li><a href='install-composer.php'>📦 Installer Composer</a></li>";
echo "<li><a href='simple-app.php'>🚀 Application simple</a></li>";
echo "</ul>";

echo "<style>body{font-family:Arial;max-width:800px;margin:20px auto;padding:20px;}h1{color:#2c3e50;}a{color:#3498db;}</style>";
echo "</body></html>";
?>
