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
        $userRole = $_SESSION['user_role'] ?? '';
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
        
        return isset($_SESSION['user_id']) && ($_SESSION['user_role'] ?? '') === 'admin';
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
                    font-family: Arial, sans-serif;
                    background: #f8f9fa;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    min-height: 100vh;
                    margin: 0;
                }
                .error-container {
                    background: white;
                    padding: 2rem;
                    border-radius: 8px;
                    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
                    text-align: center;
                    max-width: 500px;
                }
                .error-icon {
                    font-size: 4rem;
                    color: #dc3545;
                    margin-bottom: 1rem;
                }
                .error-title {
                    color: #dc3545;
                    margin-bottom: 1rem;
                }
                .error-message {
                    color: #6c757d;
                    margin-bottom: 2rem;
                }
                .btn {
                    background: #007bff;
                    color: white;
                    padding: 0.75rem 1.5rem;
                    text-decoration: none;
                    border-radius: 4px;
                    display: inline-block;
                }
            </style>
        </head>
        <body>
            <div class="error-container">
                <div class="error-icon">🚫</div>
                <h1 class="error-title">Accès refusé</h1>
                <p class="error-message">
                    Vous n'avez pas les permissions nécessaires pour accéder au tableau de bord administrateur.
                    <br>Seuls les comptes administrateurs peuvent accéder à cette section.
                </p>
                <a href="index.php" class="btn">Retour à l'accueil</a>
            </div>
        </body>
        </html>
        <?php
    }
}
