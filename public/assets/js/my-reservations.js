/**
 * ParkFinder - Scripts pour la page des réservations
 * Fichier: my-reservations.js
 */

document.addEventListener('DOMContentLoaded', function() {
    
    // === VARIABLES GLOBALES ===
    let currentReservationId = null;
    
    // === INITIALISATION ===
    initializeModals();
    initializeUIEnhancements();
    
    // === GESTION DES MODALS ===
    window.confirmDownloadReceipt = function(reservationId) {
        console.log('Opening modal for reservation:', reservationId);
        currentReservationId = reservationId;
        
        const overlay = document.getElementById('modalOverlay');
        const modal = document.getElementById('confirmModal');
        
        if (!overlay || !modal) {
            console.error('Modal elements not found!');
            alert('Erreur: Impossible d\'ouvrir la fenêtre de confirmation');
            return;
        }
        
        overlay.classList.add('show');
        modal.classList.add('show');
        
        document.body.style.overflow = 'hidden';
        
        // Fermer le modal en cliquant sur l'overlay
        overlay.onclick = closeConfirmModal;
    };
    
    window.closeConfirmModal = function() {
        console.log('Closing modal');
        const overlay = document.getElementById('modalOverlay');
        const modal = document.getElementById('confirmModal');
        
        if (overlay && modal) {
            overlay.classList.remove('show');
            modal.classList.remove('show');
        }
        
        document.body.style.overflow = '';
        currentReservationId = null;
        
        if (overlay) {
            overlay.onclick = null;
        }
    };
    
    window.downloadReceipt = function() {
        console.log('Starting download for reservation:', currentReservationId);
        
        if (!currentReservationId) {
            alert('Erreur: Aucune réservation sélectionnée');
            return;
        }
        
        showLoadingState();
        
        const downloadUrl = 'generate-receipt.php?reservation_id=' + currentReservationId;
        console.log('Download URL:', downloadUrl);
        
        // Créer un lien de téléchargement invisible
        const downloadLink = document.createElement('a');
        downloadLink.href = downloadUrl;
        downloadLink.download = 'recu-reservation-' + currentReservationId + '.pdf';
        downloadLink.style.display = 'none';
        document.body.appendChild(downloadLink);
        
        // Tenter d'ouvrir dans une nouvelle fenêtre
        const downloadWindow = window.open(downloadUrl, '_blank');
        
        if (!downloadWindow) {
            console.log('Popup blocked, using direct download');
            downloadLink.click();
        }
        
        // Nettoyer le lien temporaire
        setTimeout(() => {
            document.body.removeChild(downloadLink);
        }, 1000);
        
        // Fermer le modal après un délai
        setTimeout(function() {
            closeConfirmModal();
            
            if (downloadWindow && !downloadWindow.closed) {
                showSuccessMessage('📄 Reçu téléchargé avec succès !');
            } else {
                showInfoMessage('📄 Le téléchargement du reçu a été initié. Si rien ne se passe, vérifiez vos téléchargements ou autorisez les popups.');
            }
        }, 2000);
    };
    
    // === GESTION DES ANNULATIONS ===
    window.cancelReservation = function(reservationId) {
        const reason = prompt('Veuillez indiquer la raison de l\'annulation (optionnel):');
        
        if (reason !== null) { // L'utilisateur n'a pas annulé le prompt
            if (confirm('Êtes-vous sûr de vouloir annuler cette réservation ?')) {
                const button = event.target;
                const originalText = button.innerHTML;
                button.innerHTML = '⏳ Annulation...';
                button.disabled = true;
                
                // Effectuer la requête d'annulation
                fetch('cancel-reservation.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        reservation_id: reservationId,
                        reason: reason || 'Annulation à la demande du client'
                    })
                })
                .then(function(response) {
                    if (!response.ok) {
                        throw new Error('Erreur réseau: ' + response.status);
                    }
                    return response.json();
                })
                .then(function(data) {
                    if (data.success) {
                        let message = 'Réservation annulée avec succès!';
                        if (data.refund_info) {
                            message += '\n\n' + data.refund_info;
                        }
                        alert(message);
                        
                        // Recharger la page pour voir les changements
                        window.location.reload();
                    } else {
                        alert('Erreur lors de l\'annulation: ' + (data.error || 'Erreur inconnue'));
                        resetCancelButton(button, originalText);
                    }
                })
                .catch(function(error) {
                    console.error('Erreur:', error);
                    alert('Une erreur est survenue lors de l\'annulation: ' + error.message);
                    resetCancelButton(button, originalText);
                });
            }
        }
    };
    
    // === FONCTIONS UTILITAIRES ===
    function initializeModals() {
        const modal = document.getElementById('confirmModal');
        const overlay = document.getElementById('modalOverlay');
        
        console.log('Modal element:', modal);
        console.log('Overlay element:', overlay);
        
        if (!modal || !overlay) {
            console.error('Modal or overlay element not found!');
        }
        
        // Fermer le modal avec la touche Échap
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                closeConfirmModal();
            }
        });
    }
    
    function initializeUIEnhancements() {
        // Animation des cartes au survol
        const reservationCards = document.querySelectorAll('.reservation-card');
        reservationCards.forEach(card => {
            card.addEventListener('mouseenter', function() {
                this.style.transform = 'translateY(-4px)';
            });
            
            card.addEventListener('mouseleave', function() {
                this.style.transform = 'translateY(-2px)';
            });
        });
        
        // Animation des statistiques
        const statCards = document.querySelectorAll('.stat-card');
        statCards.forEach(card => {
            card.addEventListener('mouseenter', function() {
                this.style.transform = 'translateY(-6px)';
            });
            
            card.addEventListener('mouseleave', function() {
                this.style.transform = 'translateY(0)';
            });
        });
        
        // Amélioration des boutons
        const buttons = document.querySelectorAll('.btn');
        buttons.forEach(button => {
            button.addEventListener('click', function(e) {
                // Effet de ripple
                createRippleEffect(this, e);
            });
        });
        
        // Animation d'apparition des éléments
        animateElementsOnScroll();
    }
    
    function showLoadingState() {
        const modal = document.getElementById('confirmModal');
        
        if (modal) {
            modal.innerHTML = `
                <div class="modal-content">
                    <div class="modal-header">
                        <h3>📄 Génération du reçu</h3>
                    </div>
                    <div class="modal-loading">
                        <div class="spinner"></div>
                        <p><strong>Génération de votre reçu en cours...</strong></p>
                        <small>Veuillez patienter, le téléchargement va commencer automatiquement.</small>
                    </div>
                </div>
            `;
        }
    }
    
    function resetCancelButton(button, originalText) {
        button.innerHTML = originalText;
        button.disabled = false;
    }
    
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
    
    function animateElementsOnScroll() {
        const observerOptions = {
            threshold: 0.1,
            rootMargin: '0px 0px -50px 0px'
        };
        
        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.style.opacity = '1';
                    entry.target.style.transform = 'translateY(0)';
                }
            });
        }, observerOptions);
        
        // Observer les cartes de réservation
        document.querySelectorAll('.reservation-card, .stat-card').forEach(card => {
            card.style.opacity = '0';
            card.style.transform = 'translateY(20px)';
            card.style.transition = 'all 0.6s ease';
            observer.observe(card);
        });
    }
    
    function showSuccessMessage(message) {
        showNotification(message, 'success');
    }
    
    function showInfoMessage(message) {
        showNotification(message, 'info');
    }
    
    function showErrorMessage(message) {
        showNotification(message, 'error');
    }
    
    function showNotification(message, type = 'info') {
        // Créer une notification temporaire
        const notification = document.createElement('div');
        notification.className = `notification notification-${type}`;
        notification.innerHTML = `
            <span>${message}</span>
            <button onclick="this.parentElement.remove()" style="background: none; border: none; color: inherit; font-size: 1.2rem; cursor: pointer; margin-left: 1rem;">×</button>
        `;
        
        // Styles pour la notification
        notification.style.cssText = `
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 2000;
            padding: 1rem 1.5rem;
            border-radius: 0.75rem;
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1);
            max-width: 400px;
            animation: slideInRight 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: space-between;
        `;
        
        // Couleurs selon le type
        switch (type) {
            case 'success':
                notification.style.background = '#ECFDF5';
                notification.style.color = '#059669';
                notification.style.border = '1px solid #10B981';
                break;
            case 'error':
                notification.style.background = '#FEE2E2';
                notification.style.color = '#DC2626';
                notification.style.border = '1px solid #F87171';
                break;
            default:
                notification.style.background = '#EBF8FF';
                notification.style.color = '#2563EB';
                notification.style.border = '1px solid #60A5FA';
        }
        
        document.body.appendChild(notification);
        
        // Auto-suppression après 5 secondes
        setTimeout(() => {
            if (notification.parentElement) {
                notification.style.animation = 'slideOutRight 0.3s ease';
                setTimeout(() => {
                    notification.remove();
                }, 300);
            }
        }, 5000);
    }
    
    // === GESTION DES ERREURS ===
    window.addEventListener('error', function(e) {
        console.error('Erreur JavaScript détectée:', e.error);
        showErrorMessage('Une erreur inattendue s\'est produite');
    });
    
    // === FONCTIONS DE DÉBOGAGE ===
    console.log('My Reservations JavaScript loaded successfully');
    console.log('Available functions:', {
        confirmDownloadReceipt: typeof window.confirmDownloadReceipt,
        closeConfirmModal: typeof window.closeConfirmModal,
        downloadReceipt: typeof window.downloadReceipt,
        cancelReservation: typeof window.cancelReservation
    });
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
    
    @keyframes slideInRight {
        from {
            opacity: 0;
            transform: translateX(100%);
        }
        to {
            opacity: 1;
            transform: translateX(0);
        }
    }
    
    @keyframes slideOutRight {
        from {
            opacity: 1;
            transform: translateX(0);
        }
        to {
            opacity: 0;
            transform: translateX(100%);
        }
    }
    
    .notification {
        font-family: var(--font-primary);
        font-weight: 500;
    }
`;
document.head.appendChild(style);