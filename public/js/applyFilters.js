// Débounce function
function debounce(func, wait) {
    let timeout;
    return function() {
        const context = this, args = arguments;
        clearTimeout(timeout);
        timeout = setTimeout(() => {
            func.apply(context, args);
        }, wait);
    };
}

// Skeleton loader
function generateSkeletonLoader(count = 5) {
    return `
                <div class="skeleton-container">
                    ${Array(count).fill().map(() => `
                        <div class="skeleton-item skeleton-card"></div>
                        <div class="skeleton-item skeleton-line"></div>
                        <div class="skeleton-item skeleton-line-sm"></div>
                        <div class="skeleton-item skeleton-line-xs mb-4"></div>
                    `).join('')}
                </div>
            `;
}

// Get filter values
function getFilterValues() {
    const selects = document.querySelectorAll('#dynamicFilters select');
    const filters = {};
    selects.forEach(select => {
        const key = select.name;
        const value = select.value;
        if (value !== 'all') {
            filters[key] = value;
        }
    });
    return filters;
}

// Main applyFilters function
const applyFilters = debounce(function(targetId) {
    const filters = getFilterValues();
    const params = new URLSearchParams(filters).toString();
    const baseUrl = window.location.pathname;
    const newUrl = params ? `${baseUrl}?${params}` : baseUrl;

    history.replaceState(null, '', newUrl);

    const targetElement = document.getElementById(targetId);
    if (!targetElement) return;

    // Save scroll position
    const scrollPosition = window.scrollY;

    // Show skeleton loader
    targetElement.innerHTML = generateSkeletonLoader();

    // Add loading class
    targetElement.classList.add('position-relative-loading');

    // AJAX request with timeout
    const controller = new AbortController();
    const timeoutId = setTimeout(() => controller.abort(), 15000);

    fetch(newUrl, {
        signal: controller.signal,
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
        .then(response => {
            if (!response.ok) throw new Error('Network response was not ok');
            return response.text();
        })
        .then(html => {
            const parser = new DOMParser();
            const doc = parser.parseFromString(html, 'text/html');
            const newContent = doc.getElementById(targetId);

            if (newContent) {
                // Small delay to allow skeleton to show
                setTimeout(() => {
                    targetElement.innerHTML = newContent.innerHTML;
                    window.scrollTo(0, scrollPosition);

                    // Reinitialize components if needed
                    if (typeof resetAndStartAnimations === 'function') {
                        resetAndStartAnimations();
                    }
                    if (typeof KTInit !== 'undefined') {
                        KTInit();
                    }
                }, 300);
            }
        })
        .catch(error => {
            if (error.name !== 'AbortError') {
                console.error('Fetch error:', error);
                targetElement.innerHTML = `
                            <div class="alert alert-danger">
                                <div class="d-flex flex-column align-items-center py-4">
                                    <i class="bi bi-exclamation-triangle-fill fs-1 text-danger mb-3"></i>
                                    <h5 class="mb-3">Erreur de chargement</h5>
                                    <button class="btn btn-primary" onclick="applyFilters('${targetId}')">
                                        <i class="bi bi-arrow-repeat me-2"></i> Réessayer
                                    </button>
                                </div>
                            </div>
                        `;
            }
        })
        .finally(() => {
            clearTimeout(timeoutId);
            targetElement.classList.remove('position-relative-loading');
        });
}, 300); // 300ms debounce delay

// Initialize event listeners
document.addEventListener("DOMContentLoaded", function() {
    const selects = document.querySelectorAll('.filter-control');
    const targetId = '<?= addslashes($targetElementId) ?>';

    selects.forEach(select => {
        select.addEventListener('change', () => applyFilters(targetId));
    });
});