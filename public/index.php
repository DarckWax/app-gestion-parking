<?php
// Application de parking - Interface d'accueil simple
session_start();

// Configuration de base
$config = [
    'db_host' => 'localhost',
    'db_name' => 'parking_management_system', 
    'db_user' => 'root',
    'db_pass' => ''
];

// Connexion base de données
try {
    $pdo = new PDO("mysql:host={$config['db_host']};dbname={$config['db_name']}", $config['db_user'], $config['db_pass']);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $dbConnected = true;
} catch(Exception $e) {
    $dbConnected = false;
}

// Récupération des statistiques
$stats = ['total_spots' => 0, 'available_spots' => 0, 'occupied_spots' => 0];
if($dbConnected) {
    try {
        $stmt = $pdo->query("SELECT COUNT(*) as total FROM parking_spots WHERE is_active = 1");
        $stats['total_spots'] = $stmt->fetch()['total'];
        
        $stmt = $pdo->query("SELECT COUNT(*) as available FROM parking_spots WHERE status = 'available' AND is_active = 1");
        $stats['available_spots'] = $stmt->fetch()['available'];
        
        $stats['occupied_spots'] = $stats['total_spots'] - $stats['available_spots'];
    } catch(Exception $e) {
        // Garder les valeurs par défaut
    }
}

// Gestion des actions simples
$action = $_GET['action'] ?? 'home';
$message = '';

if($_POST && $action === 'login') {
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';
    
    if($dbConnected) {
        try {
            $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? AND status = 'active'");
            $stmt->execute([$email]);
            $user = $stmt->fetch();
            
            if($user && password_verify($password, $user['password_hash'])) {
                $_SESSION['user_id'] = $user['user_id'];
                $_SESSION['user_name'] = $user['first_name'] . ' ' . $user['last_name'];
                $_SESSION['user_role'] = $user['role'];
                $message = "Connexion réussie! Bienvenue {$user['first_name']}.";
            } else {
                $message = "Identifiants incorrects.";
            }
        } catch(Exception $e) {
            $message = "Erreur de connexion.";
        }
    }
}

if($action === 'logout') {
    session_destroy();
    header('Location: index.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ParkFinder - Système de Gestion de Parking</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            color: #333;
        }
        
        .header {
            background: rgba(255,255,255,0.95);
            backdrop-filter: blur(10px);
            padding: 1rem 0;
            box-shadow: 0 2px 20px rgba(0,0,0,0.1);
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
        }
        
        .navbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .logo {
            display: flex;
            align-items: center;
            font-size: 1.8rem;
            font-weight: 700;
            color: #2c3e50;
            text-decoration: none;
        }
        
        .logo::before {
            content: "🚗";
            margin-right: 10px;
            font-size: 2rem;
        }
        
        .nav-links {
            display: flex;
            gap: 2rem;
            align-items: center;
        }
        
        .nav-links a {
            color: #2c3e50;
            text-decoration: none;
            font-weight: 500;
            padding: 0.5rem 1rem;
            border-radius: 6px;
            transition: all 0.3s ease;
        }
        
        .nav-links a:hover {
            background: #3498db;
            color: white;
        }
        
        .hero {
            padding: 4rem 0;
            text-align: center;
            color: white;
        }
        
        .hero h1 {
            font-size: 3.5rem;
            margin-bottom: 1rem;
            font-weight: 700;
            text-shadow: 0 2px 4px rgba(0,0,0,0.3);
        }
        
        .hero p {
            font-size: 1.3rem;
            margin-bottom: 2rem;
            opacity: 0.9;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 2rem;
            margin: 3rem auto;
            max-width: 800px;
        }
        
        .stat-card {
            background: rgba(255,255,255,0.95);
            backdrop-filter: blur(10px);
            padding: 2rem;
            border-radius: 15px;
            text-align: center;
            box-shadow: 0 8px 32px rgba(0,0,0,0.1);
            color: #2c3e50;
        }
        
        .stat-number {
            font-size: 3rem;
            font-weight: 700;
            color: #3498db;
            margin-bottom: 0.5rem;
        }
        
        .stat-label {
            font-size: 1.1rem;
            font-weight: 500;
        }
        
        .features {
            background: white;
            padding: 4rem 0;
        }
        
        .features-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 2rem;
            margin-top: 3rem;
        }
        
        .feature-card {
            background: #f8f9fa;
            padding: 2rem;
            border-radius: 12px;
            text-align: center;
            border: 2px solid transparent;
            transition: all 0.3s ease;
        }
        
        .feature-card:hover {
            border-color: #3498db;
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        }
        
        .feature-icon {
            font-size: 3rem;
            margin-bottom: 1rem;
        }
        
        .feature-title {
            font-size: 1.3rem;
            font-weight: 600;
            margin-bottom: 1rem;
            color: #2c3e50;
        }
        
        .btn {
            display: inline-block;
            padding: 1rem 2rem;
            background: #3498db;
            color: white;
            text-decoration: none;
            border-radius: 8px;
            font-weight: 600;
            transition: all 0.3s ease;
            border: none;
            cursor: pointer;
            font-size: 1rem;
        }
        
        .btn:hover {
            background: #2980b9;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }
        
        .btn-secondary {
            background: transparent;
            border: 2px solid white;
            color: white;
        }
        
        .btn-secondary:hover {
            background: white;
            color: #3498db;
        }
        
        .login-section {
            background: rgba(255,255,255,0.95);
            backdrop-filter: blur(10px);
            padding: 2rem;
            border-radius: 15px;
            max-width: 400px;
            margin: 2rem auto;
            box-shadow: 0 8px 32px rgba(0,0,0,0.1);
        }
        
        .form-group {
            margin-bottom: 1rem;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: #2c3e50;
        }
        
        .form-group input {
            width: 100%;
            padding: 0.75rem;
            border: 2px solid #e9ecef;
            border-radius: 6px;
            font-size: 1rem;
            transition: border-color 0.3s ease;
        }
        
        .form-group input:focus {
            outline: none;
            border-color: #3498db;
            box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.1);
        }
        
        .message {
            padding: 1rem;
            border-radius: 6px;
            margin-bottom: 1rem;
            text-align: center;
        }
        
        .message.success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .message.error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .footer {
            background: #2c3e50;
            color: white;
            text-align: center;
            padding: 2rem 0;
        }
        
        @media (max-width: 768px) {
            .hero h1 {
                font-size: 2.5rem;
            }
            
            .nav-links {
                flex-direction: column;
                gap: 1rem;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <!-- Header -->
    <header class="header">
        <div class="container">
            <nav class="navbar">
                <a href="index.php" class="logo">ParkFinder</a>
                <div class="nav-links">
                    <?php if(isset($_SESSION['user_id'])): ?>
                        <span>Bonjour, <?= htmlspecialchars($_SESSION['user_name']) ?></span>
                        <a href="?action=dashboard">Tableau de bord</a>
                        <a href="?action=logout">Déconnexion</a>
                    <?php else: ?>
                        <a href="#login">Connexion</a>
                        <a href="#features">Services</a>
                        <a href="#about">À propos</a>
                    <?php endif; ?>
                </div>
            </nav>
        </div>
    </header>

    <!-- Hero Section -->
    <section class="hero">
        <div class="container">
            <h1>Gestion de Parking Intelligente</h1>
            <p>Trouvez, réservez et payez votre place de parking en quelques clics</p>
            
            <!-- Statistics -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-number"><?= $stats['total_spots'] ?></div>
                    <div class="stat-label">Places totales</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?= $stats['available_spots'] ?></div>
                    <div class="stat-label">Places disponibles</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?= $stats['occupied_spots'] ?></div>
                    <div class="stat-label">Places occupées</div>
                </div>
            </div>
            
            <?php if(isset($_SESSION['user_id'])): ?>
                <div style="margin-top: 2rem;">
                    <a href="?action=dashboard" class="btn">Accéder au tableau de bord</a>
                    <a href="?action=book" class="btn btn-secondary">Réserver une place</a>
                </div>
            <?php else: ?>
                <div style="margin-top: 2rem;">
                    <a href="#login" class="btn">Commencer maintenant</a>
                    <a href="#features" class="btn btn-secondary">En savoir plus</a>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <!-- Login Section -->
    <?php if(!isset($_SESSION['user_id'])): ?>
    <section id="login" class="hero" style="padding: 2rem 0;">
        <div class="container">
            <div class="login-section">
                <h2 style="text-align: center; margin-bottom: 1.5rem; color: #2c3e50;">Connexion</h2>
                
                <?php if($message): ?>
                    <div class="message <?= strpos($message, 'réussie') !== false ? 'success' : 'error' ?>">
                        <?= htmlspecialchars($message) ?>
                    </div>
                <?php endif; ?>
                
                <form method="post" action="?action=login">
                    <div class="form-group">
                        <label for="email">Email :</label>
                        <input type="email" id="email" name="email" value="admin@parkingsystem.com" required>
                    </div>
                    <div class="form-group">
                        <label for="password">Mot de passe :</label>
                        <input type="password" id="password" name="password" value="admin123" required>
                    </div>
                    <button type="submit" class="btn" style="width: 100%;">Se connecter</button>
                </form>
                
                <div style="text-align: center; margin-top: 1rem; font-size: 0.9rem; color: #666;">
                    <strong>Compte de test :</strong><br>
                    Email: admin@parkingsystem.com<br>
                    Mot de passe: admin123
                </div>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <!-- Features Section -->
    <section id="features" class="features">
        <div class="container">
            <h2 style="text-align: center; font-size: 2.5rem; margin-bottom: 1rem; color: #2c3e50;">
                Nos Services
            </h2>
            <p style="text-align: center; font-size: 1.2rem; color: #666; margin-bottom: 2rem;">
                Une solution complète pour tous vos besoins de stationnement
            </p>
            
            <div class="features-grid">
                <div class="feature-card">
                    <div class="feature-icon">🅿️</div>
                    <h3 class="feature-title">Réservation en temps réel</h3>
                    <p>Consultez la disponibilité en temps réel et réservez instantanément votre place.</p>
                </div>
                
                <div class="feature-card">
                    <div class="feature-icon">💳</div>
                    <h3 class="feature-title">Paiement sécurisé</h3>
                    <p>Payez en ligne de manière sécurisée avec PayPal ou carte bancaire.</p>
                </div>
                
                <div class="feature-card">
                    <div class="feature-icon">📱</div>
                    <h3 class="feature-title">Interface moderne</h3>
                    <p>Application web responsive qui s'adapte à tous vos appareils.</p>
                </div>
                
                <div class="feature-card">
                    <div class="feature-icon">🔔</div>
                    <h3 class="feature-title">Notifications</h3>
                    <p>Recevez des rappels et alertes pour vos réservations.</p>
                </div>
                
                <div class="feature-card">
                    <div class="feature-icon">📊</div>
                    <h3 class="feature-title">Tableau de bord</h3>
                    <p>Gérez toutes vos réservations depuis un tableau de bord intuitif.</p>
                </div>
                
                <div class="feature-card">
                    <div class="feature-icon">🔒</div>
                    <h3 class="feature-title">Sécurité</h3>
                    <p>Vos données sont protégées avec les dernières technologies de sécurité.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="footer">
        <div class="container">
            <p>&copy; 2024 ParkFinder. Tous droits réservés.</p>
            <p>Système de gestion de parking intelligent</p>
            <?php if(!$dbConnected): ?>
                <p style="color: #e74c3c; margin-top: 1rem;">
                    ⚠️ Base de données non connectée - <a href="create-db.php" style="color: #fff;">Créer la base</a>
                </p>
            <?php endif; ?>
        </div>
    </footer>

    <script>
        // Smooth scrolling pour les liens d'ancrage
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    target.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });
                }
            });
        });

        // Animation des cartes statistiques
        const observerOptions = {
            threshold: 0.1,
            rootMargin: '0px 0px -50px 0px'
        };

        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.style.opacity = '1';
                    entry.target.style.transform = 'translateY(0)';
                }
            });
        }, observerOptions);

        document.querySelectorAll('.stat-card, .feature-card').forEach(card => {
            card.style.opacity = '0';
            card.style.transform = 'translateY(20px)';
            card.style.transition = 'all 0.6s ease';
            observer.observe(card);
        });
    </script>
</body>
</html>
