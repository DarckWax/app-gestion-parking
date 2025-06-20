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
                $messageType = 'error';
            } else {
                // Créer l'utilisateur
                $passwordHash = password_hash($password, PASSWORD_DEFAULT);
                
                $insertQuery = "INSERT INTO users (first_name, last_name, email, phone, password_hash, role, status, email_verified, phone_verified, created_at) VALUES (?, ?, ?, ?, ?, 'user', 'active', FALSE, FALSE, NOW())";
                
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
                    
                    // Optionnel : connecter automatiquement l'utilisateur
                    /*
                    $newUserId = $pdo->lastInsertId();
                    $_SESSION['user_id'] = $newUserId;
                    $_SESSION['user_name'] = $firstName . ' ' . $lastName;
                    $_SESSION['user_role'] = 'user';
                    */
                } else {
                    $message = "Erreur lors de l'insertion en base de données.";
                    $messageType = 'error';
                }
            }
        } catch(PDOException $e) {
            // Log l'erreur détaillée pour le debug
            error_log("Erreur inscription: " . $e->getMessage());
            
            // Message utilisateur simplifié
            if(strpos($e->getMessage(), 'Duplicate entry') !== false) {
                $message = "Cette adresse email est déjà utilisée.";
            } else {
                $message = "Erreur technique lors de la création du compte. Veuillez réessayer.";
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

// Inclure le middleware pour les vérifications
require_once '../app/middlewares/AdminMiddleware.php';
use App\Middlewares\AdminMiddleware;
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ParkFinder - Système de Gestion de Parking</title>
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
            /* Palette Verte et Noire */
            --primary-green: #10B981;
            --dark-green: #059669;
            --light-green: #34D399;
            --accent-green: #6EE7B7;
            --pale-green: #ECFDF5;
            
            --primary-black: #111827;
            --dark-black: #000000;
            --gray-900: #1F2937;
            --gray-800: #374151;
            --gray-700: #4B5563;
            --gray-600: #6B7280;
            --gray-300: #D1D5DB;
            --gray-100: #F3F4F6;
            --white: #FFFFFF;
            
            /* Typographie */
            --font-primary: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            --font-display: 'Space Grotesk', -apple-system, BlinkMacSystemFont, sans-serif;
            
            /* Tailles */
            --text-xs: 0.75rem;
            --text-sm: 0.875rem;
            --text-base: 1rem;
            --text-lg: 1.125rem;
            --text-xl: 1.25rem;
            --text-2xl: 1.5rem;
            --text-3xl: 1.875rem;
            --text-4xl: 2.25rem;
            --text-5xl: 3rem;
            --text-6xl: 3.75rem;
            
            /* Espacement */
            --space-1: 0.25rem;
            --space-2: 0.5rem;
            --space-3: 0.75rem;
            --space-4: 1rem;
            --space-5: 1.25rem;
            --space-6: 1.5rem;
            --space-8: 2rem;
            --space-10: 2.5rem;
            --space-12: 3rem;
            --space-16: 4rem;
            --space-20: 5rem;
            
            /* Radius */
            --radius-sm: 0.375rem;
            --radius-md: 0.5rem;
            --radius-lg: 0.75rem;
            --radius-xl: 1rem;
            --radius-2xl: 1.5rem;
            
            /* Shadows */
            --shadow-sm: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
            --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
            --shadow-xl: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
            --shadow-2xl: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
        }
        
        body {
            font-family: var(--font-primary);
            background: linear-gradient(135deg, var(--primary-black) 0%, var(--gray-900) 50%, var(--dark-green) 100%);
            min-height: 100vh;
            color: var(--white);
            font-weight: 400;
            line-height: 1.6;
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
        }
        
        .header {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-bottom: 1px solid var(--gray-300);
            padding: var(--space-4) 0;
            box-shadow: var(--shadow-lg);
            position: sticky;
            top: 0;
            z-index: 50;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 var(--space-5);
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
            font-size: var(--text-2xl);
            font-weight: 700;
            color: var(--primary-black);
            text-decoration: none;
            transition: all 0.3s ease;
            position: relative;
        }
        
        .logo-icon {
            width: 32px;
            height: 32px;
            margin-right: var(--space-3);
            background: linear-gradient(135deg, var(--primary-green), var(--dark-green));
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--white);
            font-size: 18px;
            font-weight: 800;
            box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
            position: relative;
            overflow: hidden;
        }
        
        .logo-icon::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: linear-gradient(45deg, transparent, rgba(255,255,255,0.1), transparent);
            transform: rotate(45deg);
            transition: all 0.5s ease;
            opacity: 0;
        }
        
        .logo:hover .logo-icon::before {
            opacity: 1;
            animation: shine 0.8s ease-in-out;
        }
        
        .logo-text {
            font-weight: 800;
            background: linear-gradient(135deg, var(--primary-black), var(--gray-800));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        
        .logo:hover {
            transform: translateY(-1px);
        }
        
        .logo:hover .logo-icon {
            transform: scale(1.05);
            box-shadow: 0 6px 20px rgba(16, 185, 129, 0.4);
        }
        
        @keyframes shine {
            0% { transform: translateX(-100%) translateY(-100%) rotate(45deg); }
            100% { transform: translateX(100%) translateY(100%) rotate(45deg); }
        }
        
        .nav-links {
            display: flex;
            gap: var(--space-6);
            align-items: center;
        }
        
        .nav-links a {
            color: var(--gray-700);
            text-decoration: none;
            font-weight: 500;
            font-size: var(--text-sm);
            padding: var(--space-2) var(--space-4);
            border-radius: var(--radius-lg);
            transition: all 0.3s ease;
            position: relative;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .nav-links a::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 50%;
            width: 0;
            height: 2px;
            background: var(--primary-green);
            transition: all 0.3s ease;
            transform: translateX(-50%);
        }
        
        .nav-links a:hover {
            color: var(--primary-green);
            background: var(--pale-green);
            transform: translateY(-1px);
        }
        
        .nav-links a:hover::after {
            width: 80%;
        }
        
        .hero {
            padding: var(--space-20) 0;
            text-align: center;
            position: relative;
            overflow: hidden;
        }
        
        .hero::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1000 1000"><defs><pattern id="grid" width="50" height="50" patternUnits="userSpaceOnUse"><path d="M 50 0 L 0 0 0 50" fill="none" stroke="%23059669" stroke-width="0.5" opacity="0.1"/></pattern></defs><rect width="100%" height="100%" fill="url(%23grid)"/></svg>');
            z-index: -1;
        }
        
        .hero h1 {
            font-family: var(--font-display);
            font-size: clamp(var(--text-4xl), 5vw, var(--text-6xl));
            font-weight: 800;
            margin-bottom: var(--space-6);
            background: linear-gradient(135deg, var(--white) 0%, var(--accent-green) 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            text-shadow: 0 4px 8px rgba(0, 0, 0, 0.3);
            line-height: 1.1;
        }
        
        .hero p {
            font-size: var(--text-xl);
            margin-bottom: var(--space-10);
            opacity: 0.9;
            max-width: 600px;
            margin-left: auto;
            margin-right: auto;
            font-weight: 300;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: var(--space-8);
            margin: var(--space-16) auto;
            max-width: 900px;
        }
        
        .stat-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            padding: var(--space-8);
            border-radius: var(--radius-2xl);
            text-align: center;
            box-shadow: var(--shadow-2xl);
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
            box-shadow: var(--shadow-2xl), 0 0 40px rgba(16, 185, 129, 0.3);
            border-color: var(--primary-green);
        }
        
        .stat-number {
            font-family: var(--font-display);
            font-size: var(--text-5xl);
            font-weight: 800;
            color: var(--primary-green);
            margin-bottom: var(--space-3);
            line-height: 1;
        }
        
        .stat-label {
            font-size: var(--text-lg);
            font-weight: 600;
            color: var(--gray-800);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .features {
            background: var(--white);
            color: var(--primary-black);
            padding: var(--space-20) 0;
            position: relative;
        }
        
        .features::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 100px;
            background: linear-gradient(180deg, transparent 0%, var(--white) 100%);
            z-index: 1;
        }
        
        .section-title {
            text-align: center;
            font-family: var(--font-display);
            font-size: var(--text-5xl);
            font-weight: 800;
            margin-bottom: var(--space-4);
            color: var(--primary-black);
            position: relative;
            z-index: 2;
        }
        
        .section-subtitle {
            text-align: center;
            font-size: var(--text-xl);
            color: var(--gray-600);
            margin-bottom: var(--space-16);
            font-weight: 300;
            max-width: 600px;
            margin-left: auto;
            margin-right: auto;
        }
        
        .features-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
            gap: var(--space-8);
            margin-top: var(--space-16);
        }
        
        .feature-card {
            background: var(--gray-100);
            padding: var(--space-8);
            border-radius: var(--radius-xl);
            text-align: center;
            border: 2px solid transparent;
            transition: all 0.4s ease;
            position: relative;
            overflow: hidden;
        }
        
        .feature-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(16, 185, 129, 0.1), transparent);
            transition: left 0.5s ease;
        }
        
        .feature-card:hover {
            border-color: var(--primary-green);
            transform: translateY(-8px);
            box-shadow: var(--shadow-xl);
            background: var(--pale-green);
        }
        
        .feature-card:hover::before {
            left: 100%;
        }
        
        .feature-icon {
            font-size: var(--text-5xl);
            margin-bottom: var(--space-4);
            display: block;
            filter: grayscale(100%);
            transition: filter 0.3s ease;
        }
        
        .feature-card:hover .feature-icon {
            filter: grayscale(0%);
        }
        
        .feature-title {
            font-family: var(--font-display);
            font-size: var(--text-xl);
            font-weight: 700;
            margin-bottom: var(--space-4);
            color: var(--primary-black);
        }
        
        .feature-description {
            color: var(--gray-700);
            font-weight: 400;
            line-height: 1.7;
        }
        
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: var(--space-4) var(--space-8);
            background: var(--primary-green);
            color: var(--white);
            text-decoration: none;
            border-radius: var(--radius-lg);
            font-weight: 600;
            font-size: var(--text-base);
            transition: all 0.3s ease;
            border: 2px solid var(--primary-green);
            cursor: pointer;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            position: relative;
            overflow: hidden;
        }
        
        .btn::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
            transition: left 0.5s ease;
        }
        
        .btn:hover {
            background: var(--dark-green);
            border-color: var(--dark-green);
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
        }
        
        .btn:hover::before {
            left: 100%;
        }
        
        .btn-secondary {
            background: transparent;
            color: var(--white);
            border-color: var(--white);
        }
        
        .btn-secondary:hover {
            background: var(--white);
            color: var(--primary-green);
            border-color: var(--white);
        }
        
        .login-section {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            padding: var(--space-10);
            border-radius: var(--radius-2xl);
            max-width: 450px;
            margin: var(--space-8) auto;
            box-shadow: var(--shadow-2xl);
            border: 1px solid rgba(16, 185, 129, 0.2);
        }
        
        .login-title {
            text-align: center;
            font-family: var(--font-display);
            font-size: var(--text-3xl);
            font-weight: 700;
            margin-bottom: var(--space-8);
            color: var(--primary-black);
        }
        
        .form-group {
            margin-bottom: var(--space-6);
        }
        
        .form-group label {
            display: block;
            margin-bottom: var(--space-2);
            font-weight: 600;
            color: var(--gray-800);
            font-size: var(--text-sm);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .form-group input {
            width: 100%;
            padding: var(--space-4);
            border: 2px solid var(--gray-300);
            border-radius: var(--radius-lg);
            font-size: var(--text-base);
            transition: all 0.3s ease;
            background: var(--white);
            font-family: var(--font-primary);
        }
        
        .form-group input:focus {
            outline: none;
            border-color: var(--primary-green);
            box-shadow: 0 0 0 4px rgba(16, 185, 129, 0.1);
            transform: translateY(-1px);
        }
        
        .message {
            padding: var(--space-4);
            border-radius: var(--radius-lg);
            margin-bottom: var(--space-4);
            text-align: center;
            font-weight: 500;
            border: 1px solid;
        }
        
        .message.success {
            background: var(--pale-green);
            color: var(--dark-green);
            border-color: var(--primary-green);
        }
        
        .message.error {
            background: #FEF2F2;
            color: #DC2626;
            border-color: #F87171;
        }
        
        .test-credentials {
            text-align: center;
            margin-top: var(--space-6);
            padding: var(--space-4);
            background: var(--gray-100);
            border-radius: var(--radius-lg);
            font-size: var(--text-sm);
            color: var(--gray-700);
        }
        
        .footer {
            background: var(--primary-black);
            color: var(--white);
            text-align: center;
            padding: var(--space-16) 0;
            border-top: 1px solid var(--gray-800);
        }
        
        .footer p {
            margin-bottom: var(--space-2);
            opacity: 0.8;
        }
        
        .footer a {
            color: var(--primary-green);
            text-decoration: none;
        }
        
        .footer a:hover {
            color: var(--accent-green);
            text-decoration: underline;
        }
        
        /* Profile Dropdown Styles */
        .profile-container {
            position: relative;
            display: inline-block;
        }
        
        .profile-button {
            display: flex;
            align-items: center;
            gap: var(--space-2);
            padding: var(--space-3) var(--space-4);
            background: var(--white);
            border: 2px solid var(--gray-300);
            border-radius: var(--radius-lg);
            color: var(--gray-700);
            text-decoration: none;
            font-weight: 500;
            font-size: var(--text-sm);
            transition: all 0.3s ease;
            cursor: pointer;
            position: relative;
            min-width: 120px;
            justify-content: center;
        }
        
        .profile-button:hover {
            border-color: var(--primary-green);
            background: var(--pale-green);
            color: var(--primary-green);
            transform: translateY(-1px);
            box-shadow: var(--shadow-md);
        }
        
        .profile-icon {
            width: 20px;
            height: 20px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary-green), var(--accent-green));
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--white);
            font-size: var(--text-xs);
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        .profile-button:hover .profile-icon {
            transform: scale(1.1);
            box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.2);
        }
        
        .dropdown-arrow {
            width: 0;
            height: 0;
            border-left: 4px solid transparent;
            border-right: 4px solid transparent;
            border-top: 4px solid currentColor;
            transition: transform 0.3s ease;
        }
        
        .profile-container.open .dropdown-arrow {
            transform: rotate(180deg);
        }
        
        .profile-dropdown {
            position: absolute;
            top: calc(100% + var(--space-2));
            right: 0;
            background: var(--white);
            border: 1px solid var(--gray-300);
            border-radius: var(--radius-xl);
            box-shadow: var(--shadow-xl);
            min-width: 280px;
            opacity: 0;
            visibility: hidden;
            transform: translateY(-10px);
            transition: all 0.3s ease;
            z-index: 1000;
            overflow: hidden;
        }
        
        .profile-container.open .profile-dropdown {
            opacity: 1;
            visibility: visible;
            transform: translateY(0);
        }
        
        .dropdown-header {
            padding: var(--space-5) var(--space-6);
            background: linear-gradient(135deg, var(--primary-green), var(--dark-green));
            color: var(--white);
            text-align: center;
        }
        
        .dropdown-header h3 {
            font-family: var(--font-display);
            font-size: var(--text-lg);
            font-weight: 600;
            margin-bottom: var(--space-1);
        }
        
        .dropdown-header p {
            font-size: var(--text-sm);
            opacity: 0.9;
            font-weight: 300;
        }
        
        .dropdown-content {
            padding: var(--space-4);
        }
        
        .dropdown-section {
            margin-bottom: var(--space-4);
        }
        
        .dropdown-section:last-child {
            margin-bottom: 0;
        }
        
        .dropdown-section-title {
            font-size: var(--text-xs);
            font-weight: 600;
            color: var(--gray-600);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: var(--space-3);
            padding-left: var(--space-2);
        }
        
        .dropdown-item {
            display: flex;
            align-items: center;
            gap: var(--space-3);
            padding: var(--space-3) var(--space-4);
            color: var(--gray-700);
            text-decoration: none;
            border-radius: var(--radius-lg);
            transition: all 0.3s ease;
            font-weight: 500;
            font-size: var(--text-sm);
        }
        
        .dropdown-item:hover {
            background: var(--pale-green);
            color: var(--primary-green);
            transform: translateX(4px);
        }
        
        .dropdown-item-icon {
            width: 18px;
            height: 18px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: var(--text-sm);
            opacity: 0.7;
        }
        
        .dropdown-divider {
            height: 1px;
            background: var(--gray-300);
            margin: var(--space-3) 0;
        }
        
        /* User info when logged in */
        .user-info {
            display: flex;
            align-items: center;
            gap: var(--space-3);
            padding: var(--space-4);
            background: var(--gray-100);
            border-radius: var(--radius-lg);
            margin-bottom: var(--space-4);
        }
        
        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary-green), var(--accent-green));
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--white);
            font-weight: 600;
            font-size: var(--text-lg);
        }
        
        .user-details h4 {
            font-weight: 600;
            color: var(--primary-black);
            margin-bottom: var(--space-1);
            font-size: var(--text-sm);
        }
        
        .user-details p {
            font-size: var(--text-xs);
            color: var(--gray-600);
            margin: 0;
        }
        
        /* Responsive adjustments */
        @media (max-width: 768px) {
            .profile-dropdown {
                right: -20px;
                min-width: 250px;
            }
            
            .profile-button {
                min-width: 100px;
                padding: var(--space-2) var(--space-3);
            }
        }
        
        /* Overlay for mobile */
        .dropdown-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.3);
            opacity: 0;
            visibility: hidden;
            transition: all 0.3s ease;
            z-index: 999;
        }
        
        .profile-container.open .dropdown-overlay {
            opacity: 1;
            visibility: visible;
        }
        
        @media (max-width: 768px) {
            .dropdown-overlay {
                display: block;
            }
        }
        
        /* Registration Form Styles */
        .registration-section {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            padding: var(--space-10);
            border-radius: var(--radius-2xl);
            max-width: 500px;
            margin: var(--space-8) auto;
            box-shadow: var(--shadow-2xl);
            border: 1px solid rgba(16, 185, 129, 0.2);
        }
        
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: var(--space-4);
        }
        
        .form-group.full-width {
            grid-column: 1 / -1;
        }
        
        .back-link {
            display: inline-flex;
            align-items: center;
            gap: var(--space-2);
            color: var(--primary-green);
            text-decoration: none;
            font-weight: 500;
            margin-bottom: var(--space-6);
            transition: all 0.3s ease;
        }
        
        .back-link:hover {
            color: var(--dark-green);
            transform: translateX(-2px);
        }
        
        .password-requirements {
            font-size: var(--text-xs);
            color: var(--gray-600);
            margin-top: var(--space-2);
            padding: var(--space-3);
            background: var(--gray-100);
            border-radius: var(--radius-md);
        }
        
        .password-requirements ul {
            margin: 0;
            padding-left: var(--space-4);
        }
        
        /* Animation et transitions */
        .fade-in {
            opacity: 0;
            transform: translateY(10px);
            transition: opacity 0.6s ease, transform 0.6s ease;
        }
        
        .fade-in.visible {
            opacity: 1;
            transform: translateY(0);
        }
        
        @keyframes typing {
            from { width: 0 }
            to { width: 100% }
        }

        @keyframes blink-caret {
            from, to { border-color: transparent }
            50% { border-color: var(--accent-green) }
        }
    </style>
</head>
<body>
    <!-- Header -->
    <header class="header">
        <div class="container">
            <nav class="navbar">
                <a href="index.php" class="logo">
                    <div class="logo-icon">P</div>
                    <span class="logo-text">ParkFinder</span>
                </a>
                
                <div class="nav-links">
                    <a href="#features">Services</a>
                    <a href="#about">À propos</a>
                    
                    <!-- Profile Dropdown -->
                    <div class="profile-container" id="profileContainer">
                        <div class="dropdown-overlay" onclick="closeProfileDropdown()"></div>
                        
                        <button class="profile-button" onclick="toggleProfileDropdown()">
                            <?php if(isset($_SESSION['user_id'])): ?>
                                <div class="profile-icon">
                                    <?= strtoupper(substr($_SESSION['user_name'], 0, 1)) ?>
                                </div>
                                <span><?= explode(' ', $_SESSION['user_name'])[0] ?></span>
                            <?php else: ?>
                                <div class="profile-icon">👤</div>
                                <span>Profil</span>
                            <?php endif; ?>
                            <div class="dropdown-arrow"></div>
                        </button>
                        
                        <div class="profile-dropdown">
                            <?php if(isset($_SESSION['user_id'])): ?>
                                <!-- User logged in -->
                                <div class="dropdown-header">
                                    <h3>Bienvenue !</h3>
                                    <p><?= htmlspecialchars($_SESSION['user_name']) ?></p>
                                </div>
                                
                                <div class="dropdown-content">
                                    <div class="user-info">
                                        <div class="user-avatar">
                                            <?= strtoupper(substr($_SESSION['user_name'], 0, 1)) ?>
                                        </div>
                                        <div class="user-details">
                                            <h4><?= htmlspecialchars($_SESSION['user_name']) ?></h4>
                                            <p>Membre <?= ucfirst($_SESSION['user_role'] ?? 'user') ?></p>
                                        </div>
                                    </div>
                                    
                                    <div class="dropdown-section">
                                        <div class="dropdown-section-title">Navigation</div>
                                        
                                        <!-- Dashboard uniquement pour les admins -->
                                        <?php if (AdminMiddleware::isAdmin()): ?>
                                            <a href="dashboard.php" class="dropdown-item">
                                                <div class="dropdown-item-icon">👑</div>
                                                Tableau de bord Admin
                                            </a>
                                        <?php endif; ?>
                                        
                                        <a href="my-reservations.php" class="dropdown-item">
                                            <div class="dropdown-item-icon">🅿️</div>
                                            Mes réservations
                                        </a>
                                        <a href="?action=profile" class="dropdown-item">
                                            <div class="dropdown-item-icon">⚙️</div>
                                            Paramètres
                                        </a>
                                    </div>
                                    
                                    <div class="dropdown-divider"></div>
                                    
                                    <div class="dropdown-section">
                                        <a href="?action=logout" class="dropdown-item" style="color: #DC2626;">
                                            <div class="dropdown-item-icon">🚪</div>
                                            Déconnexion
                                        </a>
                                    </div>
                                </div>
                            <?php else: ?>
                                <!-- User not logged in -->
                                <div class="dropdown-header">
                                    <h3>Mon Compte</h3>
                                    <p>Accédez à votre espace personnel</p>
                                </div>
                                
                                <div class="dropdown-content">
                                    <div class="dropdown-section">
                                        <div class="dropdown-section-title">Connexion</div>
                                        <a href="#login" class="dropdown-item" onclick="closeProfileDropdown()">
                                            <div class="dropdown-item-icon">🔐</div>
                                            Se connecter
                                        </a>
                                        <a href="?action=register" class="dropdown-item" onclick="closeProfileDropdown()">
                                            <div class="dropdown-item-icon">✨</div>
                                            Créer un compte
                                        </a>
                                    </div>
                                    
                                    <div class="dropdown-divider"></div>
                                    
                                    <div class="dropdown-section">
                                        <div class="dropdown-section-title">Découvrir</div>
                                        <a href="#features" class="dropdown-item" onclick="closeProfileDropdown()">
                                            <div class="dropdown-item-icon">🚀</div>
                                            Nos services
                                        </a>
                                        <a href="#about" class="dropdown-item" onclick="closeProfileDropdown()">
                                            <div class="dropdown-item-icon">ℹ️</div>
                                            À propos
                                        </a>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
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
                
                <?php if(isset($_SESSION['user_id'])): ?>
                    <div style="margin-top: var(--space-8); display: flex; gap: var(--space-4); justify-content: center; flex-wrap: wrap;">
                        
                        <!-- Bouton dashboard pour admins uniquement -->
                        <?php if (AdminMiddleware::isAdmin()): ?>
                            <a href="dashboard.php" class="btn">
                                👑 Tableau de bord Admin
                            </a>
                        <?php else: ?>
                            <a href="my-reservations.php" class="btn">
                                Mes réservations
                            </a>
                        <?php endif; ?>
                        
                        <a href="reservation.php" class="btn btn-secondary">Réserver une place</a>
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
        <?php endif; ?>

        <!-- Features Section -->
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

        // Animation des cartes au scroll
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

        // Observer les éléments avec animation
        document.querySelectorAll('.stat-card, .feature-card').forEach(card => {
            card.style.opacity = '0';
            card.style.transform = 'translateY(20px)';
            card.style.transition = 'all 0.6s ease';
            observer.observe(card);
        });

        // Animation de typing pour le titre principal
        const title = document.querySelector('.hero h1');
        if (title) {
            title.style.overflow = 'hidden';
            title.style.borderRight = '3px solid var(--accent-green)';
            title.style.whiteSpace = 'nowrap';
            title.style.animation = 'typing 3s steps(40, end), blink-caret 0.75s step-end infinite';
        }

        // Parallax effet léger sur le hero
        window.addEventListener('scroll', () => {
            const scrolled = window.pageYOffset;
            const hero = document.querySelector('.hero');
            if (hero) {
                hero.style.transform = `translateY(${scrolled * 0.3}px)`;
            }
        });

        // Profile dropdown functionality
        function toggleProfileDropdown() {
            const container = document.getElementById('profileContainer');
            container.classList.toggle('open');
        }
        
        function closeProfileDropdown() {
            const container = document.getElementById('profileContainer');
            container.classList.remove('open');
        }
        
        // Close dropdown when clicking outside
        document.addEventListener('click', function(event) {
            const container = document.getElementById('profileContainer');
            const button = container.querySelector('.profile-button');
            
            if (!container.contains(event.target)) {
                container.classList.remove('open');
            }
        });
        
        // Close dropdown on escape key
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                closeProfileDropdown();
            }
        });
        
        // Prevent dropdown from closing when clicking inside
        document.querySelector('.profile-dropdown').addEventListener('click', function(event) {
            event.stopPropagation();
        });
        
        function scrollToLogin() {
            window.location.href = 'index.php#login';
            setTimeout(() => {
                document.getElementById('login').scrollIntoView({
                    behavior: 'smooth',
                    block: 'start'
                });
            }, 100);
        }
        
        // Validation en temps réel du formulaire
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.querySelector('form[action="?action=register"]');
            if (form) {
                const passwordField = document.getElementById('password');
                const confirmPasswordField = document.getElementById('confirm_password');
                
                function validatePasswords() {
                    const password = passwordField.value;
                    const confirmPassword = confirmPasswordField.value;
                    
                    if (confirmPassword && password !== confirmPassword) {
                        confirmPasswordField.setCustomValidity('Les mots de passe ne correspondent pas');
                    } else {
                        confirmPasswordField.setCustomValidity('');
                    }
                }
                
                passwordField.addEventListener('input', validatePasswords);
                confirmPasswordField.addEventListener('input', validatePasswords);
                
                // Validation avant soumission
                form.addEventListener('submit', function(e) {
                    const firstName = document.getElementById('first_name').value.trim();
                    const lastName = document.getElementById('last_name').value.trim();
                    const email = document.getElementById('email').value.trim();
                    const phone = document.getElementById('phone').value.trim();
                    const password = passwordField.value;
                    const confirmPassword = confirmPasswordField.value;
                    
                    let errors = [];
                    
                    if (firstName.length < 2) errors.push('Le prénom doit contenir au moins 2 caractères');
                    if (lastName.length < 2) errors.push('Le nom doit contenir au moins 2 caractères');
                    if (!email.includes('@')) errors.push('Format d\'email invalide');
                    if (phone.length < 10) errors.push('Le téléphone doit contenir au moins 10 caractères');
                    if (password.length < 6) errors.push('Le mot de passe doit faire au moins 6 caractères');
                    if (password !== confirmPassword) errors.push('Les mots de passe ne correspondent pas');
                    
                    if (errors.length > 0) {
                        e.preventDefault();
                        alert('Erreurs de validation:\n• ' + errors.join('\n• '));
                    }
                });
            }
        });
    </script>
</body>
</html>
