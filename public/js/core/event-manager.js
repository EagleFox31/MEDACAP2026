// Gestion centralisée des événements
class EventManager {
    constructor() {
        this.handlers = {};
    }

    on(event, selector, handler) {
        if (!this.handlers[event]) this.handlers[event] = [];
        this.handlers[event].push({ selector, handler });

        document.addEventListener(event, e => {
            const target = e.target.closest(selector);
            if (target) handler(e, target);
        });
    }

    trigger(event, data) {
        const eventObj = new CustomEvent(event, { detail: data });
        document.dispatchEvent(eventObj);
    }
}

const eventManager = new EventManager();

// Initialisation des événements
// document.addEventListener('DOMContentLoaded', () => {
//     // Boutons de formulaire
//     eventManager.on('click', 'button[form]', async (e, button) => {
//         e.preventDefault();
//
//         const formId = button.getAttribute("form");
//         const form = document.getElementById(formId);
//         if (!form) return;
//
//         // Récupère l'URL du formulaire
//         let formAction = form.getAttribute("action") || "";
//         let fullUrl;
//
//         // Si l'URL est déjà absolue (http:// ou https://), on ne préfixe pas
//         if (/^https?:\/\//i.test(formAction)) {
//             fullUrl = formAction;
//         } else {
//             const basePath = window.location.pathname.split("/").slice(0, -1).join("/");
//             const baseUrl = window.location.origin + basePath;
//             fullUrl = baseUrl.replace(/\/+$/, "") + "/" + formAction.replace(/^\/+/, "");
//         }
//
//         // Ajoute les paramètres GET actuels
//         const getParams = new URLSearchParams(window.location.search);
//         if (getParams.toString()) {
//             // Gère correctement l'ajout des paramètres selon si l'URL a déjà des paramètres
//             fullUrl += (fullUrl.includes('?') ? '&' : '?') + getParams.toString();
//         }
//
//         // Détermine la méthode HTTP à utiliser
//         let method = "POST";
//         const methodOverride = form.querySelector('input[name="_method"]');
//         if (methodOverride) {
//             method = methodOverride.value.toUpperCase();
//         } else {
//             method = (form.getAttribute("method") || "POST").toUpperCase();
//         }
//
//         // Prépare les options pour fetch
//         const fetchOptions = {
//             method: method,
//             headers: {}
//         };
//
//         // Pour les méthodes GET/HEAD, on passe les données dans l'URL
//         // Pour les autres méthodes, on utilise FormData dans le corps
//         if (["GET", "HEAD"].includes(method)) {
//             // Convertir FormData en paramètres URL
//             const formData = new FormData(form);
//             const params = new URLSearchParams();
//
//             for (const [key, value] of formData.entries()) {
//                 // Ne pas inclure _method dans les paramètres GET
//                 if (key !== "_method") {
//                     params.append(key, value);
//                 }
//             }
//
//             // Ajouter les paramètres à l'URL
//             fullUrl += (fullUrl.includes('?') ? '&' : '?') + params.toString();
//         } else {
//             // Pour POST/PUT/PATCH/DELETE, utiliser FormData dans le corps
//             const formData = new FormData(form);
//
//             // Supprimer _method du FormData car il est déjà dans la méthode
//             if (methodOverride) {
//                 formData.delete("_method");
//             }
//
//             // Ajouter le FormData au corps de la requête
//             fetchOptions.body = formData;
//         }
//
//         try {
//             const response = await fetch(fullUrl, fetchOptions);
//
//             // Vérifier le type de contenu de la réponse
//             const contentType = response.headers.get('content-type');
//
//             if (contentType && contentType.includes('application/json')) {
//                 // Traitement JSON pour les réponses API
//                 const result = await response.json();
//
//                 if (result.success) {
//                     uiManager.showToast(result.message || "Opération réussie", "success");
//
//                     // Fermer le formulaire après mise à jour
//                     if (method !== "GET") {
//                         setTimeout(() => {
//                             const formContainer = document.querySelector('.custom-main-panel > .glass-effect:last-child');
//                             formContainer.innerHTML = '';
//                         }, 2000);
//                     }
//                 } else {
//                     uiManager.showToast(result.message || "Une erreur est survenue", "error");
//                 }
//             } else {
//                 // Traitement HTML pour les réponses de formulaire
//                 const html = await response.text();
//
//                 if (method === "GET") {
//                     // Charger le HTML directement dans la colonne
//                     const formContainer = document.querySelector('.custom-main-panel > .glass-effect:last-child');
//                     formContainer.innerHTML = html;
//
//                     // Réinitialiser les composants JS si nécessaire
//                     initFormComponents();
//                 } else {
//                     // Pour les autres méthodes, afficher un message
//                     uiManager.showToast("Opération réussie", "success");
//                 }
//             }
//         } catch (error) {
//             uiManager.showToast("Erreur réseau ou serveur : " + error.message, "error");
//         }
//     })
//
//     // Barre de recherche dynamique (avec debounce)
//     eventManager.on('input', '#mySearchInput', debounce((e, input) => {
//         const value = input.value.trim();
//         const params = new URLSearchParams(window.location.search);
//
//         if (value) {
//             params.set('filter_value', value);
//         } else {
//             params.delete('filter_value');
//         }
//
//         const newUrl = window.location.pathname + (params.toString() ? '?' + params.toString() : '');
//         window.history.replaceState({}, '', newUrl);
//
//         // Appel AJAX pour recharger le contenu
//         ajaxManager.reloadFilteredContent("trainings-informations");
//     }, 300));
// });



// Réinitialisation après chargement AJAX
document.addEventListener('content-updated', () => {
    // Délai pour laisser le DOM se mettre à jour
    setTimeout(() => {
        try {
            // Réinitialiser les animations
            if (typeof animationManager !== 'undefined') {
                animationManager.resetAndStartAnimations();
            }

            // Réinitialiser les autres composants
            if (typeof FiltersManager !== 'undefined') FiltersManager.init();
            if (typeof TableRowManager !== 'undefined') TableRowManager.init();
        } catch (error) {
            console.error('Content update error:', error);
        }
    }, 100); // 100ms delay
});
