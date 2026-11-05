/**
 * Modales de l'application (remplacent confirm() / alert() du navigateur).
 *
 *   MedacapModal.confirm('Supprimer ?', { title: 'Confirmation', okText: 'Supprimer', tone: 'danger' })
 *       .then(function (ok) { ... });
 *   MedacapModal.alert('Enregistré.');
 *
 * Formulaire : <form data-confirm="Ajouter {minutes} minutes ?" data-confirm-title="..." data-confirm-ok="..."
 * data-confirm-tone="danger"> — {champ} est remplacé par la valeur du champ du formulaire. Le bouton cliqué
 * (name/value) est conservé à l'envoi.
 */
(function () {
    if (window.MedacapModal) {
        return;
    }

    var css = ''
        + '.mdc-modal{position:fixed;inset:0;z-index:20000;display:none;align-items:center;justify-content:center;'
        + 'padding:16px;background:rgba(17,24,39,.45);backdrop-filter:blur(2px)}'
        + '.mdc-modal.open{display:flex}'
        + '.mdc-modal-box{width:100%;max-width:420px;background:#fff;border-radius:16px;padding:22px 24px 18px;'
        + 'box-shadow:0 20px 50px rgba(0,0,0,.25);font-family:inherit;animation:mdc-in .15s ease-out}'
        + '@keyframes mdc-in{from{opacity:0;transform:translateY(8px) scale(.98)}to{opacity:1;transform:none}}'
        + '.mdc-modal-title{margin:0 0 8px;font-size:1.05rem;font-weight:700;color:#111827}'
        + '.mdc-modal-text{margin:0;font-size:.92rem;line-height:1.5;color:#374151;white-space:pre-line}'
        + '.mdc-modal-actions{display:flex;justify-content:flex-end;gap:8px;margin-top:20px}'
        + '.mdc-modal-actions button{border:1px solid #d1d5db;background:#fff;color:#374151;border-radius:10px;'
        + 'padding:8px 16px;font-size:.88rem;font-weight:600;cursor:pointer;font-family:inherit}'
        + '.mdc-modal-actions button:hover{background:#f9fafb}'
        + '.mdc-modal-actions .mdc-ok{background:#2563eb;border-color:#2563eb;color:#fff}'
        + '.mdc-modal-actions .mdc-ok:hover{background:#1d4ed8}'
        + '.mdc-modal.danger .mdc-ok{background:#dc2626;border-color:#dc2626}'
        + '.mdc-modal.danger .mdc-ok:hover{background:#b91c1c}';

    var root, titleEl, textEl, okBtn, cancelBtn, pending = null;

    function build() {
        if (root) {
            return;
        }
        var style = document.createElement('style');
        style.textContent = css;
        document.head.appendChild(style);

        root = document.createElement('div');
        root.className = 'mdc-modal';
        root.setAttribute('role', 'dialog');
        root.setAttribute('aria-modal', 'true');
        root.innerHTML = '<div class="mdc-modal-box"><h3 class="mdc-modal-title"></h3><p class="mdc-modal-text"></p>'
            + '<div class="mdc-modal-actions"><button type="button" class="mdc-cancel"></button>'
            + '<button type="button" class="mdc-ok"></button></div></div>';
        document.body.appendChild(root);
        titleEl = root.querySelector('.mdc-modal-title');
        textEl = root.querySelector('.mdc-modal-text');
        okBtn = root.querySelector('.mdc-ok');
        cancelBtn = root.querySelector('.mdc-cancel');

        okBtn.addEventListener('click', function () { close(true); });
        cancelBtn.addEventListener('click', function () { close(false); });
        root.addEventListener('click', function (e) {
            if (e.target === root) {
                close(false);
            }
        });
        document.addEventListener('keydown', function (e) {
            if (!pending) {
                return;
            }
            if (e.key === 'Escape') {
                close(false);
            } else if (e.key === 'Enter') {
                e.preventDefault();
                close(true);
            }
        });
    }

    function close(result) {
        root.classList.remove('open');
        var resolve = pending;
        pending = null;
        if (resolve) {
            resolve(result);
        }
    }

    function open(message, opts, withCancel) {
        build();
        if (pending) {
            close(false);
        }
        opts = opts || {};
        titleEl.textContent = opts.title || (withCancel ? 'Confirmation' : 'Information');
        titleEl.style.display = opts.title === '' ? 'none' : '';
        textEl.textContent = message;
        okBtn.textContent = opts.okText || 'OK';
        cancelBtn.textContent = opts.cancelText || 'Annuler';
        cancelBtn.style.display = withCancel ? '' : 'none';
        root.classList.toggle('danger', opts.tone === 'danger');
        root.classList.add('open');
        okBtn.focus();
        return new Promise(function (resolve) { pending = resolve; });
    }

    window.MedacapModal = {
        confirm: function (message, opts) { return open(message, opts, true); },
        alert: function (message, opts) { return open(message, opts, false); },
        isOpen: function () { return !!pending; }
    };

    document.addEventListener('submit', function (e) {
        var form = e.target;
        if (!form.hasAttribute || !form.hasAttribute('data-confirm')) {
            return;
        }
        e.preventDefault();
        var submitter = e.submitter || null;
        var message = form.getAttribute('data-confirm').replace(/\{(\w+)\}/g, function (all, name) {
            var field = form.elements[name];
            return field ? field.value : all;
        });
        window.MedacapModal.confirm(message, {
            title: form.getAttribute('data-confirm-title') || undefined,
            okText: form.getAttribute('data-confirm-ok') || undefined,
            tone: form.getAttribute('data-confirm-tone') || undefined
        }).then(function (ok) {
            if (!ok) {
                return;
            }
            if (submitter && submitter.name) {
                var hidden = document.createElement('input');
                hidden.type = 'hidden';
                hidden.name = submitter.name;
                hidden.value = submitter.value;
                form.appendChild(hidden);
            }
            HTMLFormElement.prototype.submit.call(form);
        });
    });
})();
