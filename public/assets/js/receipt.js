/**
 * ParkFinder - Scripts pour la génération de reçu PDF
 * Fichier: receipt.js
 */

document.addEventListener('DOMContentLoaded', function() {
    
    // === INITIALISATION ===
    initializeReceiptPage();
    
    // === GESTION DE L'IMPRESSION ===
    setupPrintHandlers();
    
    // === AMÉLIORATION DE L'UI ===
    enhanceReceiptDisplay();
});

/**
 * Initialise la page de reçu
 */
function initializeReceiptPage() {
    console.log('Initialisation de la page de reçu PDF');
    
    // Vérifier si le document est prêt
    if (document.readyState === 'complete') {
        handleDocumentReady();
    } else {
        window.addEventListener('load', handleDocumentReady);
    }
}

/**
 * Gère les actions une fois le document chargé
 */
function handleDocumentReady() {
    // Afficher un message de succès
    showSuccessMessage();
    
    // Proposer l'impression automatique après un délai
    setTimeout(() => {
        offerAutoPrint();
    }, 1000);
    
    // Ajouter les handlers pour les interactions
    addInteractionHandlers();
}

/**
 * Configure les gestionnaires d'impression
 */
function setupPrintHandlers() {
    // Raccourci clavier pour imprimer
    document.addEventListener('keydown', function(e) {
        if ((e.ctrlKey || e.metaKey) && e.key === 'p') {
            e.preventDefault();
            printReceipt();
        }
    });
    
    // Bouton d'impression s'il existe
    const printButton = document.getElementById('printButton');
    if (printButton) {
        printButton.addEventListener('click', printReceipt);
    }
}

/**
 * Améliore l'affichage du reçu
 */
function enhanceReceiptDisplay() {
    // Animation d'apparition
    animateReceiptElements();
    
    // Amélioration de la lisibilité
    enhanceReadability();
    
    // Ajout d'éléments interactifs
    addInteractiveElements();
}

/**
 * Anime les éléments du reçu
 */
function animateReceiptElements() {
    const sections = document.querySelectorAll('.section');
    
    sections.forEach((section, index) => {
        section.style.opacity = '0';
        section.style.transform = 'translateY(20px)';
        section.style.transition = 'all 0.5s ease';
        
        setTimeout(() => {
            section.style.opacity = '1';
            section.style.transform = 'translateY(0)';
        }, index * 100);
    });
}

/**
 * Améliore la lisibilité du document
 */
function enhanceReadability() {
    // Mettre en évidence les montants
    const amounts = document.querySelectorAll('.detail-value');
    amounts.forEach(amount => {
        if (amount.textContent.includes('€')) {
            amount.style.fontWeight = 'bold';
            amount.style.color = '#059669';
        }
    });
    
    // Améliorer les codes de réservation
    const codes = document.querySelectorAll('.detail-value');
    codes.forEach(code => {
        if (code.textContent.match(/^[A-Z0-9\-]+$/)) {
            code.style.fontFamily = 'monospace';
            code.style.background = '#f3f4f6';
            code.style.padding = '2px 6px';
            code.style.borderRadius = '4px';
        }
    });
}

/**
 * Ajoute des éléments interactifs
 */
function addInteractiveElements() {
    // Ajouter un bouton de téléchargement si pas déjà présent
    if (!document.getElementById('downloadButton')) {
        addDownloadButton();
    }
    
    // Ajouter un bouton d'impression si pas déjà présent
    if (!document.getElementById('printButton')) {
        addPrintButton();
    }
    
    // Ajouter les informations sur les raccourcis
    addKeyboardShortcuts();
}

/**
 * Ajoute un bouton de téléchargement
 */
function addDownloadButton() {
    const printInfo = document.querySelector('.print-info');
    if (printInfo) {
        const downloadButton = document.createElement('button');
        downloadButton.id = 'downloadButton';
        downloadButton.innerHTML = '💾 Sauvegarder en PDF';
        downloadButton.style.cssText = `
            background: #10B981;
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 6px;
            cursor: pointer;
            margin: 0 8px;
            font-size: 12px;
            transition: background 0.3s ease;
        `;
        
        downloadButton.addEventListener('click', saveToPDF);
        downloadButton.addEventListener('mouseenter', function() {
            this.style.background = '#059669';
        });
        downloadButton.addEventListener('mouseleave', function() {
            this.style.background = '#10B981';
        });
        
        printInfo.appendChild(downloadButton);
    }
}

/**
 * Ajoute un bouton d'impression
 */
function addPrintButton() {
    const printInfo = document.querySelector('.print-info');
    if (printInfo) {
        const printButton = document.createElement('button');
        printButton.id = 'printButton';
        printButton.innerHTML = '🖨️ Imprimer';
        printButton.style.cssText = `
            background: #6B7280;
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 6px;
            cursor: pointer;
            margin: 0 8px;
            font-size: 12px;
            transition: background 0.3s ease;
        `;
        
        printButton.addEventListener('click', printReceipt);
        printButton.addEventListener('mouseenter', function() {
            this.style.background = '#4B5563';
        });
        printButton.addEventListener('mouseleave', function() {
            this.style.background = '#6B7280';
        });
        
        printInfo.appendChild(printButton);
    }
}

/**
 * Ajoute les informations sur les raccourcis clavier
 */
function addKeyboardShortcuts() {
    const printInfo = document.querySelector('.print-info');
    if (printInfo) {
        const shortcuts = document.createElement('div');
        shortcuts.style.cssText = `
            margin-top: 10px;
            font-size: 11px;
            color: #6B7280;
            font-style: italic;
        `;
        shortcuts.innerHTML = 'Raccourcis: Ctrl+P pour imprimer • Ctrl+S pour sauvegarder';
        printInfo.appendChild(shortcuts);
    }
}

/**
 * Affiche un message de succès
 */
function showSuccessMessage() {
    const message = document.createElement('div');
    message.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        background: #ECFDF5;
        color: #059669;
        padding: 12px 20px;
        border-radius: 8px;
        border: 1px solid #10B981;
        box-shadow: 0 4px 12px rgba(16, 185, 129, 0.2);
        z-index: 1000;
        font-size: 14px;
        font-weight: 500;
        animation: slideInRight 0.5s ease;
    `;
    message.innerHTML = '✅ Reçu généré avec succès !';
    
    document.body.appendChild(message);
    
    // Supprimer le message après 3 secondes
    setTimeout(() => {
        message.style.animation = 'slideOutRight 0.5s ease';
        setTimeout(() => {
            message.remove();
        }, 500);
    }, 3000);
}

/**
 * Propose l'impression automatique
 */
function offerAutoPrint() {
    // Ne proposer l'impression que si on n'est pas déjà en train d'imprimer
    if (!window.matchMedia('print').matches) {
        if (confirm('📄 Voulez-vous imprimer ce reçu maintenant ?')) {
            printReceipt();
        }
    }
}

/**
 * Lance l'impression du reçu
 */
function printReceipt() {
    // Masquer les éléments non imprimables
    const printInfo = document.querySelector('.print-info');
    if (printInfo) {
        printInfo.style.display = 'none';
    }
    
    // Optimiser pour l'impression
    document.body.classList.add('printing');
    
    // Lancer l'impression
    window.print();
    
    // Restaurer l'affichage après impression
    setTimeout(() => {
        if (printInfo) {
            printInfo.style.display = 'block';
        }
        document.body.classList.remove('printing');
    }, 1000);
}

/**
 * Sauvegarde le document en PDF
 */
function saveToPDF() {
    // Utiliser l'API de sauvegarde du navigateur
    if (window.chrome && window.chrome.runtime) {
        // Chrome - utiliser l'impression avec "Sauvegarder en PDF"
        printReceipt();
    } else {
        // Autres navigateurs - rediriger vers la génération PDF côté serveur
        const url = new URL(window.location.href);
        url.searchParams.set('format', 'pdf');
        url.searchParams.set('download', '1');
        
        const link = document.createElement('a');
        link.href = url.toString();
        link.download = `Recu_ParkFinder_${getReceiptCode()}.pdf`;
        link.click();
    }
}

/**
 * Ajoute des handlers d'interaction
 */
function addInteractionHandlers() {
    // Gestionnaire pour la touche Échap
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            if (confirm('Voulez-vous fermer cette page ?')) {
                window.close();
            }
        }
    });
    
    // Gestionnaire pour Ctrl+S (sauvegarder)
    document.addEventListener('keydown', function(e) {
        if ((e.ctrlKey || e.metaKey) && e.key === 's') {
            e.preventDefault();
            saveToPDF();
        }
    });
}

/**
 * Récupère le code de réservation depuis le document
 */
function getReceiptCode() {
    const codeElement = document.querySelector('.detail-value');
    if (codeElement && codeElement.textContent.match(/^[A-Z0-9\-]+$/)) {
        return codeElement.textContent.trim();
    }
    return 'Recu_' + new Date().getTime();
}

/**
 * Gestion des erreurs globales
 */
window.addEventListener('error', function(e) {
    console.error('Erreur dans le reçu:', e.error);
});

/**
 * Fonctions utilitaires pour le debug
 */
window.receiptUtils = {
    print: printReceipt,
    save: saveToPDF,
    getCode: getReceiptCode
};

// === STYLES CSS DYNAMIQUES ===
const style = document.createElement('style');
style.textContent = `
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
    
    .printing .print-info {
        display: none !important;
    }
    
    .printing body {
        margin: 0 !important;
    }
`;
document.head.appendChild(style);

console.log('Receipt JavaScript loaded successfully');