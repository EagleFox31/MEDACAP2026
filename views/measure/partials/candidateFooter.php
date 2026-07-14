<?php
/**
 * Pied de page candidat — fermeture du layout léger.
 */
$year = date('Y');
?>
</main>
<footer class="cand-footer" role="contentinfo">
    <span><?php echo $year; ?> © CFAO Mobility Academy — Tous droits réservés</span>
</footer>
<style>
    .cand-footer {
        flex-shrink: 0;
        padding: 0.65rem 1.25rem;
        text-align: center;
        font-size: 0.72rem;
        color: rgba(15, 23, 42, 0.45);
        background: rgba(255, 255, 255, 0.45);
        backdrop-filter: blur(12px);
        -webkit-backdrop-filter: blur(12px);
        border-top: 1px solid rgba(255, 255, 255, 0.65);
    }
</style>
</body>
</html>
