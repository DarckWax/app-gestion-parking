<?php

namespace App\Core;

use App\Utils\Logger;

/**
 * Router Class - Handles URL routing and request dispatching
 */
class Router
{
    private $routes = [];
    private $currentRoute = null;
    
    public function addRoute($method, $path, $handler)
    {
        $this->routes[] = [
            'method' => strtoupper($method),
            'path' => $path,
            'handler' => $handler,
            'pattern' => $this->convertToPattern($path)
        ];
    }
    
    private function convertToPattern($path)
    {
        // Convert route parameters like {id} to regex patterns
        $pattern = preg_replace('/\{([a-zA-Z0-9_]+)\}/', '([a-zA-Z0-9_-]+)', $path);
        $pattern = str_replace('/', '\/', $pattern);
        return '/^' . $pattern . '$/';
    }
    
    public function handleRequest()
    {
        $method = $_SERVER['REQUEST_METHOD'];
        $path = $this->getCurrentPath();
        
        foreach ($this->routes as $route) {
            if ($route['method'] === $method && preg_match($route['pattern'], $path, $matches)) {
                array_shift($matches); // Remove full match
                $this->currentRoute = $route;
                return $this->dispatch($route['handler'], $matches);
            }
        }
        
        // No route found
        $this->handle404();
    }
    
    private function getCurrentPath()
    {
        $path = $_SERVER['REQUEST_URI'];
        $path = parse_url($path, PHP_URL_PATH);
        return rtrim($path, '/') ?: '/';
    }
    
    private function dispatch($handler, $params = [])
    {
        list($controllerName, $method) = explode('@', $handler);
        
        $controllerClass = "App\\Controllers\\{$controllerName}";
        
        if (!class_exists($controllerClass)) {
            Logger::error("Controller not found: {$controllerClass}");
            $this->handle404();
            return;
        }
        
        $controller = new $controllerClass();
        
        if (!method_exists($controller, $method)) {
            Logger::error("Method not found: {$controllerClass}::{$method}");
            $this->handle404();
            return;
        }
        
        try {
            call_user_func_array([$controller, $method], $params);
        } catch (\Exception $e) {
            Logger::error("Controller error: " . $e->getMessage(), [
                'controller' => $controllerClass,
                'method' => $method,
                'params' => $params
            ]);
            $this->handle500($e);
        }
    }
    
    private function handle404()
    {
        http_response_code(404);
        include __DIR__ . '/../views/errors/404.php';
    }
    
    private function handle500($exception = null)
    {
        http_response_code(500);
        include __DIR__ . '/../views/errors/500.php';
    }
    
    public function redirect($path, $code = 302)
    {
        http_response_code($code);
        header("Location: {$path}");
        exit;
    }
}
