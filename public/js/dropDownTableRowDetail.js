let currentOpenDetail = null;

function toggleDetail(element) {
    const row = element.closest('tr');
    const detailRow = row.nextElementSibling;

    if (!detailRow || !detailRow.classList.contains('detail-row')) return;

    const detailContent = detailRow.querySelector('.detail-content');
    if (!detailContent) return;

    const isOpening = detailContent.style.maxHeight === '0px' || !detailContent.style.maxHeight;

    // Fermer le détail précédent s'il est différent
    if (currentOpenDetail && currentOpenDetail !== detailContent) {
        closeDetail(currentOpenDetail);
    }

    if (isOpening) {
        // Ouvrir le nouveau détail
        detailRow.style.display = 'table-row';
        const maxHeight = Math.min(detailContent.scrollHeight, 300); // Limite à 300px max
        detailContent.style.maxHeight = maxHeight + 'px';
        row.classList.add('active');
        currentOpenDetail = detailContent;
    } else {
        // Fermer le détail actuel
        closeDetail(detailContent);
        currentOpenDetail = null;
    }
}

function closeDetail(detailContent) {
    if (!detailContent) return;

    const detailRow = detailContent.closest('.detail-row');
    const mainRow = detailRow ? detailRow.previousElementSibling : null;

    detailContent.style.maxHeight = '0';

    if (mainRow) mainRow.classList.remove('active');

    // Masquer la row après l'animation
    setTimeout(() => {
        if (detailRow && detailContent.style.maxHeight === '0px') {
            detailRow.style.display = 'none';
        }
    }, 300);
}