(function () {
    'use strict';

    function clearMedacapCookies() {
        document.cookie = 'userFullName=; Max-Age=-99999999; path=/';
        document.cookie = 'userObjectId=; Max-Age=-99999999; path=/';
        document.cookie = 'chatExpanded=; Max-Age=-99999999; path=/';
    }

    function bindLogoutButton() {
        var btn = document.getElementById('medacap-logout-button');
        if (!btn || btn.getAttribute('data-medacap-bound') === '1') {
            return;
        }
        btn.setAttribute('data-medacap-bound', '1');
        btn.addEventListener('click', function (event) {
            event.preventDefault();
            clearMedacapCookies();
            var redirect = btn.getAttribute('data-logout-redirect') || btn.getAttribute('href') || './logout.php';
            window.location.href = redirect;
        });
    }

    function initLoadingSpinnerHide() {
        document.body.classList.add('loading-active');

        function hideSpinner() {
            var el = document.getElementById('spinner');
            if (el) {
                el.classList.remove('show');
                el.style.display = 'none';
            }
            document.body.classList.remove('loading-active');
        }

        setTimeout(function () {
            if (document.readyState === 'complete') {
                hideSpinner();
            }
        }, 100);

        setTimeout(hideSpinner, 1500);
        window.addEventListener('load', hideSpinner);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', bindLogoutButton);
    } else {
        bindLogoutButton();
    }

    initLoadingSpinnerHide();
})();
