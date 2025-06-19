<?php
echo "<h1>📦 Installation Composer</h1>";

$vendorDir = __DIR__ . '/../vendor';
$composerJson = __DIR__ . '/../composer.json';

// Vérifier si composer.json existe
if(!file_exists($composerJson)) {
    echo "<h2>Création du composer.json</h2>";
    $composerContent = [
        "name" => "parkfinder/parking-management",
        "description" => "Parking Management System",
        "type" => "project",
        "require" => [
            "php" => ">=7.4",
            "vlucas/phpdotenv" => "^5.4"
        ],
        "autoload" => [
            "psr-4" => [
                "App\\" => "app/"
            ]
        ]
    ];
    
    file_put_contents($composerJson, json_encode($composerContent, JSON_PRETTY_PRINT));
    echo "<p>✅ composer.json créé</p>";
}

// Vérifier si vendor existe
if(is_dir($vendorDir)) {
    echo "<h2>✅ Composer déjà installé</h2>";
    echo "<p>Dossier vendor trouvé</p>";
    
    if(file_exists($vendorDir . '/autoload.php')) {
        echo "<p>✅ Autoloader disponible</p>";
        
        // Test autoloader
        require_once $vendorDir . '/autoload.php';
        if(class_exists('Dotenv\\Dotenv')) {
            echo "<p>✅ Dotenv disponible</p>";
        } else {
            echo "<p>❌ Dotenv manquant</p>";
        }
    }
} else {
    echo "<h2>❌ Composer non installé</h2>";
    echo "<p>Pour installer Composer, exécutez dans le terminal:</p>";
    echo "<pre style='background:#f5f5f5;padding:10px;border-radius:5px;'>";
    echo "cd C:\\Web_FullStack\\www\\ParkFinder\n";
    echo "composer install";
    echo "</pre>";
    
    echo "<p>Ou téléchargez Composer depuis: <a href='https://getcomposer.org/download/' target='_blank'>getcomposer.org</a></p>";
}

// Créer .env si absent
$envFile = __DIR__ . '/../.env';
if(!file_exists($envFile)) {
    echo "<h2>Création du fichier .env</h2>";
    $envContent = "DB_HOST=localhost\nDB_PORT=3306\nDB_NAME=parking_management_system\nDB_USER=root\nDB_PASS=\n\nAPP_NAME=\"Parking Management System\"\nAPP_ENV=development\nAPP_DEBUG=true\nAPP_URL=http://localhost/ParkFinder\nAPP_TIMEZONE=Europe/Paris";
    
    file_put_contents($envFile, $envContent);
    echo "<p>✅ Fichier .env créé avec les paramètres par défaut</p>";
}

echo "<hr>";
echo "<p><a href='start.php'>← Retour aux tests</a></p>";
echo "<p><a href='simple-app.php'>→ Tester l'application</a></p>";

echo "<style>body{font-family:Arial;max-width:800px;margin:20px auto;padding:20px;}h1{color:#2c3e50;}a{color:#3498db;}pre{background:#f5f5f5;padding:10px;border-radius:5px;}</style>";
?>
