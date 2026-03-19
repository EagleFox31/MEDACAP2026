// Gestion des requêtes AJAX
class AjaxManager {
    async reloadFilteredContent(targetId) {
        const targetElement = document.getElementById(targetId);
        if (!targetElement) return;

        const url = window.location.pathname + window.location.search;
        const scrollPosition = window.scrollY;

        // Afficher le squelette
        uiManager.showSkeleton(targetElement);

        try {
            const response = await fetch(url, {
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            });

            if (!response.ok) throw new Error("Erreur de chargement des données");

            const html = await response.text();
            const parser = new DOMParser();
            const doc = parser.parseFromString(html, "text/html");
            const newContent = doc.getElementById(targetId);

            if (newContent) {
                targetElement.innerHTML = newContent.innerHTML;
                window.scrollTo(0, scrollPosition);

                // 🔽🔽🔽 NOUVEAU CODE POUR SELECT2 🔽🔽🔽
                // Réinitialiser Select2 dans la zone rafraîchie
                $(targetElement).find('select[data-control="select2"]').each(function() {
                    // Détruire l'instance existante si nécessaire
                    if ($(this).hasClass('select2-hidden-accessible')) {
                        $(this).select2('destroy');
                    }
                    // Réinitialiser Select2
                    $(this).select2({
                        width: '100%',
                        // Ajouter d'autres options si nécessaire
                    });
                });
                // 🔼🔼🔼 FIN DU NOUVEAU CODE 🔼🔼🔼

                // Déclencher l'événement de mise à jour
                eventManager.trigger('content-updated');
            }
        } catch (err) {
            console.error('AJAX error:', err);
            targetElement.innerHTML =
                `<div class="alert alert-danger">
                <div class="d-flex flex-column align-items-center py-4">
                    <i class="bi bi-exclamation-triangle-fill fs-1 text-danger mb-3"></i>
                    <h5 class="mb-3">Erreur de chargement</h5>
                    <button class="btn btn-primary" onclick="ajaxManager.reloadFilteredContent('${targetId}')">
                        Réessayer
                    </button>
                </div>
            </div>`;
        } finally {
            targetElement.classList.remove('loading');
        }
    }
}

const ajaxManager = new AjaxManager();