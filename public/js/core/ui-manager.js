// Gestion des composants UI
class UIManager {
    showToast(message, type = "info", targetSelector = null) {
        const toasts = document.querySelectorAll('.ui-toast');

        const matchingToast = Array.from(toasts).find(toast =>
            toast.getAttribute('data-type') === type
        );

        if (matchingToast) {
            const toastContent = matchingToast.querySelector('.toast-content');
            toastContent.textContent = message;

            // Assure que le toast est visible
            matchingToast.style.display = 'block';
            matchingToast.style.transition = 'none'; // retire la transition temporairement
            matchingToast.style.opacity = '0';

            // Forcer un reflow pour réinitialiser l'animation
            void matchingToast.offsetWidth;

            // Rétablir la transition
            matchingToast.style.transition = 'opacity 0.5s ease';

            // Lance l'animation d'apparition
            requestAnimationFrame(() => {
                matchingToast.style.opacity = '1';
            });

            // Cache après 3s
            setTimeout(() => {
                matchingToast.style.opacity = '0';
                setTimeout(() => {
                    matchingToast.style.display = 'none';
                    toastContent.textContent = ''; // reset message
                }, 500);
            }, 3000);
        } else {
            console.warn(`Aucun toast trouvé pour le type: ${type}`);
        }
    }


    showSkeleton(element, count = 3) {
        element.classList.add('loading');
        element.innerHTML = `
        <div class="skeleton-container">
            ${Array(count).fill().map(() => `
                <div class="skeleton-item skeleton-card mb-4"></div>
                <div class="skeleton-item skeleton-line mb-2"></div>
                <div class="skeleton-item skeleton-line-sm mb-2"></div>
                <div class="skeleton-item skeleton-line-xs mb-4"></div>
            `).join('')}
        </div>
        `;
    }

    generateCheckbox(attributes = {}) {
        const checkbox = document.createElement('input');
        checkbox.type = 'checkbox';
        checkbox.className = 'custom-checkbox';

        Object.entries(attributes).forEach(([key, value]) => {
            checkbox.setAttribute(key, value);
        });

        return checkbox;
    }
}

const uiManager = new UIManager();