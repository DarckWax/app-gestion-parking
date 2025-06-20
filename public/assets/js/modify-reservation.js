/**
 * ParkFinder - Scripts pour la modification de réservation
 * Fichier: modify-reservation.js
 */

document.addEventListener('DOMContentLoaded', function() {
    
    // === VALIDATION DES DATES ===
    initializeDateValidation();
    
    // === AMÉLIORATIONS UI ===
    initializeUIEnhancements();
    
    // === GESTION DES FORMULAIRES ===
    initializeFormHandling();
});

/**
 * Initialise la validation des dates
 */
function initializeDateValidation() {
    const startDateInput = document.getElementById('start_datetime');
    const endDateInput = document.getElementById('end_datetime');
    
    if (!startDateInput || !endDateInput) return;
    
    // Validation des dates côté client
    startDateInput.addEventListener('change', function() {
        const startValue = this.value;
        
        if (startValue) {
            // Vérifier que la date de début est dans le futur
            const now = new Date();
            const startDate = new Date(startValue);
            
            if (startDate <= now) {
                showValidationMessage('La date de début doit être dans le futur', 'error');
                this.value = '';
                return;
            }
            
            // Ajuster la date de fin minimum
            const minEndDate = new Date(startValue);
            minEndDate.setHours(minEndDate.getHours() + 1);
            endDateInput.min = minEndDate.toISOString().slice(0, 16);
            
            // Ajuster la date de fin si elle est antérieure
            if (endDateInput.value && new Date(endDateInput.value) <= new Date(startValue)) {
                endDateInput.value = minEndDate.toISOString().slice(0, 16);
                showValidationMessage('La date de fin a été ajustée automatiquement', 'warning');
            }
        }
    });
    
    endDateInput.addEventListener('change', function() {
        const endValue = this.value;
        const startValue = startDateInput.value;
        
        if (endValue && startValue) {
            const endDate = new Date(endValue);
            const startDate = new Date(startValue);
            
            // Vérifier que la date de fin est postérieure à la date de début
            if (endDate <= startDate) {
                showValidationMessage('La date de fin doit être postérieure à la date de début', 'error');
                this.value = '';
                return;
            }
            
            // Calculer la durée
            const duration = (endDate - startDate) / (1000 * 60 * 60); // en heures
            if (duration < 1) {
                showValidationMessage('La durée minimum est d\'1 heure', 'warning');
            } else if (duration > 24) {
                showValidationMessage('La durée maximum est de 24 heures', 'warning');
            }
        }
    });
}

/**
 * Initialise les améliorations de l'interface utilisateur
 */
function initializeUIEnhancements() {
    // Animation des boutons
    const buttons = document.querySelectorAll('.btn');
    buttons.forEach(button => {
        button.addEventListener('click', function(e) {
            // Effet de ripple
            createRippleEffect(this, e);
        });
    });
    
    // Amélioration des champs de formulaire
    const inputs = document.querySelectorAll('.form-input');
    inputs.forEach(input => {
        // Animation au focus
        input.addEventListener('focus', function() {
            this.parentElement.classList.add('focused');
        });
        
        input.addEventListener('blur', function() {
            this.parentElement.classList.remove('focused');
            
            // Validation visuelle
            if (this.checkValidity()) {
                this.classList.remove('invalid');
                this.classList.add('valid');
            } else {
                this.classList.remove('valid');
                this.classList.add('invalid');
            }
        });
        
        // Validation en temps réel
        input.addEventListener('input', function() {
            clearValidationMessage();
        });
    });
    
    // Amélioration du champ plaque d'immatriculation
    const plateInput = document.getElementById('vehicle_plate');
    if (plateInput) {
        plateInput.addEventListener('input', function() {
            // Formater automatiquement la plaque (format français)
            let value = this.value.toUpperCase().replace(/[^A-Z0-9]/g, '');
            
            if (value.length >= 2) {
                value = value.substring(0, 2) + '-' + value.substring(2);
            }
            if (value.length >= 6) {
                value = value.substring(0, 6) + '-' + value.substring(6, 8);
            }
            
            this.value = value;
        });
    }
}

/**
 * Initialise la gestion des formulaires
 */
function initializeFormHandling() {
    const form = document.querySelector('form');
    if (!form) return;
    
    form.addEventListener('submit', function(e) {
        // Validation finale avant soumission
        if (!validateForm()) {
            e.preventDefault();
            return false;
        }
        
        // Afficher un indicateur de chargement
        showLoadingState();
    });
}

/**
 * Valide le formulaire complet
 */
function validateForm() {
    const startInput = document.getElementById('start_datetime');
    const endInput = document.getElementById('end_datetime');
    
    if (!startInput.value || !endInput.value) {
        showValidationMessage('Veuillez remplir toutes les dates requises', 'error');
        return false;
    }
    
    const startDate = new Date(startInput.value);
    const endDate = new Date(endInput.value);
    const now = new Date();
    
    if (startDate <= now) {
        showValidationMessage('La date de début doit être dans le futur', 'error');
        startInput.focus();
        return false;
    }
    
    if (endDate <= startDate) {
        showValidationMessage('La date de fin doit être postérieure à la date de début', 'error');
        endInput.focus();
        return false;
    }
    
    return true;
}

/**
 * Affiche un message de validation
 */
function showValidationMessage(message, type = 'info') {
    // Supprimer les anciens messages
    clearValidationMessage();
    
    const messageDiv = document.createElement('div');
    messageDiv.className = `message ${type} validation-message`;
    messageDiv.innerHTML = `
        <span>${getMessageIcon(type)}</span>
        <span>${message}</span>
    `;
    
    // Insérer le message au début du formulaire
    const form = document.querySelector('form');
    if (form) {
        form.insertBefore(messageDiv, form.firstChild);
    }
    
    // Auto-suppression après 5 secondes pour les warnings
    if (type === 'warning') {
        setTimeout(() => {
            clearValidationMessage();
        }, 5000);
    }
}

/**
 * Supprime les messages de validation
 */
function clearValidationMessage() {
    const existingMessages = document.querySelectorAll('.validation-message');
    existingMessages.forEach(msg => msg.remove());
}

/**
 * Retourne l'icône appropriée pour le type de message
 */
function getMessageIcon(type) {
    switch (type) {
        case 'success': return '✅';
        case 'error': return '❌';
        case 'warning': return '⚠️';
        default: return 'ℹ️';
    }
}

/**
 * Crée un effet de ripple sur un bouton
 */
function createRippleEffect(button, event) {
    const ripple = document.createElement('span');
    ripple.style.cssText = `
        position: absolute;
        border-radius: 50%;
        background: rgba(255, 255, 255, 0.6);
        transform: scale(0);
        animation: ripple 0.6s linear;
        pointer-events: none;
    `;
    
    const rect = button.getBoundingClientRect();
    const size = Math.max(rect.width, rect.height);
    ripple.style.width = ripple.style.height = size + 'px';
    ripple.style.left = (event.clientX - rect.left - size / 2) + 'px';
    ripple.style.top = (event.clientY - rect.top - size / 2) + 'px';
    
    button.style.position = 'relative';
    button.style.overflow = 'hidden';
    button.appendChild(ripple);
    
    setTimeout(() => {
        ripple.remove();
    }, 600);
}

/**
 * Affiche un état de chargement
 */
function showLoadingState() {
    const submitButton = document.querySelector('button[type="submit"]');
    if (submitButton) {
        submitButton.disabled = true;
        submitButton.innerHTML = '⏳ Modification en cours...';
    }
}

/**
 * Gestion des erreurs globales
 */
window.addEventListener('error', function(e) {
    console.error('Erreur JavaScript détectée:', e.error);
    showValidationMessage('Une erreur inattendue s\'est produite', 'error');
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
    
    .form-group.focused .form-label {
        color: var(--primary-green);
        transform: translateY(-2px);
        transition: all 0.3s ease;
    }
    
    .form-input.valid {
        border-color: var(--primary-green);
        box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.1);
    }
    
    .form-input.invalid {
        border-color: #F87171;
        box-shadow: 0 0 0 3px rgba(248, 113, 113, 0.1);
    }
    
    .validation-message {
        animation: slideIn 0.3s ease-out;
    }
    
    @keyframes slideIn {
        from {
            opacity: 0;
            transform: translateY(-10px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }
`;
document.head.appendChild(style);