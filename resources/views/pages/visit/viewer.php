<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Visionneuse - Echange de Liens</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="<?= e(base_path('assets/css/style.css')) ?>">
</head>
<body class="viewer-page">
    <!-- Header viewer -->
    <div class="viewer-header">
        <div class="viewer-header-left">
            <a href="<?= e(base_path()) ?>" class="logo-sm"><i class="fas fa-link"></i> <?= e($seo['site_name'] ?? 'Echange de Liens') ?></a>
        </div>
        <div class="viewer-header-center">
            <div class="viewer-timer" id="viewerTimer">
                <i class="fas fa-clock"></i>
                <span id="countdown"><?= $duration ?></span>s
            </div>
            <div class="viewer-progress">
                <div class="viewer-progress-bar" id="progressBar"></div>
            </div>
        </div>
        <div class="viewer-header-right">
            <a href="<?= e($link['url']) ?>" target="_blank" rel="noopener noreferrer" class="viewer-open-btn" title="<?= e(tr('Ouvrir le site')) ?>">
                <i class="fas fa-external-link-alt"></i> <?= e(tr('Ouvrir le site')) ?>
            </a>
            <span class="viewer-site-name"><?= e(truncate($link['title'] ?? $link['url'], 40)) ?></span>
        </div>
    </div>

    <!-- Iframe -->
    <div class="viewer-iframe-container">
        <iframe src="<?= e($link['url']) ?>" id="siteFrame" sandbox="allow-scripts allow-same-origin allow-popups allow-forms" loading="eager"></iframe>
    </div>

    <!-- Bannières bas -->
    <div class="viewer-footer">
        <div class="viewer-banner-left">
            <?php $vAdLeft = $viewer_ads[0] ?? null; if ($vAdLeft): ?>
            <div class="ad-slot viewer-ad-slot">
                <?php if ($vAdLeft['type'] === 'banner' && $vAdLeft['image_url']): ?>
                <a href="<?= e($vAdLeft['target_url']) ?>" target="_blank" rel="noopener">
                    <img src="<?= e($vAdLeft['image_url']) ?>" alt="<?= e($vAdLeft['title']) ?>" loading="lazy">
                </a>
                <?php elseif ($vAdLeft['html_code']): ?>
                <?= $vAdLeft['html_code'] ?>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
        <div class="viewer-counter">
            <div class="counter-circle" id="counterCircle">
                <span id="counterText"><?= $duration ?></span>
            </div>
            <p id="counterMessage">Patientez...</p>
        </div>
        <div class="viewer-banner-right">
            <?php if (!empty($viewer_ads[1])): $vAdRight = $viewer_ads[1]; ?>
            <div class="ad-slot viewer-ad-slot">
                <?php if ($vAdRight['type'] === 'banner' && $vAdRight['image_url']): ?>
                <a href="<?= e($vAdRight['target_url']) ?>" target="_blank" rel="noopener">
                    <img src="<?= e($vAdRight['image_url']) ?>" alt="<?= e($vAdRight['title']) ?>" loading="lazy">
                </a>
                <?php elseif ($vAdRight['html_code']): ?>
                <?= $vAdRight['html_code'] ?>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <input type="hidden" id="visitId" value="<?= $visit_id ?>">
    <input type="hidden" id="sessionToken" value="<?= e($session_token) ?>">
    <input type="hidden" id="requiredDuration" value="<?= $duration ?>">

    <script>
    (function() {
        const visitId = document.getElementById('visitId').value;
        const sessionToken = document.getElementById('sessionToken').value;
        const requiredDuration = parseInt(document.getElementById('requiredDuration').value);
        const countdownEl = document.getElementById('countdown');
        const counterText = document.getElementById('counterText');
        const counterMessage = document.getElementById('counterMessage');
        const progressBar = document.getElementById('progressBar');
        let elapsed = 0;
        let validated = false;

        const timer = setInterval(() => {
            elapsed++;
            const remaining = Math.max(0, requiredDuration - elapsed);
            countdownEl.textContent = remaining;
            counterText.textContent = remaining;
            progressBar.style.width = Math.min(100, (elapsed / requiredDuration) * 100) + '%';

            if (elapsed >= requiredDuration && !validated) {
                validated = true;
                clearInterval(timer);
                counterMessage.textContent = 'Visite validée !';
                document.getElementById('counterCircle').classList.add('validated');
                progressBar.style.width = '100%';

                // Validation AJAX
                fetch('visit/validate', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
                    body: 'visit_id=' + visitId + '&duration=' + elapsed + '&session_token=' + encodeURIComponent(sessionToken)
                })
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        if (data.guest) {
                            // Visiteur non connecté : points mis en cagnotte,
                            // transférés lors de son inscription
                            counterMessage.innerHTML = data.wallet_credited
                                ? '<i class="fas fa-piggy-bank"></i> +' + data.points_earned + ' point(s) mis de côté (total : ' + data.wallet_total + ') ! <a href="register" style="color:inherit;text-decoration:underline;">Inscrivez-vous</a> pour les utiliser.'
                                : '<i class="fas fa-check-circle"></i> ' + (data.message || 'Visite validée !');
                        } else {
                            counterMessage.innerHTML = '<i class="fas fa-check-circle"></i> +' + data.points_earned + ' point(s) !';
                        }
                    } else {
                        counterMessage.textContent = data.message || 'Erreur';
                    }
                })
                .catch(() => { counterMessage.textContent = 'Erreur réseau'; });
            }
        }, 1000);
    })();
    </script>
</body>
</html>
