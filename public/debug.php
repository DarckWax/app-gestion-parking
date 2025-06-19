<?php
echo "<h1>Diagnostic ParkFinder</h1>";

// Test PHP de base
echo "<h2>✅ PHP fonctionne</h2>";
echo "<p>Version PHP: " . phpversion() . "</p>";
echo "<p>Répertoire actuel: " . __DIR__ . "</p>";

// Test des extensions requises
$extensions = ['pdo', 'pdo_mysql', 'json', 'session'];
echo "<h2>Extensions PHP</h2>";
foreach ($extensions as $ext) {
    if (extension_loaded($ext)) {
        echo "<p>✅ $ext: Disponible</p>";
    } else {
        echo "<p>❌ $ext: MANQUANT</p>";
    }
}

// Test des fichiers
$files = [
    '../vendor/autoload.php' => 'Composer Autoloader',
    '../.env' => 'Fichier de configuration',
    '../app/core/Database.php' => 'Classe Database',
    '../app/core/Router.php' => 'Classe Router'
];

echo "<h2>Fichiers requis</h2>";
foreach ($files as $file => $desc) {
    if (file_exists(__DIR__ . '/' . $file)) {
        echo "<p>✅ $desc: Trouvé</p>";
    } else {
        echo "<p>❌ $desc: MANQUANT - $file</p>";
    }
}

// Test permissions
echo "<h2>Permissions</h2>";
$dirs = ['../logs', '../vendor', '../app'];
foreach ($dirs as $dir) {
    $path = __DIR__ . '/' . $dir;
    if (is_dir($path)) {
        if (is_writable($path)) {
            echo "<p>✅ $dir: Lecture/Écriture OK</p>";
        } else {
            echo "<p>⚠️ $dir: Lecture seule</p>";
        }
    } else {
        echo "<p>❌ $dir: Dossier manquant</p>";
    }
}

echo "<h2>Test terminé</h2>";
echo "<p><a href='index.php'>Tester index.php</a></p>";
?>
