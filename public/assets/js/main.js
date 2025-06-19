/**
 * ParkFinder - Main JavaScript Application
 * OOP MVC Structure in Vanilla JavaScript
 */

// Application namespace
const ParkFinder = {
    Models: {},
    Views: {},
    Controllers: {},
    Utils: {},
    Config: {
        baseUrl: window.location.origin,
        apiUrl: '/api',
        csrf_token: document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
    }
};

// Base Model Class
ParkFinder.Models.BaseModel = class {
    constructor() {
        this.data = {};
        this.listeners = {};
    }

    // Event system
    on(event, callback) {
        if (!this.listeners[event]) {
            this.listeners[event] = [];
        }
        this.listeners[event].push(callback);
    }

    off(event, callback) {
        if (this.listeners[event]) {
            this.listeners[event] = this.listeners[event].filter(cb => cb !== callback);
        }
    }

    trigger(event, data) {
        if (this.listeners[event]) {
            this.listeners[event].forEach(callback => callback(data));
        }
    }

    // HTTP methods
    async request(method, url, data = null) {
        const options = {
            method: method.toUpperCase(),
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            }
        };

        if (ParkFinder.Config.csrf_token) {
            options.headers['X-CSRF-TOKEN'] = ParkFinder.Config.csrf_token;
        }

        if (data && method.toUpperCase() !== 'GET') {
            options.body = JSON.stringify(data);
        }

        try {
            const response = await fetch(url, options);
            const result = await response.json();
            
            if (!response.ok) {
                throw new Error(result.message || 'Request failed');
            }
            
            return result;
        } catch (error) {
            console.error('Request error:', error);
            throw error;
        }
    }

    async get(url) {
        return this.request('GET', url);
    }

    async post(url, data) {
        return this.request('POST', url, data);
    }

    async put(url, data) {
        return this.request('PUT', url, data);
    }

    async delete(url) {
        return this.request('DELETE', url);
    }
};

// Base View Class
ParkFinder.Views.BaseView = class {
    constructor(element) {
        this.element = typeof element === 'string' ? document.querySelector(element) : element;
        this.listeners = {};
        this.init();
    }

    init() {
        // Override in subclasses
    }

    // DOM manipulation
    $(selector) {
        return this.element.querySelector(selector);
    }

    $$(selector) {
        return this.element.querySelectorAll(selector);
    }

    show() {
        this.element.style.display = 'block';
        return this;
    }

    hide() {
        this.element.style.display = 'none';
        return this;
    }

    addClass(className) {
        this.element.classList.add(className);
        return this;
    }

    removeClass(className) {
        this.element.classList.remove(className);
        return this;
    }

    toggleClass(className) {
        this.element.classList.toggle(className);
        return this;
    }

    // Event handling
    on(event, selector, callback) {
        if (typeof selector === 'function') {
            callback = selector;
            selector = null;
        }

        const handler = (e) => {
            if (!selector || e.target.matches(selector) || e.target.closest(selector)) {
                callback.call(this, e);
            }
        };

        this.element.addEventListener(event, handler);
        
        if (!this.listeners[event]) {
            this.listeners[event] = [];
        }
        this.listeners[event].push(handler);
    }

    off(event) {
        if (this.listeners[event]) {
            this.listeners[event].forEach(handler => {
                this.element.removeEventListener(event, handler);
            });
            delete this.listeners[event];
        }
    }

    // Template rendering
    render(template, data = {}) {
        if (typeof template === 'string') {
            this.element.innerHTML = this.interpolate(template, data);
        }
        return this;
    }

    interpolate(template, data) {
        return template.replace(/\{\{([^}]+)\}\}/g, (match, key) => {
            return data[key.trim()] || '';
        });
    }
};

// Base Controller Class
ParkFinder.Controllers.BaseController = class {
    constructor(model, view) {
        this.model = model;
        this.view = view;
        this.init();
    }

    init() {
        // Override in subclasses
    }

    // Utility methods
    showLoading() {
        ParkFinder.Utils.showLoading();
    }

    hideLoading() {
        ParkFinder.Utils.hideLoading();
    }

    showAlert(message, type = 'info') {
        ParkFinder.Utils.showAlert(message, type);
    }

    handleError(error) {
        console.error('Controller error:', error);
        this.showAlert(error.message || 'An error occurred', 'error');
    }
};

// Utility Functions
ParkFinder.Utils = {
    // Loading indicator
    showLoading() {
        let loader = document.getElementById('loadingIndicator');
        if (!loader) {
            loader = document.createElement('div');
            loader.id = 'loadingIndicator';
            loader.className = 'loading-overlay';
            loader.innerHTML = '<div class="loading-spinner"></div>';
            document.body.appendChild(loader);
        }
        loader.style.display = 'flex';
    },

    hideLoading() {
        const loader = document.getElementById('loadingIndicator');
        if (loader) {
            loader.style.display = 'none';
        }
    },

    // Alert system
    showAlert(message, type = 'info', duration = 5000) {
        const alert = document.createElement('div');
        alert.className = `alert alert-${type} alert-dismissible`;
        alert.innerHTML = `
            <span>${message}</span>
            <button type="button" class="alert-close" onclick="this.parentElement.remove()">&times;</button>
        `;

        const container = document.getElementById('flashMessages') || document.body;
        container.appendChild(alert);

        // Auto-remove after duration
        setTimeout(() => {
            if (alert.parentElement) {
                alert.remove();
            }
        }, duration);
    },

    // Form validation
    validateForm(form) {
        const errors = [];
        const formData = new FormData(form);
        
        // Required fields
        form.querySelectorAll('[required]').forEach(field => {
            if (!field.value.trim()) {
                errors.push(`${field.name} is required`);
                field.classList.add('is-invalid');
            } else {
                field.classList.remove('is-invalid');
            }
        });

        // Email validation
        form.querySelectorAll('input[type="email"]').forEach(field => {
            if (field.value && !this.isValidEmail(field.value)) {
                errors.push('Please enter a valid email address');
                field.classList.add('is-invalid');
            }
        });

        // Phone validation
        form.querySelectorAll('input[type="tel"]').forEach(field => {
            if (field.value && !this.isValidPhone(field.value)) {
                errors.push('Please enter a valid phone number');
                field.classList.add('is-invalid');
            }
        });

        return {
            isValid: errors.length === 0,
            errors: errors,
            data: Object.fromEntries(formData)
        };
    },

    isValidEmail(email) {
        return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
    },

    isValidPhone(phone) {
        return /^[\+]?[1-9][\d]{0,15}$/.test(phone.replace(/\s/g, ''));
    },

    // Date/time utilities
    formatDateTime(dateString) {
        const date = new Date(dateString);
        return date.toLocaleString();
    },

    formatDate(dateString) {
        const date = new Date(dateString);
        return date.toLocaleDateString();
    },

    formatTime(dateString) {
        const date = new Date(dateString);
        return date.toLocaleTimeString();
    },

    // Currency formatting
    formatCurrency(amount, currency = 'USD') {
        return new Intl.NumberFormat('en-US', {
            style: 'currency',
            currency: currency
        }).format(amount);
    },

    // Local storage
    setStorage(key, value) {
        try {
            localStorage.setItem(key, JSON.stringify(value));
        } catch (e) {
            console.error('Storage error:', e);
        }
    },

    getStorage(key, defaultValue = null) {
        try {
            const item = localStorage.getItem(key);
            return item ? JSON.parse(item) : defaultValue;
        } catch (e) {
            console.error('Storage error:', e);
            return defaultValue;
        }
    },

    removeStorage(key) {
        try {
            localStorage.removeItem(key);
        } catch (e) {
            console.error('Storage error:', e);
        }
    },

    // Debounce function
    debounce(func, wait) {
        let timeout;
        return function executedFunction(...args) {
            const later = () => {
                clearTimeout(timeout);
                func(...args);
            };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
    },

    // Throttle function
    throttle(func, limit) {
        let inThrottle;
        return function() {
            const args = arguments;
            const context = this;
            if (!inThrottle) {
                func.apply(context, args);
                inThrottle = true;
                setTimeout(() => inThrottle = false, limit);
            }
        };
    }
};

// Application initialization
document.addEventListener('DOMContentLoaded', function() {
    // Initialize navigation
    const navbarToggle = document.getElementById('navbarToggle');
    const navbarMenu = document.getElementById('navbarMenu');
    
    if (navbarToggle && navbarMenu) {
        navbarToggle.addEventListener('click', () => {
            navbarMenu.classList.toggle('active');
        });
    }

    // Initialize dropdowns
    document.querySelectorAll('.dropdown').forEach(dropdown => {
        const toggle = dropdown.querySelector('.dropdown-toggle');
        const menu = dropdown.querySelector('.dropdown-menu');
        
        if (toggle && menu) {
            toggle.addEventListener('click', (e) => {
                e.preventDefault();
                dropdown.classList.toggle('active');
            });
        }
    });

    // Close dropdowns when clicking outside
    document.addEventListener('click', (e) => {
        document.querySelectorAll('.dropdown.active').forEach(dropdown => {
            if (!dropdown.contains(e.target)) {
                dropdown.classList.remove('active');
            }
        });
    });

    // Initialize alerts
    document.querySelectorAll('.alert-dismissible').forEach(alert => {
        const closeBtn = alert.querySelector('.alert-close');
        if (closeBtn) {
            closeBtn.addEventListener('click', () => {
                alert.remove();
            });
        }
    });

    // Auto-hide flash messages
    setTimeout(() => {
        document.querySelectorAll('.alert').forEach(alert => {
            alert.style.opacity = '0';
            setTimeout(() => alert.remove(), 300);
        });
    }, 5000);

    // Initialize form validation
    document.querySelectorAll('form').forEach(form => {
        form.addEventListener('submit', function(e) {
            const validation = ParkFinder.Utils.validateForm(this);
            if (!validation.isValid) {
                e.preventDefault();
                ParkFinder.Utils.showAlert(validation.errors[0], 'error');
            }
        });
    });

    // Initialize real-time features
    if (window.location.pathname.includes('/dashboard') || window.location.pathname.includes('/book')) {
        initializeRealTimeFeatures();
    }
});

// Real-time features
function initializeRealTimeFeatures() {
    // Check for notifications every 30 seconds
    setInterval(async () => {
        try {
            const response = await fetch('/api/notifications');
            const notifications = await response.json();
            
            if (notifications.length > 0) {
                updateNotificationBadge(notifications.length);
            }
        } catch (error) {
            console.error('Failed to fetch notifications:', error);
        }
    }, 30000);

    // Update parking availability every 60 seconds
    setInterval(async () => {
        const availabilityDisplay = document.getElementById('parkingAvailability');
        if (availabilityDisplay) {
            try {
                const response = await fetch('/api/spots/check-availability');
                const availability = await response.json();
                updateAvailabilityDisplay(availability);
            } catch (error) {
                console.error('Failed to update availability:', error);
            }
        }
    }, 60000);
}

function updateNotificationBadge(count) {
    let badge = document.getElementById('notificationBadge');
    if (!badge) {
        badge = document.createElement('span');
        badge.id = 'notificationBadge';
        badge.className = 'badge badge-danger';
        const bellIcon = document.querySelector('.notification-bell');
        if (bellIcon) {
            bellIcon.appendChild(badge);
        }
    }
    badge.textContent = count;
    badge.style.display = count > 0 ? 'inline' : 'none';
}

function updateAvailabilityDisplay(availability) {
    const display = document.getElementById('parkingAvailability');
    if (display) {
        display.innerHTML = `
            <div class="availability-summary">
                <span class="available-count">${availability.available}</span> available /
                <span class="total-count">${availability.total}</span> total spots
            </div>
        `;
    }
}

// Export for use in other files
window.ParkFinder = ParkFinder;
