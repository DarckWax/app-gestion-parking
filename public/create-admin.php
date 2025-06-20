<?php
/**
 * Script pour créer un compte administrateur
 */

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
    
    // Supprimer les anciens comptes admin
    $pdo->exec("DELETE FROM users WHERE role = 'admin'");
    
    // Hash du mot de passe admin123
    $passwordHash = password_hash('admin123', PASSWORD_DEFAULT);
    
    // Insérer le compte admin principal
    $stmt = $pdo->prepare("
        INSERT INTO users (
            first_name, last_name, email, phone, password_hash, 
            role, status, email_verified, phone_verified, created_at
        ) VALUES (?, ?, ?, ?, ?, 'admin', 'active', TRUE, TRUE, NOW())
    ");
    
    $adminAccounts = [
        ['Admin', 'System', 'admin@parkingsystem.com', '+33123456789'],
        ['Super', 'Admin', 'admin@parkfinder.com', '+33987654321']
    ];
    
    foreach ($adminAccounts as $admin) {
        $stmt->execute([
            $admin[0], // first_name
            $admin[1], // last_name
            $admin[2], // email
            $admin[3], // phone
            $passwordHash
        ]);
    }
    
    echo "<h1>✅ Comptes administrateur créés avec succès</h1>";
    echo "<h2>Identifiants de connexion :</h2>";
    echo "<div style='background:#d4edda; padding:1rem; border-radius:5px; margin:1rem 0;'>";
    echo "<strong>Compte 1 :</strong><br>";
    echo "Email: <code>admin@parkingsystem.com</code><br>";
    echo "Mot de passe: <code>admin123</code><br><br>";
    echo "<strong>Compte 2 :</strong><br>";
    echo "Email: <code>admin@parkfinder.com</code><br>";
    echo "Mot de passe: <code>admin123</code>";
    echo "</div>";
    
    // Vérifier les comptes créés
    $stmt = $pdo->query("SELECT user_id, first_name, last_name, email, role FROM users WHERE role = 'admin'");
    $admins = $stmt->fetchAll();
    
    echo "<h3>Comptes admin dans la base :</h3>";
    echo "<table border='1' style='border-collapse:collapse; width:100%;'>";
    echo "<tr style='background:#f8f9fa;'><th>ID</th><th>Nom</th><th>Email</th><th>Rôle</th></tr>";
    foreach ($admins as $admin) {
        echo "<tr>";
        echo "<td>{$admin['user_id']}</td>";
        echo "<td>{$admin['first_name']} {$admin['last_name']}</td>";
        echo "<td>{$admin['email']}</td>";
        echo "<td>{$admin['role']}</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    echo "<p style='margin-top:2rem;'><a href='index.php'>🏠 Retour à l'accueil</a></p>";
    
} catch (Exception $e) {
    echo "<h1>❌ Erreur</h1>";
    echo "<p>Impossible de créer les comptes admin : " . $e->getMessage() . "</p>";
    echo "<p><a href='create-db.php'>Créer la base de données d'abord</a></p>";
}
?>

<style>
body { font-family: Arial, sans-serif; max-width: 800px; margin: 2rem auto; padding: 2rem; }
table { margin: 1rem 0; }
th, td { padding: 0.5rem; text-align: left; }
code { background: #f8f9fa; padding: 0.2rem 0.4rem; border-radius: 3px; }
</style>
