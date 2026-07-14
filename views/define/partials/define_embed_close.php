<?php
/** Bouton fermer — mode embed (?embed=1). */
?>
<button type="button" id="define-embed-close" class="define-embed-close" aria-label="Fermer" title="Fermer">
    <span aria-hidden="true">&times;</span>
</button>
<style>
    .define-embed-close {
        position: fixed;
        top: 16px;
        right: 16px;
        z-index: 1050;
        width: 44px;
        height: 44px;
        padding: 0;
        border: none;
        border-radius: 50%;
        background: rgba(255, 255, 255, 0.92);
        backdrop-filter: blur(6px);
        box-shadow: 0 4px 16px rgba(0, 0, 0, 0.12);
        font-size: 28px;
        line-height: 1;
        color: #1e293b;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: transform 0.15s ease, background 0.15s ease;
    }
    .define-embed-close:hover {
        background: #fff;
        transform: scale(1.05);
    }
    .define-embed-close:focus-visible {
        outline: 2px solid #1a56db;
        outline-offset: 2px;
    }
</style>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        var btn = document.getElementById('define-embed-close');
        if (!btn) return;

        btn.addEventListener('click', function () {
            var params = new URLSearchParams(window.location.search);
            var returnUrl = params.get('returnUrl');

            if (returnUrl) {
                try {
                    window.location.href = decodeURIComponent(returnUrl);
                    return;
                } catch (e) {}
            }

            if (window.opener && !window.opener.closed) {
                window.close();
                return;
            }

            if (window.history.length > 1) {
                window.history.back();
                return;
            }

            window.close();
        });
    });
</script>
