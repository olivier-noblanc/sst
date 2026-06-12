<?php
/**
 * Footer Template — Application SST DREETS BFC
 * 
 * Closing tags and copyright notice.
 */
?>
    <footer class="footer" role="contentinfo">
        <p>&copy; <?php echo date('Y'); ?> <?php echo e(getConfig('app_nom_complet', 'DREETS Bourgogne-Franche-Comté')); ?> — Application SST <a href="<?php echo url('changelog'); ?>" class="footer-version" title="Voir l'historique des modifications">v<?php echo e(APP_VERSION); ?></a> — PHP <?php echo PHP_VERSION; ?> — D&eacute;veloppeur : Olivier Noblanc</p>
    </footer>
    <button type="button" class="back-to-top" id="backToTop" aria-label="Retour en haut" title="Retour en haut">&#x25B2;</button>
</main><!-- end #main-content -->
<script>
// Back to top button — appears after scrolling down
(function() {
    var btn = document.getElementById('backToTop');
    if (!btn) return;
    window.addEventListener('scroll', function() {
        if (window.pageYOffset > 400) {
            btn.classList.add('back-to-top--visible');
        } else {
            btn.classList.remove('back-to-top--visible');
        }
    }, { passive: true });
    btn.addEventListener('click', function() {
        window.scrollTo({ top: 0, behavior: 'smooth' });
    });
})();
</script>
</body>
</html>
