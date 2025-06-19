<?php
session_start();

// Configuration simple
$config = [
    'db_host' => 'localhost',
    'db_name' => 'parking_management_system',
    'db_user' => 'root',
    'db_pass' => ''
];

echo "<h1>🚗 ParkFinder - Application Simple</h1>";

try {
    // Connexion base de données
    $pdo = new PDO("mysql:host={$config['db_host']};dbname={$config['db_name']}", $config['db_user'], $config['db_pass']);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Gestion des actions
    $action = $_GET['action'] ?? 'home';
    
    switch($action) {
        case 'login':
            echo "<h2>🔐 Connexion</h2>";
            if($_POST) {
                $email = $_POST['email'] ?? '';
                $password = $_POST['password'] ?? '';
                
                $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? AND status = 'active'");
                $stmt->execute([$email]);
                $user = $stmt->fetch();
                
                if($user && password_verify($password, $user['password_hash'])) {
                    $_SESSION['user_id'] = $user['user_id'];
                    $_SESSION['user_name'] = $user['first_name'] . ' ' . $user['last_name'];
                    $_SESSION['user_role'] = $user['role'];
                    echo "<p>✅ Connexion réussie! <a href='?action=dashboard'>Tableau de bord</a></p>";
                } else {
                    echo "<p>❌ Identifiants incorrects</p>";
                }
            }
            
            echo "<form method='post'>";
            echo "<p>Email: <input type='email' name='email' value='admin@parkingsystem.com' required></p>";
            echo "<p>Mot de passe: <input type='password' name='password' value='admin123' required></p>";
            echo "<p><button type='submit'>Se connecter</button></p>";
            echo "</form>";
            echo "<p><small>Utilisateur test: admin@parkingsystem.com / admin123</small></p>";
            break;
            
        case 'dashboard':
            if(!isset($_SESSION['user_id'])) {
                echo "<p>❌ Veuillez vous connecter. <a href='?action=login'>Connexion</a></p>";
                break;
            }
            
            echo "<h2>📊 Tableau de bord</h2>";
            echo "<p>Bienvenue, {$_SESSION['user_name']}!</p>";
            
            // Statistiques
            $stmt = $pdo->query("SELECT COUNT(*) as total FROM parking_spots WHERE is_active = 1");
            $totalSpots = $stmt->fetch()['total'];
            
            $stmt = $pdo->query("SELECT COUNT(*) as available FROM parking_spots WHERE status = 'available' AND is_active = 1");
            $availableSpots = $stmt->fetch()['available'];
            
            echo "<div style='display:flex;gap:20px;margin:20px 0;'>";
            echo "<div style='background:#e8f5e8;padding:15px;border-radius:8px;'>";
            echo "<h3>🅿️ Places totales</h3><p style='font-size:2em;margin:0;'>$totalSpots</p>";
            echo "</div>";
            echo "<div style='background:#e8f8ff;padding:15px;border-radius:8px;'>";
            echo "<h3>✅ Places disponibles</h3><p style='font-size:2em;margin:0;'>$availableSpots</p>";
            echo "</div>";
            echo "</div>";
            
            echo "<h3>Actions:</h3>";
            echo "<ul>";
            echo "<li><a href='?action=spots'>Voir les places</a></li>";
            echo "<li><a href='?action=reservations'>Mes réservations</a></li>";
            echo "<li><a href='?action=logout'>Déconnexion</a></li>";
            echo "</ul>";
            break;
            
        case 'spots':
            echo "<h2>🅿️ Places de parking</h2>";
            
            $stmt = $pdo->query("SELECT * FROM parking_spots WHERE is_active = 1 ORDER BY zone_section, spot_number");
            $spots = $stmt->fetchAll();
            
            echo "<div style='display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:15px;margin:20px 0;'>";
            foreach($spots as $spot) {
                $statusColor = $spot['status'] === 'available' ? '#e8f5e8' : '#ffe8e8';
                $statusIcon = $spot['status'] === 'available' ? '✅' : '❌';
                
                echo "<div style='background:$statusColor;padding:15px;border-radius:8px;border:2px solid #ddd;'>";
                echo "<h4>$statusIcon {$spot['spot_number']}</h4>";
                echo "<p>Type: {$spot['spot_type']}</p>";
                echo "<p>Zone: {$spot['zone_section']}</p>";
                echo "<p>Statut: {$spot['status']}</p>";
                echo "</div>";
            }
            echo "</div>";
            break;
            
        case 'logout':
            session_destroy();
            echo "<p>✅ Déconnexion réussie</p>";
            echo "<p><a href='?'>Retour accueil</a></p>";
            break;
            
        default:
            echo "<h2>🏠 Accueil</h2>";
            echo "<p>Système de gestion de parking</p>";
            
            if(isset($_SESSION['user_id'])) {
                echo "<p>Connecté en tant que: {$_SESSION['user_name']}</p>";
                echo "<p><a href='?action=dashboard'>📊 Tableau de bord</a></p>";
            } else {
                echo "<p><a href='?action=login'>🔐 Se connecter</a></p>";
            }
            
            // Stats publiques
            $stmt = $pdo->query("SELECT COUNT(*) as available FROM parking_spots WHERE status = 'available' AND is_active = 1");
            $available = $stmt->fetch()['available'];
            echo "<p>Places disponibles: <strong>$available</strong></p>";
    }
    
} catch(Exception $e) {
    echo "<h2>❌ Erreur</h2>";
    echo "<p>" . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<p>Vérifiez:</p>";
    echo "<ul>";
    echo "<li>MySQL est démarré</li>";
    echo "<li>La base de données existe</li>";
    echo "<li><a href='create-db.php'>Créer la base de données</a></li>";
    echo "</ul>";
}

echo "<hr>";
echo "<p><a href='start.php'>🔧 Tests système</a> | <a href='?'>🏠 Accueil</a></p>";

echo "<style>
body{font-family:Arial;max-width:1000px;margin:20px auto;padding:20px;}
h1{color:#2c3e50;background:white;padding:20px;border-radius:8px;box-shadow:0 2px 4px rgba(0,0,0,0.1);}
a{color:#3498db;text-decoration:none;}
a:hover{text-decoration:underline;}
input,button{padding:8px;margin:5px;border:1px solid #ddd;border-radius:4px;}
button{background:#3498db;color:white;cursor:pointer;}
button:hover{background:#2980b9;}
</style>";
?>
