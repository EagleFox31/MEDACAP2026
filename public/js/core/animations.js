class AnimationManager {
    constructor() {
        this.countUpInstances = [];
        this.CountUpClass = window.CountUp || (window.countUp && window.countUp.CountUp);

        if (!this.CountUpClass) {
            console.error("CountUp library is not available.");
        }
    }

    initCountUps() {
        this.countUpInstances = [];

        const elements = document.querySelectorAll('[data-value]');
        if (!elements.length || !this.CountUpClass) return;

        elements.forEach(el => {
            if (!el || !el.id) return;

            const value = parseFloat(el.getAttribute('data-value'));
            if (isNaN(value)) return;

            try {
                const countUpInstance = new this.CountUpClass(el.id, value, {
                    duration: 2.5,
                    useEasing: true,
                    useGrouping: true,
                    separator: ' ',
                    decimal: ',',
                    enableScrollSpy: false
                });

                this.countUpInstances.push({
                    element: el,
                    countUp: countUpInstance
                });

                el.style.opacity = "1";
            } catch (error) {
                console.error('CountUp init error:', error);
                el.textContent = value.toLocaleString();
                el.style.opacity = "1";
            }
        });
    }

    startCountUpAnimations() {
        if (!this.countUpInstances.length) return;

        this.countUpInstances.forEach((instance, index) => {
            setTimeout(() => {
                try {
                    if (instance.countUp && typeof instance.countUp.start === 'function') {
                        instance.countUp.start();
                    }
                } catch (error) {
                    console.error('CountUp start error:', error);
                }
            }, 100 * index);
        });
    }

    initProgressBarAnimations() {
        const progressBars = document.querySelectorAll('.progress-bar');
        if (!progressBars.length || !this.CountUpClass) return;

        progressBars.forEach(bar => {
            const finalWidth = bar.style.width;
            const finalPercentage = bar.getAttribute('aria-valuenow');

            bar.style.width = '0%';
            bar.textContent = '0%';

            bar.dataset.finalWidth = finalWidth;
            bar.dataset.finalPercentage = finalPercentage;
        });

        setTimeout(() => {
            progressBars.forEach((bar, index) => {
                setTimeout(() => {
                    const finalWidth = bar.dataset.finalWidth;
                    const finalPercentage = bar.dataset.finalPercentage;

                    bar.style.transition = 'width 1.5s ease-in-out';
                    bar.style.width = finalWidth || `${finalPercentage}%`;

                    if (finalPercentage) {
                        const percentageValue = parseInt(finalPercentage, 10);
                        const tempId = `progress-count-${Date.now()}-${index}`;
                        bar.id = tempId;

                        try {
                            const countUpInstance = new this.CountUpClass(
                                tempId,
                                percentageValue,
                                {
                                    duration: 1.5,
                                    suffix: '%',
                                    enableScrollSpy: false
                                }
                            );

                            if (!countUpInstance.error) {
                                countUpInstance.start();
                            } else {
                                bar.textContent = `${percentageValue}%`;
                            }
                        } catch (error) {
                            bar.textContent = `${percentageValue}%`;
                        }
                    }
                }, 150 * index);
            });
        }, 300);
    }

    resetAndStartAnimations() {
        this.initCountUps();
        this.startCountUpAnimations();
        this.initProgressBarAnimations();
    }
}

// Initialisation
document.addEventListener('DOMContentLoaded', () => {
    window.animationManager = new AnimationManager();

    if (window.animationManager.CountUpClass) {
        animationManager.resetAndStartAnimations();
    } else {
        console.error("CountUp library not loaded!");
        document.querySelectorAll('[data-value]').forEach(el => {
            el.style.opacity = "1";
        });
    }
});
