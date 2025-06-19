/**
 * Payment Processing JavaScript - DEMO MODE
 * Handles PayPal Sandbox and Credit Card Demo payments
 */

class PaymentProcessor {
    constructor() {
        this.paypalLoaded = false;
        this.currentPaymentMethod = null;
        this.reservationId = null;
        this.amount = 0;
        this.demoMode = true; // Always demo mode
        
        this.init();
    }
    
    init() {
        // Show demo mode notice
        this.showDemoNotice();
        
        // Get payment data from page
        const paymentForm = document.getElementById('paymentForm');
        if (paymentForm) {
            this.reservationId = paymentForm.dataset.reservationId;
            this.amount = parseFloat(paymentForm.dataset.amount);
        }
        
        // Initialize payment method selection
        this.initPaymentMethods();
        
        // Load PayPal SDK in sandbox mode
        this.loadPayPalSDK();
        
        // Initialize credit card form
        this.initCreditCardForm();
    }
    
    showDemoNotice() {
        console.log('🔥 DEMO MODE ACTIVE - PayPal Sandbox Environment');
        console.log('💳 No real money will be charged');
        console.log('🧪 This is for testing purposes only');
    }
    
    initPaymentMethods() {
        const paymentMethods = document.querySelectorAll('input[name="payment_method"]');
        paymentMethods.forEach(method => {
            method.addEventListener('change', (e) => {
                this.currentPaymentMethod = e.target.value;
                this.showPaymentForm(e.target.value);
            });
        });
        
        // Set initial payment method
        const selectedMethod = document.querySelector('input[name="payment_method"]:checked');
        if (selectedMethod) {
            this.currentPaymentMethod = selectedMethod.value;
            this.showPaymentForm(selectedMethod.value);
        }
    }
    
    showPaymentForm(method) {
        // Hide all payment forms
        document.querySelectorAll('.payment-form').forEach(form => {
            form.style.display = 'none';
        });
        
        // Show selected payment form
        const selectedForm = document.getElementById(`${method}-form`);
        if (selectedForm) {
            selectedForm.style.display = 'block';
        }
    }
    
    loadPayPalSDK() {
        const clientId = document.querySelector('meta[name="paypal-client-id"]')?.content;
        const mode = document.querySelector('meta[name="paypal-mode"]')?.content || 'sandbox';
        
        if (!clientId) {
            console.error('PayPal client ID not found');
            return;
        }
        
        // Check if PayPal SDK is already loaded
        if (window.paypal) {
            this.initPayPalButtons();
            return;
        }
        
        // Always load PayPal SDK in sandbox mode for demo
        const script = document.createElement('script');
        script.src = `https://www.paypal.com/sdk/js?client-id=${clientId}&currency=USD&intent=capture&enable-funding=venmo,paylater&disable-funding=card`;
        script.setAttribute('data-partner-attribution-id', 'ParkFinder_Cart_Demo');
        
        script.onload = () => {
            this.paypalLoaded = true;
            console.log('✅ PayPal SDK loaded in SANDBOX mode');
            this.initPayPalButtons();
        };
        
        script.onerror = () => {
            console.error('❌ Failed to load PayPal SDK');
            this.showPayPalError();
        };
        
        document.head.appendChild(script);
    }
    
    initPayPalButtons() {
        if (!window.paypal) {
            console.error('PayPal SDK not available');
            return;
        }
        
        const paypalContainer = document.getElementById('paypal-button-container');
        if (!paypalContainer) {
            console.error('PayPal button container not found');
            return;
        }
        
        // Clear any existing buttons
        paypalContainer.innerHTML = '';
        
        window.paypal.Buttons({
            style: {
                layout: 'vertical',
                color: 'blue',
                shape: 'rect',
                label: 'paypal',
                height: 50
            },
            
            createOrder: (data, actions) => {
                console.log('🏗️ Creating PayPal order (DEMO)...');
                return this.createPayPalOrder();
            },
            
            onApprove: (data, actions) => {
                console.log('✅ PayPal payment approved (DEMO)', data);
                return this.approvePayPalPayment(data.orderID);
            },
            
            onError: (err) => {
                console.error('❌ PayPal error:', err);
                ParkFinder.Utils.showAlert('PayPal payment failed (Demo Mode). This is normal in testing.', 'warning');
                
                // In demo mode, offer to simulate success
                setTimeout(() => {
                    if (confirm('Demo Mode: Simulate successful payment?')) {
                        this.simulatePayPalSuccess();
                    }
                }, 2000);
            },
            
            onCancel: (data) => {
                console.log('⚠️ PayPal payment cancelled (DEMO)', data);
                ParkFinder.Utils.showAlert('PayPal payment was cancelled.', 'warning');
            }
        }).render('#paypal-button-container');
        
        // Add demo notice below PayPal buttons
        const demoNotice = document.createElement('div');
        demoNotice.className = 'paypal-demo-notice';
        demoNotice.innerHTML = `
            <p><small>🧪 Demo Mode: This will open PayPal's sandbox login</small></p>
        `;
        paypalContainer.appendChild(demoNotice);
    }
    
    async createPayPalOrder() {
        try {
            const response = await fetch('/payment/create-paypal-order', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': ParkFinder.Config.csrf_token
                },
                body: JSON.stringify({
                    reservation_id: this.reservationId,
                    amount: this.amount,
                    demo_mode: true
                })
            });
            
            const data = await response.json();
            
            if (!response.ok) {
                throw new Error(data.error || 'Failed to create PayPal order');
            }
            
            console.log('🎯 PayPal order created (DEMO):', data.order_id);
            return data.order_id;
            
        } catch (error) {
            console.error('Error creating PayPal order:', error);
            
            // In demo mode, create a fake order ID if API fails
            const fakeOrderId = 'DEMO_ORDER_' + Date.now();
            console.log('🔄 Using fake order ID for demo:', fakeOrderId);
            return fakeOrderId;
        }
    }
    
    async approvePayPalPayment(orderID) {
        try {
            ParkFinder.Utils.showLoading();
            console.log('⚡ Processing PayPal payment (DEMO):', orderID);
            
            const response = await fetch('/payment/process', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': ParkFinder.Config.csrf_token
                },
                body: JSON.stringify({
                    reservation_id: this.reservationId,
                    payment_method: 'paypal',
                    amount: this.amount,
                    paypal_order_id: orderID,
                    demo_mode: true,
                    csrf_token: ParkFinder.Config.csrf_token
                })
            });
            
            const result = await response.json();
            
            ParkFinder.Utils.hideLoading();
            
            if (result.success) {
                console.log('🎉 Payment successful (DEMO)');
                ParkFinder.Utils.showAlert('Demo payment successful! 🎉', 'success');
                setTimeout(() => {
                    window.location.href = result.redirect;
                }, 2000);
            } else {
                throw new Error(result.message || 'Payment processing failed');
            }
            
        } catch (error) {
            ParkFinder.Utils.hideLoading();
            console.error('Error processing PayPal payment:', error);
            
            // In demo mode, offer simulation
            if (confirm('Demo Mode: Payment API failed. Simulate success?')) {
                this.simulatePayPalSuccess();
            } else {
                ParkFinder.Utils.showAlert('Demo payment failed: ' + error.message, 'error');
            }
        }
    }
    
    simulatePayPalSuccess() {
        console.log('🎭 Simulating PayPal success...');
        ParkFinder.Utils.showAlert('Demo payment simulated successfully! 🎭', 'success');
        setTimeout(() => {
            window.location.href = '/payment/success?demo=true';
        }, 2000);
    }
    
    showPayPalError() {
        const paypalContainer = document.getElementById('paypal-button-container');
        if (paypalContainer) {
            paypalContainer.innerHTML = `
                <div class="paypal-error">
                    <p>⚠️ PayPal SDK failed to load (Demo Mode)</p>
                    <button class="btn btn-secondary" onclick="location.reload()">Retry</button>
                    <button class="btn btn-primary" onclick="paymentProcessor.simulatePayPalSuccess()">Simulate Success</button>
                </div>
            `;
        }
    }
    
    initCreditCardForm() {
        const creditCardForm = document.getElementById('credit-card-form');
        if (!creditCardForm) return;
        
        // Format card number input
        const cardNumberInput = document.getElementById('card_number');
        if (cardNumberInput) {
            cardNumberInput.addEventListener('input', (e) => {
                let value = e.target.value.replace(/\s/g, '').replace(/[^0-9]/gi, '');
                let formattedValue = value.match(/.{1,4}/g)?.join(' ') || value;
                if (formattedValue.length > 19) formattedValue = formattedValue.substr(0, 19);
                e.target.value = formattedValue;
            });
        }
        
        // Format expiry date input
        const expiryInput = document.getElementById('card_expiry');
        if (expiryInput) {
            expiryInput.addEventListener('input', (e) => {
                let value = e.target.value.replace(/\D/g, '');
                if (value.length >= 2) {
                    value = value.substring(0, 2) + '/' + value.substring(2, 4);
                }
                e.target.value = value;
            });
        }
        
        // CVV input
        const cvvInput = document.getElementById('card_cvv');
        if (cvvInput) {
            cvvInput.addEventListener('input', (e) => {
                e.target.value = e.target.value.replace(/\D/g, '').substring(0, 4);
            });
        }
        
        // Credit card form submission
        creditCardForm.addEventListener('submit', (e) => {
            e.preventDefault();
            this.processCreditCardPayment();
        });
    }
    
    async processCreditCardPayment() {
        const form = document.getElementById('credit-card-form');
        const formData = new FormData(form);
        
        // Validate form
        if (!this.validateCreditCardForm(formData)) {
            return;
        }
        
        // Demo mode: Check for demo card numbers
        const cardNumber = formData.get('card_number').replace(/\s/g, '');
        const isDemoCard = this.isDemoCardNumber(cardNumber);
        
        if (this.demoMode && !isDemoCard) {
            if (!confirm('This is not a demo card number. Continue anyway?')) {
                return;
            }
        }
        
        try {
            ParkFinder.Utils.showLoading();
            console.log('💳 Processing credit card payment (DEMO)...');
            
            // Add additional data
            formData.append('reservation_id', this.reservationId);
            formData.append('payment_method', 'credit_card');
            formData.append('amount', this.amount);
            formData.append('demo_mode', 'true');
            formData.append('csrf_token', ParkFinder.Config.csrf_token);
            
            const response = await fetch('/payment/process', {
                method: 'POST',
                body: formData
            });
            
            const result = await response.json();
            
            ParkFinder.Utils.hideLoading();
            
            if (result.success) {
                console.log('🎉 Credit card payment successful (DEMO)');
                ParkFinder.Utils.showAlert('Demo credit card payment successful! 🎉', 'success');
                setTimeout(() => {
                    window.location.href = result.redirect;
                }, 2000);
            } else {
                throw new Error(result.message || 'Payment processing failed');
            }
            
        } catch (error) {
            ParkFinder.Utils.hideLoading();
            console.error('Error processing credit card payment:', error);
            
            // In demo mode, offer simulation for certain errors
            if (this.demoMode && (error.message.includes('API') || error.message.includes('connection'))) {
                if (confirm('Demo Mode: API error. Simulate successful payment?')) {
                    this.simulateCreditCardSuccess();
                    return;
                }
            }
            
            ParkFinder.Utils.showAlert('Demo payment failed: ' + error.message, 'error');
        }
    }
    
    simulateCreditCardSuccess() {
        console.log('🎭 Simulating credit card success...');
        ParkFinder.Utils.showAlert('Demo credit card payment simulated successfully! 🎭', 'success');
        setTimeout(() => {
            window.location.href = '/payment/success?demo=true';
        }, 2000);
    }
    
    isDemoCardNumber(cardNumber) {
        const demoCards = [
            '4111111111111111', // Visa
            '5555555555554444', // MasterCard
            '371449635398431',  // Amex
            '6011111111111117', // Discover
            '4000000000000002', // Visa (declined)
            '4000000000009995'  // Visa (insufficient funds)
        ];
        
        return demoCards.includes(cardNumber);
    }
}

// Initialize payment processor when DOM is ready
document.addEventListener('DOMContentLoaded', () => {
    if (document.getElementById('paymentForm')) {
        window.paymentProcessor = new PaymentProcessor();
    }
});

// Add demo-specific styles
const demoStyles = `
    .paypal-demo-notice {
        text-align: center;
        margin-top: 1rem;
        padding: 0.5rem;
        background: #e8f4fd;
        border-radius: 4px;
        border: 1px solid #bee5eb;
    }
    
    .paypal-error {
        text-align: center;
        padding: 2rem;
        background: #f8f9fa;
        border-radius: 8px;
        border: 2px dashed #dee2e6;
    }
    
    .paypal-error button {
        margin: 0.5rem;
    }
    
    .demo-mode-badge {
        position: fixed;
        top: 10px;
        right: 10px;
        background: #ff6b35;
        color: white;
        padding: 0.5rem 1rem;
        border-radius: 20px;
        font-size: 0.8rem;
        font-weight: bold;
        z-index: 10000;
        box-shadow: 0 2px 10px rgba(255, 107, 53, 0.3);
    }
`;

// Add styles to head
const styleSheet = document.createElement('style');
styleSheet.textContent = demoStyles;
document.head.appendChild(styleSheet);

// Add demo mode badge
const demoBadge = document.createElement('div');
demoBadge.className = 'demo-mode-badge';
demoBadge.textContent = '🧪 DEMO MODE';
document.body.appendChild(demoBadge);
