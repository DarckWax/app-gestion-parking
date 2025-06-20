<?php
// Contrôle d'accès administrateur obligatoire
require_once '../app/middlewares/AdminMiddleware.php';
use App\Middlewares\AdminMiddleware;

AdminMiddleware::requireAdmin();

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

try {
    $pdo = new PDO("mysql:host={$config['db_host']};dbname={$config['db_name']}", $config['db_user'], $config['db_pass']);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $dbConnected = true;
} catch(Exception $e) {
    $dbConnected = false;
}

$message = '';
$messageType = '';

// Traitement des actions
if ($_POST && $dbConnected) {
    $action = $_POST['action'] ?? '';
    $userId = $_POST['user_id'] ?? '';
    
    try {
        switch ($action) {
            case 'toggle_status':
                $stmt = $pdo->prepare("UPDATE users SET status = CASE WHEN status = 'active' THEN 'inactive' ELSE 'active' END WHERE user_id = ?");
                $stmt->execute([$userId]);
                $message = "Statut utilisateur modifié avec succès";
                $messageType = 'success';
                break;
                
            case 'change_role':
                $newRole = $_POST['role'] ?? '';
                if (in_array($newRole, ['user', 'admin'])) {
                    $stmt = $pdo->prepare("UPDATE users SET role = ? WHERE user_id = ?");
                    $stmt->execute([$newRole, $userId]);
                    $message = "Rôle utilisateur modifié avec succès";
                    $messageType = 'success';
                }
                break;
                
            case 'delete_user':
                // Vérifier qu'on ne supprime pas le dernier admin
                $stmt = $pdo->query("SELECT COUNT(*) as count FROM users WHERE role = 'admin' AND status = 'active'");
                $adminCount = $stmt->fetch()['count'];
                
                $stmt = $pdo->prepare("SELECT role FROM users WHERE user_id = ?");
                $stmt->execute([$userId]);
                $userRole = $stmt->fetch()['role'] ?? '';
                
                if ($userRole === 'admin' && $adminCount <= 1) {
                    throw new Exception("Impossible de supprimer le dernier administrateur");
                }
                
                $stmt = $pdo->prepare("DELETE FROM users WHERE user_id = ?");
                $stmt->execute([$userId]);
                $message = "Utilisateur supprimé avec succès";
                $messageType = 'success';
                break;
                
            case 'add_user':
                $firstName = trim($_POST['first_name'] ?? '');
                $lastName = trim($_POST['last_name'] ?? '');
                $email = trim($_POST['email'] ?? '');
                $phone = trim($_POST['phone'] ?? '');
                $role = $_POST['role'] ?? 'user';
                $password = $_POST['password'] ?? '';
                
                if (empty($firstName) || empty($lastName) || empty($email) || empty($password)) {
                    throw new Exception("Tous les champs obligatoires doivent être remplis");
                }
                
                // Vérifier si l'email existe déjà
                $stmt = $pdo->prepare("SELECT user_id FROM users WHERE email = ?");
                $stmt->execute([$email]);
                if ($stmt->fetch()) {
                    throw new Exception("Cette adresse email est déjà utilisée");
                }
                
                $passwordHash = password_hash($password, PASSWORD_DEFAULT);
                
                $stmt = $pdo->prepare("INSERT INTO users (first_name, last_name, email, phone, password_hash, role, status, email_verified, phone_verified, created_at) VALUES (?, ?, ?, ?, ?, ?, 'active', TRUE, FALSE, NOW())");
                $stmt->execute([$firstName, $lastName, $email, $phone, $passwordHash, $role]);
                
                $message = "Utilisateur créé avec succès";
                $messageType = 'success';
                break;
        }
    } catch (Exception $e) {
        $message = "Erreur: " . $e->getMessage();
        $messageType = 'error';
    }
}

// Récupération des utilisateurs
$users = [];
$totalUsers = 0;
$activeUsers = 0;
$adminUsers = 0;

if ($dbConnected) {
    try {
        $stmt = $pdo->query("SELECT user_id, first_name, last_name, email, phone, role, status, email_verified, phone_verified, created_at FROM users ORDER BY created_at DESC");
        $users = $stmt->fetchAll();
        
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM users");
        $totalUsers = $stmt->fetch()['count'];
        
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM users WHERE status = 'active'");
        $activeUsers = $stmt->fetch()['count'];
        
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM users WHERE role = 'admin'");
        $adminUsers = $stmt->fetch()['count'];
    } catch (Exception $e) {
        // Garder les valeurs par défaut
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des Utilisateurs - ParkFinder Admin</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Space+Grotesk:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
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
            margin: 0;
        }
        
        /* Header identique au dashboard */
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
            font-size: 2.5rem;
            font-weight: 800;
            margin-bottom: 1rem;
            background: linear-gradient(135deg, var(--white) 0%, var(--accent-green) 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        
        .page-subtitle {
            font-size: 1.125rem;
            opacity: 0.9;
            font-weight: 300;
        }
        
        /* Messages */
        .message {
            padding: 1rem;
            border-radius: 0.75rem;
            margin-bottom: 2rem;
            text-align: center;
            font-weight: 500;
        }
        
        .message.success {
            background: var(--pale-green);
            color: var(--dark-green);
            border: 1px solid var(--primary-green);
        }
        
        .message.error {
            background: #FEF2F2;
            color: #DC2626;
            border: 1px solid #F87171;
        }
        
        /* Statistiques */
        .stats-overview {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
            margin-bottom: 3rem;
        }
        
        .stat-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            padding: 1.5rem;
            border-radius: 1rem;
            text-align: center;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            border-left: 4px solid var(--primary-green);
        }
        
        .stat-number {
            font-family: var(--font-display);
            font-size: 2rem;
            font-weight: 800;
            color: var(--primary-green);
            margin-bottom: 0.5rem;
        }
        
        .stat-label {
            font-weight: 600;
            color: var(--gray-800);
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        /* Section principale */
        .users-section {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-radius: 1.5rem;
            padding: 2rem;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
            margin-bottom: 2rem;
        }
        
        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
            flex-wrap: wrap;
            gap: 1rem;
        }
        
        .section-title {
            font-family: var(--font-display);
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--primary-black);
        }
        
        .btn {
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 0.5rem;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.3s ease;
            font-size: 0.9rem;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, var(--primary-green), var(--dark-green));
            color: white;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(16, 185, 129, 0.4);
        }
        
        .btn-danger {
            background: #dc3545;
            color: white;
        }
        
        .btn-warning {
            background: #ffc107;
            color: var(--gray-800);
        }
        
        .btn-small {
            padding: 0.5rem 0.75rem;
            font-size: 0.8rem;
        }
        
        /* Table des utilisateurs */
        .users-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 1rem;
        }
        
        .users-table th,
        .users-table td {
            padding: 1rem;
            text-align: left;
            border-bottom: 1px solid var(--gray-300);
        }
        
        .users-table th {
            background: var(--gray-100);
            color: var(--gray-800);
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.8rem;
            letter-spacing: 0.5px;
        }
        
        .users-table td {
            color: var(--gray-800);
        }
        
        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary-green), var(--accent-green));
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
            margin-right: 1rem;
        }
        
        .user-info {
            display: flex;
            align-items: center;
        }
        
        .user-details h4 {
            margin: 0;
            color: var(--gray-800);
            font-weight: 600;
        }
        
        .user-details p {
            margin: 0;
            color: var(--gray-600);
            font-size: 0.9rem;
        }
        
        .status-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 1rem;
            font-size: 0.8rem;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .status-active {
            background: var(--pale-green);
            color: var(--dark-green);
        }
        
        .status-inactive {
            background: #FEF2F2;
            color: #DC2626;
        }
        
        .role-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 1rem;
            font-size: 0.8rem;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .role-admin {
            background: linear-gradient(135deg, #dc3545, #c82333);
            color: white;
        }
        
        .role-user {
            background: var(--gray-100);
            color: var(--gray-800);
        }
        
        .actions {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
        }
        
        /* Modal */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }
        
        .modal.show {
            display: flex;
        }
        
        .modal-content {
            background: white;
            border-radius: 1rem;
            padding: 2rem;
            max-width: 500px;
            width: 90%;
            max-height: 90vh;
            overflow-y: auto;
        }
        
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
        }
        
        .modal-title {
            font-family: var(--font-display);
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--primary-black);
        }
        
        .close {
            background: none;
            border: none;
            font-size: 1.5rem;
            cursor: pointer;
            color: var(--gray-600);
        }
        
        .form-group {
            margin-bottom: 1.5rem;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: var(--gray-800);
        }
        
        .form-group input,
        .form-group select {
            width: 100%;
            padding: 0.75rem;
            border: 2px solid var(--gray-300);
            border-radius: 0.5rem;
            font-size: 1rem;
            transition: border-color 0.3s ease;
        }
        
        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            border-color: var(--primary-green);
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
            
            .stats-overview {
                grid-template-columns: 1fr;
            }
            
            .users-table {
                font-size: 0.9rem;
            }
            
            .users-table th,
            .users-table td {
                padding: 0.75rem 0.5rem;
            }
            
            .actions {
                flex-direction: column;
            }
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
                
                <div class="admin-nav">
                    <a href="dashboard.php">Tableau de bord</a>
                    <a href="admin-users.php" class="active">Utilisateurs</a>
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
                <h1 class="page-title">Gestion des Utilisateurs</h1>
                <p class="page-subtitle">Administration des comptes utilisateurs et administrateurs</p>
            </div>

            <!-- Message -->
            <?php if ($message): ?>
                <div class="message <?= $messageType ?>">
                    <?= htmlspecialchars($message) ?>
                </div>
            <?php endif; ?>

            <!-- Statistiques -->
            <div class="stats-overview">
                <div class="stat-card">
                    <div class="stat-number"><?= $totalUsers ?></div>
                    <div class="stat-label">Total utilisateurs</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?= $activeUsers ?></div>
                    <div class="stat-label">Utilisateurs actifs</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?= $adminUsers ?></div>
                    <div class="stat-label">Administrateurs</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?= $totalUsers - $activeUsers ?></div>
                    <div class="stat-label">Utilisateurs inactifs</div>
                </div>
            </div>

            <!-- Section utilisateurs -->
            <div class="users-section">
                <div class="section-header">
                    <h2 class="section-title">Liste des Utilisateurs</h2>
                    <button class="btn btn-primary" onclick="openAddUserModal()">
                        ➕ Ajouter un utilisateur
                    </button>
                </div>

                <?php if ($dbConnected && !empty($users)): ?>
                    <table class="users-table">
                        <thead>
                            <tr>
                                <th>Utilisateur</th>
                                <th>Contact</th>
                                <th>Rôle</th>
                                <th>Statut</th>
                                <th>Inscription</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($users as $user): ?>
                                <tr>
                                    <td>
                                        <div class="user-info">
                                            <div class="user-avatar">
                                                <?= strtoupper(substr($user['first_name'], 0, 1)) ?>
                                            </div>
                                            <div class="user-details">
                                                <h4><?= htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) ?></h4>
                                                <p><?= htmlspecialchars($user['email']) ?></p>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <div>
                                            <p><?= htmlspecialchars($user['phone']) ?></p>
                                            <p style="font-size: 0.8rem; color: var(--gray-600);">
                                                📧 <?= $user['email_verified'] ? 'Vérifié' : 'Non vérifié' ?>
                                                📱 <?= $user['phone_verified'] ? 'Vérifié' : 'Non vérifié' ?>
                                            </p>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="role-badge role-<?= $user['role'] ?>">
                                            <?= $user['role'] === 'admin' ? '👑 Admin' : '👤 User' ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="status-badge status-<?= $user['status'] ?>">
                                            <?= $user['status'] === 'active' ? '✅ Actif' : '❌ Inactif' ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?= date('d/m/Y', strtotime($user['created_at'])) ?>
                                    </td>
                                    <td>
                                        <div class="actions">
                                            <form method="post" style="display: inline;">
                                                <input type="hidden" name="action" value="toggle_status">
                                                <input type="hidden" name="user_id" value="<?= $user['user_id'] ?>">
                                                <button type="submit" class="btn btn-small btn-warning">
                                                    <?= $user['status'] === 'active' ? '⏸️' : '▶️' ?>
                                                </button>
                                            </form>
                                            
                                            <form method="post" style="display: inline;">
                                                <input type="hidden" name="action" value="change_role">
                                                <input type="hidden" name="user_id" value="<?= $user['user_id'] ?>">
                                                <input type="hidden" name="role" value="<?= $user['role'] === 'admin' ? 'user' : 'admin' ?>">
                                                <button type="submit" class="btn btn-small btn-primary">
                                                    <?= $user['role'] === 'admin' ? '👤' : '👑' ?>
                                                </button>
                                            </form>
                                            
                                            <form method="post" style="display: inline;" onsubmit="return confirm('Êtes-vous sûr de vouloir supprimer cet utilisateur ?')">
                                                <input type="hidden" name="action" value="delete_user">
                                                <input type="hidden" name="user_id" value="<?= $user['user_id'] ?>">
                                                <button type="submit" class="btn btn-small btn-danger">
                                                    🗑️
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <p style="text-align: center; color: var(--gray-600); margin: 2rem 0;">
                        <?= $dbConnected ? 'Aucun utilisateur trouvé.' : 'Erreur de connexion à la base de données.' ?>
                    </p>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <!-- Modal d'ajout d'utilisateur -->
    <div id="addUserModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Ajouter un utilisateur</h3>
                <button class="close" onclick="closeAddUserModal()">&times;</button>
            </div>
            
            <form method="post">
                <input type="hidden" name="action" value="add_user">
                
                <div class="form-group">
                    <label for="first_name">Prénom *</label>
                    <input type="text" id="first_name" name="first_name" required>
                </div>
                
                <div class="form-group">
                    <label for="last_name">Nom *</label>
                    <input type="text" id="last_name" name="last_name" required>
                </div>
                
                <div class="form-group">
                    <label for="email">Email *</label>
                    <input type="email" id="email" name="email" required>
                </div>
                
                <div class="form-group">
                    <label for="phone">Téléphone</label>
                    <input type="tel" id="phone" name="phone">
                </div>
                
                <div class="form-group">
                    <label for="role">Rôle *</label>
                    <select id="role" name="role" required>
                        <option value="user">Utilisateur</option>
                        <option value="admin">Administrateur</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="password">Mot de passe *</label>
                    <input type="password" id="password" name="password" required>
                </div>
                
                <div style="display: flex; gap: 1rem; justify-content: flex-end;">
                    <button type="button" class="btn" onclick="closeAddUserModal()" style="background: var(--gray-300); color: var(--gray-800);">
                        Annuler
                    </button>
                    <button type="submit" class="btn btn-primary">
                        Créer l'utilisateur
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openAddUserModal() {
            document.getElementById('addUserModal').classList.add('show');
        }
        
        function closeAddUserModal() {
            document.getElementById('addUserModal').classList.remove('show');
        }
        
        // Fermer le modal en cliquant à l'extérieur
        document.getElementById('addUserModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeAddUserModal();
            }
        });
    </script>
</body>
</html>
