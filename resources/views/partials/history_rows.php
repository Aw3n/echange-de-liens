<?php
// Lignes du tableau d'historique des points.
// Partial partagé entre le rendu initial du tableau de bord et
// l'endpoint AJAX /dashboard/history (« Charger plus »).
?>
<?php foreach ($points_history as $ph): ?>
    <tr>
        <td><?= format_date($ph['created_at']) ?></td>
        <td><span class="badge badge-<?= str_starts_with($ph['type'], 'earn') || in_array($ph['type'], ['referral', 'referral_visit', 'bonus', 'banner_click', 'wheel', 'balloon']) ? 'success' : 'warning' ?>"><?= e($ph['type']) ?></span></td>
        <td class="<?= (int) $ph['amount'] > 0 ? 'text-success' : 'text-danger' ?>"><?= (int) $ph['amount'] > 0 ? '+' : '' ?><?= format_number((int) $ph['amount']) ?></td>
        <td><?= e($ph['description'] ?? '') ?></td>
    </tr>
<?php endforeach; ?>
