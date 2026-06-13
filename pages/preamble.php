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

<?php require __DIR__ . '/../templates/alert.php'; ?>

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
        <!-- TODO (revue juridique/RH requise) : identifier le texte applicable au registre RAMI.
        Piste : signalement des actes de violence, discrimination, harcèlement et agissements sexistes
        (art. 6 quater A, loi n° 83-634 du 13/07/1983, issu de la loi n° 2019-828 du 06/08/2019).
        Le décret d'application initial n° 2020-256 du 13/03/2020 a été abrogé le 01/02/2025 :
        vérifier le texte en vigueur avant publication. NE PAS inventer de numéro de remplacement. -->
        <strong>Cadre juridique à confirmer</strong> — Dispositif de signalement des actes de violence, de discrimination, de harcèlement et d'agissements sexistes (art. 6 quater A de la loi n° 83-634 du 13 juillet 1983, issu de la loi n° 2019-828 du 6 août 2019). Le texte d'application est en cours d'identification par le service juridique/RH.<br>
        Le registre des actes d'agressions, de menaces et d'incivilités permet de consigner tout acte de violence externe (usagers, tiers) ou interne (collègues, hiérarchie) subi par un agent dans le cadre de ses fonctions.
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
        Le registre de danger grave et imminent permet à tout agent de signaler une situation de danger grave et imminent, c'est-à-dire une menace pouvant entraîner un accident du travail grave ou une maladie professionnelle grave dans l'immédiat.
    </p>
    <ul>
        <li>Danger nécessitant une action immédiate</li>
        <li>Risque d'accident grave ou de maladie professionnelle grave</li>
        <li>Situation pouvant être constatée par un membre du CHSCT</li>
        <li>Droit de retrait en cas de danger grave et imminent</li>
    </ul>
</div>

<div class="card content-section">
    <h3>Modalités de signalement</h3>
    <ul>
        <li>Tout agent peut inscrire un signalement dans le registre correspondant à la situation rencontrée.</li>
        <li>Le signalement est confidentiel par défaut. Selon le paramétrage choisi par le superviseur, l'agent peut choisir de rendre son signalement public, ou celui-ci peut être visible par tous les agents du site. Les superviseurs et les membres du CHSCT ont accès à tous les signalements.</li>
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
    <h3>Protection des données</h3>
    <p>
        Les données collectées dans le cadre de ces registres sont traitées conformément au <strong>Règlement Général sur la Protection des Données (RGPD)</strong> et à la loi Informatique et Libertés.
        Seuls les personnes habilitées (superviseurs, membres du CHSCT) ont accès aux données en fonction de leur rôle et de leur site d'affectation.
    </p>
</div>
