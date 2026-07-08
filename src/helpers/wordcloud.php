<?php

/**
 * Word cloud helper — Application SST DREETS BFC
 *
 * Extracted from formatting.php for single-responsibility clarity.
 */

/**
 * Build a word cloud from report descriptions/objets for a given registry type.
 *
 * Extracts the most frequent meaningful words (min 4 chars, excludes common French stop words).
 * Returns HTML spans with font sizes proportional to frequency.
 *
 * @param PDO    $pdo      Database connection
 * @param string $type     Report type (rsst/rami/dgi)
 * @param int    $maxWords Maximum number of words to display
 * @return string          HTML word cloud, or empty string if no data
 */
function buildWordCloud(PDO $pdo, string $type, int $maxWords = 30): string
{
    $stopWords = [
        'dans', 'pour', 'avec', 'plus', 'cette', 'tout', 'faire', 'être', 'avoir',
        'nous', 'vous', 'ils', 'elle', 'elles', 'monter', 'comme', 'mais', 'aussi',
        'bien', 'fait', 'leur', 'après', 'très', 'chez', 'entre', 'encore', 'avant',
        'peut', 'depuis', 'sans', 'tous', 'toute', 'toutes', 'quel', 'quelle',
        'autre', 'autres', 'deux', 'mêmes', 'même', 'ces', 'des', 'aux',
        'quelque', 'quelques', 'chaque', 'dont', 'rien', 'toujours', 'souvent',
        'quand', 'alors', 'ainsi', 'donc', 'car', 'notre', 'votre', 'moins',
        'trop', 'peu', 'beaucoup', 'celui', 'ceux', 'celle', 'celles',
        'signalement', 'signalements', 'agent', 'agents', 'superviseur',
        'registre', 'état', 'date', 'création', 'refusée', 'acceptée',
    ];

    try {
        $stmt = $pdo->prepare("SELECT objet, description FROM reports WHERE type = :type AND etat != 'abandonne'");
        $stmt->execute([':type' => $type]);
        $allText = '';
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $allText .= ' ' . ($row['objet'] ?? '') . ' ' . ($row['description'] ?? '');
        }
    } catch (Exception $e) {
        return '';
    }

    if (empty(trim($allText))) {
        return '';
    }

    $words = preg_split('/[^\p{L}]+/u', mb_strtolower($allText, 'UTF-8'), -1, PREG_SPLIT_NO_EMPTY);
    $freq = [];
    foreach ($words as $word) {
        $word = trim($word);
        if (mb_strlen($word, 'UTF-8') < 4) continue;
        if (in_array($word, $stopWords)) continue;
        $freq[$word] = ($freq[$word] ?? 0) + 1;
    }

    if (empty($freq)) return '';

    arsort($freq);
    $topWords = array_slice($freq, 0, $maxWords, true);

    $maxFreq = max($topWords);
    $minFreq = min($topWords);
    $range = max($maxFreq - $minFreq, 1);

    $html = '<div class="word-cloud" role="img" aria-label="Nuage de mots des signalements">';
    foreach ($topWords as $word => $count) {
        $ratio = ($count - $minFreq) / $range;
        $size = 0.7 + ($ratio * 1.3);
        $html .= '<span class="word-cloud__word" style="font-size:' . number_format($size, 1) . 'rem;" title="' . e($word) . ' (' . $count . ')">' . e($word) . '</span> ';
    }
    $html .= '</div>';
    return $html;
}
