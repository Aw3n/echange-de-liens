/**
 * Echange de Liens - JavaScript principal
 */

document.addEventListener('DOMContentLoaded', function() {

    // ========================================
    // Dark/Light Mode Toggle
    // ========================================
    const themeToggle = document.getElementById('themeToggle');
    const html = document.documentElement;

    // Charger le thème sauvegardé
    const savedTheme = localStorage.getItem('theme') || 'light';
    html.setAttribute('data-theme', savedTheme);
    updateThemeIcon(savedTheme);

    if (themeToggle) {
        themeToggle.addEventListener('click', function() {
            const current = html.getAttribute('data-theme');
            const next = current === 'light' ? 'dark' : 'light';
            html.setAttribute('data-theme', next);
            localStorage.setItem('theme', next);
            updateThemeIcon(next);
        });
    }

    function updateThemeIcon(theme) {
        if (themeToggle) {
            themeToggle.innerHTML = theme === 'dark'
                ? '<i class="fas fa-sun"></i>'
                : '<i class="fas fa-moon"></i>';
        }
    }

    // ========================================
    // Mobile Menu
    // ========================================
    const mobileMenuBtn = document.getElementById('mobileMenuBtn');
    const mainNav = document.getElementById('mainNav');

    if (mobileMenuBtn && mainNav) {
        mobileMenuBtn.addEventListener('click', function() {
            mainNav.classList.toggle('show');
            const icon = mobileMenuBtn.querySelector('i');
            if (mainNav.classList.contains('show')) {
                icon.className = 'fas fa-times';
            } else {
                icon.className = 'fas fa-bars';
            }
        });
    }

    // ========================================
    // Auto-dismiss alerts
    // ========================================
    document.querySelectorAll('.alert-dismissible').forEach(function(alert) {
        setTimeout(function() {
            alert.style.transition = 'opacity 0.3s';
            alert.style.opacity = '0';
            setTimeout(function() { alert.remove(); }, 300);
        }, 5000);
    });

    // ========================================
    // Confirmations
    // ========================================
    document.querySelectorAll('[data-confirm]').forEach(function(el) {
        el.addEventListener('click', function(e) {
            if (!confirm(el.getAttribute('data-confirm'))) {
                e.preventDefault();
            }
        });
    });

    // ========================================
    // Copy to clipboard
    // ========================================
    document.querySelectorAll('[data-copy]').forEach(function(btn) {
        btn.addEventListener('click', function() {
            const text = btn.getAttribute('data-copy');
            navigator.clipboard.writeText(text).then(function() {
                showToast('Copié !');
            });
        });
    });

    // ========================================
    // Bannières HTML : affichage élargi (tous thèmes)
    // ========================================
    // Les widgets tiers (euroads, etc.) sont souvent injectés dans des
    // conteneurs/iframes plus petits que leur contenu réel → barres de
    // défilement et bannière rognée. On élargit la racine injectée et les
    // iframes à 100 % de l'emplacement, et on remonte les hauteurs trop
    // petites vers le format standard 728x90. Idempotent : relancé après
    // le load et un délai pour couvrir les scripts publicitaires asynchrones.
    function enlargeAdSlots() {
        document.querySelectorAll('.ad-slot').forEach(function(slot) {
            // Iframes publicitaires : largeur standard (728 max, réduite si
            // l'emplacement est plus étroit) et CENTRAGE horizontal ; le
            // contenu d'une iframe étant inaccessible (cross-origin), on
            // centre l'iframe elle-même plutôt que de l'étirer à 100 %.
            slot.querySelectorAll('iframe, embed, object').forEach(function(f) {
                var sw = slot.clientWidth || 728;
                f.style.width = Math.min(sw, 728) + 'px';
                f.style.maxWidth = '100%';
                f.style.display = 'block';
                f.style.margin = '0 auto';
                var h = parseInt(f.getAttribute('height'), 10) || 0;
                if (h > 0 && h < 90) { f.style.height = '90px'; }
            });
            Array.prototype.forEach.call(slot.children, function(child) {
                if (child.tagName === 'A' || child.tagName === 'IMG') { return; }
                child.style.width = '100%';
                child.style.maxWidth = '100%';
                child.style.overflow = 'visible';
                child.style.textAlign = 'center';
                if (child.tagName === 'DIV' || child.tagName === 'TABLE') { child.style.height = 'auto'; }
            });
        });
    }
    enlargeAdSlots();
    window.addEventListener('load', enlargeAdSlots);
    setTimeout(enlargeAdSlots, 1500);
    // Injection publicitaire tardive (scripts asynchrones) : recentre
    // automatiquement à chaque modification du DOM des emplacements
    // pendant les 15 premières secondes (childList uniquement : les
    // changements de style appliqués ne redéclenchent pas l'observer).
    if (window.MutationObserver) {
        var adObserver = new MutationObserver(function() { enlargeAdSlots(); });
        document.querySelectorAll('.ad-slot').forEach(function(slot) {
            adObserver.observe(slot, { childList: true, subtree: true });
        });
        setTimeout(function() { adObserver.disconnect(); }, 15000);
    }

    // ========================================
    // AJAX point assignment
    // ========================================
    document.querySelectorAll('.ajax-assign-points').forEach(function(form) {
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            const formData = new FormData(form);

            fetch('/links/assign-points', {
                method: 'POST',
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                body: formData
            })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (data.success) {
                    showToast(data.message, 'success');
                    setTimeout(function() { location.reload(); }, 1000);
                } else {
                    showToast(data.message, 'error');
                }
            })
            .catch(function() { showToast('Erreur réseau', 'error'); });
        });
    });
});

/**
 * Affiche un toast notification
 */
function showToast(message, type) {
    type = type || 'info';
    var toast = document.createElement('div');
    toast.className = 'alert alert-' + (type === 'error' ? 'danger' : type === 'success' ? 'success' : 'info');
    toast.style.cssText = 'position:fixed;bottom:20px;right:20px;z-index:9999;padding:12px 20px;border-radius:8px;animation:slideUp 0.3s;';
    toast.textContent = message;
    document.body.appendChild(toast);
    setTimeout(function() {
        toast.style.opacity = '0';
        toast.style.transition = 'opacity 0.3s';
        setTimeout(function() { toast.remove(); }, 300);
    }, 3000);
}
