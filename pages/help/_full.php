<?php
/** @var bool $dgiEnabled */
/** @var bool $ramiEnabled */
/** @var int $registryCount */
/** @var bool $hotlineEnabled */
/** @var string $hotlineNumber */
?>
<!-- AIDE COMPLÈTE — Superviseur / CHSCT -->
<h1 class="page-title">Documentation</h1>

<!-- Bandeau d'aide humaine -->
<?php if ($hotlineEnabled): ?>
<div class="help-contact-banner" role="complementary" aria-label="Hotline">
    <span class="help-contact-banner__icon" aria-hidden="true">📞</span>
    <div class="help-contact-banner__text">
        <strong>Besoin d'aide ?</strong> Appelez la hotline au <strong class="help-hotline-number"><?php echo e($hotlineNumber); ?></strong><br>
        <small>Un conseiller vous répondra directement pour vous guider.</small>
    </div>
</div>
<?php endif; ?>

<!-- Sommaire -->
<nav class="help-toc" aria-label="Sommaire de la documentation">
    <h2 class="help-toc__title">📑 Sommaire</h2>
    <ol class="help-toc__list">
        <li><a href="#profils"><span class="help-toc__num">1</span> Profils utilisateurs</a></li>
        <li><a href="#droits"><span class="help-toc__num">2</span> Tableau des droits</a></li>
        <li><a href="#confidentialite"><span class="help-toc__num">3</span> Confidentialité des signalements</a></li>
        <li><a href="#registres"><span class="help-toc__num">4</span> Les <?php echo $registryCount; ?> registres</a></li>
        <li><a href="#cycle-vie"><span class="help-toc__num">5</span> Cycle de vie d'un signalement</a></li>
        <li><a href="#cas-usage"><span class="help-toc__num">6</span> Cas d'usage</a>
            <ol>
                <li><a href="#cu1"><span class="help-toc__num-sub">6a</span> Signaler un événement RSST</a></li>
                <?php if ($ramiEnabled): ?><li><a href="#cu2"><span class="help-toc__num-sub">6b</span> Signalement RAMI pour un collègue</a></li><?php endif; ?>
                <?php if ($dgiEnabled): ?><li><a href="#cu3"><span class="help-toc__num-sub">6c</span> Danger Grave et Imminent (DGI)</a></li><?php endif; ?>
                <li><a href="#cu4"><span class="help-toc__num-sub">6d</span> Traiter un signalement</a></li>
                <li><a href="#cu5"><span class="help-toc__num-sub">6e</span> Abandonner un signalement</a></li>
                <li><a href="#cu6"><span class="help-toc__num-sub">6f</span> Consulter la synthèse (<?php echo e(getRoleLabelShort('chsct')); ?>)</a></li>
                <li><a href="#cu7"><span class="help-toc__num-sub">6g</span> Gérer les utilisateurs et la configuration</a></li>
                <li><a href="#cu8"><span class="help-toc__num-sub">6h</span> Imprimer une fiche de signalement</a></li>
            </ol>
        </li>
        <li><a href="#auth"><span class="help-toc__num">7</span> Connexion</a></li>
    </ol>
</nav>

<?php include __DIR__ . '/_profiles.php'; ?>
<?php include __DIR__ . '/_rights.php'; ?>
<?php include __DIR__ . '/_confidentiality.php'; ?>
<?php include __DIR__ . '/_registres.php'; ?>
<?php include __DIR__ . '/_lifecycle.php'; ?>
<?php include __DIR__ . '/_use_cases.php'; ?>
<?php include __DIR__ . '/_auth.php'; ?>

<!-- Liens utiles -->
<div class="card--spaced-top help-cards-row">
    <a href="<?php echo url('preamble'); ?>" class="btn btn--outline">Lire le Préambule</a>
    <a href="<?php echo url('changelog'); ?>" class="btn btn--outline">Journal des modifications</a>
</div>
