<?php
echo "<h1>🔍 Diagnostic Complet ParkFinder</h1>";

// Test 1: Structure des fichiers
echo "<h2>1. Structure des fichiers</h2>";
$requiredFiles = [
    '../composer.json' => 'Composer config',
    '../vendor/autoload.php' => 'Autoloader',
    '../.env' => 'Configuration',
    '../app/core/Database.php' => 'Database class',
    '../app/core/Router.php' => 'Router class',
    '../app/controllers/HomeController.php' => 'Home controller'
];

foreach ($requiredFiles as $file => $desc) {
    $path = __DIR__ . '/' . $file;
    if (file_exists($path)) {
        echo "✅ $desc<br>";
    } else {
        echo "❌ $desc manquant: $file<br>";
    }
}

// Test 2: Permissions
echo "<h2>2. Permissions des dossiers</h2>";
$dirs = ['../logs', '../vendor', '../public/assets'];
foreach ($dirs as $dir) {
    $path = __DIR__ . '/' . $dir;
    if (is_dir($path)) {
        if (is_writable($path)) {
            echo "✅ $dir: Écriture OK<br>";
        } else {
            echo "⚠️ $dir: Lecture seule<br>";
        }
    } else {
        echo "❌ $dir: Dossier manquant<br>";
    }
}

// Test 3: Composer
echo "<h2>3. Test Composer</h2>";
if (file_exists(__DIR__ . '/../composer.json')) {
    $composer = json_decode(file_get_contents(__DIR__ . '/../composer.json'), true);
    if ($composer) {
        echo "✅ composer.json valide<br>";
        echo "Nom: " . ($composer['name'] ?? 'Non défini') . "<br>";
    } else {
        echo "❌ composer.json invalide<br>";
    }
} else {
    echo "❌ composer.json manquant<br>";
}

// Test 4: Autoloader
echo "<h2>4. Test Autoloader</h2>";
if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    try {
        require_once __DIR__ . '/../vendor/autoload.php';
        echo "✅ Autoloader chargé<br>";
        
        if (class_exists('Dotenv\Dotenv')) {
            echo "✅ Dotenv disponible<br>";
        } else {
            echo "❌ Dotenv manquant<br>";
        }
    } catch (Exception $e) {
        echo "❌ Erreur autoloader: " . $e->getMessage() . "<br>";
    }
} else {
    echo "❌ Autoloader manquant<br>";
}

// Test 5: Variables d'environnement
echo "<h2>5. Variables d'environnement</h2>";
if (file_exists(__DIR__ . '/../.env')) {
    try {
        $dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../');
        $dotenv->load();
        echo "✅ .env chargé<br>";
        echo "DB_HOST: " . ($_ENV['DB_HOST'] ?? 'Non défini') . "<br>";
        echo "DB_NAME: " . ($_ENV['DB_NAME'] ?? 'Non défini') . "<br>";
    } catch (Exception $e) {
        echo "❌ Erreur .env: " . $e->getMessage() . "<br>";
    }
} else {
    echo "❌ Fichier .env manquant<br>";
}

echo "<h2>🏁 Diagnostic terminé</h2>";
echo "<p><a href='index.php'>Tester l'application</a></p>";
?>
