// Enhanced UI Components for Prince Panel
class EnhancedUI {
    constructor() {
        this.init();
    }

    init() {
        this.setupLoadingSpinners();
        this.setupSmoothTransitions();
        this.setupFormEnhancements();
        this.setupScrollAnimations();
        this.setupTooltips();
    }

    // Loading Spinner System
    setupLoadingSpinners() {
        this.createLoadingOverlay();
        this.enhanceForms();
        this.enhanceButtons();
    }

    createLoadingOverlay() {
        const overlay = document.createElement('div');
        overlay.id = 'loadingOverlay';
        overlay.innerHTML = `
            <div class="loading-content">
                <div class="spinner"></div>
                <p>Loading...</p>
            </div>
        `;
        overlay.style.cssText = `
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 9999;
            transition: all 0.3s ease;
        `;
        
        const style = document.createElement('style');
        style.textContent = `
            .loading-content {
                text-align: center;
                color: var(--primary-color, #667eea);
            }
            .loading-content p {
                margin-top: 1rem;
                font-weight: 600;
            }
        `;
        document.head.appendChild(style);
        document.body.appendChild(overlay);
    }

    showLoading(message = 'Loading...') {
        const overlay = document.getElementById('loadingOverlay');
        const text = overlay.querySelector('p');
        if (text) text.textContent = message;
        overlay.style.display = 'flex';
    }

    hideLoading() {
        const overlay = document.getElementById('loadingOverlay');
        overlay.style.display = 'none';
    }

    enhanceForms() {
        document.querySelectorAll('form').forEach(form => {
            form.addEventListener('submit', (e) => {
                const submitBtn = form.querySelector('button[type="submit"]');
                if (submitBtn && !submitBtn.disabled) {
                    this.showButtonLoading(submitBtn);
                    this.showLoading('Processing...');
                }
            });
        });
    }

    enhanceButtons() {
        document.querySelectorAll('.btn').forEach(button => {
            button.addEventListener('click', function(e) {
                if (this.dataset.loading !== 'true') {
                    this.style.transform = 'scale(0.95)';
                    setTimeout(() => {
                        this.style.transform = '';
                    }, 150);
                }
            });
        });
    }

    showButtonLoading(button) {
        if (button.dataset.loading === 'true') return;
        
        button.dataset.loading = 'true';
        button.dataset.originalText = button.innerHTML;
        button.disabled = true;
        button.innerHTML = `
            <span class="spinner-border spinner-border-sm me-2" role="status"></span>
            Loading...
        `;
    }

    hideButtonLoading(button) {
        if (button.dataset.loading !== 'true') return;
        
        button.disabled = false;
        button.innerHTML = button.dataset.originalText;
        delete button.dataset.loading;
        delete button.dataset.originalText;
    }

    // Smooth Transitions
    setupSmoothTransitions() {
        // Page transition
        document.addEventListener('DOMContentLoaded', () => {
            document.body.style.opacity = '0';
            document.body.style.transform = 'translateY(20px)';
            
            setTimeout(() => {
                document.body.style.transition = 'all 0.6s ease';
                document.body.style.opacity = '1';
                document.body.style.transform = 'translateY(0)';
            }, 100);
        });

        // Link transitions
        document.querySelectorAll('a[href]').forEach(link => {
            if (link.href.includes(window.location.hostname) && !link.href.includes('#')) {
                link.addEventListener('click', (e) => {
                    e.preventDefault();
                    this.navigateWithTransition(link.href);
                });
            }
        });
    }

    navigateWithTransition(url) {
        document.body.style.transition = 'all 0.3s ease';
        document.body.style.opacity = '0';
        document.body.style.transform = 'translateY(-20px)';
        
        setTimeout(() => {
            window.location.href = url;
        }, 300);
    }

    // Form Enhancements
    setupFormEnhancements() {
        // Floating labels
        document.querySelectorAll('.form-control').forEach(input => {
            this.setupFloatingLabel(input);
        });

        // Real-time validation
        document.querySelectorAll('input[required]').forEach(input => {
            this.setupRealTimeValidation(input);
        });
    }

    setupFloatingLabel(input) {
        const wrapper = input.closest('.form-group') || input.parentElement;
        if (!wrapper.querySelector('.floating-label')) {
            const label = wrapper.querySelector('label');
            if (label && input.placeholder) {
                label.classList.add('floating-label');
                
                const updateLabel = () => {
                    if (input.value || input === document.activeElement) {
                        label.style.transform = 'translateY(-1.5rem) scale(0.8)';
                        label.style.color = 'var(--primary-color, #667eea)';
                    } else {
                        label.style.transform = '';
                        label.style.color = '';
                    }
                };

                input.addEventListener('focus', updateLabel);
                input.addEventListener('blur', updateLabel);
                input.addEventListener('input', updateLabel);
                updateLabel();
            }
        }
    }

    setupRealTimeValidation(input) {
        const showValidation = (isValid, message = '') => {
            let feedback = input.parentElement.querySelector('.validation-feedback');
            if (!feedback) {
                feedback = document.createElement('div');
                feedback.className = 'validation-feedback';
                input.parentElement.appendChild(feedback);
            }

            if (isValid) {
                input.style.borderColor = 'var(--success-color, #51cf66)';
                feedback.style.color = 'var(--success-color, #51cf66)';
                feedback.textContent = '✓ Valid';
            } else {
                input.style.borderColor = 'var(--danger-color, #ff6b6b)';
                feedback.style.color = 'var(--danger-color, #ff6b6b)';
                feedback.textContent = message;
            }
        };

        input.addEventListener('input', () => {
            if (input.type === 'email') {
                const isValid = /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(input.value);
                showValidation(isValid, 'Please enter a valid email address');
            } else if (input.type === 'password') {
                const isValid = input.value.length >= 6;
                showValidation(isValid, 'Password must be at least 6 characters');
            } else if (input.required) {
                const isValid = input.value.trim().length > 0;
                showValidation(isValid, 'This field is required');
            }
        });
    }

    // Scroll Animations
    setupScrollAnimations() {
        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.style.opacity = '1';
                    entry.target.style.transform = 'translateY(0)';
                }
            });
        }, { threshold: 0.1 });

        document.querySelectorAll('.fade-in, .card, .stats-card').forEach(el => {
            el.style.opacity = '0';
            el.style.transform = 'translateY(30px)';
            el.style.transition = 'all 0.6s ease';
            observer.observe(el);
        });
    }

    // Enhanced Tooltips
    setupTooltips() {
        document.querySelectorAll('[title]').forEach(element => {
            this.createTooltip(element);
        });
    }

    createTooltip(element) {
        const tooltip = document.createElement('div');
        tooltip.className = 'custom-tooltip';
        tooltip.textContent = element.title;
        element.title = ''; // Remove default tooltip

        const style = `
            position: absolute;
            background: rgba(0, 0, 0, 0.8);
            color: white;
            padding: 0.5rem;
            border-radius: 0.25rem;
            font-size: 0.875rem;
            z-index: 1000;
            opacity: 0;
            transition: opacity 0.3s ease;
            pointer-events: none;
        `;
        tooltip.style.cssText = style;

        document.body.appendChild(tooltip);

        element.addEventListener('mouseenter', (e) => {
            const rect = element.getBoundingClientRect();
            tooltip.style.top = rect.top - tooltip.offsetHeight - 5 + 'px';
            tooltip.style.left = rect.left + (rect.width / 2) - (tooltip.offsetWidth / 2) + 'px';
            tooltip.style.opacity = '1';
        });

        element.addEventListener('mouseleave', () => {
            tooltip.style.opacity = '0';
        });
    }
}

// Utility functions
window.UI = {
    showNotification: function(message, type = 'info') {
        const notification = document.createElement('div');
        notification.className = `notification notification-${type}`;
        notification.innerHTML = `
            <div class="notification-content">
                <i class="fas fa-${type === 'success' ? 'check' : type === 'error' ? 'exclamation-triangle' : 'info'}-circle"></i>
                <span>${message}</span>
                <button class="notification-close">&times;</button>
            </div>
        `;
        
        const style = `
            position: fixed;
            top: 20px;
            right: 20px;
            background: ${type === 'success' ? '#51cf66' : type === 'error' ? '#ff6b6b' : '#74c0fc'};
            color: white;
            padding: 1rem;
            border-radius: 0.5rem;
            z-index: 10000;
            transform: translateX(100%);
            transition: transform 0.3s ease;
            max-width: 300px;
        `;
        notification.style.cssText = style;
        
        document.body.appendChild(notification);
        
        setTimeout(() => {
            notification.style.transform = 'translateX(0)';
        }, 100);
        
        notification.querySelector('.notification-close').addEventListener('click', () => {
            notification.style.transform = 'translateX(100%)';
            setTimeout(() => notification.remove(), 300);
        });
        
        setTimeout(() => {
            notification.style.transform = 'translateX(100%)';
            setTimeout(() => notification.remove(), 300);
        }, 5000);
    }
};

// Initialize Enhanced UI
document.addEventListener('DOMContentLoaded', () => {
    new EnhancedUI();
});