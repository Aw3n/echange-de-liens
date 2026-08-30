<?php
// Module « Balloon Pop-Up » : inclus dans le layout principal.
// Quand le déclencheur (toutes les X visites validées) est consommé
// pour l'utilisateur connecté, 1 à 3 ballons dorés flottent de bas en
// haut à vitesses et positions aléatoires ; chaque ballon cliqué
// éclate et rapporte des points (tirage serveur via /balloon/pop).
?>
<?php $balloonUser = \App\Core\Session::user(); ?>
<?php if ($balloonUser && balloon_take_pending((int) $balloonUser['id'])): ?>
<style>
.balloon-layer{position:fixed;inset:0;pointer-events:none;z-index:9999;overflow:hidden}
.balloon-rise{position:absolute;bottom:-190px;pointer-events:auto;cursor:pointer;animation:balloonRise var(--dur) linear forwards;will-change:transform}
.balloon-rise img{display:block;width:var(--size);height:auto;filter:drop-shadow(0 6px 14px rgba(0,0,0,.28));animation:balloonSway var(--swaydur) ease-in-out infinite alternate}
.balloon-rise.popped{animation:none}
.balloon-rise.popped img{animation:balloonPop .35s ease-out forwards}
.balloon-gain{position:fixed;z-index:10000;font-weight:800;font-size:1.05rem;color:#8a6d1a;background:rgba(255,255,255,.92);border:1px solid #d4af37;border-radius:999px;padding:.15rem .7rem;pointer-events:none;animation:balloonGain 1.4s ease-out forwards}
@keyframes balloonRise{from{transform:translateY(0)}to{transform:translateY(calc(-100vh - 240px))}}
@keyframes balloonSway{from{transform:translateX(calc(var(--sway) * -1)) rotate(-4deg)}to{transform:translateX(var(--sway)) rotate(4deg)}}
@keyframes balloonPop{0%{transform:scale(1);opacity:1}60%{transform:scale(1.35);opacity:.9}100%{transform:scale(0);opacity:0}}
@keyframes balloonGain{from{transform:translateY(0);opacity:1}to{transform:translateY(-70px);opacity:0}}
</style>
<script>
(function () {
    var IMG = '<?= e(base_path('assets/img/balloon.png')) ?>';
    var URL = '<?= e(base_path('balloon/pop')) ?>';
    var CSRF = '<?= e($csrf_token ?? \App\Core\Session::csrfToken()) ?>';
    var LBL_POINTS = <?= json_encode(tr('points')) ?>;

    // Petit délai : la page finit de se peindre avant l'apparition
    setTimeout(function () {
        var layer = document.createElement('div');
        layer.className = 'balloon-layer';
        document.body.appendChild(layer);
        var n = 1 + Math.floor(Math.random() * 3); // 1 à 3 ballons
        for (var i = 0; i < n; i++) { spawn(layer, i); }
    }, 1200);

    function spawn(layer, i) {
        setTimeout(function () {
            var w = document.createElement('div');
            w.className = 'balloon-rise';
            // Taille, vitesse de montée (assez lente pour cliquer),
            // amplitude et vitesse de balancement : tout aléatoire
            w.style.left = (5 + Math.random() * 85).toFixed(1) + 'vw';
            w.style.setProperty('--dur', (10 + Math.random() * 6).toFixed(2) + 's');
            w.style.setProperty('--size', (80 + Math.round(Math.random() * 50)) + 'px');
            w.style.setProperty('--sway', (10 + Math.round(Math.random() * 40)) + 'px');
            w.style.setProperty('--swaydur', (1.2 + Math.random() * 1.6).toFixed(2) + 's');
            var img = document.createElement('img');
            img.src = IMG;
            img.alt = '';
            w.appendChild(img);
            layer.appendChild(w);

            // Ballon sorti de l'écran sans être cliqué : nettoyage
            w.addEventListener('animationend', function (ev) {
                if (ev.animationName === 'balloonRise') { w.remove(); }
            });

            // Clic : le ballon éclate, disparaît, et rapporte
            // un nombre de points tiré côté serveur
            w.addEventListener('click', function () {
                if (w.dataset.popped) { return; }
                w.dataset.popped = '1';
                var rect = w.getBoundingClientRect();
                w.classList.add('popped');
                setTimeout(function () { w.remove(); }, 400);
                var body = new URLSearchParams();
                body.append('_csrf_token', CSRF);
                fetch(URL, { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: body.toString() })
                    .then(function (res) { return res.json(); })
                    .then(function (data) {
                        if (data && data.success) {
                            toast('+' + data.points + ' ' + LBL_POINTS, rect.left + rect.width / 2, rect.top);
                        }
                    })
                    .catch(function () {});
            });
        }, i * 700 + Math.random() * 500);
    }

    function toast(text, x, y) {
        var t = document.createElement('div');
        t.className = 'balloon-gain';
        t.textContent = text;
        t.style.left = Math.max(10, Math.min(window.innerWidth - 110, x)) + 'px';
        t.style.top = Math.max(10, y) + 'px';
        document.body.appendChild(t);
        setTimeout(function () { t.remove(); }, 1400);
    }
})();
</script>
<?php endif; ?>
