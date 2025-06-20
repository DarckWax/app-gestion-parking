/* ===================================
   ADMIN PARKING SPOTS - JAVASCRIPT
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
    }
}

// Fermer une modale
function closeModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.style.display = 'none';
        // Réinitialiser le formulaire si c'est une modale de création
        if (modalId === 'createModal') {
            const form = modal.querySelector('form');
            if (form) {
                form.reset();
            }
        }
    }
}

/**
 * Gestion des places de parking
 */

// Modifier une place de parking
function editSpot(spot) {
    // Vérifier que l'objet spot contient les données nécessaires
    if (!spot || !spot.spot_id) {
        console.error('Données de place invalides:', spot);
        return;
    }

    // Remplir les champs du formulaire de modification
    const elements = {
        'edit_spot_id': spot.spot_id,
        'edit_spot_number': spot.spot_number || '',
        'edit_location': spot.location || '',
        'edit_type': spot.type || 'standard',
        'edit_hourly_rate': spot.hourly_rate || '',
        'edit_status': spot.status || 'available',
        'edit_description': spot.description || ''
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
        isActiveCheckbox.checked = spot.is_active == 1;
    }

    // Ouvrir la modale de modification
    openModal('editModal');
}

// Supprimer une place de parking
function deleteSpot(spotId, spotNumber) {
    // Validation des paramètres
    if (!spotId || !spotNumber) {
        console.error('Paramètres invalides pour la suppression');
        return;
    }

    // Confirmation de suppression
    const confirmMessage = `Êtes-vous sûr de vouloir supprimer la place "${spotNumber}" ?\n\nCette action est irréversible.`;
    
    if (confirm(confirmMessage)) {
        // Remplir le formulaire de suppression
        const spotIdField = document.getElementById('delete_spot_id');
        const deleteForm = document.getElementById('deleteForm');
        
        if (spotIdField && deleteForm) {
            spotIdField.value = spotId;
            deleteForm.submit();
        } else {
            console.error('Formulaire de suppression non trouvé');
        }
    }
}

/**
 * Gestionnaires d'événements
 */

// Fermer les modales en cliquant à l'extérieur
window.onclick = function(event) {
    if (event.target.classList.contains('modal')) {
        event.target.style.display = 'none';
    }
}

// Gestion des événements clavier
document.addEventListener('keydown', function(event) {
    // Fermer les modales avec la touche Escape
    if (event.key === 'Escape') {
        const modals = document.querySelectorAll('.modal');
        modals.forEach(modal => {
            if (modal.style.display === 'block') {
                modal.style.display = 'none';
            }
        });
    }
});

/**
 * Initialisation au chargement de la page
 */
document.addEventListener('DOMContentLoaded', function() {
    console.log('🅿️ Admin Parking Spots - Interface initialisée');
    
    // Vérifier que tous les éléments essentiels sont présents
    const essentialElements = [
        'createModal',
        'editModal',
        'deleteForm'
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
            // Validation basique avant soumission
            const requiredFields = form.querySelectorAll('input[required], select[required]');
            let isValid = true;
            
            requiredFields.forEach(field => {
                if (!field.value.trim()) {
                    field.style.borderColor = '#dc2626';
                    isValid = false;
                } else {
                    field.style.borderColor = '';
                }
            });
            
            if (!isValid) {
                event.preventDefault();
                alert('⚠️ Veuillez remplir tous les champs obligatoires');
                return false;
            }
        });
    });
    
    // Améliorer l'UX des champs numériques
    const numberInputs = document.querySelectorAll('input[type="number"]');
    numberInputs.forEach(input => {
        input.addEventListener('input', function() {
            // Validation en temps réel pour les tarifs
            if (this.name === 'hourly_rate') {
                const value = parseFloat(this.value);
                if (value < 0) {
                    this.value = 0;
                }
                if (value > 100) {
                    this.value = 100;
                }
            }
        });
    });
    
    // Améliorer les sélecteurs de tri
    const sortHeaders = document.querySelectorAll('.sort-header');
    sortHeaders.forEach(header => {
        header.addEventListener('mouseenter', function() {
            this.style.opacity = '0.8';
        });
        
        header.addEventListener('mouseleave', function() {
            this.style.opacity = '1';
        });
    });
    
    console.log('✅ Interface administrateur des places de parking prête');
});

/**
 * Utilitaires
 */

// Fonction pour formater les prix
function formatPrice(price) {
    return new Intl.NumberFormat('fr-FR', {
        style: 'currency',
        currency: 'EUR'
    }).format(price);
}

// Fonction pour valider les numéros de place
function validateSpotNumber(spotNumber) {
    // Format attendu: lettres suivies de chiffres (ex: A001, B123)
    const pattern = /^[A-Z]+\d+$/;
    return pattern.test(spotNumber.toUpperCase());
}

// Fonction pour capitaliser la première lettre
function capitalize(str) {
    return str.charAt(0).toUpperCase() + str.slice(1).toLowerCase();
}