/**
 * Mise à jour AJAX du dashboard collaborateurs (sans rechargement complet).
 */
(function () {
    var pane = document.getElementById('supervisor-dashboard-pane');
    if (!pane) return;

    var endpoint = pane.getAttribute('data-endpoint') || './collaboratorDashboard';
    var loading = document.getElementById('supervisor-dashboard-loading');

    function setLoading(on) {
        if (!loading) return;
        loading.classList.toggle('d-none', !on);
    }

    function runPaneScripts(container) {
        container.querySelectorAll('script').forEach(function (oldScript) {
            var script = document.createElement('script');
            if (oldScript.src) {
                script.src = oldScript.src;
            } else {
                script.textContent = oldScript.textContent;
            }
            oldScript.replaceWith(script);
        });
    }

    function updateTechnicianSelect(technicians, selectedId) {
        var select = document.getElementById('filterTechnician');
        if (!select) return;
        var current = selectedId || select.value || 'all';
        select.innerHTML = '';
        var allOpt = document.createElement('option');
        allOpt.value = 'all';
        allOpt.textContent = 'Tous les collaborateurs';
        if (current === 'all') allOpt.selected = true;
        select.appendChild(allOpt);
        Object.keys(technicians || {}).forEach(function (id) {
            var opt = document.createElement('option');
            opt.value = id;
            opt.textContent = technicians[id];
            if (String(current) === String(id)) opt.selected = true;
            select.appendChild(opt);
        });
    }

    function buildUrl(params) {
        var url = new URL(endpoint, window.location.href);
        var current = new URL(window.location.href);
        ['embed', 'returnUrl'].forEach(function (key) {
            var value = current.searchParams.get(key);
            if (value && params[key] === undefined) {
                params[key] = value;
            }
        });
        Object.keys(params).forEach(function (key) {
            if (params[key] !== undefined && params[key] !== null && params[key] !== '') {
                url.searchParams.set(key, params[key]);
            }
        });
        return url.toString();
    }

    function getUrlFilterDefaults() {
        var parsed = new URL(window.location.href);
        return {
            agency: parsed.searchParams.get('agency') || 'all',
            technicianId: parsed.searchParams.get('technicianId') || 'all',
            level: parsed.searchParams.get('level') || 'all',
            brand: parsed.searchParams.get('brand') || 'all',
            scope: parsed.searchParams.get('scope') || parsed.searchParams.get('year') || 'pluri'
        };
    }

    function getEmbedFilterParams() {
        var form = document.getElementById('embedPlanFilterForm');
        if (!form) return null;
        var defaults = getUrlFilterDefaults();
        return {
            agency: defaults.agency,
            technicianId: defaults.technicianId,
            brand: form.querySelector('[name="brand"]') ? form.querySelector('[name="brand"]').value : defaults.brand,
            level: form.querySelector('[name="level"]') ? form.querySelector('[name="level"]').value : defaults.level,
            scope: form.querySelector('[name="scope"]') ? form.querySelector('[name="scope"]').value : defaults.scope
        };
    }

    function initEmbedBrandPicker() {
        var picker = document.querySelector('[data-embed-brand-picker]');
        if (!picker || picker.dataset.ready === '1') return;
        picker.dataset.ready = '1';

        var hidden = picker.querySelector('[name="brand"]');
        var toggle = picker.querySelector('.embed-brand-picker-toggle');
        var menu = picker.querySelector('.embed-brand-picker-menu');
        var current = picker.querySelector('.embed-brand-picker-current');
        if (!hidden || !toggle || !menu || !current) return;

        function closeMenu() {
            menu.hidden = true;
            toggle.setAttribute('aria-expanded', 'false');
            picker.classList.remove('is-open');
        }

        function openMenu() {
            menu.hidden = false;
            toggle.setAttribute('aria-expanded', 'true');
            picker.classList.add('is-open');
        }

        function renderCurrent(value, label, logo) {
            hidden.value = value;
            if (value === 'all') {
                current.innerHTML =
                    '<span class="embed-brand-picker-all-icon" aria-hidden="true">' +
                    '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">' +
                    '<rect x="3" y="3" width="7" height="7" rx="1"></rect>' +
                    '<rect x="14" y="3" width="7" height="7" rx="1"></rect>' +
                    '<rect x="3" y="14" width="7" height="7" rx="1"></rect>' +
                    '<rect x="14" y="14" width="7" height="7" rx="1"></rect>' +
                    '</svg></span>' +
                    '<span class="embed-brand-picker-label"></span>';
            } else {
                current.innerHTML =
                    '<img alt="" class="embed-brand-picker-logo" loading="lazy" decoding="async" ' +
                    'onerror="this.src=\'/MEDACAP/public/images/default.png\'">' +
                    '<span class="embed-brand-picker-label"></span>';
                var img = current.querySelector('img');
                if (img) img.src = logo || '/MEDACAP/public/images/default.png';
            }
            var labelEl = current.querySelector('.embed-brand-picker-label');
            if (labelEl) labelEl.textContent = label;
        }

        toggle.addEventListener('click', function () {
            if (menu.hidden) openMenu();
            else closeMenu();
        });

        menu.querySelectorAll('.embed-brand-picker-option').forEach(function (option) {
            option.addEventListener('click', function () {
                var value = option.getAttribute('data-value') || 'all';
                var label = option.getAttribute('data-label') || value;
                var logo = option.getAttribute('data-logo') || '';

                menu.querySelectorAll('.embed-brand-picker-option').forEach(function (item) {
                    var active = item === option;
                    item.classList.toggle('is-active', active);
                    item.setAttribute('aria-selected', active ? 'true' : 'false');
                });

                renderCurrent(value, label, logo);
                closeMenu();

                var embedForm = document.getElementById('embedPlanFilterForm');
                if (embedForm && window.CollaboratorDashboard && window.CollaboratorDashboard.load) {
                    window.CollaboratorDashboard.load(getEmbedFilterParams());
                }
            });
        });

        document.addEventListener('click', function (e) {
            if (!picker.contains(e.target)) closeMenu();
        });

        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') closeMenu();
        });
    }

    function getFilterParams() {
        var embedParams = getEmbedFilterParams();
        if (embedParams) return embedParams;

        var defaults = getUrlFilterDefaults();
        var agency = document.getElementById('filterAgency');
        var tech = document.getElementById('filterTechnician');
        var levelInput = document.querySelector('#supervisorFilterForm input[name="level"]');
        var brandInput = document.querySelector('#supervisorFilterForm input[name="brand"]');
        return {
            agency: agency ? agency.value : defaults.agency,
            technicianId: tech ? tech.value : defaults.technicianId,
            level: levelInput ? levelInput.value : defaults.level,
            brand: brandInput ? brandInput.value : defaults.brand,
            scope: defaults.scope
        };
    }

    function load(params, pushState) {
        if (typeof pushState === 'undefined') pushState = true;
        var url = buildUrl(Object.assign({ partial: '1' }, params));
        setLoading(true);

        return fetch(url, {
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin'
        })
            .then(function (res) {
                if (!res.ok) throw new Error('HTTP ' + res.status);
                return res.json();
            })
            .then(function (data) {
                pane.innerHTML = data.html || '';
                runPaneScripts(pane);
                updateTechnicianSelect(data.technicians || {}, data.technicianId || 'all');
                if (data.title) {
                    document.title = data.title;
                }

                var publicUrl = buildUrl(params);
                if (pushState) {
                    history.replaceState(null, '', publicUrl);
                }

                var levelInput = document.querySelector('#supervisorFilterForm input[name="level"]');
                var brandInput = document.querySelector('#supervisorFilterForm input[name="brand"]');
                if (levelInput) levelInput.value = params.level || 'all';
                if (brandInput) brandInput.value = params.brand || 'all';
            })
            .catch(function (err) {
                console.error(err);
                pane.innerHTML = '<div class="alert alert-danger mt-2">Impossible de charger les données.</div>';
            })
            .finally(function () {
                setLoading(false);
            });
    }

    function loadFromUrl(url) {
        var parsed = new URL(url, window.location.href);
        return load({
            agency: parsed.searchParams.get('agency') || 'all',
            technicianId: parsed.searchParams.get('technicianId') || 'all',
            level: parsed.searchParams.get('level') || 'all',
            brand: parsed.searchParams.get('brand') || 'all',
            scope: parsed.searchParams.get('scope') || parsed.searchParams.get('year') || 'pluri'
        });
    }

    window.CollaboratorDashboard = {
        load: load,
        loadFromUrl: loadFromUrl
    };

    var form = document.getElementById('supervisorFilterForm');
    if (form) {
        form.addEventListener('submit', function (e) {
            e.preventDefault();
            load(getFilterParams());
        });
    }

    var embedForm = document.getElementById('embedPlanFilterForm');
    if (embedForm) {
        initEmbedBrandPicker();
        embedForm.querySelectorAll('select').forEach(function (select) {
            select.addEventListener('change', function () {
                load(getEmbedFilterParams());
            });
        });
    }

    var agencySelect = document.getElementById('filterAgency');
    if (agencySelect) {
        agencySelect.addEventListener('change', function () {
            var params = getFilterParams();
            params.technicianId = 'all';
            load(params);
        });
    }

    var techSelect = document.getElementById('filterTechnician');
    if (techSelect) {
        techSelect.addEventListener('change', function () {
            load(getFilterParams());
        });
    }

    pane.addEventListener('click', function (e) {
        var link = e.target.closest('a.supervisor-dashboard-nav');
        if (!link) return;
        e.preventDefault();
        loadFromUrl(link.href);
    });
})();
