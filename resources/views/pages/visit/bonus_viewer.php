<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bonus - Echange de Liens</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="<?= e(base_path('assets/css/style.css')) ?>">
</head>
<body class="viewer-page">
    <div class="viewer-header">
        <div class="viewer-header-left"><a href="<?= e(base_path()) ?>" class="logo-sm"><i class="fas fa-gift"></i> Bonus</a></div>
        <div class="viewer-header-center">
            <div class="viewer-timer"><i class="fas fa-clock"></i> <span id="countdown"><?= $duration ?></span>s</div>
            <div class="viewer-progress"><div class="viewer-progress-bar" id="progressBar"></div></div>
        </div>
        <div class="viewer-header-right"><span><?= e($ad['title']) ?></span></div>
    </div>
    <div class="viewer-iframe-container">
        <?php if ($ad['target_url']): ?>
            <iframe src="<?= e($ad['target_url']) ?>" sandbox="allow-scripts allow-same-origin allow-popups"></iframe>
        <?php else: ?>
            <img src="<?= e($ad['image_url']) ?>" alt="<?= e($ad['title']) ?>" style="max-width:100%;margin:auto;display:block;">
        <?php endif; ?>
    </div>
    <div class="viewer-footer">
        <div class="viewer-banner-left"></div>
        <div class="viewer-counter">
            <div class="counter-circle" id="counterCircle"><span id="counterText"><?= $duration ?></span></div>
            <p id="counterMessage">Patientez 15s pour +5 points...</p>
        </div>
        <div class="viewer-banner-right"></div>
    </div>
    <script>
    (function() {
        const adId = <?= (int) $ad['id'] ?>;
        const required = <?= $duration ?>;
        let elapsed = 0, done = false;
        const cd = document.getElementById('countdown');
        const ct = document.getElementById('counterText');
        const cm = document.getElementById('counterMessage');
        const pb = document.getElementById('progressBar');
        const t = setInterval(() => {
            elapsed++;
            const r = Math.max(0, required - elapsed);
            cd.textContent = r; ct.textContent = r;
            pb.style.width = Math.min(100, (elapsed/required)*100) + '%';
            if (elapsed >= required && !done) {
                done = true; clearInterval(t);
                document.getElementById('counterCircle').classList.add('validated');
                pb.style.width = '100%';
                fetch('<?= e(base_path('visit/validate-banner')) ?>', {
                    method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded','X-Requested-With':'XMLHttpRequest'},
                    body:'ad_id='+adId+'&duration='+elapsed
                }).then(r=>r.json()).then(d=>{
                    cm.innerHTML = d.success ? '<i class="fas fa-check-circle"></i> +5 points bonus !' : d.message;
                }).catch(()=>{cm.textContent='Erreur';});
            }
        }, 1000);
    })();
    </script>
</body>
</html>
