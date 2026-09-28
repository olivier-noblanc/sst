/**
 * Word Cloud — spiral placement with collision detection
 * Inspired by wordcloud2.js algorithm, adapted for HTML spans.
 *
 * Réglages premium (seconde passe) :
 *   - l'encre des mots vient de --word-cloud-ink (thème du registre), avec un
 *     repli sombre neutre si la variable est absente ;
 *   - la taille est bornée par --word-cloud-min / --word-cloud-max ;
 *   - les marges de collision et le nombre d'étapes sont élargis pour éviter
 *     les mots rognés sur surface claire.
 */
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.word-cloud[data-words]').forEach(function (el) {
        var raw = el.getAttribute('data-words');
        if (!raw) return;
        var words;
        try { words = JSON.parse(raw); } catch (e) { return; }
        if (!words.length) return;

        // Set explicit height
        el.style.height = '200px';
        el.style.position = 'relative';
        el.style.overflow = 'hidden';

        var W = el.offsetWidth;
        var H = el.offsetHeight;
        var cx = W / 2;
        var cy = H / 2;

        // Encre lue depuis le thème (--word-cloud-ink), repli sombre neutre.
        var computed = window.getComputedStyle(el);
        function readRem(name, fallback) {
            var value = computed.getPropertyValue(name).trim();
            var parsed = parseFloat(value);
            return isNaN(parsed) ? fallback : parsed;
        }
        function readColor(name, fallback) {
            var value = computed.getPropertyValue(name).trim();
            return value === '' ? fallback : value;
        }
        var minSize = readRem('--word-cloud-min', 0.8);
        var maxSize = readRem('--word-cloud-max', 1.5);
        var ink = readColor('--word-cloud-ink', '#1F2937');
        var fallbackInks = [ink, '#374151', '#111827', '#334155', '#0F172A'];

        // Sort by weight descending
        words.sort(function (a, b) { return b.p - a.p; });

        var placed = [];

        words.forEach(function (item) {
            var span = document.createElement('span');
            span.className = 'word-cloud__word';
            span.textContent = item.w;
            span.title = item.w + ' (poids ' + item.p + ')';
            span.style.position = 'absolute';
            el.appendChild(span);

            // Size: poids 1-20 → taille bornée [minSize, maxSize] (rem)
            var fs = 0.6 + item.p * 0.06;
            fs = Math.max(minSize, Math.min(maxSize, fs));
            span.style.fontSize = fs + 'rem';
            span.style.fontWeight = String(Math.min(700, 400 + item.p * 35));
            span.style.color = fallbackInks[Math.floor(Math.random() * fallbackInks.length)];

            // Measure
            var tw = span.offsetWidth;
            var th = span.offsetHeight;

            // Spiral placement
            var angle = Math.random() * Math.PI * 2;
            var radius = 0;
            var found = false;

            for (var step = 0; step < 2500; step++) {
                angle += 0.5;
                radius += 0.08;
                var x = cx + radius * Math.cos(angle) * 0.8 - tw / 2;
                var y = cy + radius * Math.sin(angle) * 0.6 - th / 2;

                // Bounds
                if (x < 4 || y < 4 || x + tw > W - 4 || y + th > H - 4) {
                    if (radius > Math.min(W, H) * 0.5) break;
                    continue;
                }

                // Collision (marges élargies pour éviter le rognage)
                var ok = true;
                for (var j = 0; j < placed.length; j++) {
                    var p = placed[j];
                    if (x < p.x + p.w + 10 && x + tw + 10 > p.x &&
                        y < p.y + p.h + 6 && y + th + 6 > p.y) {
                        ok = false;
                        break;
                    }
                }

                if (ok) {
                    span.style.left = Math.round(x) + 'px';
                    span.style.top = Math.round(y) + 'px';
                    placed.push({ x: x, y: y, w: tw, h: th });
                    found = true;
                    break;
                }
            }

            if (!found) {
                span.remove();
            }
        });
    });
});