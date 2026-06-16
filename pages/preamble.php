<?php
/**
 * Preamble Page — Application SST DREETS BFC
 *
 * Static content page explaining the legal framework and purpose
 * of the three health and safety registries.
 */
$pageTitle = 'Préambule';
?>

<h1 class="page-title">Préambule — Cadre juridique</h1>


<div class="card content-section">
    <h2>Contexte réglementaire</h2>
    <p>
        Conformément aux dispositions du <strong>Code du travail</strong>, les employeurs ont l'obligation d'assurer la santé et la sécurité de leurs agents.
        La <?php echo e(getConfig('app_nom_complet', 'DREETS Bourgogne-Franche-Comté')); ?> met à disposition de ses agents trois registres permettant de signaler tout événement relatif à la santé, la sécurité ou l'intégrité physique et morale au travail.
    </p>
</div>

<div class="card card--rsst card--spaced content-section">
    <h3>&#x1F4CB; Registre de Santé et de Sécurité au Travail (RSST)</h3>
    <p>
        <strong>Décret n° 82-453 du 28 mai 1982 relatif à l'hygiène et à la sécurité du travail ainsi qu'à la médecine professionnelle et préventive dans la fonction publique, article 3-2</strong> (modifié par le décret n° 2011-774 du 28 juin 2011)<br>
        <small>Source : <a href="https://www.legifrance.gouv.fr/loda/id/LEGIARTI000024283910" target="_blank" rel="noopener">Décret 82-453 art. 3-2 — Légifrance</a></small><br>
        Le registre de santé et de sécurité au travail est tenu, dans chaque service, à la disposition de l'ensemble des agents (et, le cas échéant, des usagers) pour consultation. Il permet à tout agent de consigner ses observations et suggestions relatives à l'hygiène et à la sécurité, ainsi que de signaler toute situation de danger constatée dans l'exercice de ses fonctions.
    </p>
    <ul>
        <li>Risques liés aux locaux et équipements</li>
        <li>Problèmes d'ergonomie et d'aménagement des postes de travail</li>
        <li>Conditions environnementales (bruit, éclairage, température, qualité de l'air)</li>
        <li>Situations dangereuses non couvertes par les registres RAMI et DGI</li>
    </ul>
</div>

<div class="card card--rami card--spaced content-section">
    <h3>&#x26A0;&#xFE0F; Registre des Actes d'Agressions, de Menaces et d'Incivilités (RAMI)</h3>
    <p>
        <strong>Article L135-6 du Code général de la fonction publique (CGFP)</strong>,
        instauré par la loi n° 2019-828 du 6 août 2019 de transformation de la fonction publique
        et mis en œuvre par les articles <strong>R135-1 à R135-10 du CGFP</strong>
        (décret n° 2024-1038 du 6 novembre 2024, en vigueur depuis le 1er février 2025).<br>
        <small>Sources : <a href="https://www.legifrance.gouv.fr/codes/article_lc/LEGIARTI000044427582" target="_blank" rel="noopener">Art. L135-6 CGFP — Légifrance</a>
        &bull; <a href="https://www.legifrance.gouv.fr/codes/id/LEGIARTI000050546729/2025-02-01" target="_blank" rel="noopener">Art. R135-1 à R135-10 CGFP — Légifrance</a></small><br>
        Ce dispositif oblige tout employeur public à mettre en place un mécanisme de recueil des signalements
        des agents s'estimant victimes d'atteintes volontaires à leur intégrité physique, d'actes de violence,
        de discrimination, de harcèlement moral ou sexuel, d'agissements sexistes, de menaces ou de tout acte
        d'intimidation. Il permet également de recueillir les signalements de témoins de tels agissements.
    </p>
    <ul>
        <li>Agressions physiques ou verbales</li>
        <li>Menaces de toute nature</li>
        <li>Incivilités et comportements inacceptables</li>
        <li>Harcèlement moral ou sexuel</li>
        <li>Un agent peut signaler pour le compte d'un autre agent</li>
    </ul>
</div>

<div class="card card--dgi card--spaced content-section">
    <h3>&#x1F534; Registre de signalement d'un Danger Grave et Imminent (DGI)</h3>
    <p>
        <strong>Articles L4131-1 et suivants du Code du travail</strong> (droit de retrait) et <strong>article D4132-1 du Code du travail</strong> (formalisme du registre spécial — avis du représentant CSE/CHSCT exerçant son droit d'alerte)<br>
        <small>Sources : <a href="https://www.legifrance.gouv.fr/codes/article_lc/LEGIARTI000006902454" target="_blank" rel="noopener">Art. L4131-1 Code du travail — Légifrance</a> &bull; <a href="https://www.legifrance.gouv.fr/codes/article_lc/LEGIARTI000036484010" target="_blank" rel="noopener">Art. D4132-1 Code du travail — Légifrance</a></small><br>
        Le registre de danger grave et imminent permet à tout agent de signaler une situation de danger grave et imminent, c'est-à-dire une menace pouvant entraîner un accident du travail grave ou une maladie professionnelle grave dans l'immédiat.
    </p>
    <ul>
        <li>Danger nécessitant une action immédiate</li>
        <li>Risque d'accident grave ou de maladie professionnelle grave</li>
        <li>Situation pouvant être constatée par un membre du CSA/CHSCT</li>
        <li>Droit de retrait en cas de danger grave et imminent</li>
    </ul>
    <p class="help-note help-note--warning">
        <strong>Clarification :</strong> Le formulaire de signalement DGI de cette application vaut <strong>notification au sens de l'article L4131-1 du Code du travail</strong> (droit de retrait individuel de l'agent). La <strong>consignation formelle</strong> sur le registre spécial prévue à l'article <strong>D4132-1</strong> relève du représentant CSA/CHSCT exerçant son droit d'alerte (L4131-2) — il s'agit de deux actes juridiques distincts. Cette application couvre le premier ; le second reste du ressort du CSA/CHSCT.
    </p>
</div>

<div class="card content-section">
    <h3>Modalités de signalement</h3>
    <ul>
        <li>Tout agent peut inscrire un signalement dans le registre correspondant à la situation rencontrée.</li>
        <li>Le signalement est confidentiel par défaut. Selon le paramétrage choisi par le superviseur, l'agent peut choisir de rendre son signalement public, ou celui-ci peut être visible par tous les agents du site. Les superviseurs et les membres du CSA/CHSCT ont accès à tous les signalements.</li>
        <li>Les superviseurs s'engagent à traiter chaque signalement dans les meilleurs délais.</li>
        <li>Les signalements DGI font l'objet d'une procédure d'urgence.</li>
        <li>Aucune sanction ne peut être prise à l'encontre d'un agent pour avoir signalé une situation de danger.</li>
    </ul>
</div>

<div class="card card--spaced-top content-section">
    <h3>Compatibilité navigateur</h3>
    <p>
        Cette application est optimisée pour les navigateurs <strong>Mozilla Firefox</strong> et <strong>Google Chrome</strong> dans leurs versions récentes.
        L'utilisation d'Internet Explorer est déconseillée.
    </p>
</div>

<div class="card card--spaced-top content-section">
    <h3>Protection des données — Mention d'information RGPD (art. 13)</h3>
    <p>
        Les données collectées dans le cadre de ces registres sont traitées conformément au <strong>Règlement Général sur la Protection des Données (RGPD)</strong> et à la loi Informatique et Libertés.
        Conformément à l'article 13 du RGPD, les informations suivantes sont portées à votre connaissance au moment de la collecte :
    </p>
    <ul>
        <li><strong>Finalité du traitement :</strong> recueil et suivi des signalements en matière de santé, sécurité et conditions de travail (registres RSST, RAMI et DGI), conformément aux obligations légales incombant à l'employeur.</li>
        <li><strong>Base légale :</strong> article 6.1.e du RGPD — exécution d'une mission d'intérêt public relevant de la compétence de l'administration (gestion des registres obligatoires de santé et sécurité au travail dans la fonction publique).</li>
        <li><strong>Responsable du traitement :</strong> <?php echo e(getConfig('app_nom_complet', 'DREETS Bourgogne-Franche-Comté')); ?>.</li>
        <li><strong>Contact DPO :</strong> <?php
            $dpoContact = getConfig('app_dpo_contact', '');
            echo $dpoContact ? e($dpoContact) : '<em>à compléter dans Paramètres &rarr; Application &rarr; Contact DPO</em>';
        ?>.</li>
        <li><strong>Durée de conservation :</strong> les signalements sont conservés pendant la durée nécessaire au suivi et au traitement, puis archivés conformément à la réglementation. La durée de conservation est paramétrable par le superviseur après validation du DPO.</li>
        <li><strong>Personnes habilitées :</strong> seuls les superviseurs et les membres du CSA/CHSCT ont accès aux données en fonction de leur rôle et de leur site d'affectation.</li>
        <li><strong>Vos droits :</strong> vous disposez d'un droit d'accès, de rectification, d'effacement et d'opposition concernant vos données personnelles. Pour les exercer, contactez le DPO à l'adresse indiquée ci-dessus.</li>
        <li><strong>Droit de réclamation :</strong> vous avez le droit d'introduire une réclamation auprès de la Commission Nationale de l'Informatique et des Libertés (CNIL) — <a href="https://www.cnil.fr" target="_blank" rel="noopener">www.cnil.fr</a>.</li>
    </ul>
</div>
