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

// Gestion des actions
$action = $_GET['action'] ?? 'home';
$message = '';
$messageType = '';

// Traitement de la connexion
if($_POST && $action === 'login') {
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';
    
    if($dbConnected) {        try {
            $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch();
              if($user && password_verify($password, $user['password_hash'])) {
                $_SESSION['user_id'] = $user['user_id'];
                $_SESSION['first_name'] = $user['first_name'];
                $_SESSION['last_name'] = $user['last_name'];
                $_SESSION['email'] = $user['email'];
                $_SESSION['role'] = $user['role'];
                $message = "Connexion réussie! Bienvenue {$user['first_name']}.";
                $messageType = 'success';
            } else {
                $message = "Identifiants incorrects.";
                $messageType = 'error';
            }
        } catch(Exception $e) {
            $message = "Erreur de connexion.";
            $messageType = 'error';
        }
    }
}

// Traitement de l'inscription
if($_POST && $action === 'register') {
    $firstName = trim($_POST['first_name'] ?? '');
    $lastName = trim($_POST['last_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    
    // Validation renforcée
    $errors = [];
    
    if(empty($firstName) || strlen($firstName) < 2) {
        $errors[] = "Le prénom doit contenir au moins 2 caractères";
    }
    if(empty($lastName) || strlen($lastName) < 2) {
        $errors[] = "Le nom doit contenir au moins 2 caractères";
    }
    if(empty($email)) {
        $errors[] = "L'email est requis";
    } elseif(!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Format d'email invalide";
    }
    if(empty($phone) || strlen($phone) < 10) {
        $errors[] = "Le numéro de téléphone doit contenir au moins 10 caractères";
    }
    if(empty($password)) {
        $errors[] = "Le mot de passe est requis";
    } elseif(strlen($password) < 6) {
        $errors[] = "Le mot de passe doit faire au moins 6 caractères";
    }
    if($password !== $confirmPassword) {
        $errors[] = "Les mots de passe ne correspondent pas";
    }
    
    // Si pas d'erreurs de validation et DB connectée
    if(empty($errors) && $dbConnected) {
        try {
            // Vérifier si l'email existe déjà
            $checkEmailQuery = "SELECT user_id FROM users WHERE email = ?";
            $stmt = $pdo->prepare($checkEmailQuery);
            $stmt->execute([$email]);
            
            if($stmt->fetch()) {
                $message = "Cette adresse email est déjà utilisée.";
                $messageType = 'error';            } else {                // Créer l'utilisateur
                $passwordHash = password_hash($password, PASSWORD_DEFAULT);
                
                $insertQuery = "INSERT INTO users (first_name, last_name, email, phone, password_hash, role, created_at) VALUES (?, ?, ?, ?, ?, 'customer', NOW())";
                
                $stmt = $pdo->prepare($insertQuery);
                $result = $stmt->execute([
                    $firstName, 
                    $lastName, 
                    $email, 
                    $phone, 
                    $passwordHash
                ]);
                
                if($result) {
                    $message = "Compte créé avec succès! Vous pouvez maintenant vous connecter.";
                    $messageType = 'success';
                    $action = 'home'; // Rediriger vers l'accueil
                } else {
                    $message = "Erreur lors de l'insertion en base de données.";
                    $messageType = 'error';
                }
            }        } catch(PDOException $e) {
            // Log l'erreur détaillée pour le debug
            error_log("Erreur inscription PDO: " . $e->getMessage());
            error_log("Code erreur PDO: " . $e->getCode());
            
            // Message utilisateur selon le type d'erreur
            if(strpos($e->getMessage(), 'Duplicate entry') !== false) {
                $message = "Cette adresse email est déjà utilisée.";
            } elseif(strpos($e->getMessage(), 'cannot be null') !== false) {
                $message = "Tous les champs obligatoires doivent être remplis.";
            } elseif(strpos($e->getMessage(), 'Data too long') !== false) {
                $message = "Un des champs contient trop de caractères.";
            } elseif(strpos($e->getMessage(), "doesn't exist") !== false) {
                $message = "Erreur de structure de base de données. Contactez l'administrateur.";
            } else {
                $message = "Erreur technique lors de la création du compte: " . $e->getMessage();
            }
            $messageType = 'error';
        } catch(Exception $e) {
            // Log l'erreur
            error_log("Erreur générale inscription: " . $e->getMessage());
            $message = "Une erreur inattendue s'est produite. Veuillez réessayer.";
            $messageType = 'error';
        }
    } else if(!empty($errors)) {
        $message = implode(', ', $errors);
        $messageType = 'error';
    } else if(!$dbConnected) {
        $message = "Erreur de connexion à la base de données.";
        $messageType = 'error';
    }
}

if($action === 'logout') {
    session_destroy();
    header('Location: index.php');
    exit;
}

// Juste après la section d'authentification, ajouter cette vérification du rôle admin
if (isset($_SESSION['user_id']) && isset($_SESSION['role'])) {
    // Vérifier si le rôle en session correspond au rôle en base
    try {
        $stmt = $pdo->prepare("SELECT role FROM users WHERE user_id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $userRole = $stmt->fetchColumn();
        
        // Synchroniser le rôle en session avec celui en base
        if ($userRole && $userRole !== $_SESSION['role']) {
            $_SESSION['role'] = $userRole;
        }
    } catch (Exception $e) {
        // Log silencieux en cas d'erreur
        error_log("Erreur vérification rôle: " . $e->getMessage());
    }
}

// Inclure le middleware pour les vérifications
require_once '../app/middlewares/AdminMiddleware.php';
use App\Middlewares\AdminMiddleware;

// Fonction simple pour vérifier si l'utilisateur est admin (compatibilité)
function isAdmin() {
    return AdminMiddleware::isAdmin();
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ParkFinder - Système de Gestion de Parking</title>    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Space+Grotesk:wght@400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Styles CSS -->
    <link rel="stylesheet" href="assets/css/index.css">
</head>
<body>
    <!-- Header -->
    <header class="header">
        <div class="container">
            <nav class="navbar">
                <a href="index.php" class="logo">
                    <div class="logo-icon">P</div>
                    <span class="logo-text">ParkFinder</span>
                </a>                <div class="nav-links">
                    <?php if (isAdmin()): ?>
                        <!-- Navigation simplifiée pour les admins -->
                        <a href="admin-dashboard.php">Tableau de bord</a>
                        <a href="admin-users.php">Utilisateurs</a>
                        <a href="admin-spots.php">Places</a>
                        <a href="admin-reservations.php">Réservations</a>
                        <a href="admin-reports.php">Rapports</a>
                    <?php else: ?>
                        <!-- Navigation normale pour les utilisateurs -->
                        <a href="#features">Services</a>
                        <a href="apropos.php">À propos</a>
                    <?php endif; ?>
                      <?php if (isset($_SESSION['user_id'])): ?>
                        <?php if (!isAdmin()): ?>
                            <a href="my-reservations.php">Mes réservations</a>
                        <?php endif; ?>
                        <a href="?action=logout">Déconnexion</a>
                    <?php else: ?>
                        <a href="#login">Connexion</a>
                    <?php endif; ?>
                </div>
            </nav>
        </div>
    </header>

    <?php if($action === 'register'): ?>
        <!-- Registration Section -->
        <section style="padding: var(--space-16) 0; min-height: 80vh;">
            <div class="container">
                <div class="registration-section">
                    <a href="index.php" class="back-link">
                        ← Retour à l'accueil
                    </a>
                    
                    <h2 class="login-title">Créer un compte</h2>
                    
                    <?php if($message): ?>
                        <div class="message <?= $messageType ?>">
                            <?= htmlspecialchars($message) ?>
                        </div>
                    <?php endif; ?>
                    
                    <?php if(!$dbConnected): ?>
                        <div class="message error">
                            <strong>Erreur de base de données</strong><br>
                            Impossible de se connecter à la base de données. Vérifiez que:
                            <ul style="text-align: left; margin: 10px 0;">
                                <li>MySQL est démarré</li>
                                <li>La base 'parking_management_system' existe</li>
                                <li><a href="create-db.php" style="color: #DC2626;">Créer la base automatiquement</a></li>
                            </ul>
                        </div>
                    <?php endif; ?>
                    
                    <form method="post" action="?action=register" <?= !$dbConnected ? 'style="opacity: 0.5; pointer-events: none;"' : '' ?>>
                        <div class="form-row">
                            <div class="form-group">
                                <label for="first_name">Prénom *</label>
                                <input type="text" 
                                       id="first_name" 
                                       name="first_name" 
                                       value="<?= htmlspecialchars($_POST['first_name'] ?? '') ?>" 
                                       placeholder="Jean"
                                       minlength="2"
                                       maxlength="50"
                                       required>
                            </div>
                            <div class="form-group">
                                <label for="last_name">Nom *</label>
                                <input type="text" 
                                       id="last_name" 
                                       name="last_name" 
                                       value="<?= htmlspecialchars($_POST['last_name'] ?? '') ?>" 
                                       placeholder="Dupont"
                                       minlength="2"
                                       maxlength="50"
                                       required>
                            </div>
                        </div>
                        
                        <div class="form-group full-width">
                            <label for="email">Adresse e-mail *</label>
                            <input type="email" 
                                   id="email" 
                                   name="email" 
                                   value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" 
                                   placeholder="jean.dupont@example.com"
                                   maxlength="100"
                                   required>
                        </div>
                        
                        <div class="form-group full-width">
                            <label for="phone">Téléphone *</label>
                            <input type="tel" 
                                   id="phone" 
                                   name="phone" 
                                   value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>" 
                                   placeholder="+33 1 23 45 67 89"
                                   maxlength="20"
                                   required>
                        </div>
                        
                        <div class="form-group full-width">
                            <label for="password">Mot de passe *</label>
                            <input type="password" 
                                   id="password" 
                                   name="password" 
                                   minlength="6"
                                   maxlength="255"
                                   required>
                            <div class="password-requirements">
                                <strong>Exigences du mot de passe :</strong>
                                <ul>
                                    <li>Au moins 6 caractères</li>
                                    <li>Combinaison de lettres et chiffres recommandée</li>
                                </ul>
                            </div>
                        </div>
                        
                        <div class="form-group full-width">
                            <label for="confirm_password">Confirmer le mot de passe *</label>
                            <input type="password" 
                                   id="confirm_password" 
                                   name="confirm_password" 
                                   minlength="6"
                                   maxlength="255"
                                   required>
                        </div>
                        
                        <button type="submit" class="btn" style="width: 100%; margin-top: var(--space-4);" <?= !$dbConnected ? 'disabled' : '' ?>>
                            <?= $dbConnected ? 'Créer mon compte' : 'Base de données non disponible' ?>
                        </button>
                    </form>
                    
                    <div style="text-align: center; margin-top: var(--space-6); padding-top: var(--space-4); border-top: 1px solid var(--gray-300);">
                        <p style="color: var(--gray-600); margin: 0;">
                            Déjà un compte ? 
                            <a href="#login" style="color: var(--primary-green); text-decoration: none; font-weight: 500;" onclick="scrollToLogin()">
                                Se connecter
                            </a>
                        </p>
                    </div>
                </div>
            </div>
        </section>
    <?php else: ?>
        <!-- Hero Section -->
        <section class="hero">
            <div class="container">
                <h1>Gestion de Parking Intelligente</h1>
                <p>Trouvez, réservez et payez votre place de parking en quelques clics avec notre système moderne et sécurisé</p>
                
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
                
                <?php if(isset($_SESSION['user_id'])): ?>                    <div style="margin-top: var(--space-8); display: flex; gap: var(--space-4); justify-content: center; flex-wrap: wrap;">
                        <?php if (isAdmin()): ?>
                            <a href="admin-dashboard.php" class="btn">
                                👑 Tableau de bord Admin
                            </a>
                            <a href="admin-reports.php" class="btn btn-secondary">Rapports détaillés</a>
                        <?php else: ?>
                            <a href="my-reservations.php" class="btn">
                                Mes réservations
                            </a>
                            <a href="reservation.php" class="btn btn-secondary">Réserver une place</a>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <div style="margin-top: var(--space-8); display: flex; gap: var(--space-4); justify-content: center; flex-wrap: wrap;">
                        <a href="#login" class="btn">Commencer maintenant</a>
                        <a href="#features" class="btn btn-secondary">En savoir plus</a>
                    </div>
                <?php endif; ?>
            </div>
        </section>

        <!-- Login Section -->
        <?php if(!isset($_SESSION['user_id'])): ?>
        <section id="login" style="padding: var(--space-16) 0;">
            <div class="container">
                <div class="login-section">
                    <h2 class="login-title">Connexion</h2>
                    
                    <?php if($message && $action !== 'register'): ?>
                        <div class="message <?= $messageType ?>">
                            <?= htmlspecialchars($message) ?>
                        </div>
                    <?php endif; ?>
                    
                    <form method="post" action="?action=login">
                        <div class="form-group">
                            <label for="email">Adresse e-mail</label>
                            <input type="email" id="email" name="email" required>
                        </div>
                        <div class="form-group">
                            <label for="password">Mot de passe</label>
                            <input type="password" id="password" name="password" required>
                        </div>
                        <button type="submit" class="btn" style="width: 100%;">Se connecter</button>
                    </form>
                      <div style="text-align: center; margin-top: var(--space-6); padding-top: var(--space-4); border-top: 1px solid var(--gray-300);">
                        <p style="color: var(--gray-600); margin: 0;">
                            Pas encore de compte ? 
                            <a href="?action=register" style="color: var(--primary-green); text-decoration: none; font-weight: 500;">
                                Créer un compte
                            </a>
                        </p>
                    </div>
                </div>
            </div>
        </section>
        <?php endif; ?>        <!-- Section Services - masquée pour les admins -->
        <?php if (!isAdmin()): ?>
        <section id="features" class="features">
            <div class="container">
                <h2 class="section-title">Nos Services</h2>
                <p class="section-subtitle">
                    Une solution complète et moderne pour tous vos besoins de stationnement urbain
                </p>
                
                <div class="features-grid">
                    <div class="feature-card">
                        <div class="feature-icon">🅿️</div>
                        <h3 class="feature-title">Réservation en temps réel</h3>
                        <p class="feature-description">Consultez la disponibilité en temps réel et réservez instantanément votre place de parking avec confirmation immédiate.</p>
                    </div>
                    
                    <div class="feature-card">
                        <div class="feature-icon">💳</div>
                        <h3 class="feature-title">Paiement sécurisé</h3>
                        <p class="feature-description">Payez en ligne de manière sécurisée avec PayPal ou carte bancaire grâce à notre système de paiement chiffré.</p>
                    </div>
                    
                    <div class="feature-card">
                        <div class="feature-icon">📱</div>
                        <h3 class="feature-title">Interface moderne</h3>
                        <p class="feature-description">Application web responsive qui s'adapte parfaitement à tous vos appareils : mobile, tablette et desktop.</p>
                    </div>
                    
                    <div class="feature-card">
                        <div class="feature-icon">🔔</div>
                        <h3 class="feature-title">Notifications intelligentes</h3>
                        <p class="feature-description">Recevez des rappels et alertes personnalisées pour vos réservations et disponibilités de places.</p>
                    </div>
                    
                    <div class="feature-card">
                        <div class="feature-icon">📊</div>
                        <h3 class="feature-title">Tableau de bord</h3>
                        <p class="feature-description">Gérez toutes vos réservations et consultez vos statistiques depuis un tableau de bord intuitif et complet.</p>
                    </div>
                    
                    <div class="feature-card">
                        <div class="feature-icon">🔒</div>
                        <h3 class="feature-title">Sécurité maximale</h3>
                        <p class="feature-description">Vos données sont protégées avec les dernières technologies de sécurité et chiffrement de bout en bout.</p>
                    </div>
                </div>
            </div>
        </section>
        <?php endif; ?>
    <?php endif; ?>

    <!-- Footer -->
    <footer class="footer">
        <div class="container">
            <p>&copy; 2024 ParkFinder. Tous droits réservés.</p>
            <p>Système de gestion de parking intelligent et moderne</p>
            <?php if(!$dbConnected): ?>
                <p style="color: #F87171; margin-top: var(--space-4);">
                    ⚠️ Base de données non connectée - <a href="create-db.php">Créer la base de données</a>
                </p>
            <?php endif; ?>
        </div>
    </footer>    
    <!-- Scripts JavaScript -->
    <script src="assets/js/index.js"></script>
</body>
</html>