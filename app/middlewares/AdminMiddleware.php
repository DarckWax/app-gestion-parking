<?php

namespace App\Middlewares;

/**
 * Middleware pour vérifier les droits d'administration
 */
class AdminMiddleware
{
    /**
     * Vérifie si l'utilisateur connecté est un administrateur
     */
    public static function requireAdmin()
    {
        // Démarrer la session seulement si elle n'est pas déjà active
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        // Vérifier si l'utilisateur est connecté
        if (!isset($_SESSION['user_id'])) {
            header('Location: index.php?error=not_logged_in');
            exit;
        }
          // Vérifier le rôle admin
        $userRole = $_SESSION['role'] ?? '';
        if ($userRole !== 'admin') {
            self::showAccessDeniedPage();
            exit;
        }
        
        return true;
    }
      /**
     * Vérifie si l'utilisateur est admin sans redirection
     */
    public static function isAdmin()
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        return isset($_SESSION['user_id']) && ($_SESSION['role'] ?? '') === 'admin';
    }
    
    /**
     * Affiche une page d'accès refusé
     */
    private static function showAccessDeniedPage()
    {
        http_response_code(403);
        ?>
        <!DOCTYPE html>
        <html lang="fr">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Accès refusé - ParkFinder</title>
            <style>
                body {
                    font-family: 'Inter', Arial, sans-serif;
                    background: linear-gradient(135deg, #111827 0%, #1F2937 50%, #10B981 100%);
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    min-height: 100vh;
                    margin: 0;
                    color: white;
                }
                .error-container {
                    background: rgba(255, 255, 255, 0.95);
                    backdrop-filter: blur(20px);
                    padding: 3rem;
                    border-radius: 1.5rem;
                    box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
                    text-align: center;
                    max-width: 500px;
                    color: #111827;
                }
                .error-icon {
                    font-size: 4rem;
                    margin-bottom: 1.5rem;
                }
                .error-title {
                    font-size: 2rem;
                    font-weight: 800;
                    color: #dc3545;
                    margin-bottom: 1rem;
                    font-family: 'Space Grotesk', sans-serif;
                }
                .error-message {
                    color: #6B7280;
                    margin-bottom: 2rem;
                    line-height: 1.6;
                }
                .btn {
                    background: linear-gradient(135deg, #10B981, #059669);
                    color: white;
                    padding: 0.75rem 2rem;
                    text-decoration: none;
                    border-radius: 0.75rem;
                    display: inline-block;
                    font-weight: 600;
                    transition: all 0.3s ease;
                }
                .btn:hover {
                    transform: translateY(-2px);
                    box-shadow: 0 10px 25px -5px rgba(16, 185, 129, 0.3);
                }
            </style>
        </head>
        <body>
            <div class="error-container">
                <div class="error-icon">🚫</div>
                <h1 class="error-title">Accès refusé</h1>
                <p class="error-message">
                    Vous n'avez pas les permissions nécessaires pour accéder au tableau de bord administrateur.
                    <br><br>Seuls les comptes administrateurs peuvent accéder à cette section.
                </p>
                <a href="index.php" class="btn">← Retour à l'accueil</a>
            </div>
        </body>
        </html>
        <?php
    }
}