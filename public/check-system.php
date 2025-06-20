<?php
echo "<h1>🔍 Vérification du système ParkFinder</h1>";

// Vérifier la base de données
echo "<h2>1. Base de données</h2>";
try {
    $pdo = new PDO('mysql:host=localhost;dbname=parking_management_system', 'root', '');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "<p>✅ Connexion DB réussie</p>";
    
    // Vérifier les tables principales
    $tables = ['users', 'parking_spots', 'reservations', 'payments', 'pricing_rules'];
    foreach ($tables as $table) {
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM $table");
        $count = $stmt->fetch()['count'];
        echo "<p>✅ Table $table: $count enregistrements</p>";
    }
    
} catch (Exception $e) {
    echo "<p>❌ Erreur DB: " . $e->getMessage() . "</p>";
    echo "<p><a href='create-db.php'>Créer la base de données</a></p>";
}

// Vérifier les fichiers
echo "<h2>2. Fichiers système</h2>";
$files = [
    'index.php' => 'Page d\'accueil',
    'reservation.php' => 'Page de réservation',
    'process-reservation.php' => 'Traitement réservation',
    'payment.php' => 'Page de paiement',
    'payment-success.php' => 'Traitement paiement',
    'my-reservations.php' => 'Mes réservations',
    'modify-reservation.php' => 'Modification réservation',
    'cancel-reservation.php' => 'Annulation réservation',
    'generate-receipt.php' => 'Génération reçu'
];

foreach ($files as $file => $description) {
    if (file_exists($file)) {
        echo "<p>✅ $file ($description)</p>";
    } else {
        echo "<p>❌ $file manquant ($description)</p>";
    }
}

// Vérifier les dossiers
echo "<h2>3. Dossiers</h2>";
$dirs = ['../logs', '../database'];
foreach ($dirs as $dir) {
    if (is_dir($dir)) {
        echo "<p>✅ Dossier $dir existe</p>";
    } else {
        echo "<p>❌ Dossier $dir manquant</p>";
        if ($dir === '../logs') {
            mkdir($dir, 0755, true);
            echo "<p>✅ Dossier logs créé</p>";
        }
    }
}

// Test des URLs
echo "<h2>4. Test des liens</h2>";
$baseUrl = 'http://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']);
$links = [
    'index.php' => 'Accueil',
    'reservation.php' => 'Réservation',
    'my-reservations.php' => 'Mes réservations'
];

foreach ($links as $link => $title) {
    echo "<p><a href='$link' target='_blank'>🔗 Tester $title</a></p>";
}

echo "<hr>";
echo "<p><strong>Si tous les tests sont verts ✅, le système devrait fonctionner correctement.</strong></p>";
echo "<p><a href='index.php'>🏠 Retour à l'accueil</a></p>";

echo "<style>
body { font-family: Arial, sans-serif; max-width: 800px; margin: 20px auto; padding: 20px; }
h1, h2 { color: #2c3e50; }
p { margin: 8px 0; }
a { color: #3498db; text-decoration: none; }
a:hover { text-decoration: underline; }
</style>";
?>
