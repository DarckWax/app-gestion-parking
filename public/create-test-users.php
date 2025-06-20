<?php
echo "<h1>👥 Création des comptes utilisateurs de test</h1>";

try {
    // Connexion à la base de données
    $pdo = new PDO('mysql:host=localhost;dbname=parking_management_system', 'root', '');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "<p> Connexion à la base de données réussie</p>";
    
    // Mot de passe par défaut pour tous les utilisateurs test
    $defaultPassword = 'client123';
    $passwordHash = password_hash($defaultPassword, PASSWORD_DEFAULT);
    
    echo "<div style='background: #e8f4fd; padding: 15px; border-radius: 8px; margin: 20px 0; border-left: 4px solid #3498db;'>";
    echo "<h3 style='color: #2980b9; margin-top: 0;'>ℹ Informations importantes</h3>";
    echo "<p><strong>Mot de passe par défaut:</strong> client123</p>";
    echo "<p><strong>Tous les comptes:</strong> Rôle 'user' (client)</p>";
    echo "<p><strong>Hash généré:</strong> " . substr($passwordHash, 0, 50) . "...</p>";
    echo "</div>";
    
    // Utilisateurs de test à créer
    $testUsers = [
        [
            'first_name' => 'Jean',
            'last_name' => 'Dupont',
            'email' => 'jean.dupont@email.com',
            'phone' => '+33612345678',
            'email_verified' => true,
            'phone_verified' => false
        ],
        [
            'first_name' => 'Marie',
            'last_name' => 'Martin',
            'email' => 'marie.martin@email.com',
            'phone' => '+33623456789',
            'email_verified' => true,
            'phone_verified' => true
        ],
        [
            'first_name' => 'Pierre',
            'last_name' => 'Durand',
            'email' => 'pierre.durand@email.com',
            'phone' => '+33634567890',
            'email_verified' => false,
            'phone_verified' => false
        ],
        [
            'first_name' => 'Sophie',
            'last_name' => 'Lefevre',
            'email' => 'sophie.lefevre@email.com',
            'phone' => '+33645678901',
            'email_verified' => true,
            'phone_verified' => true
        ],
        [
            'first_name' => 'Thomas',
            'last_name' => 'Bernard',
            'email' => 'thomas.bernard@email.com',
            'phone' => '+33656789012',
            'email_verified' => true,
            'phone_verified' => false
        ],
        [
            'first_name' => 'Emma',
            'last_name' => 'Petit',
            'email' => 'emma.petit@email.com',
            'phone' => '+33667890123',
            'email_verified' => false,
            'phone_verified' => true
        ]
    ];
    
    echo "<h2>Création des utilisateurs</h2>";
    
    $created = 0;
    $skipped = 0;
    
    $insertStmt = $pdo->prepare("
        INSERT INTO users (first_name, last_name, email, phone, password_hash, role, status, email_verified, phone_verified) 
        VALUES (?, ?, ?, ?, ?, 'user', 'active', ?, ?)
    ");
    
    $checkStmt = $pdo->prepare("SELECT user_id FROM users WHERE email = ?");
    
    foreach ($testUsers as $user) {
        // Vérifier si l'utilisateur existe déjà
        $checkStmt->execute([$user['email']]);
        
        if ($checkStmt->fetch()) {
            echo "<p>⚠️ <strong>{$user['first_name']} {$user['last_name']}</strong> ({$user['email']}) - Déjà existant</p>";
            $skipped++;
        } else {
            // Créer l'utilisateur
            $result = $insertStmt->execute([
                $user['first_name'],
                $user['last_name'],
                $user['email'],
                $user['phone'],
                $passwordHash,
                $user['email_verified'] ? 1 : 0,
                $user['phone_verified'] ? 1 : 0
            ]);
            
            if ($result) {
                $newUserId = $pdo->lastInsertId();
                $emailStatus = $user['email_verified'] ? '✅' : '❌';
                $phoneStatus = $user['phone_verified'] ? '✅' : '❌';
                echo "<p>✅ <strong>{$user['first_name']} {$user['last_name']}</strong> (ID: $newUserId) - Email: $emailStatus Phone: $phoneStatus</p>";
                $created++;
            } else {
                echo "<p>❌ Erreur lors de la création de {$user['first_name']} {$user['last_name']}</p>";
            }
        }
    }
    
    echo "<div style='background: #e8f5e8; padding: 20px; border-radius: 8px; margin: 20px 0;'>";
    echo "<h3 style='color: #27ae60; margin-top: 0;'>📊 Résumé</h3>";
    echo "<p><strong>✅ Créés:</strong> $created utilisateurs</p>";
    echo "<p><strong>⚠️ Ignorés:</strong> $skipped utilisateurs (déjà existants)</p>";
    echo "</div>";
    
    // Afficher tous les utilisateurs clients
    echo "<h2>👥 Liste des utilisateurs clients</h2>";
    $stmt = $pdo->query("
        SELECT user_id, first_name, last_name, email, phone, 
               email_verified, phone_verified, created_at, last_login
        FROM users 
        WHERE role = 'user' 
        ORDER BY created_at DESC
    ");
    $users = $stmt->fetchAll();
    
    if ($users) {
        echo "<table border='1' style='width: 100%; border-collapse: collapse; margin: 20px 0;'>";
        echo "<tr style='background: #f8f9fa;'>";
        echo "<th style='padding: 10px;'>ID</th>";
        echo "<th style='padding: 10px;'>Nom</th>";
        echo "<th style='padding: 10px;'>Email</th>";
        echo "<th style='padding: 10px;'>Téléphone</th>";
        echo "<th style='padding: 10px;'>Vérifications</th>";
        echo "<th style='padding: 10px;'>Créé le</th>";
        echo "<th style='padding: 10px;'>Dernière connexion</th>";
        echo "</tr>";
        
        foreach ($users as $user) {
            $emailIcon = $user['email_verified'] ? '✅' : '❌';
            $phoneIcon = $user['phone_verified'] ? '✅' : '❌';
            $lastLogin = $user['last_login'] ? date('d/m/Y H:i', strtotime($user['last_login'])) : 'Jamais';
            
            echo "<tr>";
            echo "<td style='padding: 8px; text-align: center;'>{$user['user_id']}</td>";
            echo "<td style='padding: 8px;'><strong>{$user['first_name']} {$user['last_name']}</strong></td>";
            echo "<td style='padding: 8px;'>{$user['email']}</td>";
            echo "<td style='padding: 8px;'>{$user['phone']}</td>";
            echo "<td style='padding: 8px; text-align: center;'>$emailIcon $phoneIcon</td>";
            echo "<td style='padding: 8px;'>" . date('d/m/Y H:i', strtotime($user['created_at'])) . "</td>";
            echo "<td style='padding: 8px;'>$lastLogin</td>";
            echo "</tr>";
        }
        echo "</table>";
        
        echo "<p><strong>Total:</strong> " . count($users) . " utilisateurs clients</p>";
    }
    
    // Test de connexion
    echo "<h2>Test de connexion</h2>";
    if (!empty($testUsers)) {
        $testUser = $testUsers[0]; // Prendre le premier utilisateur pour test
        
        echo "<p>Test avec: <strong>{$testUser['first_name']} {$testUser['last_name']}</strong></p>";
        
        $loginStmt = $pdo->prepare("SELECT user_id, password_hash FROM users WHERE email = ?");
        $loginStmt->execute([$testUser['email']]);
        $userLogin = $loginStmt->fetch();
        
        if ($userLogin && password_verify($defaultPassword, $userLogin['password_hash'])) {
            echo "<div style='background: #e8f5e8; padding: 15px; border-radius: 8px; border: 1px solid #27ae60;'>";
            echo "<p>✅ <strong>Test de connexion réussi!</strong></p>";
            echo "<p>Utilisateur ID: {$userLogin['user_id']}</p>";
            echo "</div>";
        } else {
            echo "<div style='background: #fee; padding: 15px; border-radius: 8px; border: 1px solid #e74c3c;'>";
            echo "<p>❌ <strong>Test de connexion échoué!</strong></p>";
            echo "</div>";
        }
    }
    
    echo "<div style='background: #fff3cd; padding: 20px; border-radius: 8px; margin: 20px 0; border: 1px solid #ffeaa7;'>";
    echo "<h3 style='color: #856404; margin-top: 0;'>🔑 Comptes de test créés</h3>";
    echo "<p><strong>Utilisez ces identifiants pour tester l'application :</strong></p>";
    echo "<ul style='margin: 10px 0;'>";
    foreach ($testUsers as $user) {
        echo "<li><strong>{$user['email']}</strong> / client123 - {$user['first_name']} {$user['last_name']}</li>";
    }
    echo "</ul>";
    echo "<p><em>Tous les comptes utilisent le mot de passe : <strong>client123</strong></em></p>";
    echo "</div>";
    
} catch(Exception $e) {
    echo "<div style='background: #fee; padding: 15px; border-radius: 8px; border: 1px solid #fcc;'>";
    echo "<h2>❌ Erreur</h2>";
    echo "<p><strong>Message:</strong> " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<p><strong>Fichier:</strong> " . $e->getFile() . " ligne " . $e->getLine() . "</p>";
    echo "</div>";
}

echo "<hr>";
echo "<h2>🔗 Actions disponibles</h2>";
echo "<div style='display: flex; gap: 15px; flex-wrap: wrap;'>";
echo "<a href='index.php#login' style='background: #27ae60; color: white; padding: 10px 15px; border-radius: 5px; text-decoration: none;'>🔐 Tester la connexion</a>";
echo "<a href='debug-register.php' style='background: #3498db; color: white; padding: 10px 15px; border-radius: 5px; text-decoration: none;'>🔍 Debug système</a>";
echo "<a href='index.php' style='background: #95a5a6; color: white; padding: 10px 15px; border-radius: 5px; text-decoration: none;'>🏠 Accueil</a>";
echo "</div>";

echo "<style>
body { font-family: Arial, sans-serif; max-width: 1200px; margin: 20px auto; padding: 20px; line-height: 1.6; }
h1, h2, h3 { color: #2c3e50; }
table { border-collapse: collapse; margin: 20px 0; }
th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
th { background-color: #f2f2f2; font-weight: 600; }
tr:nth-child(even) { background-color: #f9f9f9; }
a { color: #3498db; text-decoration: none; }
a:hover { text-decoration: underline; }
</style>";
?>
