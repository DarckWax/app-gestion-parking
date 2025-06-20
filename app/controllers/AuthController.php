<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Models\User;
use App\Utils\Security;
use App\Utils\Validator;
use App\Utils\Logger;

class AuthController extends Controller
{
    private $userModel;
    
    public function __construct()
    {
        parent::__construct();
        $this->userModel = new User();
    }
    
    public function loginForm()
    {
        if ($this->isAuthenticated()) {
            $this->redirect('/dashboard');
        }
        
        $this->view('auth/login', [
            'title' => 'Login',
            'csrf_token' => Security::generateCSRFToken(),
            'error' => $this->flash('error'),
            'success' => $this->flash('success')
        ]);
    }
    
    public function login()
    {
        if ($this->isAuthenticated()) {
            $this->redirect('/dashboard');
        }
        
        $email = Security::sanitizeInput($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $rememberMe = isset($_POST['remember_me']);
        
        if (!Security::rateLimitCheck($email, 5, 900)) {
            $this->flash('error', 'Too many login attempts. Please try again later.');
            $this->redirect('/login');
        }
        
        $validator = new Validator($_POST);
        $validator->required('email')->email('email')
                 ->required('password');
        
        if ($validator->fails()) {
            $this->flash('error', $validator->getFirstError());
            $this->redirect('/login');
        }
        
        $user = $this->userModel->findByEmail($email);
        
        if (!$user || !Security::verifyPassword($password, $user['password_hash'])) {
            Logger::warning('Failed login attempt', ['email' => $email, 'ip' => $_SERVER['REMOTE_ADDR']]);
            $this->flash('error', 'Invalid credentials');
            $this->redirect('/login');
        }
        
        if ($user['status'] !== 'active') {
            $this->flash('error', 'Account is not active. Please contact administrator.');
            $this->redirect('/login');
        }
        
        $_SESSION['user_id'] = $user['user_id'];
        $_SESSION['user_email'] = $user['email'];
        $_SESSION['user_name'] = $user['first_name'] . ' ' . $user['last_name'];
        $_SESSION['user_role'] = $user['role'];
        
        $this->userModel->updateLastLogin($user['user_id']);
        
        Logger::info('User logged in', ['user_id' => $user['user_id'], 'email' => $email]);
        
        $redirectTo = $_SESSION['redirect_after_login'] ?? '/dashboard';
        unset($_SESSION['redirect_after_login']);
        
        $this->redirect($redirectTo);
    }
    
    public function registerForm()
    {
        if ($this->isAuthenticated()) {
            $this->redirect('/dashboard');
        }
        
        $this->view('auth/register', [
            'title' => 'Register',
            'csrf_token' => Security::generateCSRFToken(),
            'error' => $this->flash('error'),
            'success' => $this->flash('success')
        ]);
    }
    
    public function register()
    {
        if ($this->isAuthenticated()) {
            $this->redirect('/dashboard');
        }
        
        $validator = new Validator($_POST);
        $validator->required('first_name')->max('first_name', 50)
                 ->required('last_name')->max('last_name', 50)
                 ->required('email')->email('email')->unique('email', 'users', 'email')
                 ->required('phone')->phone('phone')
                 ->required('password')->min('password', 8)
                 ->required('password_confirm');
        
        if ($validator->fails()) {
            $this->flash('error', $validator->getFirstError());
            $this->redirect('/register');
        }
        
        if ($_POST['password'] !== $_POST['password_confirm']) {
            $this->flash('error', 'Password confirmation does not match');
            $this->redirect('/register');
        }
        
        try {
            $userData = [
                'first_name' => Security::sanitizeInput($_POST['first_name']),
                'last_name' => Security::sanitizeInput($_POST['last_name']),
                'email' => Security::sanitizeInput($_POST['email']),
                'phone' => Security::sanitizeInput($_POST['phone']),
                'password' => $_POST['password']
            ];
            
            $userId = $this->userModel->createUser($userData);
            
            Logger::info('New user registered', ['user_id' => $userId, 'email' => $userData['email']]);
            
            $this->flash('success', 'Registration successful! Please login.');
            $this->redirect('/login');
            
        } catch (\Exception $e) {
            Logger::error('Registration failed: ' . $e->getMessage());
            $this->flash('error', 'Registration failed. Please try again.');
            $this->redirect('/register');
        }
    }
    
    public function logout()
    {
        if ($this->isAuthenticated()) {
            Logger::info('User logged out', ['user_id' => $_SESSION['user_id']]);
        }
        
        session_destroy();
        $this->redirect('/');
    }
}
