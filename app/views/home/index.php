<?php
$title = 'Welcome to ParkFinder';
$bodyClass = 'home-page';
ob_start();
?>

<!-- Hero Section -->
<section class="hero-section">
    <div class="container">
        <div class="hero-content">
            <h1 class="hero-title">Smart Parking Made Simple</h1>
            <p class="hero-subtitle">Find, book, and pay for parking spots with ease. Real-time availability, secure payments, and instant confirmations.</p>
            <div class="hero-actions">
                <?php if (isset($_SESSION['user_id'])): ?>
                    <a href="/book" class="btn btn-primary btn-lg">Book Parking Now</a>
                    <a href="/dashboard" class="btn btn-secondary btn-lg">Go to Dashboard</a>
                <?php else: ?>
                    <a href="/register" class="btn btn-primary btn-lg">Get Started</a>
                    <a href="/login" class="btn btn-secondary btn-lg">Login</a>
                <?php endif; ?>
            </div>
        </div>
        <div class="hero-image">
            <img src="/assets/images/parking-illustration.svg" alt="Smart Parking" class="img-fluid">
        </div>
    </div>
</section>

<!-- Features Section -->
<section class="features-section">
    <div class="container">
        <h2 class="section-title text-center">Why Choose ParkFinder?</h2>
        <div class="features-grid">
            <div class="feature-card">
                <div class="feature-icon">
                    <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M12 2l9 4.5-9 4.5-9-4.5L12 2z"/>
                        <path d="M12 11l9-4.5v9l-9 4.5-9-4.5v-9l9 4.5z"/>
                    </svg>
                </div>
                <h3 class="feature-title">Real-Time Availability</h3>
                <p class="feature-description">See available parking spots in real-time and book instantly without waiting.</p>
            </div>
            
            <div class="feature-card">
                <div class="feature-icon">
                    <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <rect x="2" y="3" width="20" height="14" rx="2" ry="2"/>
                        <line x1="8" y1="21" x2="16" y2="21"/>
                        <line x1="12" y1="17" x2="12" y2="21"/>
                    </svg>
                </div>
                <h3 class="feature-title">Easy Booking</h3>
                <p class="feature-description">Book your parking spot in just a few clicks with our intuitive interface.</p>
            </div>
            
            <div class="feature-card">
                <div class="feature-icon">
                    <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <rect x="1" y="4" width="22" height="16" rx="2" ry="2"/>
                        <line x1="1" y1="10" x2="23" y2="10"/>
                    </svg>
                </div>
                <h3 class="feature-title">Secure Payments</h3>
                <p class="feature-description">Safe and secure payment processing with multiple payment options.</p>
            </div>
            
            <div class="feature-card">
                <div class="feature-icon">
                    <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/>
                        <path d="M13.73 21a2 2 0 0 1-3.46 0"/>
                    </svg>
                </div>
                <h3 class="feature-title">Smart Notifications</h3>
                <p class="feature-description">Get timely reminders and updates about your parking reservations.</p>
            </div>
            
            <div class="feature-card">
                <div class="feature-icon">
                    <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="12" cy="12" r="10"/>
                        <polyline points="12,6 12,12 16,14"/>
                    </svg>
                </div>
                <h3 class="feature-title">24/7 Access</h3>
                <p class="feature-description">Access your parking spots and manage reservations anytime, anywhere.</p>
            </div>
            
            <div class="feature-card">
                <div class="feature-icon">
                    <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M9 11H5a2 2 0 0 0-2 2v7a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7a2 2 0 0 0-2-2h-4"/>
                        <polyline points="9,11 12,14 15,11"/>
                        <line x1="12" y1="14" x2="12" y2="3"/>
                    </svg>
                </div>
                <h3 class="feature-title">Mobile Friendly</h3>
                <p class="feature-description">Fully responsive design that works perfectly on all devices.</p>
            </div>
        </div>
    </div>
</section>

<!-- Statistics Section -->
<section class="stats-section">
    <div class="container">
        <div class="stats-grid">
            <div class="stat-item">
                <div class="stat-number">1,500+</div>
                <div class="stat-label">Happy Customers</div>
            </div>
            <div class="stat-item">
                <div class="stat-number">250+</div>
                <div class="stat-label">Parking Spots</div>
            </div>
            <div class="stat-item">
                <div class="stat-number">99.9%</div>
                <div class="stat-label">Uptime</div>
            </div>
            <div class="stat-item">
                <div class="stat-number">24/7</div>
                <div class="stat-label">Support</div>
            </div>
        </div>
    </div>
</section>

<!-- How It Works Section -->
<section class="how-it-works-section">
    <div class="container">
        <h2 class="section-title text-center">How It Works</h2>
        <div class="steps-container">
            <div class="step">
                <div class="step-number">1</div>
                <div class="step-content">
                    <h3>Create Account</h3>
                    <p>Sign up for free and set up your profile in minutes.</p>
                </div>
            </div>
            <div class="step">
                <div class="step-number">2</div>
                <div class="step-content">
                    <h3>Find Parking</h3>
                    <p>Search and select from available parking spots in real-time.</p>
                </div>
            </div>
            <div class="step">
                <div class="step-number">3</div>
                <div class="step-content">
                    <h3>Book & Pay</h3>
                    <p>Reserve your spot and pay securely online.</p>
                </div>
            </div>
            <div class="step">
                <div class="step-number">4</div>
                <div class="step-content">
                    <h3>Park & Go</h3>
                    <p>Arrive at your reserved spot and enjoy hassle-free parking.</p>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- CTA Section -->
<section class="cta-section">
    <div class="container">
        <div class="cta-content">
            <h2>Ready to Get Started?</h2>
            <p>Join thousands of satisfied customers who trust ParkFinder for their parking needs.</p>
            <?php if (!isset($_SESSION['user_id'])): ?>
                <a href="/register" class="btn btn-primary btn-lg">Sign Up Now</a>
            <?php else: ?>
                <a href="/book" class="btn btn-primary btn-lg">Book Your Spot</a>
            <?php endif; ?>
        </div>
    </div>
</section>

<style>
/* Homepage specific styles */
.hero-section {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 100px 0;
    overflow: hidden;
}

.hero-content {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 4rem;
    align-items: center;
}

.hero-title {
    font-size: 3.5rem;
    font-weight: 700;
    margin-bottom: 1.5rem;
    line-height: 1.2;
}

.hero-subtitle {
    font-size: 1.3rem;
    margin-bottom: 2rem;
    opacity: 0.9;
    line-height: 1.6;
}

.hero-actions {
    display: flex;
    gap: 1rem;
    flex-wrap: wrap;
}

.features-section {
    padding: 100px 0;
    background: #f8f9fa;
}

.section-title {
    font-size: 2.5rem;
    margin-bottom: 4rem;
    color: #2c3e50;
}

.features-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 3rem;
}

.feature-card {
    background: white;
    padding: 2.5rem;
    border-radius: 12px;
    text-align: center;
    box-shadow: 0 5px 30px rgba(0,0,0,0.1);
    transition: transform 0.3s ease;
}

.feature-card:hover {
    transform: translateY(-10px);
}

.feature-icon {
    color: #3498db;
    margin-bottom: 1.5rem;
}

.feature-title {
    font-size: 1.5rem;
    margin-bottom: 1rem;
    color: #2c3e50;
}

.feature-description {
    color: #666;
    line-height: 1.6;
}

.stats-section {
    background: #2c3e50;
    color: white;
    padding: 80px 0;
}

.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 2rem;
    text-align: center;
}

.stat-number {
    font-size: 3rem;
    font-weight: 700;
    color: #3498db;
    margin-bottom: 0.5rem;
}

.stat-label {
    font-size: 1.1rem;
    opacity: 0.9;
}

.how-it-works-section {
    padding: 100px 0;
}

.steps-container {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 3rem;
    margin-top: 4rem;
}

.step {
    text-align: center;
    position: relative;
}

.step-number {
    width: 60px;
    height: 60px;
    background: #3498db;
    color: white;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
    font-weight: 700;
    margin: 0 auto 1.5rem;
}

.step h3 {
    margin-bottom: 1rem;
    color: #2c3e50;
}

.cta-section {
    background: linear-gradient(135deg, #3498db, #2980b9);
    color: white;
    padding: 80px 0;
    text-align: center;
}

.cta-content h2 {
    font-size: 2.5rem;
    margin-bottom: 1rem;
}

.cta-content p {
    font-size: 1.2rem;
    margin-bottom: 2rem;
    opacity: 0.9;
}

@media (max-width: 768px) {
    .hero-content {
        grid-template-columns: 1fr;
        text-align: center;
    }
    
    .hero-title {
        font-size: 2.5rem;
    }
    
    .hero-actions {
        justify-content: center;
    }
    
    .features-grid {
        grid-template-columns: 1fr;
    }
    
    .stats-grid {
        grid-template-columns: repeat(2, 1fr);
    }
    
    .steps-container {
        grid-template-columns: 1fr;
    }
}
</style>

<?php
$content = ob_get_clean();
include __DIR__ . '/../layouts/main.php';
?>
