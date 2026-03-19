// Function to initialize CountUp instances for all elements with data-value
function initCountUps() {
    // Clear any existing instances
    countUpInstances = [];

    // Find all elements with data-value attribute
    document.querySelectorAll('[data-value]').forEach(el => {
        if (!el || !el.id) return; // Skip if element or id is missing

        const value = parseFloat(el.getAttribute('data-value'));
        if (!isNaN(value)) {
            try {
                // Create CountUp instance with formatting
                const countUpInstance = new countUp.CountUp(el.id, value, {
                    duration: 2.5,
                    useEasing: true,
                    useGrouping: true,
                    separator: ' ',
                    decimal: ',',
                    enableScrollSpy: false
                });

                // Store the instance for later use
                countUpInstances.push({
                    element: el,
                    countUp: countUpInstance
                });

                // Show the element (was hidden with opacity 0)
                el.style.opacity = 1;
            } catch (error) {
                console.error('Error creating CountUp for', el.id, error);
            }
        }
    });
}

// Function to start all CountUp animations with staggered timing
function startCountUpAnimations() {
    countUpInstances.forEach((instance, index) => {
        setTimeout(() => {
            try {
                if (instance && instance.countUp && typeof instance.countUp.start === 'function') {
                    instance.countUp.start();
                }
            } catch (error) {
                console.error('Error starting CountUp animation', error);
            }
        }, 100 * index); // Stagger animations by 100ms
    });
}


// Function to animate progress bars with CountUp effect
function initProgressBarAnimations() {
    // Get all progress bar elements
    const progressBars = document.querySelectorAll('.progress-bar');

    // Reset all progress bars to 0% width first
    progressBars.forEach(bar => {
        // Store the original width percentage
        const finalWidth = bar.style.width;
        const finalPercentage = bar.getAttribute('aria-valuenow');

        // Set initial width to 0%
        bar.style.width = '0%';

        // Store final width value as data attribute
        bar.setAttribute('data-final-width', finalWidth);
        bar.setAttribute('data-final-percentage', finalPercentage);

        // Hide percentage text initially
        bar.textContent = '0%';
    });

    // Animate progress bars after a short delay
    setTimeout(() => {
        progressBars.forEach((bar, index) => {
            // Stagger animations
            setTimeout(() => {
                const finalWidth = bar.getAttribute('data-final-width');
                const finalPercentage = bar.getAttribute('data-final-percentage');

                // Animate width
                bar.style.transition = 'width 1.5s ease-in-out';
                bar.style.width = finalWidth || finalPercentage + '%';

                // Animate percentage text using CountUp
                if (finalPercentage) {
                    const percentageValue = parseInt(finalPercentage, 10);
                    // Create temporary span for CountUp if needed
                    const tempId = 'progress-count-' + index;
                    bar.id = tempId;

                    // Use CountUp for the text
                    const countUpInstance = new countUp.CountUp(tempId, percentageValue, {
                        duration: 1.5,
                        useEasing: true,
                        suffix: '%',
                        enableScrollSpy: false
                    });

                    countUpInstance.start();
                }
            }, 150 * index);
        });
    }, 300);
}



// Update animations when filters change
function resetAndStartAnimations() {
    initCountUps();
    startCountUpAnimations();
    initProgressBarAnimations(); // Also restart progress bar animations
    // No need to call initHistogramAnimations() as ApexCharts handles its own animations
}

//Premier appel pour animer au chargement
resetAndStartAnimations();