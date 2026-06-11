<?php
/**
 * Footer Template — Application SST DREETS BFC
 * 
 * Closing tags and copyright notice.
 */
?>
    <footer class="footer" role="contentinfo">
        <p>&copy; <?php echo date('Y'); ?> <?php echo e(getConfig('app_nom_complet', 'DREETS Bourgogne-Franche-Comté')); ?> — Application SST <a href="<?php echo url('changelog'); ?>" class="footer-version" title="Voir l'historique des modifications">v<?php echo e(APP_VERSION); ?></a> — Développeur : Olivier Noblanc</p>
    </footer>
</div><!-- end .main -->
</body>
</html>
