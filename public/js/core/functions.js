// Fonction pour réinitialiser tous les composants du formulaire
function initFormComponents() {
    // 1. Select2
    document.querySelectorAll('select[data-control="select2"]').forEach(select => {
        if ($(select).hasClass("select2-hidden-accessible")) {
            $(select).select2('destroy');
        }
        $(select).select2({
            placeholder: $(select).attr('placeholder') || 'Sélectionnez...',
            allowClear: true,
            width: '100%'
        });
    });

    // 2. Dates répétables
    document.querySelectorAll('.remove-date').forEach(btn => {
        btn.removeEventListener('click', removeDate);
        btn.addEventListener('click', function() { removeDate(this); });
    });

    document.getElementById('add-date-btn')?.removeEventListener('click', addDate);
    document.getElementById('add-date-btn')?.addEventListener('click', addDate);

    // 3. Drag&drop fichiers
    document.querySelectorAll(".modern-file-upload").forEach(dropZone => {
        let fileInput = dropZone.querySelector("input[type=file]");
        let fileNameDisplay = dropZone.querySelector(".file-name");
        let spinner = dropZone.querySelector(".file-loading-spinner");

        // Nettoyage anciens listeners
        dropZone.replaceWith(dropZone.cloneNode(true));
        dropZone = document.querySelector(".modern-file-upload"); // reselect après clone

        dropZone.addEventListener("click", () => fileInput.click());
        dropZone.addEventListener("dragover", e => { e.preventDefault(); dropZone.classList.add("dragover"); });
        dropZone.addEventListener("dragleave", () => dropZone.classList.remove("dragover"));
        dropZone.addEventListener("drop", e => {
            e.preventDefault();
            dropZone.classList.remove("dragover");
            spinner.classList.add("active");
            fileInput.files = e.dataTransfer.files;
            setTimeout(() => updateFileName(fileInput, fileNameDisplay, spinner), 300);
        });
        fileInput.addEventListener("change", () => {
            spinner.classList.add("active");
            setTimeout(() => updateFileName(fileInput, fileNameDisplay, spinner), 300);
        });
    });
}


function debounce(func, delay = 300) {
    let timeout;
    return function (...args) {
        clearTimeout(timeout);
        timeout = setTimeout(() => func.apply(this, args), delay);
    };
}

// --- Fonction réutilisable ---
async function handleAjaxFormSubmit(form, button = null) {
    if (!form) return;

    // URL complète
    let formAction = form.getAttribute("action") || "";
    let fullUrl = /^https?:\/\//i.test(formAction)
        ? formAction
        : window.location.origin + "/" + formAction.replace(/^\/+/, "");

    // Ajouter GET params actuels de la page
    const currentParams = new URLSearchParams(window.location.search);
    if (currentParams.toString()) {
        const urlObj = new URL(fullUrl, window.location.origin);
        currentParams.forEach((val, key) => {
            if (!urlObj.searchParams.has(key)) urlObj.searchParams.set(key, val);
        });
        fullUrl = urlObj.toString();
    }

    // Toujours POST
    const fetchOptions = { method: "POST", headers: {} };
    const formData = new FormData(form);

    // Si le bouton cliqué est fourni → ajouter sa valeur
    if (button && button.name) {
        formData.append(button.name, button.value);
    }

    // Si le formulaire n’a pas de _method mais method != POST, on l’ajoute
    const method = (form.getAttribute("method") || "POST").toUpperCase();
    if (method !== "POST" && !formData.has("_method")) {
        formData.append("_method", method);
    }

    // Ajout des données dans le body
    fetchOptions.body = formData;

    // Debug: voir les données envoyées
    const debugData = {};
    formData.forEach((v, k) => debugData[k] = v);
    console.log("Données envoyées:", debugData);

    // Envoi
    return fetch(fullUrl, fetchOptions);
}
