<!-- Page Roue de la fortune -->
<?php if (empty($wheel_enabled)): ?>
<div class="card">
    <div class="card-header"><h2><i class="fas fa-dharmachakra"></i> <?= tr('Roue de la fortune') ?></h2></div>
    <div class="card-body">
        <p class="text-muted"><?= tr('Module désactivé par l\'administrateur.') ?></p>
    </div>
</div>
<?php else: ?>
<div class="card">
    <div class="card-header"><h2><i class="fas fa-dharmachakra"></i> <?= tr('Roue de la fortune') ?></h2></div>
    <div class="card-body">
        <p class="text-muted" style="text-align:center;"><?= tr('Un tour gratuit toutes les') ?> <?= (int) $wheel_interval_hours ?> <?= tr('heures') ?> — <?= tr('Bonne chance !') ?></p>

        <div class="wheel-box">
            <div class="wheel-pointer"></div>
            <svg id="wheelSvg" viewBox="0 0 300 300" role="img" aria-label="<?= e(tr('Roue de la fortune')) ?>"></svg>
            <div class="wheel-hub"><i class="fas fa-dharmachakra"></i></div>
        </div>

        <div class="wheel-controls">
            <button type="button" id="wheelSpinBtn" class="btn btn-primary btn-lg"><i class="fas fa-play"></i> <?= tr('Tourner la roue') ?></button>
            <div id="wheelCountdown" class="text-muted mt-1"></div>
            <div id="wheelResult" class="mt-1" style="display:none;font-weight:700;font-size:1.15rem;"></div>
        </div>

        <?php if (!empty($wheel_url)): ?>
        <p class="text-muted mt-2" style="text-align:center;font-size:.85rem;"><i class="fas fa-external-link-alt"></i> <?= tr('Le lien partenaire s\'ouvre dans un nouvel onglet pendant la rotation.') ?></p>
        <?php endif; ?>
    </div>
</div>

<script>
(function () {
    'use strict';

    var SEGMENTS = <?= json_encode(array_map(function ($s) { return ['value' => $s['value'], 'color' => $s['color']]; }, $wheel_segments), JSON_UNESCAPED_UNICODE) ?>;
    var WHEEL_URL = <?= json_encode((string) $wheel_url) ?>;
    var SPIN_URL = <?= json_encode(base_path('wheel/spin')) ?>;
    var CSRF = <?= json_encode((string) $wheel_csrf) ?>;
    var NEXT_AT = <?= (int) $wheel_next_at ?>;

    var TXT = {
        spin: <?= json_encode(tr('Tourner la roue')) ?>,
        next: <?= json_encode(tr('Prochain tour dans')) ?>,
        ready: <?= json_encode(tr('La roue est prête, tentez votre chance !')) ?>,
        won: <?= json_encode(tr('Vous avez gagné')) ?>,
        points: <?= json_encode(tr('points')) ?>,
        error: <?= json_encode(tr('Une erreur est survenue, réessayez.')) ?>
    };

    var N = SEGMENTS.length;
    var ARC = 360 / N;
    var svg = document.getElementById('wheelSvg');
    var btn = document.getElementById('wheelSpinBtn');
    var cd = document.getElementById('wheelCountdown');
    var res = document.getElementById('wheelResult');
    var NS = 'http://www.w3.org/2000/svg';

    // ---- Construction de la roue (SVG responsive, couleurs fixes) ----
    function pt(deg, rad) {
        var a = (deg - 90) * Math.PI / 180;
        return [150 + rad * Math.cos(a), 150 + rad * Math.sin(a)];
    }
    SEGMENTS.forEach(function (s, i) {
        var a0 = i * ARC, a1 = (i + 1) * ARC;
        var p0 = pt(a0, 148), p1 = pt(a1, 148);
        var path = document.createElementNS(NS, 'path');
        path.setAttribute('d', 'M150,150 L' + p0[0].toFixed(2) + ',' + p0[1].toFixed(2) +
            ' A148,148 0 0 1 ' + p1[0].toFixed(2) + ',' + p1[1].toFixed(2) + ' Z');
        path.setAttribute('fill', s.color);
        svg.appendChild(path);

        var mid = a0 + ARC / 2;
        var tp = pt(mid, 100);
        // Nombres toujours lisibles à l'endroit : on retourne l'étiquette
        // de 180° sur la moitié basse de la roue (sinon « 100 » se lit « 001 »)
        var rot = (mid > 90 && mid < 270) ? mid + 180 : mid;
        var label = document.createElementNS(NS, 'text');
        label.setAttribute('x', tp[0].toFixed(2));
        label.setAttribute('y', tp[1].toFixed(2));
        label.setAttribute('text-anchor', 'middle');
        label.setAttribute('dominant-baseline', 'middle');
        label.setAttribute('transform', 'rotate(' + rot.toFixed(2) + ' ' + tp[0].toFixed(2) + ' ' + tp[1].toFixed(2) + ')');
        label.setAttribute('class', 'wheel-label');
        label.textContent = s.value;
        svg.appendChild(label);
    });

    // ---- Rotation (le résultat vient TOUJOURS du serveur) ----
    var rotation = 0;
    var spinning = false;

    function spinTo(index) {
        var jitter = (Math.random() * 2 - 1) * (ARC / 2 - 5);
        var desired = ((360 - (index * ARC + ARC / 2) + jitter) % 360 + 360) % 360;
        var current = ((rotation % 360) + 360) % 360;
        var delta = desired - current;
        if (delta <= 0) { delta += 360; }
        rotation += 360 * 4 + delta;
        svg.style.transition = 'transform 4.6s cubic-bezier(.12,.8,.08,1)';
        svg.style.transform = 'rotate(' + rotation + 'deg)';
    }

    // ---- Compte à rebours ----
    function fmt(sec) {
        var h = Math.floor(sec / 3600), m = Math.floor((sec % 3600) / 60), s = sec % 60;
        function z(n) { return (n < 10 ? '0' : '') + n; }
        return z(h) + ':' + z(m) + ':' + z(s);
    }
    function tick() {
        var left = NEXT_AT - Math.floor(Date.now() / 1000);
        if (left <= 0) {
            if (!spinning) { btn.disabled = false; }
            cd.textContent = TXT.ready;
        } else {
            btn.disabled = true;
            cd.textContent = TXT.next + ' ' + fmt(left);
        }
    }
    setInterval(tick, 1000);
    tick();

    // ---- Clic : ouverture synchrone de l'onglet (geste utilisateur =>
    // ---- non bloqué par les anti-popups) puis spin côté serveur ----
    btn.addEventListener('click', function () {
        if (btn.disabled || spinning) { return; }
        spinning = true;
        btn.disabled = true;
        res.style.display = 'none';

        if (WHEEL_URL) {
            window.open(WHEEL_URL, '_blank');
        }

        fetch(SPIN_URL, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
            body: '_csrf_token=' + encodeURIComponent(CSRF)
        })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (!data || !data.success) {
                    spinning = false;
                    res.style.display = 'block';
                    res.className = 'mt-1 text-danger';
                    res.textContent = (data && data.message) ? data.message : TXT.error;
                    tick();
                    return;
                }
                spinTo(data.index);
                setTimeout(function () {
                    spinning = false;
                    NEXT_AT = data.next_at;
                    res.style.display = 'block';
                    res.className = 'mt-1 text-success';
                    res.textContent = TXT.won + ' ' + data.points + ' ' + TXT.points + ' !';
                    tick();
                }, 4800);
            })
            .catch(function () {
                spinning = false;
                res.style.display = 'block';
                res.className = 'mt-1 text-danger';
                res.textContent = TXT.error;
                tick();
            });
    });
})();
</script>
<?php endif; ?>
