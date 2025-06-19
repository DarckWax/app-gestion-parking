<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $title ?? 'Parking Management System' ?></title>
    <meta name="description" content="Complete parking management and reservation system">
    <meta name="author" content="ParkFinder">
    
    <!-- CSS Files -->
    <link rel="stylesheet" href="/assets/css/main.css">
    <link rel="stylesheet" href="/assets/css/components.css">
    <link rel="stylesheet" href="/assets/css/responsive.css">
    
    <!-- Icons -->
    <link rel="icon" type="image/x-icon" href="/assets/images/favicon.ico">
    
    <!-- Custom CSS -->
    <?php if (isset($customCSS)): ?>
        <?php foreach ($customCSS as $css): ?>
            <link rel="stylesheet" href="<?= $css ?>">
        <?php endforeach; ?>
    <?php endif; ?>
</head>
<body class="<?= $bodyClass ?? '' ?>">
    <!-- Header -->
    <header class="main-header">
        <nav class="navbar">
            <div class="container">
                <div class="navbar-brand">
                    <a href="/" class="logo">
                        <img src="/assets/images/logo.png" alt="ParkFinder" class="logo-img">
                        <span class="logo-text">ParkFinder</span>
                    </a>
                </div>
                
                <div class="navbar-menu" id="navbarMenu">
                    <ul class="navbar-nav">
                        <li class="nav-item">
                            <a href="/" class="nav-link">Home</a>
                        </li>
                        
                        <?php if (isset($_SESSION['user_id'])): ?>
                            <li class="nav-item">
                                <a href="/dashboard" class="nav-link">Dashboard</a>
                            </li>
                            <li class="nav-item">
                                <a href="/book" class="nav-link">Book Parking</a>
                            </li>
                            <li class="nav-item">
                                <a href="/reservations" class="nav-link">My Reservations</a>
                            </li>
                            
                            <?php if ($_SESSION['user_role'] === 'admin'): ?>
                                <li class="nav-item dropdown">
                                    <a href="#" class="nav-link dropdown-toggle">Admin</a>
                                    <ul class="dropdown-menu">
                                        <li><a href="/admin" class="dropdown-link">Dashboard</a></li>
                                        <li><a href="/admin/users" class="dropdown-link">Manage Users</a></li>
                                        <li><a href="/admin/spots" class="dropdown-link">Manage Spots</a></li>
                                        <li><a href="/admin/reservations" class="dropdown-link">Reservations</a></li>
                                        <li><a href="/admin/payments" class="dropdown-link">Payments</a></li>
                                        <li><a href="/admin/reports" class="dropdown-link">Reports</a></li>
                                    </ul>
                                </li>
                            <?php endif; ?>
                            
                            <li class="nav-item dropdown">
                                <a href="#" class="nav-link dropdown-toggle">
                                    <?= htmlspecialchars($_SESSION['user_name']) ?>
                                </a>
                                <ul class="dropdown-menu">
                                    <li><a href="/profile" class="dropdown-link">Profile</a></li>
                                    <li><a href="/logout" class="dropdown-link">Logout</a></li>
                                </ul>
                            </li>
                        <?php else: ?>
                            <li class="nav-item">
                                <a href="/login" class="nav-link">Login</a>
                            </li>
                            <li class="nav-item">
                                <a href="/register" class="nav-link btn btn-primary">Register</a>
                            </li>
                        <?php endif; ?>
                    </ul>
                </div>
                
                <button class="navbar-toggle" id="navbarToggle">
                    <span></span>
                    <span></span>
                    <span></span>
                </button>
            </div>
        </nav>
    </header>
    
    <!-- Main Content -->
    <main class="main-content">
        <?php if (isset($showBreadcrumb) && $showBreadcrumb): ?>
            <div class="breadcrumb-container">
                <div class="container">
                    <nav class="breadcrumb">
                        <?php foreach ($breadcrumb as $item): ?>
                            <?php if ($item['active']): ?>
                                <span class="breadcrumb-item active"><?= $item['text'] ?></span>
                            <?php else: ?>
                                <a href="<?= $item['url'] ?>" class="breadcrumb-item"><?= $item['text'] ?></a>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </nav>
                </div>
            </div>
        <?php endif; ?>
        
        <!-- Flash Messages -->
        <div id="flashMessages" class="flash-messages">
            <?php
            $flashTypes = ['success', 'error', 'warning', 'info'];
            foreach ($flashTypes as $type):
                if (isset($_SESSION['flash'][$type])):
            ?>
                <div class="alert alert-<?= $type ?> alert-dismissible">
                    <button type="button" class="alert-close" data-dismiss="alert">&times;</button>
                    <?= htmlspecialchars($_SESSION['flash'][$type]) ?>
                </div>
                <?php unset($_SESSION['flash'][$type]); ?>
            <?php endif; endforeach; ?>
        </div>
        
        <!-- Page Content -->
        <div class="page-content">
            <?= $content ?? '' ?>
        </div>
    </main>
    
    <!-- Footer -->
    <footer class="main-footer">
        <div class="container">
            <div class="footer-content">
                <div class="footer-section">
                    <h4>ParkFinder</h4>
                    <p>Your reliable parking management solution</p>
                </div>
                <div class="footer-section">
                    <h4>Quick Links</h4>
                    <ul>
                        <li><a href="/">Home</a></li>
                        <li><a href="/book">Book Parking</a></li>
                        <li><a href="/contact">Contact</a></li>
                    </ul>
                </div>
                <div class="footer-section">
                    <h4>Support</h4>
                    <ul>
                        <li><a href="/help">Help Center</a></li>
                        <li><a href="/faq">FAQ</a></li>
                        <li><a href="/terms">Terms of Service</a></li>
                    </ul>
                </div>
            </div>
            <div class="footer-bottom">
                <p>&copy; <?= date('Y') ?> ParkFinder. All rights reserved.</p>
            </div>
        </div>
    </footer>
    
    <!-- JavaScript Files -->
    <script src="/assets/js/utils.js"></script>
    <script src="/assets/js/components.js"></script>
    <script src="/assets/js/main.js"></script>
    
    <!-- Custom JS -->
    <?php if (isset($customJS)): ?>
        <?php foreach ($customJS as $js): ?>
            <script src="<?= $js ?>"></script>
        <?php endforeach; ?>
    <?php endif; ?>
    
    <!-- Inline JavaScript -->
    <?php if (isset($jsCode)): ?>
        <script><?= $jsCode ?></script>
    <?php endif; ?>
</body>
</html>
