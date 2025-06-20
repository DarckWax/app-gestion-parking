/**
 * ===============================================
 * JAVASCRIPT SPÉCIALISÉ - GESTION DES RÉSERVATIONS
 * ===============================================
 */

// Variables globales
let searchTimeout;
let currentModal = null;

// ===============================================
// GESTION DES MODALES
// ===============================================

/**
 * Ouvrir une modale
 */
function openModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.classList.add('show');
        currentModal = modalId;
        document.body.style.overflow = 'hidden';
        
        // Focus sur le premier input
        const firstInput = modal.querySelector('input:not([type="hidden"]), select, textarea');
        if (firstInput) {
            setTimeout(() => firstInput.focus(), 100);
        }
    }
}

/**
 * Fermer une modale
 */
function closeModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.classList.remove('show');
        currentModal = null;
        document.body.style.overflow = '';
        
        // Réinitialiser les formulaires
        const forms = modal.querySelectorAll('form');
        forms.forEach(form => {
            if (form.id !== 'deleteForm') {
                form.reset();
                // Réinitialiser les champs cachés de recherche
                const hiddenInputs = form.querySelectorAll('input[type="hidden"]');
                hiddenInputs.forEach(input => {
                    if (input.name === 'user_id' || input.name === 'spot_id') {
                        input.value = '';
                    }
                });
            }
        });
        
        // Cacher les résultats de recherche
        hideSearchResults();
    }
}

/**
 * Fermer modale en cliquant sur l'overlay
 */
document.addEventListener('click', function(e) {
    if (e.target.classList.contains('modal')) {
        closeModal(currentModal);
    }
});

/**
 * Fermer modale avec Escape
 */
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape' && currentModal) {
        closeModal(currentModal);
    }
});

// ===============================================
// RECHERCHE DYNAMIQUE D'UTILISATEURS
// ===============================================

/**
 * Initialiser la recherche d'utilisateurs
 */
function initUserSearch() {
    const searchInput = document.getElementById('create_user_search');
    const resultsDiv = document.getElementById('user_search_results');
    const hiddenInput = document.getElementById('selected_user_id');

    if (!searchInput) return;

    searchInput.addEventListener('input', function() {
        const query = this.value.trim();
        
        if (query.length < 2) {
            hideSearchResults();
            hiddenInput.value = '';
            return;
        }

        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(() => {
            searchUsers(query, resultsDiv, hiddenInput, searchInput);
        }, 300);
    });

    // Cacher les résultats quand on clique ailleurs
    document.addEventListener('click', function(e) {
        if (!searchInput.contains(e.target) && !resultsDiv.contains(e.target)) {
            hideSearchResults();
        }
    });
}

/**
 * Rechercher des utilisateurs
 */
async function searchUsers(query, resultsDiv, hiddenInput, searchInput) {
    try {
        showLoadingInResults(resultsDiv, 'Recherche d\'utilisateurs...');

        const response = await fetch(`search-users.php?q=${encodeURIComponent(query)}`);
        const users = await response.json();

        if (users.length === 0) {
            showNoResults(resultsDiv, 'Aucun utilisateur trouvé');
            return;
        }

        displayUserResults(users, resultsDiv, hiddenInput, searchInput);
    } catch (error) {
        console.error('Erreur lors de la recherche d\'utilisateurs:', error);
        showErrorInResults(resultsDiv, 'Erreur lors de la recherche');
    }
}

/**
 * Afficher les résultats de recherche d'utilisateurs
 */
function displayUserResults(users, resultsDiv, hiddenInput, searchInput) {
    resultsDiv.innerHTML = '';
    resultsDiv.style.display = 'block';

    users.forEach(user => {
        const item = document.createElement('div');
        item.className = 'search-result-item';
        item.innerHTML = `
            <div class="search-result-primary">
                ${escapeHtml(user.first_name)} ${escapeHtml(user.last_name)}
            </div>
            <div class="search-result-secondary">
                ${escapeHtml(user.email)} • ${escapeHtml(user.phone)}
            </div>
        `;

        item.addEventListener('click', function() {
            hiddenInput.value = user.user_id;
            searchInput.value = `${user.first_name} ${user.last_name} (${user.email})`;
            hideSearchResults();
        });

        resultsDiv.appendChild(item);
    });
}

// ===============================================
// RECHERCHE DYNAMIQUE DE PLACES
// ===============================================

/**
 * Initialiser la recherche de places
 */
function initSpotSearch() {
    const searchInput = document.getElementById('create_spot_search');
    const resultsDiv = document.getElementById('spot_search_results');
    const hiddenInput = document.getElementById('selected_spot_id');

    if (!searchInput) return;

    searchInput.addEventListener('input', function() {
        const query = this.value.trim();
        
        if (query.length < 1) {
            hideSearchResults();
            hiddenInput.value = '';
            return;
        }

        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(() => {
            searchSpots(query, resultsDiv, hiddenInput, searchInput);
        }, 300);
    });
}

/**
 * Rechercher des places de parking
 */
async function searchSpots(query, resultsDiv, hiddenInput, searchInput) {
    try {
        showLoadingInResults(resultsDiv, 'Recherche de places...');

        const response = await fetch(`search-spots.php?q=${encodeURIComponent(query)}`);
        const spots = await response.json();

        if (spots.length === 0) {
            showNoResults(resultsDiv, 'Aucune place trouvée');
            return;
        }

        displaySpotResults(spots, resultsDiv, hiddenInput, searchInput);
    } catch (error) {
        console.error('Erreur lors de la recherche de places:', error);
        showErrorInResults(resultsDiv, 'Erreur lors de la recherche');
    }
}

/**
 * Afficher les résultats de recherche de places
 */
function displaySpotResults(spots, resultsDiv, hiddenInput, searchInput) {
    resultsDiv.innerHTML = '';
    resultsDiv.style.display = 'block';

    spots.forEach(spot => {
        const item = document.createElement('div');
        item.className = 'search-result-item';
        
        // Icône selon le type de place
        const typeIcon = getSpotTypeIcon(spot.spot_type);
        const statusColor = spot.status === 'available' ? '#10b981' : '#ef4444';
        
        item.innerHTML = `
            <div class="search-result-primary">
                ${typeIcon} Place ${escapeHtml(spot.spot_number)}
                <span style="color: ${statusColor}; margin-left: 0.5rem;">●</span>
            </div>
            <div class="search-result-secondary">
                Zone ${escapeHtml(spot.zone_section)} - Étage ${spot.floor_level} • ${getSpotTypeLabel(spot.spot_type)} • ${getStatusLabel(spot.status)}
            </div>
        `;

        // Désactiver si la place n'est pas disponible
        if (spot.status !== 'available') {
            item.style.opacity = '0.6';
            item.style.cursor = 'not-allowed';
        } else {
            item.addEventListener('click', function() {
                hiddenInput.value = spot.spot_id;
                searchInput.value = `Place ${spot.spot_number} (${spot.zone_section}${spot.floor_level})`;
                hideSearchResults();
            });
        }

        resultsDiv.appendChild(item);
    });
}

// ===============================================
// GESTION DES STATUTS DE RÉSERVATION
// ===============================================

/**
 * Ouvrir la modale de mise à jour du statut
 */
function updateReservationStatus(reservationId, reservationCode) {
    document.getElementById('status_reservation_id').value = reservationId;
    document.getElementById('status-reservation-info').innerHTML = `
        <div style="background: #f3f4f6; padding: 1rem; border-radius: 0.5rem; margin-bottom: 1rem;">
            <strong>Réservation:</strong> ${escapeHtml(reservationCode)}
        </div>
    `;
    
    // Gérer l'affichage conditionnel du champ de raison d'annulation
    const statusSelect = document.getElementById('new_status');
    const cancellationGroup = document.getElementById('cancellation_reason_group');
    
    statusSelect.addEventListener('change', function() {
        if (this.value === 'cancelled') {
            cancellationGroup.style.display = 'block';
            document.getElementById('cancellation_reason').required = true;
        } else {
            cancellationGroup.style.display = 'none';
            document.getElementById('cancellation_reason').required = false;
        }
    });
    
    openModal('statusModal');
}

/**
 * Supprimer une réservation
 */
function deleteReservation(reservationId, reservationCode) {
    if (confirm(`Êtes-vous sûr de vouloir supprimer définitivement la réservation ${reservationCode} ?\n\nCette action est irréversible.`)) {
        document.getElementById('delete_reservation_id').value = reservationId;
        document.getElementById('deleteForm').submit();
    }
}

// ===============================================
// AFFICHAGE DES DÉTAILS DE RÉSERVATION
// ===============================================

/**
 * Afficher les détails d'une réservation
 */
function viewReservationDetails(reservation) {
    const content = document.getElementById('reservation-details-content');
    
    content.innerHTML = `
        <div class="reservation-details">
            <div class="details-grid">
                <div class="detail-section">
                    <h4>🎫 Informations générales</h4>
                    <div class="detail-item">
                        <span class="detail-label">Code de réservation:</span>
                        <span class="detail-value">${escapeHtml(reservation.reservation_code)}</span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">Statut:</span>
                        <span class="detail-value">
                            <span class="status-badge ${getStatusBadgeClass(reservation.status)}">
                                ${getStatusLabel(reservation.status)}
                            </span>
                        </span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">Montant:</span>
                        <span class="detail-value">${formatCurrency(reservation.total_amount)}</span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">Paiement:</span>
                        <span class="detail-value">
                            <span class="payment-badge ${getPaymentStatusBadgeClass(reservation.payment_status)}">
                                ${getPaymentStatusLabel(reservation.payment_status)}
                            </span>
                        </span>
                    </div>
                </div>

                <div class="detail-section">
                    <h4>👤 Client</h4>
                    <div class="detail-item">
                        <span class="detail-label">Nom:</span>
                        <span class="detail-value">${escapeHtml(reservation.first_name)} ${escapeHtml(reservation.last_name)}</span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">Email:</span>
                        <span class="detail-value">${escapeHtml(reservation.email)}</span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">Téléphone:</span>
                        <span class="detail-value">${escapeHtml(reservation.phone || 'Non renseigné')}</span>
                    </div>
                </div>

                <div class="detail-section">
                    <h4>🅿️ Place de parking</h4>
                    <div class="detail-item">
                        <span class="detail-label">Numéro:</span>
                        <span class="detail-value">${escapeHtml(reservation.spot_number)}</span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">Zone:</span>
                        <span class="detail-value">${escapeHtml(reservation.zone_section)} - Étage ${reservation.floor_level}</span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">Type:</span>
                        <span class="detail-value">
                            <span class="type-badge type-${reservation.spot_type}">
                                ${getSpotTypeLabel(reservation.spot_type)}
                            </span>
                        </span>
                    </div>
                </div>

                <div class="detail-section">
                    <h4>📅 Période de réservation</h4>
                    <div class="detail-item">
                        <span class="detail-label">Début:</span>
                        <span class="detail-value">${formatDateTime(reservation.start_datetime)}</span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">Fin:</span>
                        <span class="detail-value">${formatDateTime(reservation.end_datetime)}</span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">Durée:</span>
                        <span class="detail-value">${calculateDuration(reservation.start_datetime, reservation.end_datetime)}</span>
                    </div>
                </div>
            </div>

            ${reservation.vehicle_plate || reservation.vehicle_model ? `
                <div class="detail-section">
                    <h4>🚗 Véhicule</h4>
                    ${reservation.vehicle_plate ? `
                        <div class="detail-item">
                            <span class="detail-label">Plaque:</span>
                            <span class="detail-value">${escapeHtml(reservation.vehicle_plate)}</span>
                        </div>
                    ` : ''}
                    ${reservation.vehicle_model ? `
                        <div class="detail-item">
                            <span class="detail-label">Modèle:</span>
                            <span class="detail-value">${escapeHtml(reservation.vehicle_model)}</span>
                        </div>
                    ` : ''}
                </div>
            ` : ''}

            ${reservation.special_requests ? `
                <div class="detail-section">
                    <h4>📝 Demandes spéciales</h4>
                    <p style="margin: 0; font-style: italic; color: #6b7280;">
                        "${escapeHtml(reservation.special_requests)}"
                    </p>
                </div>
            ` : ''}

            ${reservation.cancellation_reason ? `
                <div class="detail-section">
                    <h4>❌ Raison d'annulation</h4>
                    <p style="margin: 0; font-style: italic; color: #ef4444;">
                        "${escapeHtml(reservation.cancellation_reason)}"
                    </p>
                </div>
            ` : ''}

            <div class="detail-section">
                <h4>📊 Informations système</h4>
                <div class="detail-item">
                    <span class="detail-label">Créée le:</span>
                    <span class="detail-value">${formatDateTime(reservation.created_at)}</span>
                </div>
                ${reservation.updated_at && reservation.updated_at !== reservation.created_at ? `
                    <div class="detail-item">
                        <span class="detail-label">Modifiée le:</span>
                        <span class="detail-value">${formatDateTime(reservation.updated_at)}</span>
                    </div>
                ` : ''}
            </div>
        </div>
    `;
    
    openModal('detailsModal');
}

// ===============================================
// FONCTIONS UTILITAIRES
// ===============================================

/**
 * Cacher tous les résultats de recherche
 */
function hideSearchResults() {
    const results = document.querySelectorAll('.search-results');
    results.forEach(result => {
        result.style.display = 'none';
        result.innerHTML = '';
    });
}

/**
 * Afficher un état de chargement
 */
function showLoadingInResults(container, message) {
    container.innerHTML = `
        <div class="search-result-item" style="text-align: center; opacity: 0.7;">
            <div class="search-result-primary">⏳ ${message}</div>
        </div>
    `;
    container.style.display = 'block';
}

/**
 * Afficher un message d'absence de résultats
 */
function showNoResults(container, message) {
    container.innerHTML = `
        <div class="search-result-item" style="text-align: center; opacity: 0.7;">
            <div class="search-result-primary">❌ ${message}</div>
        </div>
    `;
    container.style.display = 'block';
}

/**
 * Afficher une erreur
 */
function showErrorInResults(container, message) {
    container.innerHTML = `
        <div class="search-result-item" style="text-align: center; color: #ef4444;">
            <div class="search-result-primary">⚠️ ${message}</div>
        </div>
    `;
    container.style.display = 'block';
}

/**
 * Échapper le HTML
 */
function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

/**
 * Formater une devise
 */
function formatCurrency(amount) {
    return new Intl.NumberFormat('fr-FR', {
        style: 'currency',
        currency: 'EUR'
    }).format(amount);
}

/**
 * Formater une date/heure
 */
function formatDateTime(dateString) {
    if (!dateString) return 'Non défini';
    
    return new Intl.DateTimeFormat('fr-FR', {
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
        hour: '2-digit',
        minute: '2-digit'
    }).format(new Date(dateString));
}

/**
 * Calculer la durée entre deux dates
 */
function calculateDuration(start, end) {
    if (!start || !end) return 'Non défini';
    
    const startDate = new Date(start);
    const endDate = new Date(end);
    const diffMs = endDate - startDate;
    const diffHours = Math.round(diffMs / (1000 * 60 * 60));
    const diffDays = Math.floor(diffHours / 24);
    
    if (diffDays > 0) {
        const remainingHours = diffHours % 24;
        return `${diffDays}j ${remainingHours}h`;
    } else {
        return `${diffHours}h`;
    }
}

/**
 * Obtenir l'icône pour un type de place
 */
function getSpotTypeIcon(type) {
    const icons = {
        'standard': '🅿️',
        'disabled': '♿',
        'electric': '🔌',
        'compact': '🚗',
        'reserved': '⭐'
    };
    return icons[type] || '🅿️';
}

/**
 * Obtenir le label pour un type de place
 */
function getSpotTypeLabel(type) {
    const labels = {
        'standard': 'Standard',
        'disabled': 'PMR',
        'electric': 'Électrique',
        'compact': 'Compact',
        'reserved': 'Réservée'
    };
    return labels[type] || type;
}

/**
 * Obtenir la classe CSS pour un statut
 */
function getStatusBadgeClass(status) {
    return `status-${status}`;
}

/**
 * Obtenir le label pour un statut
 */
function getStatusLabel(status) {
    const labels = {
        'pending': 'En attente',
        'confirmed': 'Confirmée',
        'active': 'Active',
        'completed': 'Terminée',
        'cancelled': 'Annulée',
        'no_show': 'Absent',
        'available': 'Disponible',
        'occupied': 'Occupée',
        'maintenance': 'Maintenance',
        'reserved': 'Réservée'
    };
    return labels[status] || status;
}

/**
 * Obtenir la classe CSS pour un statut de paiement
 */
function getPaymentStatusBadgeClass(status) {
    return `payment-${status}`;
}

/**
 * Obtenir le label pour un statut de paiement
 */
function getPaymentStatusLabel(status) {
    const labels = {
        'pending': 'En attente',
        'paid': 'Payé',
        'refunded': 'Remboursé',
        'failed': 'Échoué'
    };
    return labels[status] || status;
}

// ===============================================
// INITIALISATION
// ===============================================

/**
 * Initialiser tous les composants JavaScript
 */
document.addEventListener('DOMContentLoaded', function() {
    // Initialiser les recherches
    initUserSearch();
    initSpotSearch();
    
    // Validation des formulaires
    initFormValidation();
    
    console.log('🎉 Interface de gestion des réservations initialisée');
});

/**
 * Initialiser la validation des formulaires
 */
function initFormValidation() {
    // Validation des dates
    const startDateInput = document.getElementById('create_start_datetime');
    const endDateInput = document.getElementById('create_end_datetime');
    
    if (startDateInput && endDateInput) {
        // Définir la date minimum à maintenant
        const now = new Date();
        now.setMinutes(now.getMinutes() - now.getTimezoneOffset());
        const isoString = now.toISOString().slice(0, 16);
        startDateInput.min = isoString;
        
        startDateInput.addEventListener('change', function() {
            // La date de fin doit être après la date de début
            const startDate = new Date(this.value);
            startDate.setHours(startDate.getHours() + 1); // Minimum 1h de réservation
            endDateInput.min = startDate.toISOString().slice(0, 16);
            
            // Réajuster la date de fin si nécessaire
            if (endDateInput.value && new Date(endDateInput.value) <= new Date(this.value)) {
                endDateInput.value = startDate.toISOString().slice(0, 16);
            }
        });
    }
}

/**
 * Auto-calculer le montant basé sur la durée (optionnel)
 */
function calculateAmount() {
    const startInput = document.getElementById('create_start_datetime');
    const endInput = document.getElementById('create_end_datetime');
    const amountInput = document.getElementById('create_total_amount');
    
    if (!startInput.value || !endInput.value) return;
    
    const start = new Date(startInput.value);
    const end = new Date(endInput.value);
    const hours = Math.ceil((end - start) / (1000 * 60 * 60));
    
    // Tarif de base (peut être récupéré de l'API selon le type de place)
    const hourlyRate = 3.00;
    const estimatedAmount = hours * hourlyRate;
    
    if (estimatedAmount > 0) {
        amountInput.value = estimatedAmount.toFixed(2);
    }
}