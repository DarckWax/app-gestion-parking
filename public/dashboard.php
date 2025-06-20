<?php
// Contrôle d'accès administrateur obligatoire
require_once '../app/middlewares/AdminMiddleware.php';
use App\Middlewares\AdminMiddleware;

// Vérifier les droits admin avant tout affichage
AdminMiddleware::requireAdmin();

// Démarrer la session seulement si elle n'est pas déjà active
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Configuration de base
$config = [
    'db_host' => 'localhost',
    'db_name' => 'parking_management_system', 
    'db_user' => 'root',
    'db_pass' => ''
];

// Connexion base de données pour les statistiques
try {
    $pdo = new PDO("mysql:host={$config['db_host']};dbname={$config['db_name']}", $config['db_user'], $config['db_pass']);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $dbConnected = true;
} catch(Exception $e) {
    $dbConnected = false;
}

// Récupération des statistiques
$stats = [
    'total_users' => 0,
    'total_spots' => 0,
    'total_reservations' => 0,
    'today_reservations' => 0,
    'revenue_today' => 0,
    'available_spots' => 0
];

if($dbConnected) {
    try {
        // Statistiques utilisateurs
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM users WHERE status = 'active'");
        $stats['total_users'] = $stmt->fetch()['count'];
        
        // Statistiques places
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM parking_spots WHERE is_active = 1");
        $stats['total_spots'] = $stmt->fetch()['count'];
        
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM parking_spots WHERE status = 'available' AND is_active = 1");
        $stats['available_spots'] = $stmt->fetch()['count'];
        
        // Statistiques réservations
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM reservations");
        $stats['total_reservations'] = $stmt->fetch()['count'];
        
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM reservations WHERE DATE(created_at) = CURDATE()");
        $stats['today_reservations'] = $stmt->fetch()['count'];
        
        // Revenus du jour (simulation)
        $stmt = $pdo->query("SELECT COUNT(*) * 15.50 as revenue FROM reservations WHERE DATE(created_at) = CURDATE()");
        $stats['revenue_today'] = $stmt->fetch()['revenue'] ?? 0;
        
    } catch(Exception $e) {
        // Garder les valeurs par défaut
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Admin - ParkFinder</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Space+Grotesk:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        :root {
            /* Palette cohérente avec le site */
            --primary-green: #10B981;
            --dark-green: #059669;
            --light-green: #34D399;
            --accent-green: #6EE7B7;
            --pale-green: #ECFDF5;
            
            --primary-black: #111827;
            --gray-900: #1F2937;
            --gray-800: #374151;
            --gray-700: #4B5563;
            --gray-600: #6B7280;
            --gray-300: #D1D5DB;
            --gray-100: #F3F4F6;
            --white: #FFFFFF;
            
            --font-primary: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            --font-display: 'Space Grotesk', -apple-system, BlinkMacSystemFont, sans-serif;
        }
        
        body {
            font-family: var(--font-primary);
            background: linear-gradient(135deg, var(--primary-black) 0%, var(--gray-900) 50%, var(--dark-green) 100%);
            min-height: 100vh;
            color: var(--white);
            line-height: 1.6;
        }
        
        /* Header identique au site */
        .header {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-bottom: 1px solid var(--gray-300);
            padding: 1rem 0;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            position: sticky;
            top: 0;
            z-index: 50;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 1.5rem;
        }
        
        .navbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .logo {
            display: flex;
            align-items: center;
            font-family: var(--font-display);
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--primary-black);
            text-decoration: none;
            transition: all 0.3s ease;
        }
        
        .logo-icon {
            width: 32px;
            height: 32px;
            margin-right: 0.75rem;
            background: linear-gradient(135deg, var(--primary-green), var(--dark-green));
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--white);
            font-size: 18px;
            font-weight: 800;
            box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
        }
        
        .logo-text {
            font-weight: 800;
            background: linear-gradient(135deg, var(--primary-black), var(--gray-800));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        
        .admin-nav {
            display: flex;
            gap: 2rem;
            align-items: center;
        }
        
        .admin-nav a {
            color: var(--gray-700);
            text-decoration: none;
            font-weight: 500;
            font-size: 0.9rem;
            padding: 0.5rem 1rem;
            border-radius: 0.75rem;
            transition: all 0.3s ease;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .admin-nav a:hover, .admin-nav a.active {
            color: var(--primary-green);
            background: var(--pale-green);
            transform: translateY(-1px);
        }
        
        .admin-badge {
            background: linear-gradient(135deg, #dc3545, #c82333);
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 1.5rem;
            font-size: 0.8rem;
            font-weight: 600;
            letter-spacing: 0.5px;
            box-shadow: 0 2px 8px rgba(220, 53, 69, 0.3);
        }
        
        /* Contenu principal */
        .main-content {
            padding: 3rem 0;
        }
        
        .page-header {
            text-align: center;
            margin-bottom: 3rem;
        }
        
        .page-title {
            font-family: var(--font-display);
            font-size: 3rem;
            font-weight: 800;
            margin-bottom: 1rem;
            background: linear-gradient(135deg, var(--white) 0%, var(--accent-green) 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        
        .page-subtitle {
            font-size: 1.25rem;
            opacity: 0.9;
            font-weight: 300;
        }
        
        /* Grille de statistiques */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 2rem;
            margin-bottom: 3rem;
        }
        
        .stat-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            padding: 2rem;
            border-radius: 1.5rem;
            text-align: center;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
            border: 1px solid rgba(16, 185, 129, 0.2);
            transition: all 0.4s ease;
            position: relative;
            overflow: hidden;
        }
        
        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--primary-green), var(--accent-green));
        }
        
        .stat-card:hover {
            transform: translateY(-8px) scale(1.02);
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.15);
            border-color: var(--primary-green);
        }
        
        .stat-icon {
            font-size: 2.5rem;
            margin-bottom: 1rem;
            opacity: 0.8;
        }
        
        .stat-number {
            font-family: var(--font-display);
            font-size: 2.5rem;
            font-weight: 800;
            color: var(--primary-green);
            margin-bottom: 0.5rem;
            line-height: 1;
        }
        
        .stat-label {
            font-size: 1rem;
            font-weight: 600;
            color: var(--gray-800);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        /* Actions rapides */
        .quick-actions {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            padding: 2rem;
            border-radius: 1.5rem;
            margin-bottom: 3rem;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
        }
        
        .actions-title {
            font-family: var(--font-display);
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--primary-black);
            margin-bottom: 1.5rem;
            text-align: center;
        }
        
        .actions-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
        }
        
        .action-btn {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 1rem 1.5rem;
            background: linear-gradient(135deg, var(--primary-green), var(--dark-green));
            color: white;
            text-decoration: none;
            border-radius: 0.75rem;
            font-weight: 600;
            transition: all 0.3s ease;
            border: none;
            cursor: pointer;
        }
        
        .action-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(16, 185, 129, 0.4);
        }
        
        .action-icon {
            font-size: 1.25rem;
        }
        
        /* Footer */
        .footer {
            background: var(--primary-black);
            color: var(--white);
            text-align: center;
            padding: 2rem 0;
            border-top: 1px solid var(--gray-800);
            margin-top: 3rem;
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .admin-nav {
                gap: 1rem;
                flex-wrap: wrap;
            }
            
            .page-title {
                font-size: 2rem;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
                gap: 1rem;
            }
            
            .actions-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <!-- Header avec navigation admin -->
    <header class="header">
        <div class="container">
            <nav class="navbar">
                <a href="index.php" class="logo">
                    <div class="logo-icon">P</div>
                    <span class="logo-text">ParkFinder</span>
                </a>
                
                <div class="admin-nav">
                    <a href="dashboard.php" class="active">Tableau de bord</a>
                    <a href="admin-users.php">Utilisateurs</a>
                    <a href="admin-spots.php">Places</a>
                    <a href="admin-reservations.php">Réservations</a>
                    <a href="index.php">Retour site</a>
                </div>
                
                <div class="admin-badge">
                    👑 ADMIN
                </div>
            </nav>
        </div>
    </header>

    <!-- Contenu principal -->
    <main class="main-content">
        <div class="container">
            <!-- En-tête de page -->
            <div class="page-header">
                <h1 class="page-title">Tableau de Bord Admin</h1>
                <p class="page-subtitle">Vue d'ensemble et gestion du système ParkFinder</p>
            </div>

            <!-- Statistiques principales -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon">👥</div>
                    <div class="stat-number"><?= $stats['total_users'] ?></div>
                    <div class="stat-label">Utilisateurs actifs</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon">🅿️</div>
                    <div class="stat-number"><?= $stats['available_spots'] ?>/<?= $stats['total_spots'] ?></div>
                    <div class="stat-label">Places disponibles</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon">📅</div>
                    <div class="stat-number"><?= $stats['total_reservations'] ?></div>
                    <div class="stat-label">Réservations totales</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon">📊</div>
                    <div class="stat-number"><?= $stats['today_reservations'] ?></div>
                    <div class="stat-label">Réservations aujourd'hui</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon">💰</div>
                    <div class="stat-number"><?= number_format($stats['revenue_today'], 2) ?>€</div>
                    <div class="stat-label">Revenus du jour</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon">⚡</div>
                    <div class="stat-number"><?= $dbConnected ? 'OK' : 'KO' ?></div>
                    <div class="stat-label">État système</div>
                </div>
            </div>

            <!-- Actions rapides -->
            <div class="quick-actions">
                <h2 class="actions-title">Actions Rapides</h2>
                <div class="actions-grid">
                    <a href="admin-users.php" class="action-btn">
                        <span class="action-icon">👥</span>
                        Gérer les utilisateurs
                    </a>
                    <a href="admin-spots.php" class="action-btn">
                        <span class="action-icon">🅿️</span>
                        Gérer les places
                    </a>
                    <a href="admin-reservations.php" class="action-btn">
                        <span class="action-icon">📅</span>
                        Voir les réservations
                    </a>
                    <a href="admin-reports.php" class="action-btn">
                        <span class="action-icon">📊</span>
                        Rapports détaillés
                    </a>
                    <a href="admin-settings.php" class="action-btn">
                        <span class="action-icon">⚙️</span>
                        Paramètres système
                    </a>
                    <a href="logs/" class="action-btn">
                        <span class="action-icon">📋</span>
                        Consulter les logs
                    </a>
                </div>
            </div>
        </div>
    </main>

    <!-- Footer -->
    <footer class="footer">
        <div class="container">
            <p>&copy; 2024 ParkFinder Admin. Tous droits réservés.</p>
            <p>Connecté en tant que <strong><?= htmlspecialchars($_SESSION['user_name']) ?></strong></p>
        </div>
    </footer>

    <script>
        // Animation des cartes statistiques
        document.addEventListener('DOMContentLoaded', function() {
            const statCards = document.querySelectorAll('.stat-card');
            
            statCards.forEach((card, index) => {
                card.style.opacity = '0';
                card.style.transform = 'translateY(20px)';
                card.style.transition = 'all 0.6s ease';
                
                setTimeout(() => {
                    card.style.opacity = '1';
                    card.style.transform = 'translateY(0)';
                }, index * 100);
            });
        });
    </script>
</body>
</html>