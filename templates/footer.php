<?php
/**
 * Footer Template — Application SST DREETS BFC
 * 
 * Closing tags and copyright notice.
 * Back-to-top link: CSS-only, no JavaScript required.
 * Uses :target or a simple anchor link for zero-JS durability.
 */
?>
    <footer class="footer" role="contentinfo">
        <p>&copy; <?php echo date('Y'); ?> <?php echo e(getConfig('app_nom_complet', 'DREETS Bourgogne-Franche-Comté')); ?> — Application SST <a href="<?php echo url('changelog'); ?>" class="footer-version" title="Voir l'historique des modifications">v<?php echo defined('APP_VERSION') ? e(APP_VERSION) : ''; ?></a> — PHP <?php echo PHP_VERSION; ?> — D&eacute;veloppeur : Olivier Noblanc</p>
    </footer>
    <a href="#top" class="back-to-top" aria-label="Retour en haut" title="Retour en haut">&#x25B2;</a>
</main><!-- end #main-content -->
</body>
</html>
