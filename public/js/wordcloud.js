/**
 * Word Cloud — spiral placement with collision detection
 * Inspired by wordcloud2.js algorithm, adapted for HTML spans.
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

        var colors = ['#ffffff', '#f0f4ff', '#d6e4ff', '#e0eaff', '#c8d8ff'];

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

            // Size: weight 1-10 maps to font-size (0.5rem to 1.1rem)
            var fs = 0.5 + item.p * 0.07;
            span.style.fontSize = fs + 'rem';
            span.style.fontWeight = String(Math.min(700, 400 + item.p * 35));
            span.style.color = colors[Math.floor(Math.random() * colors.length)];

            // Measure
            var tw = span.offsetWidth;
            var th = span.offsetHeight;

            // Spiral placement
            var angle = Math.random() * Math.PI * 2;
            var radius = 0;
            var found = false;

            for (var step = 0; step < 1500; step++) {
                angle += 0.5;
                radius += 0.08;
                var x = cx + radius * Math.cos(angle) * 0.8 - tw / 2;
                var y = cy + radius * Math.sin(angle) * 0.6 - th / 2;

                // Bounds
                if (x < 4 || y < 4 || x + tw > W - 4 || y + th > H - 4) {
                    if (radius > Math.min(W, H) * 0.5) break;
                    continue;
                }

                // Collision (wider margins to reduce overlap)
                var ok = true;
                for (var j = 0; j < placed.length; j++) {
                    var p = placed[j];
                    if (x < p.x + p.w + 8 && x + tw + 8 > p.x &&
                        y < p.y + p.h + 4 && y + th + 4 > p.y) {
                        ok = false;
                        break;
                    }
                }

                if (ok) {
                    span.style.left = Math.round(x) + 'px';
                    span.style.top = Math.round(y) + 'px';
                    span.style.textShadow = '0 1px 3px rgba(0,0,0,0.2)';
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
