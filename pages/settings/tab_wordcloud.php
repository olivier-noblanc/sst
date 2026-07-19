<?php
/**
 * Settings Tab — Word Cloud Configuration
 *
 * Configure words and weights for the home page word cloud.
 * Words are displayed with randomized importance on each page load.
 *
 * Variables attendues: $csrfToken
 */

$configService = \App\Services\ConfigService::getInstance();
$http = new \App\Services\HttpService();
$fmt = new \App\Services\FormattingService();
if (!isset($csrfToken)) {
    $csrfToken = (new \App\Services\SessionService())->generateCsrfToken();
}
$wordsJson = $configService->get('word_cloud_words', '[]');
/** @var list<array{word: string, weight: int}> $words */
$words = json_decode($wordsJson, true) !== null ? json_decode($wordsJson, true) : [];
?>
<form method="post" action="<?php echo $http->url('settings'); ?>">
    <input type="hidden" name="tab" value="wordcloud">
    <input type="hidden" name="csrf_token" value="<?php echo $fmt->e($csrfToken); ?>">

    <div class="card mt-4">
        <h3 class="card__subtitle">Nuage de mots — page d'accueil</h3>
        <p class="text-muted mb-4">
            Configurez les mots affichés dans le nuage de mots de la page d'accueil.
            Chaque mot a un poids (1-20) qui détermine sa taille relative.
            L'importance est randomisée à chaque chargement de page pour un rendu vivant.
        </p>

        <div id="wordcloud-words">
            <?php if (empty($words)): ?>
            <div class="wordcloud-row" data-index="0">
                <input type="text" name="words[0][word]" placeholder="Mot" class="input" required maxlength="50">
                <input type="number" name="words[0][weight]" value="10" min="1" max="20" class="input input--small">
                <button type="button" class="btn btn--danger btn--small" onclick="this.closest('.wordcloud-row').remove()">&#x2716;</button>
            </div>
            <?php else: ?>
            <?php foreach ($words as $i => $w): ?>
            <div class="wordcloud-row" data-index="<?php echo $i; ?>">
                <input type="text" name="words[<?php echo $i; ?>][word]" value="<?php echo $fmt->e($w['word'] ?? ''); ?>" class="input" required maxlength="50">
                <input type="number" name="words[<?php echo $i; ?>][weight]" value="<?php echo (int) ($w['weight'] ?? 10); ?>" min="1" max="20" class="input input--small">
                <button type="button" class="btn btn--danger btn--small" onclick="this.closest('.wordcloud-row').remove()">&#x2716;</button>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <button type="button" class="btn btn--outline mt-2" onclick="addWordRow()">&#x2795; Ajouter un mot</button>

        <div class="mt-4">
            <button type="submit" class="btn btn--primary">&#x1F4BE; Enregistrer</button>
        </div>
    </div>
</form>

<script>
function addWordRow() {
    var container = document.getElementById('wordcloud-words');
    var idx = container.querySelectorAll('.wordcloud-row').length;
    var row = document.createElement('div');
    row.className = 'wordcloud-row';
    row.dataset.index = idx;
    row.innerHTML = '<input type="text" name="words[' + idx + '][word]" placeholder="Mot" class="input" required maxlength="50">'
        + '<input type="number" name="words[' + idx + '][weight]" value="10" min="1" max="20" class="input input--small">'
        + '<button type="button" class="btn btn--danger btn--small" onclick="this.closest(\'.wordcloud-row\').remove()">&#x2716;</button>';
    container.appendChild(row);
}
</script>

<style>
.wordcloud-row {
    display: flex;
    gap: 8px;
    align-items: center;
    margin-bottom: 8px;
}
.wordcloud-row .input:first-child { flex: 1; }
.wordcloud-row .input--small { width: 70px; flex: none; }
</style>
