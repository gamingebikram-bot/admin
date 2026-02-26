// Dark Mode Functionality for Prince Panel
class DarkModeManager {
    constructor() {
        this.init();
    }

    init() {
        this.createToggleButton();
        this.loadSavedTheme();
        this.setupEventListeners();
    }

    createToggleButton() {
        // Check if toggle button already exists
        if (document.querySelector('.dark-mode-toggle')) return;

        const toggle = document.createElement('button');
        toggle.className = 'dark-mode-toggle';
        toggle.title = 'Toggle Dark Mode';
        toggle.innerHTML = '<i class="fas fa-moon" id="darkModeIcon"></i>';
        toggle.onclick = () => this.toggleDarkMode();

        document.body.appendChild(toggle);
    }

    toggleDarkMode() {
        const body = document.body;
        const icon = document.getElementById('darkModeIcon');
        
        if (body.getAttribute('data-theme') === 'dark') {
            body.removeAttribute('data-theme');
            if (icon) icon.className = 'fas fa-moon';
            localStorage.setItem('theme', 'light');
            this.dispatchThemeChange('light');
        } else {
            body.setAttribute('data-theme', 'dark');
            if (icon) icon.className = 'fas fa-sun';
            localStorage.setItem('theme', 'dark');
            this.dispatchThemeChange('dark');
        }
    }

    loadSavedTheme() {
        const savedTheme = localStorage.getItem('theme');
        const icon = document.getElementById('darkModeIcon');
        
        if (savedTheme === 'dark') {
            document.body.setAttribute('data-theme', 'dark');
            if (icon) icon.className = 'fas fa-sun';
        }
    }

    dispatchThemeChange(theme) {
        const event = new CustomEvent('themeChanged', { detail: { theme } });
        document.dispatchEvent(event);
    }

    setupEventListeners() {
        // Listen for system theme changes
        if (window.matchMedia) {
            const mediaQuery = window.matchMedia('(prefers-color-scheme: dark)');
            mediaQuery.addListener(() => {
                if (!localStorage.getItem('theme')) {
                    this.applySystemTheme();
                }
            });
        }
    }

    applySystemTheme() {
        const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
        const icon = document.getElementById('darkModeIcon');
        
        if (prefersDark) {
            document.body.setAttribute('data-theme', 'dark');
            if (icon) icon.className = 'fas fa-sun';
        } else {
            document.body.removeAttribute('data-theme');
            if (icon) icon.className = 'fas fa-moon';
        }
    }
}

// Initialize dark mode when DOM is loaded
document.addEventListener('DOMContentLoaded', () => {
    new DarkModeManager();
});

// Export for manual initialization
window.DarkModeManager = DarkModeManager;