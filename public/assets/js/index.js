/**
 * ParkFinder - Scripts pour la page d'accueil
 * Fichier: index.js
 */

document.addEventListener('DOMContentLoaded', function() {
    
    // === SMOOTH SCROLLING ===
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

    // === ANIMATIONS AU SCROLL ===
    // Configuration pour l'observer des animations
    const observerOptions = {
        threshold: 0.1,
        rootMargin: '0px 0px -50px 0px'
    };

    // Observer pour les animations d'apparition
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

    // === FONCTION UTILITAIRE ===
    // Fonction pour scroller vers la section login
    window.scrollToLogin = function() {
        window.location.href = 'index.php#login';
        setTimeout(() => {
            const loginSection = document.getElementById('login');
            if (loginSection) {
                loginSection.scrollIntoView({
                    behavior: 'smooth',
                    block: 'start'
                });
            }
        }, 100);
    };
    
    // === VALIDATION DES FORMULAIRES ===
    // Validation en temps réel du formulaire d'inscription
    const registrationForm = document.querySelector('form[action="?action=register"]');
    if (registrationForm) {
        const passwordField = document.getElementById('password');
        const confirmPasswordField = document.getElementById('confirm_password');
        
        // Fonction de validation des mots de passe
        function validatePasswords() {
            const password = passwordField.value;
            const confirmPassword = confirmPasswordField.value;
            
            if (confirmPassword && password !== confirmPassword) {
                confirmPasswordField.setCustomValidity('Les mots de passe ne correspondent pas');
            } else {
                confirmPasswordField.setCustomValidity('');
            }
        }
        
        // Écouter les changements dans les champs de mot de passe
        if (passwordField && confirmPasswordField) {
            passwordField.addEventListener('input', validatePasswords);
            confirmPasswordField.addEventListener('input', validatePasswords);
        }
        
        // Validation avant soumission du formulaire
        registrationForm.addEventListener('submit', function(e) {
            const firstName = document.getElementById('first_name')?.value.trim();
            const lastName = document.getElementById('last_name')?.value.trim();
            const email = document.getElementById('email')?.value.trim();
            const phone = document.getElementById('phone')?.value.trim();
            const password = passwordField?.value;
            const confirmPassword = confirmPasswordField?.value;
            
            let errors = [];
            
            // Validation des champs
            if (!firstName || firstName.length < 2) {
                errors.push('Le prénom doit contenir au moins 2 caractères');
            }
            if (!lastName || lastName.length < 2) {
                errors.push('Le nom doit contenir au moins 2 caractères');
            }
            if (!email || !email.includes('@')) {
                errors.push('Format d\'email invalide');
            }
            if (!phone || phone.length < 10) {
                errors.push('Le téléphone doit contenir au moins 10 caractères');
            }
            if (!password || password.length < 6) {
                errors.push('Le mot de passe doit faire au moins 6 caractères');
            }
            if (password !== confirmPassword) {
                errors.push('Les mots de passe ne correspondent pas');
            }
            
            // Afficher les erreurs s'il y en a
            if (errors.length > 0) {
                e.preventDefault();
                alert('Erreurs de validation:\n• ' + errors.join('\n• '));
            }
        });
    }

    // === AMÉLIORATIONS UI ===
    // Ajouter des effets visuels supplémentaires
    initializeUIEnhancements();
});

/**
 * Initialise les améliorations de l'interface utilisateur
 */
function initializeUIEnhancements() {
    // Animation des cartes au survol
    const cards = document.querySelectorAll('.stat-card, .feature-card');
    cards.forEach(card => {
        card.addEventListener('mouseenter', function() {
            this.style.transform = 'translateY(-8px) scale(1.02)';
        });
        
        card.addEventListener('mouseleave', function() {
            this.style.transform = 'translateY(0) scale(1)';
        });
    });

    // Amélioration des formulaires
    const inputs = document.querySelectorAll('input[type="text"], input[type="email"], input[type="password"], input[type="tel"]');
    inputs.forEach(input => {
        // Animation au focus
        input.addEventListener('focus', function() {
            this.parentElement.classList.add('focused');
        });
        
        input.addEventListener('blur', function() {
            this.parentElement.classList.remove('focused');
            
            // Validation visuelle simple
            if (this.required && !this.value.trim()) {
                this.style.borderColor = '#F87171';
            } else {
                this.style.borderColor = '';
            }
        });
    });

    // Amélioration des boutons
    const buttons = document.querySelectorAll('.btn');
    buttons.forEach(button => {
        button.addEventListener('click', function(e) {
            // Effet de ripple
            const ripple = document.createElement('span');
            ripple.style.cssText = `
                position: absolute;
                border-radius: 50%;
                background: rgba(255, 255, 255, 0.6);
                transform: scale(0);
                animation: ripple 0.6s linear;
                pointer-events: none;
            `;
            
            const rect = this.getBoundingClientRect();
            const size = Math.max(rect.width, rect.height);
            ripple.style.width = ripple.style.height = size + 'px';
            ripple.style.left = (e.clientX - rect.left - size / 2) + 'px';
            ripple.style.top = (e.clientY - rect.top - size / 2) + 'px';
            
            this.style.position = 'relative';
            this.style.overflow = 'hidden';
            this.appendChild(ripple);
            
            setTimeout(() => {
                ripple.remove();
            }, 600);
        });
    });
}

// === GESTION DES ERREURS ===
window.addEventListener('error', function(e) {
    console.error('Erreur JavaScript détectée:', e.error);
});

// === STYLES CSS DYNAMIQUES ===
// Ajouter les animations CSS manquantes via JavaScript
const style = document.createElement('style');
style.textContent = `
    @keyframes ripple {
        to {
            transform: scale(2);
            opacity: 0;
        }
    }
    
    .form-group.focused label {
        color: var(--primary-green);
        transform: translateY(-2px);
    }
`;
document.head.appendChild(style);