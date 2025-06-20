/* ===================================
   ADMIN PAYMENTS - JAVASCRIPT
   ===================================*/

/**
 * Variables globales
 */
let searchTimeout = null;
let currentSearchResults = [];

/**
 * Gestion des modales
 */

// Ouvrir une modale
function openModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.style.display = 'block';
        
        // Focus sur le premier champ de saisie si disponible
        const firstInput = modal.querySelector('input:not([type="hidden"]), select, textarea');
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
        
        // Nettoyer les résultats de recherche
        clearSearchResults();
        
        // Nettoyer les messages d'erreur
        clearFormErrors(modal);
    }
}

/**
 * Gestion des remboursements
 */

// Ouvrir la modale de remboursement
function openRefundModal(payment) {
    if (!payment || !payment.payment_id) {
        console.error('Données de paiement invalides:', payment);
        showNotification('Erreur: données de paiement invalides', 'error');
        return;
    }

    // Remplir les informations du paiement
    const paymentInfo = document.getElementById('refund-payment-info');
    const paymentIdField = document.getElementById('refund_payment_id');
    const refundAmountField = document.getElementById('refund_amount');
    const maxRefundInfo = document.getElementById('max-refund-info');
    
    if (paymentInfo && paymentIdField && refundAmountField && maxRefundInfo) {
        const maxRefundAmount = parseFloat(payment.amount) - parseFloat(payment.total_refunded || 0);
        
        paymentInfo.innerHTML = `
            <div class="payment-detail-grid">
                <div class="payment-detail-card">
                    <div class="payment-detail-label">Transaction</div>
                    <div class="payment-detail-value">${escapeHtml(payment.transaction_id)}</div>
                </div>
                <div class="payment-detail-card">
                    <div class="payment-detail-label">Réservation</div>
                    <div class="payment-detail-value">${escapeHtml(payment.reservation_code)}</div>
                </div>
                <div class="payment-detail-card">
                    <div class="payment-detail-label">Client</div>
                    <div class="payment-detail-value">${escapeHtml(payment.first_name + ' ' + payment.last_name)}</div>
                </div>
                <div class="payment-detail-card">
                    <div class="payment-detail-label">Montant payé</div>
                    <div class="payment-detail-value">${formatCurrency(payment.amount)}</div>
                </div>
                <div class="payment-detail-card">
                    <div class="payment-detail-label">Déjà remboursé</div>
                    <div class="payment-detail-value">${formatCurrency(payment.total_refunded || 0)}</div>
                </div>
                <div class="payment-detail-card">
                    <div class="payment-detail-label">Remboursement max.</div>
                    <div class="payment-detail-value">${formatCurrency(maxRefundAmount)}</div>
                </div>
            </div>
        `;
        
        paymentIdField.value = payment.payment_id;
        refundAmountField.value = maxRefundAmount.toFixed(2);
        refundAmountField.max = maxRefundAmount;
        maxRefundInfo.textContent = `Maximum remboursable: ${formatCurrency(maxRefundAmount)}`;
    }

    // Ouvrir la modale
    openModal('refundModal');
}

/**
 * Gestion des détails de paiement
 */

// Afficher les détails d'un paiement
function viewPaymentDetails(payment) {
    if (!payment || !payment.payment_id) {
        console.error('Données de paiement invalides:', payment);
        showNotification('Erreur: données de paiement invalides', 'error');
        return;
    }

    const detailsContent = document.getElementById('payment-details-content');
    if (detailsContent) {
        detailsContent.innerHTML = `
            <div class="payment-detail-grid">
                <div class="payment-detail-card">
                    <div class="payment-detail-label">ID Paiement</div>
                    <div class="payment-detail-value">#${payment.payment_id}</div>
                </div>
                <div class="payment-detail-card">
                    <div class="payment-detail-label">Transaction ID</div>
                    <div class="payment-detail-value">${escapeHtml(payment.transaction_id)}</div>
                </div>
                <div class="payment-detail-card">
                    <div class="payment-detail-label">Code Réservation</div>
                    <div class="payment-detail-value">${escapeHtml(payment.reservation_code)}</div>
                </div>
                <div class="payment-detail-card">
                    <div class="payment-detail-label">Place de parking</div>
                    <div class="payment-detail-value">Place ${escapeHtml(payment.spot_number)}</div>
                </div>
                <div class="payment-detail-card">
                    <div class="payment-detail-label">Client</div>
                    <div class="payment-detail-value">
                        ${escapeHtml(payment.first_name + ' ' + payment.last_name)}<br>
                        <small style="color: var(--text-muted);">${escapeHtml(payment.email)}</small>
                    </div>
                </div>
                <div class="payment-detail-card">
                    <div class="payment-detail-label">Montant</div>
                    <div class="payment-detail-value">${formatCurrency(payment.amount)}</div>
                </div>
                <div class="payment-detail-card">
                    <div class="payment-detail-label">Méthode de paiement</div>
                    <div class="payment-detail-value">${getMethodLabel(payment.payment_method)}</div>
                </div>
                <div class="payment-detail-card">
                    <div class="payment-detail-label">Statut</div>
                    <div class="payment-detail-value">
                        <span class="status-badge ${getStatusBadgeClass(payment.status)}">
                            ${getStatusLabel(payment.status)}
                        </span>
                    </div>
                </div>
                <div class="payment-detail-card">
                    <div class="payment-detail-label">Date de création</div>
                    <div class="payment-detail-value">${formatDate(payment.created_at)}</div>
                </div>
                ${payment.total_refunded > 0 ? `
                <div class="payment-detail-card">
                    <div class="payment-detail-label">Montant remboursé</div>
                    <div class="payment-detail-value" style="color: #dc2626;">${formatCurrency(payment.total_refunded)}</div>
                </div>
                ` : ''}
                <div class="payment-detail-card">
                    <div class="payment-detail-label">Période de réservation</div>
                    <div class="payment-detail-value">
                        Du ${formatDate(payment.start_datetime)}<br>
                        Au ${formatDate(payment.end_datetime)}
                    </div>
                </div>
                <div class="payment-detail-card">
                    <div class="payment-detail-label">Montant réservation</div>
                    <div class="payment-detail-value">${formatCurrency(payment.reservation_amount)}</div>
                </div>
            </div>
        `;
    }

    // Ouvrir la modale de détails
    openModal('detailsModal');
}

/**
 * Recherche de réservations
 */

// Rechercher des réservations non payées
function searchReservations(query) {
    if (query.length < 2) {
        clearSearchResults();
        return;
    }

    // Débounce la recherche
    if (searchTimeout) {
        clearTimeout(searchTimeout);
    }

    searchTimeout = setTimeout(() => {
        performReservationSearch(query);
    }, 300);
}

// Effectuer la recherche
function performReservationSearch(query) {
    // Simuler une recherche AJAX (à remplacer par un vrai appel)
    showLoadingIndicator('Recherche en cours...');
    
    // Dans un vrai système, ceci serait un appel AJAX
    fetch(`search-unpaid-reservations.php?q=${encodeURIComponent(query)}`)
        .then(response => response.json())
        .then(data => {
            hideLoadingIndicator();
            displaySearchResults(data);
        })
        .catch(error => {
            hideLoadingIndicator();
            console.error('Erreur de recherche:', error);
            showNotification('Erreur lors de la recherche', 'error');
        });
}

// Afficher les résultats de recherche
function displaySearchResults(results) {
    const resultsContainer = document.getElementById('reservation_results');
    if (!resultsContainer) return;

    currentSearchResults = results;

    if (results.length === 0) {
        resultsContainer.innerHTML = `
            <div class="search-results-dropdown">
                <div class="search-result-item">
                    <div style="text-align: center; color: var(--text-muted);">
                        Aucune réservation non payée trouvée
                    </div>
                </div>
            </div>
        `;
        return;
    }

    const html = `
        <div class="search-results-dropdown">
            ${results.map(reservation => `
                <div class="search-result-item" onclick="selectReservation(${reservation.reservation_id})">
                    <div class="search-result-code">${escapeHtml(reservation.reservation_code)}</div>
                    <div class="search-result-details">
                        ${escapeHtml(reservation.client_name)} - ${escapeHtml(reservation.email)}<br>
                        Place ${escapeHtml(reservation.spot_number)} - ${formatCurrency(reservation.total_amount)}
                    </div>
                </div>
            `).join('')}
        </div>
    `;

    resultsContainer.innerHTML = html;
}

// Sélectionner une réservation
function selectReservation(reservationId) {
    const reservation = currentSearchResults.find(r => r.reservation_id == reservationId);
    if (!reservation) return;

    // Remplir les champs
    const reservationIdField = document.getElementById('selected_reservation_id');
    const amountField = document.getElementById('manual_amount');
    const searchField = document.getElementById('reservation_search');

    if (reservationIdField && amountField && searchField) {
        reservationIdField.value = reservation.reservation_id;
        amountField.value = reservation.total_amount;
        searchField.value = `${reservation.reservation_code} - ${reservation.client_name}`;
    }

    // Masquer les résultats
    clearSearchResults();
}

// Nettoyer les résultats de recherche
function clearSearchResults() {
    const resultsContainer = document.getElementById('reservation_results');
    if (resultsContainer) {
        resultsContainer.innerHTML = '';
    }
    currentSearchResults = [];
}

/**
 * Export des données
 */

// Exporter les paiements
function exportPayments() {
    const confirmExport = confirm('Voulez-vous exporter les données de paiements filtrées actuelles ?');
    if (!confirmExport) return;

    showLoadingIndicator('Génération de l\'export...');

    // Récupérer les paramètres de filtrage actuels
    const urlParams = new URLSearchParams(window.location.search);
    const exportParams = new URLSearchParams();
    
    // Ajouter les filtres actifs
    ['search', 'status', 'method', 'date_from', 'date_to'].forEach(param => {
        const value = urlParams.get(param);
        if (value) {
            exportParams.set(param, value);
        }
    });

    // Ajouter le paramètre d'export
    exportParams.set('export', 'csv');

    // Rediriger vers l'export
    window.location.href = `admin-payments.php?${exportParams.toString()}`;
    
    setTimeout(() => {
        hideLoadingIndicator();
    }, 2000);
}

/**
 * Fonctions utilitaires
 */

// Échapper le HTML
function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Formater une devise
function formatCurrency(amount) {
    return new Intl.NumberFormat('fr-FR', {
        style: 'currency',
        currency: 'EUR'
    }).format(amount);
}

// Formater une date
function formatDate(dateString) {
    if (!dateString) return 'N/A';
    const date = new Date(dateString);
    return date.toLocaleDateString('fr-FR', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    });
}

// Obtenir le libellé d'un statut
function getStatusLabel(status) {
    const labels = {
        'completed': 'Terminé',
        'pending': 'En attente',
        'failed': 'Échoué',
        'refunded': 'Remboursé',
        'partially_refunded': 'Partiellement remboursé'
    };
    return labels[status] || status;
}

// Obtenir la classe CSS d'un statut
function getStatusBadgeClass(status) {
    const classes = {
        'completed': 'status-completed',
        'pending': 'status-pending',
        'failed': 'status-failed',
        'refunded': 'status-refunded',
        'partially_refunded': 'status-partial-refund'
    };
    return classes[status] || 'status-unknown';
}

// Obtenir le libellé d'une méthode de paiement
function getMethodLabel(method) {
    const labels = {
        'card': 'Carte bancaire',
        'paypal': 'PayPal',
        'bank_transfer': 'Virement bancaire',
        'cash': 'Espèces',
        'manual': 'Manuel'
    };
    return labels[method] || method;
}

// Afficher une erreur sur un champ
function showFieldError(field, message) {
    clearFieldError(field);
    
    field.classList.add('field-error');
    field.style.borderColor = '#ef4444';
    
    const errorDiv = document.createElement('div');
    errorDiv.className = 'field-error-message';
    errorDiv.style.color = '#ef4444';
    errorDiv.style.fontSize = '0.75rem';
    errorDiv.style.marginTop = '0.25rem';
    errorDiv.textContent = message;
    
    field.parentNode.insertBefore(errorDiv, field.nextSibling);
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
        max-width: 350px;
        font-weight: 500;
    `;
    
    notification.textContent = message;
    document.body.appendChild(notification);
    
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
 * Validation des formulaires
 */

// Valider le formulaire de remboursement
function validateRefundForm() {
    const amountField = document.getElementById('refund_amount');
    const reasonField = document.getElementById('refund_reason');
    let isValid = true;

    if (!amountField.value || parseFloat(amountField.value) <= 0) {
        showFieldError(amountField, 'Veuillez saisir un montant valide');
        isValid = false;
    } else if (parseFloat(amountField.value) > parseFloat(amountField.max)) {
        showFieldError(amountField, 'Le montant dépasse le maximum remboursable');
        isValid = false;
    }

    if (!reasonField.value.trim()) {
        showFieldError(reasonField, 'Veuillez indiquer la raison du remboursement');
        isValid = false;
    }

    return isValid;
}

// Valider le formulaire de paiement manuel
function validateManualPaymentForm() {
    const reservationIdField = document.getElementById('selected_reservation_id');
    const amountField = document.getElementById('manual_amount');
    let isValid = true;

    if (!reservationIdField.value) {
        showNotification('Veuillez sélectionner une réservation', 'error');
        isValid = false;
    }

    if (!amountField.value || parseFloat(amountField.value) <= 0) {
        showFieldError(amountField, 'Veuillez saisir un montant valide');
        isValid = false;
    }

    return isValid;
}

/**
 * Gestionnaires d'événements
 */

// Fermer les modales en cliquant à l'extérieur
window.onclick = function(event) {
    if (event.target.classList.contains('modal')) {
        closeModal(event.target.id);
    }
}

// Gestion des événements clavier
document.addEventListener('keydown', function(event) {
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
    console.log('💳 Admin Payments - Interface initialisée');
    
    // Configurer la recherche de réservations
    const reservationSearchField = document.getElementById('reservation_search');
    if (reservationSearchField) {
        reservationSearchField.addEventListener('input', function() {
            const query = this.value.trim();
            searchReservations(query);
        });

        // Nettoyer les résultats quand le champ perd le focus
        reservationSearchField.addEventListener('blur', function() {
            setTimeout(() => {
                clearSearchResults();
            }, 200); // Délai pour permettre le clic sur un résultat
        });
    }

    // Validation des formulaires
    const refundForm = document.getElementById('refundForm');
    if (refundForm) {
        refundForm.addEventListener('submit', function(event) {
            if (!validateRefundForm()) {
                event.preventDefault();
                return false;
            }
            showLoadingIndicator('Traitement du remboursement...');
        });
    }

    const manualPaymentForm = document.getElementById('manualPaymentForm');
    if (manualPaymentForm) {
        manualPaymentForm.addEventListener('submit', function(event) {
            if (!validateManualPaymentForm()) {
                event.preventDefault();
                return false;
            }
            showLoadingIndicator('Enregistrement du paiement...');
        });
    }

    // Améliorer l'UX des champs de saisie
    const inputs = document.querySelectorAll('input, select, textarea');
    inputs.forEach(input => {
        input.addEventListener('input', function() {
            clearFieldError(this);
        });

        // Validation en temps réel pour les montants
        if (input.type === 'number' && input.name && (input.name.includes('amount') || input.name.includes('refund'))) {
            input.addEventListener('blur', function() {
                if (this.value && parseFloat(this.value) <= 0) {
                    showFieldError(this, 'Le montant doit être supérieur à 0');
                }
            });
        }
    });

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
    
    console.log('✅ Interface administrateur des paiements prête');
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