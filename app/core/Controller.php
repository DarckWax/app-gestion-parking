<?php

namespace App\Core;

use App\Utils\Security;
use App\Utils\Logger;

/**
 * Base Controller Class
 */
abstract class Controller
{
    protected $db;
    
    public function __construct()
    {
        $this->db = Database::getInstance();
        $this->initSession();
    }
    
    private function initSession()
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }
    
    protected function view($viewPath, $data = [])
    {
        extract($data);
        
        $viewFile = __DIR__ . "/../views/{$viewPath}.php";
        
        if (!file_exists($viewFile)) {
            Logger::error("View not found: {$viewPath}");
            throw new \Exception("View not found: {$viewPath}");
        }
        
        include $viewFile;
    }
    
    protected function json($data, $code = 200)
    {
        http_response_code($code);
        header('Content-Type: application/json');
        echo json_encode($data);
        exit;
    }
    
    protected function redirect($path, $code = 302)
    {
        http_response_code($code);
        header("Location: {$path}");
        exit;
    }
    
    protected function isAuthenticated()
    {
        return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
    }
    
    protected function isAdmin()
    {
        return $this->isAuthenticated() && $_SESSION['user_role'] === 'admin';
    }
    
    protected function requireAuth()
    {
        if (!$this->isAuthenticated()) {
            $this->redirect('/login');
        }
    }
    
    protected function requireAdmin()
    {
        if (!$this->isAdmin()) {
            http_response_code(403);
            $this->view('errors/403');
            exit;
        }
    }
    
    protected function getCurrentUser()
    {
        if (!$this->isAuthenticated()) {
            return null;
        }
        
        return $this->db->fetch(
            "SELECT * FROM users WHERE user_id = ?",
            [$_SESSION['user_id']]
        );
    }
    
    protected function validateCSRF()
    {
        if (!Security::validateCSRF($_POST['csrf_token'] ?? '')) {
            http_response_code(403);
            $this->json(['error' => 'Invalid CSRF token'], 403);
        }
    }
    
    protected function getInput($key, $default = null)
    {
        return $_POST[$key] ?? $_GET[$key] ?? $default;
    }
    
    protected function flash($key, $message = null)
    {
        if ($message === null) {
            $msg = $_SESSION['flash'][$key] ?? null;
            unset($_SESSION['flash'][$key]);
            return $msg;
        }
        
        $_SESSION['flash'][$key] = $message;
    }
}
