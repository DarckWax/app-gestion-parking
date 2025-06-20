/**
 * ParkFinder - Scripts pour le traitement des réservations
 * Fichier: process-reservation.js
 */

document.addEventListener('DOMContentLoaded', function() {
    
    // === INITIALISATION ===
    initializeProcessing();
    
    // === GESTION DES ÉTATS ===
    setupProcessingStates();
    
    // === AMÉLIORATION DE L'UI ===
    enhanceUserInterface();
});

/**
 * Initialise le processus de traitement
 */
function initializeProcessing() {
    console.log('Initialisation du traitement de réservation');
    
    // Vérifier si on est en mode traitement
    const isProcessing = window.location.search.includes('processing=true');
    
    if (isProcessing) {
        showProcessingInterface();
    }
    
    // Démarrer la simulation de traitement si nécessaire
    if (document.querySelector('.processing-container')) {
        startProcessingSimulation();
    }
}

/**
 * Configure les états de traitement
 */
function setupProcessingStates() {
    // États possibles du traitement
    window.processingStates = {
        VALIDATING: {
            step: 1,
            title: 'Validation des données',
            message: 'Vérification de la disponibilité et des informations...',
            icon: '🔍'
        },
        CALCULATING: {
            step: 2,
            title: 'Calcul du prix',
            message: 'Calcul du tarif selon les règles de tarification...',
            icon: '💰'
        },
        CREATING: {
            step: 3,
            title: 'Création de la réservation',
            message: 'Génération de votre réservation...',
            icon: '📝'
        },
        COMPLETING: {
            step: 4,
            title: 'Finalisation',
            message: 'Préparation de la redirection vers le paiement...',
            icon: '✅'
        }
    };
}

/**
 * Améliore l'interface utilisateur
 */
function enhanceUserInterface() {
    // Animation des éléments
    animateElements();
    
    // Gestion des boutons
    enhanceButtons();
    
    // Ajout d'effets visuels
    addVisualEffects();
}

/**
 * Affiche l'interface de traitement
 */
function showProcessingInterface() {
    const container = document.querySelector('.container');
    if (container) {
        container.innerHTML = `
            <div class="processing-container">
                <div class="processing-icon">
                    <div class="spinner"></div>
                </div>
                <h1 class="processing-title">Traitement en cours...</h1>
                <p class="processing-message">Nous traitons votre réservation. Veuillez patienter.</p>
                
                <div class="progress-container">
                    <div class="progress-bar" id="progressBar" style="width: 0%"></div>
                </div>
                
                <div class="processing-steps" id="processingSteps">
                    <div class="processing-step" id="step1">
                        <div class="step-icon">1</div>
                        <div class="step-text">Validation des données</div>
                    </div>
                    <div class="processing-step" id="step2">
                        <div class="step-icon">2</div>
                        <div class="step-text">Calcul du prix</div>
                    </div>
                    <div class="processing-step" id="step3">
                        <div class="step-icon">3</div>
                        <div class="step-text">Création de la réservation</div>
                    </div>
                    <div class="processing-step" id="step4">
                        <div class="step-icon">4</div>
                        <div class="step-text">Redirection vers le paiement</div>
                    </div>
                </div>
                
                <div class="status-message info">
                    <span>ℹ️</span>
                    <span>Ne fermez pas cette page pendant le traitement.</span>
                </div>
            </div>
        `;
    }
}

/**
 * Démarre la simulation de traitement
 */
function startProcessingSimulation() {
    const steps = Object.values(window.processingStates);
    let currentStep = 0;
    
    function processNextStep() {
        if (currentStep < steps.length) {
            const step = steps[currentStep];
            
            // Mettre à jour l'interface
            updateProcessingStep(step, currentStep + 1);
            
            // Passer à l'étape suivante après un délai
            setTimeout(() => {
                currentStep++;
                processNextStep();
            }, 1500 + Math.random() * 1000); // Délai aléatoire pour plus de réalisme
        } else {
            // Traitement terminé
            completeProcessing();
        }
    }
    
    // Démarrer après un petit délai
    setTimeout(processNextStep, 500);
}

/**
 * Met à jour l'étape de traitement
 */
function updateProcessingStep(step, stepNumber) {
    // Mettre à jour le titre et le message
    const title = document.querySelector('.processing-title');
    const message = document.querySelector('.processing-message');
    const icon = document.querySelector('.processing-icon');
    
    if (title) title.textContent = step.title;
    if (message) message.textContent = step.message;
    if (icon) icon.innerHTML = `<span style="font-size: 2rem;">${step.icon}</span>`;
    
    // Mettre à jour la barre de progression
    const progressBar = document.getElementById('progressBar');
    if (progressBar) {
        const progress = (stepNumber / 4) * 100;
        progressBar.style.width = progress + '%';
    }
    
    // Mettre à jour les étapes
    const stepElement = document.getElementById(`step${stepNumber}`);
    if (stepElement) {
        stepElement.classList.add('active');
        
        // Marquer les étapes précédentes comme terminées
        for (let i = 1; i < stepNumber; i++) {
            const prevStep = document.getElementById(`step${i}`);
            if (prevStep) {
                prevStep.classList.remove('active');
                prevStep.classList.add('completed');
                prevStep.querySelector('.step-icon').textContent = '✓';
            }
        }
    }
}

/**
 * Termine le traitement
 */
function completeProcessing() {
    const container = document.querySelector('.processing-container');
    if (container) {
        container.innerHTML = `
            <div class="processing-icon" style="background: var(--primary-green);">
                ✅
            </div>
            <h1 class="processing-title">Réservation créée avec succès !</h1>
            <p class="processing-message">Votre réservation a été créée. Redirection vers le paiement...</p>
            
            <div class="status-message success">
                <span>✅</span>
                <span>Votre réservation est prête. Vous allez être redirigé automatiquement.</span>
            </div>
            
            <div class="btn-group">
                <button class="btn" onclick="redirectToPayment()">
                    💳 Procéder au paiement
                </button>
            </div>
        `;
        
        // Redirection automatique après 3 secondes
        setTimeout(() => {
            redirectToPayment();
        }, 3000);
    }
}

/**
 * Redirige vers la page de paiement
 */
function redirectToPayment() {
    // Récupérer l'ID de réservation depuis l'URL ou une variable globale
    const urlParams = new URLSearchParams(window.location.search);
    const reservationId = urlParams.get('reservation_id') || window.reservationId;
    
    if (reservationId) {
        window.location.href = `payment.php?reservation_id=${reservationId}`;
    } else {
        // Fallback vers la page des réservations
        window.location.href = 'my-reservations.php';
    }
}

/**
 * Anime les éléments de la page
 */
function animateElements() {
    // Animation d'apparition pour les éléments
    const elements = document.querySelectorAll('.processing-container, .status-message, .reservation-details');
    
    elements.forEach((element, index) => {
        element.style.opacity = '0';
        element.style.transform = 'translateY(20px)';
        element.style.transition = 'all 0.6s ease';
        
        setTimeout(() => {
            element.style.opacity = '1';
            element.style.transform = 'translateY(0)';
        }, index * 200);
    });
}

/**
 * Améliore les boutons
 */
function enhanceButtons() {
    const buttons = document.querySelectorAll('.btn');
    
    buttons.forEach(button => {
        button.addEventListener('click', function(e) {
            // Effet de ripple
            createRippleEffect(this, e);
        });
        
        // Prévenir les clics multiples
        button.addEventListener('click', function() {
            if (this.classList.contains('processing')) return false;
            
            this.classList.add('processing');
            const originalText = this.innerHTML;
            this.innerHTML = '<div class="spinner" style="width: 16px; height: 16px; margin-right: 8px;"></div> Traitement...';
            
            setTimeout(() => {
                this.classList.remove('processing');
                this.innerHTML = originalText;
            }, 2000);
        });
    });
}

/**
 * Ajoute des effets visuels
 */
function addVisualEffects() {
    // Effet de parallaxe subtil sur le scroll
    window.addEventListener('scroll', function() {
        const scrolled = window.pageYOffset;
        const rate = scrolled * -0.5;
        
        const elements = document.querySelectorAll('.processing-container');
        elements.forEach(element => {
            element.style.transform = `translateY(${rate}px)`;
        });
    });
    
    // Effet de hover sur les cartes
    const cards = document.querySelectorAll('.processing-container, .reservation-details');
    cards.forEach(card => {
        card.addEventListener('mouseenter', function() {
            this.style.transform = 'translateY(-4px)';
        });
        
        card.addEventListener('mouseleave', function() {
            this.style.transform = 'translateY(0)';
        });
    });
}

/**
 * Crée un effet de ripple sur un élément
 */
function createRippleEffect(element, event) {
    const ripple = document.createElement('span');
    ripple.style.cssText = `
        position: absolute;
        border-radius: 50%;
        background: rgba(255, 255, 255, 0.6);
        transform: scale(0);
        animation: ripple 0.6s linear;
        pointer-events: none;
    `;
    
    const rect = element.getBoundingClientRect();
    const size = Math.max(rect.width, rect.height);
    ripple.style.width = ripple.style.height = size + 'px';
    ripple.style.left = (event.clientX - rect.left - size / 2) + 'px';
    ripple.style.top = (event.clientY - rect.top - size / 2) + 'px';
    
    element.style.position = 'relative';
    element.style.overflow = 'hidden';
    element.appendChild(ripple);
    
    setTimeout(() => {
        ripple.remove();
    }, 600);
}

/**
 * Affiche un message d'erreur
 */
function showError(message) {
    const container = document.querySelector('.container');
    if (container) {
        const errorDiv = document.createElement('div');
        errorDiv.className = 'status-message error';
        errorDiv.innerHTML = `
            <span>❌</span>
            <span>${message}</span>
        `;
        
        container.insertBefore(errorDiv, container.firstChild);
        
        // Animation d'apparition
        errorDiv.style.opacity = '0';
        errorDiv.style.transform = 'translateY(-20px)';
        setTimeout(() => {
            errorDiv.style.transition = 'all 0.3s ease';
            errorDiv.style.opacity = '1';
            errorDiv.style.transform = 'translateY(0)';
        }, 100);
    }
}

/**
 * Affiche un message de succès
 */
function showSuccess(message) {
    const container = document.querySelector('.container');
    if (container) {
        const successDiv = document.createElement('div');
        successDiv.className = 'status-message success';
        successDiv.innerHTML = `
            <span>✅</span>
            <span>${message}</span>
        `;
        
        container.insertBefore(successDiv, container.firstChild);
        
        // Animation d'apparition
        successDiv.style.opacity = '0';
        successDiv.style.transform = 'translateY(-20px)';
        setTimeout(() => {
            successDiv.style.transition = 'all 0.3s ease';
            successDiv.style.opacity = '1';
            successDiv.style.transform = 'translateY(0)';
        }, 100);
    }
}

/**
 * Gestion des erreurs globales
 */
window.addEventListener('error', function(e) {
    console.error('Erreur détectée:', e.error);
    showError('Une erreur inattendue s\'est produite');
});

/**
 * Utilitaires pour le debug
 */
window.processingUtils = {
    showProcessing: showProcessingInterface,
    completeProcessing: completeProcessing,
    showError: showError,
    showSuccess: showSuccess,
    redirectToPayment: redirectToPayment
};

// === STYLES CSS DYNAMIQUES ===
const style = document.createElement('style');
style.textContent = `
    @keyframes ripple {
        to {
            transform: scale(2);
            opacity: 0;
        }
    }
    
    .btn.processing {
        opacity: 0.8;
        cursor: not-allowed;
        transform: none !important;
    }
    
    .btn.processing:hover {
        transform: none !important;
    }
`;
document.head.appendChild(style);

console.log('Process Reservation JavaScript loaded successfully');