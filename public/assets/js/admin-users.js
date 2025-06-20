/* ===================================
   ADMIN USERS - JAVASCRIPT
   ===================================*/

/**
 * Gestion des modales
 */

// Ouvrir une modale
function openModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.style.display = 'block';
        // Focus sur le premier champ de saisie si disponible
        const firstInput = modal.querySelector('input, select, textarea');
        if (firstInput) {
            setTimeout(() => firstInput.focus(), 100);
        }
        
        // Animation d'entrée
        const content = modal.querySelector('.modal-content');
        if (content) {
            content.style.transform = 'translate(-50%, -50%) scale(0.9)';
            content.style.opacity = '0';
            setTimeout(() => {
                content.style.transform = 'translate(-50%, -50%) scale(1)';
                content.style.opacity = '1';
                content.style.transition = 'all 0.3s ease';
            }, 10);
        }
    }
}

// Fermer une modale
function closeModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        const content = modal.querySelector('.modal-content');
        if (content) {
            content.style.transform = 'translate(-50%, -50%) scale(0.9)';
            content.style.opacity = '0';
            setTimeout(() => {
                modal.style.display = 'none';
                content.style.transform = '';
                content.style.opacity = '';
                content.style.transition = '';
            }, 300);
        } else {
            modal.style.display = 'none';
        }
        
        // Réinitialiser les formulaires
        const forms = modal.querySelectorAll('form');
        forms.forEach(form => form.reset());
        
        // Nettoyer les messages d'erreur
        clearFormErrors(modal);
    }
}

/**
 * Gestion des utilisateurs
 */

// Modifier un utilisateur
function editUser(user) {
    // Vérifier que l'objet user contient les données nécessaires
    if (!user || !user.user_id) {
        console.error('Données utilisateur invalides:', user);
        showNotification('Erreur: données utilisateur invalides', 'error');
        return;
    }

    // Remplir les champs du formulaire de modification
    const elements = {
        'edit_user_id': user.user_id,
        'edit_first_name': user.first_name || '',
        'edit_last_name': user.last_name || '',
        'edit_email': user.email || '',
        'edit_phone': user.phone || '',
        'edit_role': user.role || 'customer'
    };

    // Remplir tous les champs
    for (const [fieldId, value] of Object.entries(elements)) {
        const field = document.getElementById(fieldId);
        if (field) {
            field.value = value;
        }
    }

    // Gérer la checkbox is_active
    const isActiveCheckbox = document.getElementById('edit_is_active');
    if (isActiveCheckbox) {
        isActiveCheckbox.checked = user.is_active == 1;
    }

    // Ouvrir la modale de modification
    openModal('editModal');
}

// Ouvrir la modale de changement de mot de passe
function openPasswordModal(userId, userName) {
    if (!userId || !userName) {
        console.error('Paramètres invalides pour le changement de mot de passe');
        return;
    }

    // Remplir les informations
    const userIdField = document.getElementById('password_user_id');
    const userInfoText = document.getElementById('password-user-info');
    
    if (userIdField && userInfoText) {
        userIdField.value = userId;
        userInfoText.textContent = `Changer le mot de passe pour: ${userName}`;
    }

    // Réinitialiser les champs de mot de passe
    const newPasswordField = document.getElementById('new_password');
    const confirmPasswordField = document.getElementById('confirm_password');
    
    if (newPasswordField) newPasswordField.value = '';
    if (confirmPasswordField) confirmPasswordField.value = '';

    // Ouvrir la modale
    openModal('passwordModal');
}

// Supprimer un utilisateur
function deleteUser(userId, userName) {
    // Validation des paramètres
    if (!userId || !userName) {
        console.error('Paramètres invalides pour la suppression');
        return;
    }

    // Confirmation de suppression
    const confirmMessage = `⚠️ ATTENTION ⚠️\n\nÊtes-vous sûr de vouloir supprimer l'utilisateur "${userName}" ?\n\n` +
                          `Cette action est IRRÉVERSIBLE et supprimera :\n` +
                          `• Le compte utilisateur\n` +
                          `• Toutes ses données personnelles\n` +
                          `• L'historique de ses réservations\n\n` +
                          `Tapez "SUPPRIMER" pour confirmer :`;
    
    const userInput = prompt(confirmMessage);
    
    if (userInput === 'SUPPRIMER') {
        // Remplir le formulaire de suppression
        const userIdField = document.getElementById('delete_user_id');
        const deleteForm = document.getElementById('deleteForm');
        
        if (userIdField && deleteForm) {
            userIdField.value = userId;
            
            // Ajouter un indicateur de chargement
            showLoadingIndicator('Suppression en cours...');
            
            deleteForm.submit();
        } else {
            console.error('Formulaire de suppression non trouvé');
            showNotification('Erreur: formulaire de suppression non trouvé', 'error');
        }
    } else if (userInput !== null) {
        showNotification('Suppression annulée - texte de confirmation incorrect', 'warning');
    }
}

// Basculer le statut d'un utilisateur
function toggleUserStatus(userId, userName, currentStatus) {
    if (!userId || !userName) {
        console.error('Paramètres invalides pour le changement de statut');
        return;
    }

    const action = currentStatus ? 'désactiver' : 'activer';
    const confirmMessage = `Voulez-vous ${action} le compte de "${userName}" ?`;
    
    if (confirm(confirmMessage)) {
        const userIdField = document.getElementById('toggle_user_id');
        const toggleForm = document.getElementById('toggleStatusForm');
        
        if (userIdField && toggleForm) {
            userIdField.value = userId;
            
            // Ajouter un indicateur de chargement
            showLoadingIndicator(`${action.charAt(0).toUpperCase() + action.slice(1)}ation en cours...`);
            
            toggleForm.submit();
        } else {
            console.error('Formulaire de changement de statut non trouvé');
            showNotification('Erreur: formulaire non trouvé', 'error');
        }
    }
}

/**
 * Validation des formulaires
 */

// Valider le formulaire de mot de passe
function validatePasswordForm() {
    const newPassword = document.getElementById('new_password');
    const confirmPassword = document.getElementById('confirm_password');
    
    if (!newPassword || !confirmPassword) {
        return false;
    }
    
    // Vérifier que les mots de passe correspondent
    if (newPassword.value !== confirmPassword.value) {
        showFieldError(confirmPassword, 'Les mots de passe ne correspondent pas');
        return false;
    }
    
    // Vérifier la longueur minimale
    if (newPassword.value.length < 6) {
        showFieldError(newPassword, 'Le mot de passe doit contenir au moins 6 caractères');
        return false;
    }
    
    return true;
}

// Valider le formulaire de création/modification
function validateUserForm(formId) {
    const form = document.getElementById(formId);
    if (!form) return false;
    
    let isValid = true;
    
    // Valider l'email
    const emailField = form.querySelector('input[name="email"]');
    if (emailField && !isValidEmail(emailField.value)) {
        showFieldError(emailField, 'Veuillez saisir une adresse email valide');
        isValid = false;
    }
    
    // Valider le téléphone (optionnel mais si rempli, doit être valide)
    const phoneField = form.querySelector('input[name="phone"]');
    if (phoneField && phoneField.value && !isValidPhone(phoneField.value)) {
        showFieldError(phoneField, 'Veuillez saisir un numéro de téléphone valide');
        isValid = false;
    }
    
    // Valider le mot de passe (uniquement pour création)
    if (formId === 'createModal') {
        const passwordField = form.querySelector('input[name="password"]');
        if (passwordField && passwordField.value.length < 6) {
            showFieldError(passwordField, 'Le mot de passe doit contenir au moins 6 caractères');
            isValid = false;
        }
    }
    
    return isValid;
}

/**
 * Fonctions utilitaires
 */

// Valider une adresse email
function isValidEmail(email) {
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return emailRegex.test(email);
}

// Valider un numéro de téléphone
function isValidPhone(phone) {
    // Accepter différents formats de téléphone français
    const phoneRegex = /^(?:(?:\+33|0)[1-9](?:[0-9]{8}))$/;
    const cleanPhone = phone.replace(/[\s\-\.]/g, '');
    return phoneRegex.test(cleanPhone);
}

// Afficher une erreur sur un champ
function showFieldError(field, message) {
    // Supprimer les erreurs existantes
    clearFieldError(field);
    
    // Ajouter la classe d'erreur
    field.classList.add('field-error');
    field.style.borderColor = '#ef4444';
    
    // Créer le message d'erreur
    const errorDiv = document.createElement('div');
    errorDiv.className = 'field-error-message';
    errorDiv.style.color = '#ef4444';
    errorDiv.style.fontSize = '0.75rem';
    errorDiv.style.marginTop = '0.25rem';
    errorDiv.textContent = message;
    
    // Insérer après le champ
    field.parentNode.insertBefore(errorDiv, field.nextSibling);
    
    // Focus sur le champ en erreur
    field.focus();
}

// Supprimer l'erreur d'un champ
function clearFieldError(field) {
    field.classList.remove('field-error');
    field.style.borderColor = '';
    
    const errorMessage = field.parentNode.querySelector('.field-error-message');
    if (errorMessage) {
        errorMessage.remove();
    }
}

// Supprimer toutes les erreurs d'un formulaire
function clearFormErrors(container) {
    const errorFields = container.querySelectorAll('.field-error');
    const errorMessages = container.querySelectorAll('.field-error-message');
    
    errorFields.forEach(field => {
        field.classList.remove('field-error');
        field.style.borderColor = '';
    });
    
    errorMessages.forEach(message => message.remove());
}

// Afficher une notification
function showNotification(message, type = 'info') {
    // Créer la notification
    const notification = document.createElement('div');
    notification.className = `notification notification-${type}`;
    notification.style.cssText = `
        position: fixed;
        top: 2rem;
        right: 2rem;
        padding: 1rem 1.5rem;
        background: ${type === 'success' ? '#10b981' : type === 'error' ? '#ef4444' : type === 'warning' ? '#f59e0b' : '#3b82f6'};
        color: white;
        border-radius: 0.5rem;
        box-shadow: 0 10px 25px rgba(0,0,0,0.1);
        z-index: 10000;
        animation: slideInRight 0.3s ease;
        max-width: 300px;
        font-weight: 500;
    `;
    
    notification.textContent = message;
    
    // Ajouter au DOM
    document.body.appendChild(notification);
    
    // Supprimer automatiquement
    setTimeout(() => {
        notification.style.animation = 'slideOutRight 0.3s ease';
        setTimeout(() => {
            if (notification.parentNode) {
                notification.parentNode.removeChild(notification);
            }
        }, 300);
    }, 5000);
}

// Afficher un indicateur de chargement
function showLoadingIndicator(message = 'Chargement...') {
    const existingLoader = document.getElementById('loading-indicator');
    if (existingLoader) {
        existingLoader.remove();
    }
    
    const loader = document.createElement('div');
    loader.id = 'loading-indicator';
    loader.style.cssText = `
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0,0,0,0.5);
        display: flex;
        align-items: center;
        justify-content: center;
        z-index: 10001;
        backdrop-filter: blur(2px);
    `;
    
    loader.innerHTML = `
        <div style="background: white; padding: 2rem; border-radius: 0.5rem; text-align: center; box-shadow: 0 25px 50px rgba(0,0,0,0.25);">
            <div style="width: 40px; height: 40px; border: 4px solid #e5e7eb; border-top: 4px solid #10b981; border-radius: 50%; animation: spin 1s linear infinite; margin: 0 auto 1rem;"></div>
            <div style="color: #374151; font-weight: 600;">${message}</div>
        </div>
    `;
    
    document.body.appendChild(loader);
}

// Masquer l'indicateur de chargement
function hideLoadingIndicator() {
    const loader = document.getElementById('loading-indicator');
    if (loader) {
        loader.remove();
    }
}

/**
 * Gestionnaires d'événements
 */

// Fermer les modales en cliquant à l'extérieur
window.onclick = function(event) {
    if (event.target.classList.contains('modal')) {
        const modalId = event.target.id;
        closeModal(modalId);
    }
}

// Gestion des événements clavier
document.addEventListener('keydown', function(event) {
    // Fermer les modales avec la touche Escape
    if (event.key === 'Escape') {
        const modals = document.querySelectorAll('.modal');
        modals.forEach(modal => {
            if (modal.style.display === 'block') {
                closeModal(modal.id);
            }
        });
    }
});

/**
 * Initialisation au chargement de la page
 */
document.addEventListener('DOMContentLoaded', function() {
    console.log('👥 Admin Users - Interface initialisée');
    
    // Vérifier que tous les éléments essentiels sont présents
    const essentialElements = [
        'createModal',
        'editModal',
        'passwordModal',
        'deleteForm',
        'toggleStatusForm'
    ];
    
    essentialElements.forEach(elementId => {
        const element = document.getElementById(elementId);
        if (!element) {
            console.warn(`⚠️ Élément manquant: ${elementId}`);
        }
    });
    
    // Ajouter des écouteurs d'événements aux formulaires
    const forms = document.querySelectorAll('form');
    forms.forEach(form => {
        form.addEventListener('submit', function(event) {
            const formId = this.id || this.closest('.modal')?.id;
            
            // Validation spécifique selon le formulaire
            if (formId === 'passwordForm' || this.id === 'passwordForm') {
                if (!validatePasswordForm()) {
                    event.preventDefault();
                    return false;
                }
            } else if (formId && (formId.includes('create') || formId.includes('edit'))) {
                if (!validateUserForm(formId)) {
                    event.preventDefault();
                    return false;
                }
            }
            
            // Validation générale des champs requis
            const requiredFields = this.querySelectorAll('input[required], select[required]');
            let isValid = true;
            
            requiredFields.forEach(field => {
                if (!field.value.trim()) {
                    showFieldError(field, 'Ce champ est obligatoire');
                    isValid = false;
                }
            });
            
            if (!isValid) {
                event.preventDefault();
                return false;
            }
            
            // Afficher l'indicateur de chargement
            showLoadingIndicator('Traitement en cours...');
        });
    });
    
    // Améliorer l'UX des champs de saisie
    const inputs = document.querySelectorAll('input, select, textarea');
    inputs.forEach(input => {
        // Supprimer les erreurs lors de la saisie
        input.addEventListener('input', function() {
            clearFieldError(this);
        });
        
        // Validation en temps réel pour l'email
        if (input.type === 'email') {
            input.addEventListener('blur', function() {
                if (this.value && !isValidEmail(this.value)) {
                    showFieldError(this, 'Adresse email invalide');
                }
            });
        }
        
        // Validation en temps réel pour le téléphone
        if (input.name === 'phone') {
            input.addEventListener('blur', function() {
                if (this.value && !isValidPhone(this.value)) {
                    showFieldError(this, 'Numéro de téléphone invalide');
                }
            });
        }
    });
    
    // Synchronisation des mots de passe
    const newPasswordField = document.getElementById('new_password');
    const confirmPasswordField = document.getElementById('confirm_password');
    
    if (newPasswordField && confirmPasswordField) {
        const checkPasswordMatch = () => {
            if (confirmPasswordField.value && newPasswordField.value !== confirmPasswordField.value) {
                showFieldError(confirmPasswordField, 'Les mots de passe ne correspondent pas');
            } else {
                clearFieldError(confirmPasswordField);
            }
        };
        
        newPasswordField.addEventListener('input', checkPasswordMatch);
        confirmPasswordField.addEventListener('input', checkPasswordMatch);
    }
    
    // Améliorer les boutons d'action
    const actionButtons = document.querySelectorAll('.btn');
    actionButtons.forEach(button => {
        button.addEventListener('mouseenter', function() {
            this.style.transform = 'translateY(-1px)';
        });
        
        button.addEventListener('mouseleave', function() {
            this.style.transform = '';
        });
    });
    
    // Masquer l'indicateur de chargement au chargement de la page
    hideLoadingIndicator();
    
    console.log('✅ Interface administrateur des utilisateurs prête');
});

// Ajouter les animations CSS nécessaires
const style = document.createElement('style');
style.textContent = `
    @keyframes slideInRight {
        from { transform: translateX(100%); opacity: 0; }
        to { transform: translateX(0); opacity: 1; }
    }
    
    @keyframes slideOutRight {
        from { transform: translateX(0); opacity: 1; }
        to { transform: translateX(100%); opacity: 0; }
    }
    
    @keyframes spin {
        0% { transform: rotate(0deg); }
        100% { transform: rotate(360deg); }
    }
    
    .field-error {
        border-color: #ef4444 !important;
        box-shadow: 0 0 0 3px rgba(239, 68, 68, 0.1) !important;
    }
`;
document.head.appendChild(style);