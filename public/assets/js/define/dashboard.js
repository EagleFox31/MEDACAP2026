// Dashboard JavaScript - Version nettoyée pour fichier .js
// Les données PHP doivent être passées via des variables globales ou des attributs data
/* ------------------------------------------------------------------
 *  Données injectées par PHP (script window.DASHBOARD)
 * ------------------------------------------------------------------ */
const DASH = window.DASHBOARD || {};         // sécurité si l’objet n’existe pas

const {
  brandScores,
  brandStats,
  trainingStats,
  trainingsCountsForGraph2,
  validationsCountsForGraph2,
  brandHours,
  brandLogos,
  agencyOptions,
  filtersInitial,

} = DASH;

/*  Pour ne pas réécrire tout le reste du code qui attend window.chartData  */
window.chartData = {
  brandScores,
  brandStats,
  trainingStats,
  trainingsCountsForGraph2,
  validationsCountsForGraph2,
  brandHours,
  brandLogos,
};

// Initialize CountUp instances
let countUpInstances = [];

// Cache for available brands per level
let availableBrandsForCurrentFilters = [];

document.addEventListener('DOMContentLoaded', function () {
    // Chart variables
    let brandScoresChart = null;
    let trainingChart = null;

    // Loading state management
    let isLoading = false;

    // DOM elements
    const loadingSpinner = document.getElementById('loadingSpinner');
    const statsContainer = document.getElementById('statsContainer');
    const filterForm = document.getElementById('dashboardFilters');
    const applyFiltersButton = document.getElementById('applyFiltersButton');

    // Initialize CountUp after DOM is fully loaded
    setTimeout(() => {
        initCountUps();
    }, 500);

    // Filter elements
    const filters = {
        country: document.getElementById('filterCountry'),
        agency: document.getElementById('filterAgency'),
        level: document.getElementById('filterLevel'),
        brand: document.getElementById('filterBrand'),
        technicianId: document.getElementById('filterTechnician')
    };

    // Helper function to clean URL by removing duplicate parameters
    function cleanURL(url) {
        const urlObj = new URL(url);
        // If both technician and technicianId exist, keep only technicianId
        if (urlObj.searchParams.has('technician') && urlObj.searchParams.has('technicianId')) {
            urlObj.searchParams.delete('technician');
        }
        return urlObj;
    }

    function applyFilters() {
        if (isLoading) return;

        const filterData = {};
        Object.entries(filters).forEach(([key, element]) => {
            if (element) {
                filterData[key] = element.value;
            }
        });

        console.log("Filter data:", filterData);

        const url = new URL(window.location.href);

        // Clear ALL parameters from the URL (except pagination if needed)
        Array.from(url.searchParams.keys()).forEach(key => {
            if (!['page', 'per_page', '_t'].includes(key)) {
                url.searchParams.delete(key);
            }
        });

        // Add only the current filter values
        Object.entries(filterData).forEach(([key, value]) => {
            url.searchParams.set(key, value);
        });

        // Log what we're about to do with technicianId
        if (filterData.technicianId && filterData.technicianId !== 'all') {
            console.log("Filtering for specific technician ID:", filterData.technicianId);
            console.log("Current filter values being applied:");
            console.log("- Subsidiary:", filterData.country || 'all');
            console.log("- Agency:", filterData.agency || 'all');
            console.log("- Level:", filterData.level || 'all');
            console.log("- Brand:", filterData.brand || 'all');
        }

        window.history.pushState({}, '', url);
        fetchDashboardData(url);
    }

    function bindFilterEvents() {
        if (filters.country) {
            filters.country.addEventListener('change', function () {
                if (filters.agency) filters.agency.value = 'all';
                updateFilterStates();
                applyFilters();
            });
        }

        ['agency', 'level', 'brand'].forEach(key => {
            if (filters[key]) {
                filters[key].addEventListener('change', function () {
                    updateFilterStates();
                    applyFilters();
                });
            }
        });

        // Special handler for technician filter
        if (filters.technicianId) {
            filters.technicianId.addEventListener('change', function () {
                const techId = this.value;
                console.log("🔍 TECHNICIAN FILTER CHANGED to:", techId);

                if (techId !== 'all') {
                    console.log("📊 Before update - Current filter values:");
                    console.log("- Level:", filters.level ? filters.level.value : 'none');
                    console.log("- Brand:", filters.brand ? filters.brand.value : 'none');
                }

                updateFilterStates();
                applyFilters();

                if (techId !== 'all') {
                    console.log("🔄 Update triggered for technician:", techId);
                } else {
                    console.log("🔄 Technician filter cleared - showing all technicians");
                }
            });
        }
    }

    // Loading management
    function toggleLoading(show = true) {
        isLoading = show;

        const loadingOverlay = document.getElementById('loadingOverlay');
        if (loadingOverlay) {
            loadingOverlay.classList.toggle('d-none', !show);
        }

        if (loadingSpinner) {
            loadingSpinner.style.display = show ? 'block' : 'none';
        }

        if (statsContainer) {
            statsContainer.style.opacity = show ? '0.5' : '1';
        }

        // Disable all select elements while loading
        document.querySelectorAll('select').forEach(select => {
            select.disabled = show;
        });
    }

    // Get chart data from global variables or DOM attributes
    // Ces données doivent être définies dans le HTML via PHP
    function getChartData() {
        // Option 1: Données dans des variables globales
        if (typeof window.chartData !== 'undefined') {
            return window.chartData;
        }
        
        // Option 2: Données dans des attributs data
        const dataElement = document.getElementById('chart-data');
        if (dataElement) {
            try {
                return JSON.parse(dataElement.textContent);
            } catch (e) {
                console.error('Failed to parse chart data', e);
            }
        }
        
        // Option 3: Valeurs par défaut
        return {
            brandScores: [],
            brandStats: [],
            trainingStats: [],
            trainingsCountsForGraph2: {},
            validationsCountsForGraph2: {},
            brandHours: 0,
            brandLogos: {}
        };
    }

    // Filter brand dropdown to only show brands with data
    function filterBrandDropdown() {
        const brandSelect = document.getElementById('filterBrand');
        if (!brandSelect) return;

        const brandsWithData = availableBrandsForCurrentFilters;

        // Get all options
        const options = Array.from(brandSelect.options);

        options.forEach(option => {
            if (option.value === 'all') return; // Keep 'all' option
            const brand = option.value;
            if (brandsWithData.includes(brand)) {
                option.style.display = '';
            } else {
                option.style.display = 'none';
            }
        });

        // If current selected brand is not in the list, reset to 'all'
        if (brandSelect.value !== 'all' && !brandsWithData.includes(brandSelect.value)) {
            brandSelect.value = 'all';
        }
    }

    // Initialize charts
    function initializeCharts() {
        const chartData = getChartData();
        
        // Destroy existing charts
        if (brandScoresChart) {
            brandScoresChart.destroy();
            brandScoresChart = null;
        }
        if (trainingChart) {
            if (trainingChart && typeof trainingChart.destroy === 'function') {
                trainingChart.destroy();
            }
            trainingChart = null;
        }

        // Training chart
        const trainingCanvas = document.getElementById('trainingChart');
        if (trainingCanvas && chartData.brandStats && chartData.brandStats.length > 0) {
            const recommendedData = [];
            const validatedData = [];

            const technicianSelected = filters.technicianId && filters.technicianId.value !== 'all';
            console.log("📊 Chart preparation - Technician selected:", technicianSelected, "ID:", filters.technicianId ? filters.technicianId.value : 'none');

            console.log("🔍 Filtering brands for chart display...");
            const relevantBrands = chartData.brandStats.filter(brand => {
                if (!technicianSelected) return true;

                const hasBrandData = (chartData.trainingsCountsForGraph2[brand] > 0 || chartData.validationsCountsForGraph2[brand] > 0);
                if (technicianSelected && hasBrandData) {
                    console.log(`  ✅ Including brand for technician: ${brand} (has data)`);
                } else if (technicianSelected) {
                    console.log(`  ❌ Excluding brand for technician: ${brand} (no data)`);
                }
                return hasBrandData;
            });

            if (technicianSelected) {
                console.log("📊 Technician-specific chart data:");
                console.log(`  - Showing ${relevantBrands.length} brands out of ${chartData.brandStats.length} total brands`);
                console.log("  - Relevant brands:", relevantBrands);

                console.log("📈 Data being displayed in chart:");
                relevantBrands.forEach(brand => {
                    const proposed = chartData.trainingsCountsForGraph2[brand] || 0;
                    const validated = chartData.validationsCountsForGraph2[brand] || 0;
                    console.log(`  - ${brand}: ${proposed} proposed, ${validated} validated`);
                });
            }

            relevantBrands.forEach(brand => {
                recommendedData.push(chartData.trainingsCountsForGraph2[brand] ?? 0);
                validatedData.push(chartData.validationsCountsForGraph2[brand] ?? 0);
            });

            console.log("Chart data prepared:", {
                technicianFiltered: technicianSelected,
                brands: relevantBrands,
                recommended: recommendedData,
                validated: validatedData
            });

            // Chart configuration for ApexCharts
            const options = {
                series: [{
                    name: 'Formations Proposées',
                    data: recommendedData
                }, {
                    name: 'Formations Validées',
                    data: validatedData
                }],
                chart: {
                    type: 'bar',
                    height: 500,
                    stacked: false,
                    toolbar: {
                        show: false
                    },
                    fontFamily: 'Inter, sans-serif',
                    background: 'transparent'
                },
                colors: ['#FFC107', '#0275D8'],
                plotOptions: {
                    bar: {
                        horizontal: false,
                        columnWidth: '85%',
                        endingShape: 'rounded',
                        borderRadius: 4,
                        dataLabels: {
                            position: 'center',
                        },
                    },
                },
                dataLabels: {
                    enabled: true,
                    formatter: function (val, opt) {
                        const totalProposed = recommendedData.reduce((sum, val) => sum + val, 0);
                        const percentage = totalProposed > 0 ? Math.round((val / totalProposed) * 100) : 0;
                        return `${val} `;
                    },
                    style: {
                        fontSize: '18px',
                        fontWeight: 'bold',
                        colors: ['#FFF']
                    },
                    offsetY: 0,
                    textAnchor: 'middle',
                    distributed: false,
                    background: {
                        enabled: false
                    }
                },
                stroke: {
                    width: 1,
                    colors: ['#fff']
                },
                grid: {
                    show: true,
                    borderColor: '#e7e7e7',
                    strokeDashArray: 5,
                    position: 'back',
                    xaxis: {
                        lines: {
                            show: true
                        }
                    },
                    yaxis: {
                        lines: {
                            show: true
                        }
                    },
                    padding: {
                        top: 0,
                        right: 10,
                        bottom: 50,
                        left: 10
                    }
                },
                xaxis: {
                    categories: relevantBrands,
                    labels: {
                        show: false
                    },
                    axisBorder: {
                        show: true,
                        color: '#e0e0e0'
                    },
                    axisTicks: {
                        show: true,
                        color: '#e0e0e0'
                    },
                    crosshairs: {
                        show: true,
                        stroke: {
                            color: '#b6b6b6',
                            width: 1,
                            dashArray: 3
                        }
                    }
                },
                yaxis: {
                    title: {
                        text: 'Nombre de Modules de Formations',
                        style: {
                            fontWeight: 'bold'
                        }
                    },
                    labels: {
                        formatter: function (val) {
                            return Math.round(val);
                        },
                        style: {
                            fontWeight: 'bold'
                        }
                    },
                    min: 0
                },
                tooltip: {
                    shared: true,
                    intersect: false,
                    y: {
                        formatter: function (val) {
                            return val;
                        }
                    },
                    custom: function ({series, seriesIndex, dataPointIndex}) {
                        const totalProposed = recommendedData.reduce((sum, val) => sum + val, 0);
                        const value = series[seriesIndex][dataPointIndex];
                        const percentage = totalProposed > 0 ? Math.round((value / totalProposed) * 100) : 0;

                        return '<div class="custom-tooltip">' +
                            '<span>' + (seriesIndex === 0 ? 'Formations Proposées: ' : 'Formations Validées: ') +
                            value + ' (' + percentage + '%)</span>' +
                            '</div>';
                    }
                },
                legend: {
                    position: 'top',
                    horizontalAlign: 'left',
                    offsetY: 10,
                    itemMargin: {
                        horizontal: 10,
                        vertical: 0
                    },
                    markers: {
                        width: 12,
                        height: 12,
                        radius: 2
                    },
                    fontSize: '12px'
                },
                fill: {
                    opacity: 1
                }
            };

            trainingChart = new ApexCharts(trainingCanvas, options);
            trainingChart.render();

            // Add brand logos after chart is rendered
            positionBrandLogos(trainingChart, relevantBrands, 10);
            window.addEventListener('resize', () => {
                positionBrandLogos(trainingChart, relevantBrands, -55);
            });
        }
    }

    // Update filter states based on selections
    function updateFilterStates() {
        const technicianSelected = filters.technicianId && filters.technicianId.value !== 'all';
        const countrySelected = filters.country && filters.country.value !== 'all';

        // Agency filter depends on country
        const agencyWrapper = document.getElementById('agencyFilterWrapper');
        if (agencyWrapper) {
            agencyWrapper.classList.toggle('filter-disabled', !countrySelected);
            if (filters.agency) {
                filters.agency.disabled = !countrySelected;
            }
        }

        console.log("Current filter state:", {
            technicianSelected: technicianSelected,
            countrySelected: countrySelected,
            technicianValue: filters.technicianId ? filters.technicianId.value : 'all'
        });

        if (technicianSelected) {
            console.log("TECHNICIAN FILTER SELECTED - Tracking loaded data");
            console.log("Selected technician ID:", filters.technicianId.value);

            const currentLevel = filters.level ? filters.level.value : 'all';
            const currentBrand = filters.brand ? filters.brand.value : 'all';

            console.log("Current level filter value:", currentLevel);
            console.log("Current brand filter value:", currentBrand);
        }
    }

    // Function to fetch dashboard data without page reload
    function fetchDashboardData(url) {
        toggleLoading(true);

        url = cleanURL(url);
        url.searchParams.set('_t', Date.now());
        console.log('Fetching dashboard data from:', url.toString());

        if (url.searchParams.get('technicianId') && url.searchParams.get('technicianId') !== 'all') {
            console.log("FETCHING DATA FOR TECHNICIAN:", url.searchParams.get('technicianId'));
        }

        fetch(url)
            .then(response => response.text())
            .then(html => {
                const parser = new DOMParser();
                const doc = parser.parseFromString(html, 'text/html');

                // Update dynamic filters
                const dynamicFiltersNew = doc.getElementById('dynamicFilters');
                const dynamicFilters = document.getElementById('dynamicFilters');
                if (dynamicFilters && dynamicFiltersNew) {
                    dynamicFilters.innerHTML = dynamicFiltersNew.innerHTML;

                    // Rebind filter elements
                    filters.country = document.getElementById('filterCountry');
                    filters.agency = document.getElementById('filterAgency');
                    filters.level = document.getElementById('filterLevel');
                    filters.brand = document.getElementById('filterBrand');
                    filters.technicianId = document.getElementById('filterTechnician');

                    bindFilterEvents();
                    updateFilterStates();

                    if (filters.technicianId && filters.technicianId.value !== 'all') {
                        console.log("AFTER UPDATE - Technician-specific data loaded:");
                        console.log("- Technician ID:", filters.technicianId.value);
                        console.log("- Level set to:", filters.level ? filters.level.value : 'none');
                        console.log("- Brand set to:", filters.brand ? filters.brand.value : 'none');

                        ['level', 'brand'].forEach(filterName => {
                            const el = filters[filterName];
                            if (el) {
                                console.log(`- ${filterName} filter disabled:`, el.disabled);
                                console.log(`- ${filterName} filter value:`, el.value);
                            }
                        });
                    }
                }

                // Update stats container with animation
                const statsContainerNew = doc.getElementById('statsContainer');
                if (statsContainerNew && statsContainer) {
                    statsContainer.style.opacity = 0;
                    statsContainer.style.transform = 'translateY(-10px)';
                    statsContainer.style.transition = 'all 0.3s ease';

                    setTimeout(() => {
                        statsContainer.innerHTML = statsContainerNew.innerHTML;
                        statsContainer.style.opacity = 1;
                        statsContainer.style.transform = 'translateY(0)';
                    }, 300);
                }

                // Update progress bars container with staggered animation
                const chartsRow = document.querySelector('.row.mb-5.mt-5.fade-in');
                const newChartsRow = doc.querySelector('.row.mb-5.mt-5.fade-in');
                if (chartsRow && newChartsRow) {
                    chartsRow.innerHTML = newChartsRow.innerHTML;
                    chartsRow.querySelectorAll('.card').forEach((card, index) => {
                        card.style.opacity = 0;
                        card.style.transform = 'translateY(20px)';
                        setTimeout(() => {
                            card.style.opacity = 1;
                            card.style.transform = 'translateY(0)';
                            card.style.transition = 'all 0.4s ease';
                        }, 100 * index);
                    });
                }

                // Update KPI cards
                const kpiRow = document.getElementById('kpiRow');
                const newKpiRow = doc.getElementById('kpiRow');
                if (kpiRow && newKpiRow) {
                    kpiRow.style.opacity = 0;
                    kpiRow.style.transform = 'translateX(-20px)';
                    kpiRow.style.transition = 'all 0.5s ease';

                    setTimeout(() => {
                        kpiRow.innerHTML = newKpiRow.innerHTML;
                        kpiRow.style.opacity = 1;
                        kpiRow.style.transform = 'translateX(0)';
                        resetAndStartCountUps();
                    }, 300);
                }

                // Update summary table if present
                const summaryTable = document.querySelector('.card.mb-4:last-of-type');
                const newSummaryTable = doc.querySelector('.card.mb-4:last-of-type');
                if (summaryTable && newSummaryTable) {
                    summaryTable.innerHTML = newSummaryTable.innerHTML;
                }

                // Update chart data
                const chartDataScript = doc.getElementById('chart-data');
                if (chartDataScript) {
                    try {
                        let newChartData;

                        // ‑ a) Script sous forme <script type="application/json">
                        if (chartDataScript.type === 'application/json') {
                            newChartData = JSON.parse(chartDataScript.textContent);

                        // ‑ b) Ancien format : window.DASHBOARD = {...};
                        } else {
                            const m = chartDataScript.textContent
                                        .match(/window\.DASHBOARD\s*=\s*(\{[\s\S]*\})/);
                            if (m) newChartData = JSON.parse(m[1]);
                        }

                        if (newChartData) {
                            window.DASHBOARD = newChartData;   // garde la variable “officielle”
                            window.chartData = newChartData;   // celle attendue par ton code

                            // Update cache of available brands if no specific brand is selected
                            const currentBrandFilter = filters.brand ? filters.brand.value : 'all';
                            if (currentBrandFilter === 'all') {
                                availableBrandsForCurrentFilters = [...(newChartData.brandStats || [])];
                            }
                        }

                        // Filter brand dropdown based on available data
                        filterBrandDropdown();
                    } catch (err) {
                        console.error('❌ Impossible de parser les nouvelles données', err);
                    }
                }



                // Re-initialize all charts
                initializeCharts();
                initializeCamembertCharts();

                // Add animation effect to indicate data refresh
                document.querySelectorAll('.card').forEach(card => {
                    card.classList.add('border-success');
                    card.classList.add('highlight-update');
                    setTimeout(() => {
                        card.classList.remove('border-success');
                        card.classList.remove('highlight-update');
                    }, 1500);
                });

                // Update percentage labels in doughnut chart wrappers
                const doughnutWrappers = document.querySelectorAll('.doughnut-wrapper');
                const newDoughnutWrappers = doc.querySelectorAll('.doughnut-wrapper');

                if (doughnutWrappers.length === newDoughnutWrappers.length) {
                    for (let i = 0; i < doughnutWrappers.length; i++) {
                        doughnutWrappers[i].setAttribute('data-label',
                            newDoughnutWrappers[i].getAttribute('data-label'));
                    }
                }

                toggleLoading(false);
            })
            .catch(error => {
                console.error('Error updating dashboard:', error);
                toggleLoading(false);

                const alertDiv = document.createElement('div');
                alertDiv.className = 'alert alert-danger alert-dismissible fade show mt-3';
                alertDiv.innerHTML = `
                    <strong>Erreur:</strong> Impossible de mettre à jour les données du tableau de bord.
                    <br><small>${error.message || 'Veuillez réessayer ou recharger la page.'}</small>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                `;
                document.querySelector('.container-fluid').prepend(alertDiv);

                setTimeout(() => {
                    if (alertDiv.parentNode) {
                        alertDiv.parentNode.removeChild(alertDiv);
                    }
                }, 5000);
            });
    }

    // Initialize camembert charts
    function initializeCamembertCharts() {
        console.log("ApexCharts initialized");

        const chartIds = ['juniorDonut', 'seniorDonut', 'expertDonut', 'totalDonut'];
        chartIds.forEach(id => {
            if (window[id + 'Chart']) {
                window[id + 'Chart'].destroy();
                window[id + 'Chart'] = null;
            }
        });

        initProgressBarAnimations();
    }

    // Function to animate progress bars with CountUp effect
    function initProgressBarAnimations() {
        const progressBars = document.querySelectorAll('.progress-bar');

        progressBars.forEach(bar => {
            const finalWidth = bar.style.width;
            const finalPercentage = bar.getAttribute('aria-valuenow');

            bar.style.width = '0%';
            bar.setAttribute('data-final-width', finalWidth);
            bar.setAttribute('data-final-percentage', finalPercentage);
            bar.textContent = '0%';
        });

        setTimeout(() => {
            progressBars.forEach((bar, index) => {
                setTimeout(() => {
                    const finalWidth = bar.getAttribute('data-final-width');
                    const finalPercentage = bar.getAttribute('data-final-percentage');

                    bar.style.transition = 'width 1.5s ease-in-out';
                    bar.style.width = finalWidth || finalPercentage + '%';

                    if (finalPercentage) {
                        const percentageValue = parseInt(finalPercentage, 10);
                        const tempId = 'progress-count-' + index;
                        bar.id = tempId;

                        if (typeof countUp !== 'undefined') {
                            const countUpInstance = new countUp.CountUp(tempId, percentageValue, {
                                duration: 1.5,
                                useEasing: true,
                                suffix: '%',
                                enableScrollSpy: false
                            });
                            countUpInstance.start();
                        }
                    }
                }, 150 * index);
            });
        }, 300);
    }

    // Function to initialize CountUp instances
    function initCountUps() {
        countUpInstances = [];

        document.querySelectorAll('[data-value]').forEach(el => {
            if (!el || !el.id) return;

            const value = parseFloat(el.getAttribute('data-value'));
            if (!isNaN(value)) {
                try {
                    if (typeof countUp !== 'undefined') {
                        const countUpInstance = new countUp.CountUp(el.id, value, {
                            duration: 2.5,
                            useEasing: true,
                            useGrouping: true,
                            separator: ' ',
                            decimal: ',',
                            enableScrollSpy: false
                        });

                        countUpInstances.push({
                            element: el,
                            countUp: countUpInstance
                        });

                        el.style.opacity = 1;
                    }
                } catch (error) {
                    console.error('Error creating CountUp for', el.id, error);
                }
            }
        });
    }

    // Function to start all CountUp animations
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
            }, 100 * index);
        });
    }

    // Update animations when filters change
    function resetAndStartCountUps() {
        initCountUps();
        startCountUpAnimations();
        initProgressBarAnimations();
    }

// -----------------------------------------------------------------------------
// Place les logos de marque sous chaque groupe de barres ApexCharts
// ─────────────────────────────────────────────────────────────────────────────-
function positionBrandLogos(chart, brands, offsetY = -15) {
  const logoContainer = document.getElementById('brandLogosContainer');
  if (!logoContainer) return;

  /* ------------------------------------------------------------------------
   * 1. Construire / mettre à jour un cache des divs logo déjà présentes
   * ---------------------------------------------------------------------- */
  const cache = {};               // brandName → <div>
  logoContainer.querySelectorAll('[data-brand]')
               .forEach(div => { cache[div.dataset.brand] = div; });

  /* ------------------------------------------------------------------------
   * 2. Calculer la position x pour chaque barre
   * ---------------------------------------------------------------------- */
  const chartElement = document.querySelector('#trainingChart');
  if (!chartElement) return;

  const barGroups    = chartElement.querySelectorAll('.apexcharts-series');
  if (barGroups.length < 2) return;      // pas encore rendu

  const plotArea     = chartElement.querySelector('.apexcharts-inner');
  if (!plotArea) return;

  const plotRect     = plotArea.getBoundingClientRect();
  const containerRect = logoContainer.getBoundingClientRect();
  const chartData     = getChartData();

  /* ------------------------------------------------------------------------
   * 3. Boucle sur les marques
   * ---------------------------------------------------------------------- */
  brands.forEach((brand, index) => {
    /* 3‑a. Position horizontale */
    const proposedBars  = barGroups[0].querySelectorAll('path')[index];
    const validatedBars = barGroups[1].querySelectorAll('path')[index];

    let centerX;
    if (proposedBars && validatedBars) {
      const p = proposedBars.getBoundingClientRect();
      const v = validatedBars.getBoundingClientRect();
      centerX = (p.left + p.right + v.left + v.right) / 4;
    } else {
      const groupWidth = plotRect.width / brands.length;
      centerX = plotRect.left + (index + 0.5) * groupWidth;
    }
    const xPos = centerX - containerRect.left;

    /* 3‑b. Créer ou réutiliser la div logo */
    let logoDiv = cache[brand];
    if (!logoDiv) {
      logoDiv = document.createElement('div');
      logoDiv.dataset.brand = brand;     // indispensable pour le cache
      logoDiv.style.cssText = `
        position:absolute;width:40px;height:40px;display:flex;
        justify-content:center;align-items:center;transform:translate(-50%,0)
      `;

      const img = document.createElement('img');
      img.src  = `/MEDACAP/public/assets/brands/${chartData.brandLogos[brand] || 'default-logo.png'}`;
      img.alt  = img.title = brand;
      img.style.cssText = 'width:100%;height:100%;object-fit:contain';

      img.onerror = () => {
        img.remove();
        logoDiv.textContent = brand.slice(0, 3);
        logoDiv.style.font   = '700 10px/40px sans-serif';
        logoDiv.style.color  = '#333';
      };

      logoDiv.appendChild(img);
      logoContainer.appendChild(logoDiv);   // seulement si nouveau
    }

    /* 3‑c. Mise à jour de la position (évite clignotement) */
    logoDiv.style.left = `${xPos}px`;
    logoDiv.style.top  = `${offsetY}px`;
  });
}

/* ---------------------------------------------------------------------------
 * 4. Handler resize unique (on le crée une seule fois)
 * ------------------------------------------------------------------------- */
(function () {
  let resizeTO;
  window.addEventListener('resize', () => {
    clearTimeout(resizeTO);
    resizeTO = setTimeout(() => {
      const chartData = getChartData();
      if (window.trainingChart && chartData.brandStats) {
        positionBrandLogos(window.trainingChart, chartData.brandStats, -55);
      }
    }, 250);
  });
})();


    // Initialize everything
    bindFilterEvents();
    initializeCharts();
    updateFilterStates();
    initializeCamembertCharts();

    // Initialize available brands cache
    availableBrandsForCurrentFilters = [...(getChartData().brandStats || [])];
    filterBrandDropdown();

    // Hide loading after initialization
    setTimeout(() => {
        toggleLoading(false);

        if (typeof countUp !== 'undefined') {
            startCountUpAnimations();
        } else {
            console.error('CountUp library not loaded properly');
        }
    }, 800);

    // Global error handler
    window.addEventListener('error', function (e) {
        console.error('JavaScript Error:', e.error);
        toggleLoading(false);
    });

    // Add resize handler to window
    window.addEventListener('resize', () => {
        if (window.resizeTimer) clearTimeout(window.resizeTimer);
        window.resizeTimer = setTimeout(() => {
            const chartData = getChartData();
            if (trainingChart && chartData.brandStats) {
                positionBrandLogos(trainingChart, chartData.brandStats, -55);
            }
        }, 250);
    });
});