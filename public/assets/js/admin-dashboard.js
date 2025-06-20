/**
 * ParkFinder - Scripts pour le tableau de bord administrateur
 * Fichier: admin-dashboard.js
 */

document.addEventListener('DOMContentLoaded', function() {
    
    // === INITIALISATION ===
    initializeAdminDashboard();
    
    // === GESTION DES STATISTIQUES ===
    loadDashboardStats();
    
    // === GESTION DES TABLEAUX ===
    initializeDataTables();
    
    // === GESTION DES MODALS ===
    initializeModals();
    
    // === GESTION DES NOTIFICATIONS ===
    initializeNotifications();
    
    // === MISE À JOUR AUTOMATIQUE ===
    setupAutoRefresh();
});

/**
 * Initialise le tableau de bord admin
 */
function initializeAdminDashboard() {
    console.log('Initialisation du tableau de bord administrateur');
    
    // Marquer l'élément de navigation actuel
    markActiveNavigation();
    
    // Initialiser les graphiques
    initializeCharts();
    
    // Configurer les raccourcis clavier
    setupKeyboardShortcuts();
    
    // Initialiser les tooltips
    initializeTooltips();
}

/**
 * Marque la navigation active
 */
function markActiveNavigation() {
    const currentPath = window.location.pathname;
    const navLinks = document.querySelectorAll('.nav-link');
    
    navLinks.forEach(link => {
        if (link.getAttribute('href') === currentPath || link.href === window.location.href) {
            link.classList.add('active');
        }
    });
}

/**
 * Charge les statistiques du tableau de bord
 */
function loadDashboardStats() {
    // Simuler le chargement des statistiques en temps réel
    const statCards = document.querySelectorAll('.stat-card');
    
    statCards.forEach(card => {
        const statNumber = card.querySelector('.stat-number');
        if (statNumber) {
            animateCounter(statNumber);
        }
    });
    
    // Charger les données réelles via AJAX
    fetchDashboardData();
}

/**
 * Récupère les données du tableau de bord
 */
function fetchDashboardData() {
    // Simuler un appel AJAX
    fetch('api/dashboard-stats.php')
        .then(response => response.json())
        .then(data => {
            updateDashboardStats(data);
        })
        .catch(error => {
            console.error('Erreur lors du chargement des statistiques:', error);
            showNotification('Erreur de chargement des statistiques', 'error');
        });
}

/**
 * Met à jour les statistiques du tableau de bord
 */
function updateDashboardStats(data) {
    // Mettre à jour les compteurs
    if (data.totalReservations) {
        updateStatCard('total-reservations', data.totalReservations);
    }
    
    if (data.activeReservations) {
        updateStatCard('active-reservations', data.activeReservations);
    }
    
    if (data.totalRevenue) {
        updateStatCard('total-revenue', data.totalRevenue);
    }
    
    if (data.occupancyRate) {
        updateStatCard('occupancy-rate', data.occupancyRate);
    }
    
    // Mettre à jour les graphiques
    updateCharts(data);
}

/**
 * Met à jour une carte de statistique
 */
function updateStatCard(cardId, newValue) {
    const card = document.getElementById(cardId);
    if (!card) return;
    
    const statNumber = card.querySelector('.stat-number');
    const statChange = card.querySelector('.stat-change');
    
    if (statNumber) {
        const currentValue = parseInt(statNumber.textContent.replace(/\D/g, ''));
        animateCounterTo(statNumber, currentValue, newValue);
    }
    
    // Mettre à jour l'indicateur de changement si disponible
    if (statChange && newValue.change !== undefined) {
        const changeValue = newValue.change;
        const changeElement = statChange.querySelector('.change-value');
        
        if (changeElement) {
            changeElement.textContent = Math.abs(changeValue) + '%';
        }
        
        statChange.className = 'stat-change ' + (changeValue >= 0 ? 'positive' : 'negative');
    }
}

/**
 * Anime un compteur jusqu'à une valeur donnée
 */
function animateCounter(element) {
    const finalValue = parseInt(element.textContent.replace(/\D/g, ''));
    animateCounterTo(element, 0, finalValue);
}

/**
 * Anime un compteur d'une valeur à une autre
 */
function animateCounterTo(element, startValue, endValue, duration = 2000) {
    const startTime = performance.now();
    const difference = endValue - startValue;
    
    function updateCounter(currentTime) {
        const elapsed = currentTime - startTime;
        const progress = Math.min(elapsed / duration, 1);
        
        // Fonction d'easing
        const easeOutQuart = 1 - Math.pow(1 - progress, 4);
        const currentValue = Math.round(startValue + (difference * easeOutQuart));
        
        // Formatage selon le type de donnée
        if (element.dataset.type === 'currency') {
            element.textContent = formatCurrency(currentValue);
        } else if (element.dataset.type === 'percentage') {
            element.textContent = currentValue + '%';
        } else {
            element.textContent = formatNumber(currentValue);
        }
        
        if (progress < 1) {
            requestAnimationFrame(updateCounter);
        }
    }
    
    requestAnimationFrame(updateCounter);
}

/**
 * Formate un nombre avec des séparateurs
 */
function formatNumber(num) {
    return new Intl.NumberFormat('fr-FR').format(num);
}

/**
 * Formate une valeur monétaire
 */
function formatCurrency(amount) {
    return new Intl.NumberFormat('fr-FR', {
        style: 'currency',
        currency: 'EUR'
    }).format(amount);
}

/**
 * Initialise les tableaux de données
 */
function initializeDataTables() {
    const tables = document.querySelectorAll('.data-table');
    
    tables.forEach(table => {
        makeTableSortable(table);
        addTableSearch(table);
        addTablePagination(table);
    });
}

/**
 * Rend un tableau triable
 */
function makeTableSortable(table) {
    const headers = table.querySelectorAll('th');
    
    headers.forEach((header, index) => {
        header.style.cursor = 'pointer';
        header.addEventListener('click', () => sortTable(table, index));
        
        // Ajouter l'indicateur de tri
        const sortIcon = document.createElement('span');
        sortIcon.className = 'sort-icon';
        sortIcon.innerHTML = ' ↕️';
        header.appendChild(sortIcon);
    });
}

/**
 * Trie un tableau par colonne
 */
function sortTable(table, columnIndex) {
    const tbody = table.querySelector('tbody');
    const rows = Array.from(tbody.querySelectorAll('tr'));
    const header = table.querySelectorAll('th')[columnIndex];
    const sortIcon = header.querySelector('.sort-icon');
    
    // Déterminer la direction du tri
    const isAscending = !header.classList.contains('sort-desc');
    
    // Réinitialiser tous les indicateurs de tri
    table.querySelectorAll('th').forEach(h => {
        h.classList.remove('sort-asc', 'sort-desc');
        const icon = h.querySelector('.sort-icon');
        if (icon) icon.innerHTML = ' ↕️';
    });
    
    // Trier les lignes
    rows.sort((a, b) => {
        const aValue = a.cells[columnIndex].textContent.trim();
        const bValue = b.cells[columnIndex].textContent.trim();
        
        // Essayer de convertir en nombre
        const aNum = parseFloat(aValue.replace(/[^\d.-]/g, ''));
        const bNum = parseFloat(bValue.replace(/[^\d.-]/g, ''));
        
        if (!isNaN(aNum) && !isNaN(bNum)) {
            return isAscending ? aNum - bNum : bNum - aNum;
        } else {
            return isAscending ? 
                aValue.localeCompare(bValue) : 
                bValue.localeCompare(aValue);
        }
    });
    
    // Mettre à jour l'affichage
    header.classList.add(isAscending ? 'sort-asc' : 'sort-desc');
    sortIcon.innerHTML = isAscending ? ' ↑' : ' ↓';
    
    // Réinsérer les lignes triées
    rows.forEach(row => tbody.appendChild(row));
}

/**
 * Ajoute la recherche à un tableau
 */
function addTableSearch(table) {
    const tableContainer = table.parentElement;
    const searchContainer = document.createElement('div');
    searchContainer.className = 'table-search';
    searchContainer.innerHTML = `
        <input type="text" 
               placeholder="🔍 Rechercher..." 
               class="form-input"
               style="margin-bottom: 1rem; max-width: 300px;">
    `;
    
    tableContainer.insertBefore(searchContainer, table);
    
    const searchInput = searchContainer.querySelector('input');
    searchInput.addEventListener('input', (e) => {
        filterTable(table, e.target.value);
    });
}

/**
 * Filtre un tableau selon une recherche
 */
function filterTable(table, searchTerm) {
    const rows = table.querySelectorAll('tbody tr');
    const term = searchTerm.toLowerCase();
    
    rows.forEach(row => {
        const text = row.textContent.toLowerCase();
        row.style.display = text.includes(term) ? '' : 'none';
    });
}

/**
 * Ajoute la pagination à un tableau
 */
function addTablePagination(table) {
    const rows = Array.from(table.querySelectorAll('tbody tr'));
    const rowsPerPage = 10;
    const totalPages = Math.ceil(rows.length / rowsPerPage);
    
    if (totalPages <= 1) return;
    
    const paginationContainer = document.createElement('div');
    paginationContainer.className = 'table-pagination';
    paginationContainer.style.cssText = `
        display: flex;
        justify-content: center;
        gap: 0.5rem;
        margin-top: 1rem;
    `;
    
    for (let i = 1; i <= totalPages; i++) {
        const pageBtn = document.createElement('button');
        pageBtn.className = 'btn btn-sm';
        pageBtn.textContent = i;
        pageBtn.onclick = () => showTablePage(table, i, rowsPerPage);
        paginationContainer.appendChild(pageBtn);
    }
    
    table.parentElement.appendChild(paginationContainer);
    
    // Afficher la première page
    showTablePage(table, 1, rowsPerPage);
}

/**
 * Affiche une page spécifique d'un tableau
 */
function showTablePage(table, page, rowsPerPage) {
    const rows = Array.from(table.querySelectorAll('tbody tr'));
    const startIndex = (page - 1) * rowsPerPage;
    const endIndex = startIndex + rowsPerPage;
    
    rows.forEach((row, index) => {
        row.style.display = (index >= startIndex && index < endIndex) ? '' : 'none';
    });
    
    // Mettre à jour les boutons de pagination
    const paginationBtns = table.parentElement.querySelectorAll('.table-pagination .btn');
    paginationBtns.forEach((btn, index) => {
        btn.classList.toggle('btn-secondary', index + 1 !== page);
    });
}

/**
 * Initialise les modals
 */
function initializeModals() {
    // Gérer l'ouverture des modals
    document.addEventListener('click', (e) => {
        if (e.target.hasAttribute('data-modal')) {
            const modalId = e.target.getAttribute('data-modal');
            openModal(modalId);
        }
        
        if (e.target.classList.contains('modal-close') || e.target.classList.contains('modal')) {
            closeModal(e.target.closest('.modal'));
        }
    });
    
    // Fermer les modals avec Échap
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
            const openModal = document.querySelector('.modal.show');
            if (openModal) {
                closeModal(openModal);
            }
        }
    });
}

/**
 * Ouvre un modal
 */
function openModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.classList.add('show');
        document.body.style.overflow = 'hidden';
    }
}

/**
 * Ferme un modal
 */
function closeModal(modal) {
    if (modal) {
        modal.classList.remove('show');
        document.body.style.overflow = '';
    }
}

/**
 * Initialise le système de notifications
 */
function initializeNotifications() {
    // Créer le conteneur de notifications s'il n'existe pas
    if (!document.getElementById('notification-container')) {
        const container = document.createElement('div');
        container.id = 'notification-container';
        container.style.cssText = `
            position: fixed;
            top: 1rem;
            right: 1rem;
            z-index: 1001;
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        `;
        document.body.appendChild(container);
    }
}

/**
 * Affiche une notification
 */
function showNotification(message, type = 'info', duration = 5000) {
    const container = document.getElementById('notification-container');
    if (!container) return;
    
    const notification = document.createElement('div');
    notification.className = `notification ${type}`;
    
    const icons = {
        success: '✅',
        error: '❌',
        warning: '⚠️',
        info: 'ℹ️'
    };
    
    notification.innerHTML = `
        <div style="display: flex; align-items: center; gap: 0.5rem;">
            <span style="font-size: 1.2rem;">${icons[type]}</span>
            <span>${message}</span>
            <button onclick="this.parentElement.parentElement.remove()" 
                    style="margin-left: auto; background: none; border: none; font-size: 1.2rem; cursor: pointer;">
                ×
            </button>
        </div>
    `;
    
    container.appendChild(notification);
    
    // Auto-suppression
    setTimeout(() => {
        if (notification.parentElement) {
            notification.remove();
        }
    }, duration);
}

/**
 * Initialise les graphiques
 */
function initializeCharts() {
    const chartContainers = document.querySelectorAll('.chart-container');
    
    chartContainers.forEach(container => {
        const chartType = container.dataset.chartType || 'line';
        const chartData = container.dataset.chartData || '';
        
        // Ici vous pourriez intégrer Chart.js ou une autre librairie
        // Pour l'instant, on affiche un placeholder
        if (!container.querySelector('.chart-placeholder')) {
            container.innerHTML = `
                <div class="chart-placeholder">
                    📊 Graphique ${chartType} - Données: ${chartData}
                </div>
            `;
        }
    });
}

/**
 * Met à jour les graphiques
 */
function updateCharts(data) {
    // Implémenter la mise à jour des graphiques avec les nouvelles données
    console.log('Mise à jour des graphiques avec:', data);
}

/**
 * Configure les raccourcis clavier
 */
function setupKeyboardShortcuts() {
    document.addEventListener('keydown', (e) => {
        // Ctrl+R pour actualiser les données
        if (e.ctrlKey && e.key === 'r') {
            e.preventDefault();
            refreshDashboard();
        }
        
        // Ctrl+N pour nouvelle réservation
        if (e.ctrlKey && e.key === 'n') {
            e.preventDefault();
            openModal('new-reservation-modal');
        }
        
        // Ctrl+F pour recherche
        if (e.ctrlKey && e.key === 'f') {
            e.preventDefault();
            const searchInput = document.querySelector('.table-search input');
            if (searchInput) {
                searchInput.focus();
            }
        }
    });
}

/**
 * Initialise les tooltips
 */
function initializeTooltips() {
    const elementsWithTooltip = document.querySelectorAll('[data-tooltip]');
    
    elementsWithTooltip.forEach(element => {
        element.addEventListener('mouseenter', showTooltip);
        element.addEventListener('mouseleave', hideTooltip);
    });
}

/**
 * Affiche un tooltip
 */
function showTooltip(e) {
    const element = e.target;
    const tooltipText = element.getAttribute('data-tooltip');
    
    const tooltip = document.createElement('div');
    tooltip.className = 'tooltip';
    tooltip.textContent = tooltipText;
    tooltip.style.cssText = `
        position: absolute;
        background: var(--primary-black);
        color: var(--white);
        padding: 0.5rem;
        border-radius: 0.375rem;
        font-size: 0.875rem;
        z-index: 1002;
        pointer-events: none;
    `;
    
    document.body.appendChild(tooltip);
    
    const rect = element.getBoundingClientRect();
    tooltip.style.top = (rect.top - tooltip.offsetHeight - 5) + 'px';
    tooltip.style.left = (rect.left + rect.width / 2 - tooltip.offsetWidth / 2) + 'px';
    
    element._tooltip = tooltip;
}

/**
 * Cache un tooltip
 */
function hideTooltip(e) {
    const element = e.target;
    if (element._tooltip) {
        element._tooltip.remove();
        delete element._tooltip;
    }
}

/**
 * Configure l'actualisation automatique
 */
function setupAutoRefresh() {
    const autoRefreshInterval = 30000; // 30 secondes
    
    setInterval(() => {
        fetchDashboardData();
    }, autoRefreshInterval);
    
    // Indicateur de dernière mise à jour
    updateLastRefreshTime();
    setInterval(updateLastRefreshTime, 1000);
}

/**
 * Met à jour l'indicateur de dernière actualisation
 */
function updateLastRefreshTime() {
    const lastRefreshElement = document.getElementById('last-refresh');
    if (lastRefreshElement) {
        const now = new Date();
        lastRefreshElement.textContent = `Dernière mise à jour: ${now.toLocaleTimeString('fr-FR')}`;
    }
}

/**
 * Actualise le tableau de bord
 */
function refreshDashboard() {
    showNotification('Actualisation des données...', 'info');
    
    // Ajouter un effet de chargement
    const statCards = document.querySelectorAll('.stat-card');
    statCards.forEach(card => {
        card.style.opacity = '0.7';
    });
    
    fetchDashboardData().then(() => {
        statCards.forEach(card => {
            card.style.opacity = '1';
        });
        showNotification('Données mises à jour', 'success');
    });
}

/**
 * Gestion des actions CRUD
 */
function handleCRUDAction(action, entityType, entityId = null) {
    switch (action) {
        case 'create':
            openModal(`create-${entityType}-modal`);
            break;
        case 'edit':
            openModal(`edit-${entityType}-modal`);
            loadEntityData(entityType, entityId);
            break;
        case 'delete':
            if (confirm(`Êtes-vous sûr de vouloir supprimer cet élément ?`)) {
                deleteEntity(entityType, entityId);
            }
            break;
        case 'view':
            openModal(`view-${entityType}-modal`);
            loadEntityData(entityType, entityId);
            break;
    }
}

/**
 * Charge les données d'une entité
 */
function loadEntityData(entityType, entityId) {
    fetch(`api/${entityType}/${entityId}`)
        .then(response => response.json())
        .then(data => {
            populateForm(data);
        })
        .catch(error => {
            showNotification(`Erreur lors du chargement: ${error.message}`, 'error');
        });
}

/**
 * Supprime une entité
 */
function deleteEntity(entityType, entityId) {
    fetch(`api/${entityType}/${entityId}`, {
        method: 'DELETE'
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showNotification('Élément supprimé avec succès', 'success');
            refreshDashboard();
        } else {
            showNotification('Erreur lors de la suppression', 'error');
        }
    })
    .catch(error => {
        showNotification(`Erreur: ${error.message}`, 'error');
    });
}

/**
 * Remplit un formulaire avec des données
 */
function populateForm(data) {
    Object.keys(data).forEach(key => {
        const input = document.querySelector(`[name="${key}"]`);
        if (input) {
            input.value = data[key];
        }
    });
}

/**
 * Fonctions utilitaires globales
 */
window.adminDashboard = {
    showNotification,
    refreshDashboard,
    openModal,
    closeModal,
    handleCRUDAction
};

console.log('Admin Dashboard JavaScript loaded successfully');