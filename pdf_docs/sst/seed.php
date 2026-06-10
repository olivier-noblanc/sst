<?php
/**
 * Seed Script — Application SST DREETS BFC
 * 
 * Populates the database with comprehensive test users and sample reports.
 * Run from CLI: /home/z/my-project/php-bin seed.php
 */

require_once __DIR__ . '/src/config.php';
require_once __DIR__ . '/src/database.php';
require_once __DIR__ . '/src/helpers.php';
require_once __DIR__ . '/src/queries/report_queries.php';
require_once __DIR__ . '/src/queries/user_queries.php';
require_once __DIR__ . '/src/queries/site_queries.php';

echo "=== SST DREETS BFC — Seed Script ===\n\n";

// Remove existing database to start fresh
if (file_exists(DB_PATH)) {
    unlink(DB_PATH);
    echo "Removed existing database.\n";
}

// Initialize database (creates schema + default data)
$pdo = getDB();
echo "Database initialized with schema.\n";

// ================================================================
// ADDITIONAL USERS (10 users to add to the 4 default ones = 14 total)
// ================================================================
// Default users (IDs 1-4):
//   1: admin.dev   — superviseur, Siège (site 1)
//   2: agent.dev   — agent, UR21 (site 2)
//   3: manager.dev — manager, Siège (site 1)
//   4: chsct.dev   — chsct, Siège (site 1)
//
// Additional users (IDs 5-14):
//   5: Superviseur UR25 (site 3)
//   6: Superviseur UR21 (site 2)
//   7: Agent UR25 (site 3)
//   8: Agent UR39 (site 4)
//   9: Agent UR71 (site 7)
//  10: Agent UR89 (site 8)
//  11: Agent UR58 (site 5) — scattered
//  12: Agent UR70 (site 6) — scattered
//  13: Agent UR90 (site 9) — scattered
//  14: Agent Siège (site 1) — scattered

$testUsers = [
    // 2 superviseurs (UR25, UR21)
    ['username' => 'nathalie.rousseau', 'nom' => 'Rousseau',  'prenom' => 'Nathalie', 'email' => 'nathalie.rousseau@dreets.gouv.fr', 'role' => 'superviseur', 'site_id' => 3],
    ['username' => 'marc.benjamin',     'nom' => 'Benjamin',  'prenom' => 'Marc',     'email' => 'marc.benjamin@dreets.gouv.fr',    'role' => 'superviseur', 'site_id' => 2],
    // 5 agents (one per UR: 25, 21, 39, 71, 89)
    // UR21 already covered by agent.dev (ID 2)
    ['username' => 'jean.dupont',   'nom' => 'Dupont',   'prenom' => 'Jean',    'email' => 'jean.dupont@dreets.gouv.fr',    'role' => 'agent', 'site_id' => 3],
    ['username' => 'pierre.moreau', 'nom' => 'Moreau',   'prenom' => 'Pierre',  'email' => 'pierre.moreau@dreets.gouv.fr',  'role' => 'agent', 'site_id' => 4],
    ['username' => 'sophie.robert', 'nom' => 'Robert',   'prenom' => 'Sophie',  'email' => 'sophie.robert@dreets.gouv.fr',  'role' => 'agent', 'site_id' => 7],
    ['username' => 'claude.richard','nom' => 'Richard',  'prenom' => 'Claude',  'email' => 'claude.richard@dreets.gouv.fr', 'role' => 'agent', 'site_id' => 8],
    // 4 more agents scattered across URs
    ['username' => 'anne.brun',      'nom' => 'Brun',     'prenom' => 'Anne',     'email' => 'anne.brun@dreets.gouv.fr',      'role' => 'agent', 'site_id' => 5],
    ['username' => 'luc.petit',      'nom' => 'Petit',    'prenom' => 'Luc',      'email' => 'luc.petit@dreets.gouv.fr',      'role' => 'agent', 'site_id' => 6],
    ['username' => 'isabelle.durand','nom' => 'Durand',   'prenom' => 'Isabelle', 'email' => 'isabelle.durand@dreets.gouv.fr','role' => 'agent', 'site_id' => 9],
    ['username' => 'marie.leroy',    'nom' => 'Leroy',    'prenom' => 'Marie',    'email' => 'marie.leroy@dreets.gouv.fr',    'role' => 'agent', 'site_id' => 1],
];

$stmt = $pdo->prepare('INSERT INTO users (username, nom, prenom, email, role, site_id) VALUES (:username, :nom, :prenom, :email, :role, :site_id)');

foreach ($testUsers as $user) {
    $stmt->execute($user);
}
echo "Created " . count($testUsers) . " additional test users (14 total with defaults).\n";

// ================================================================
// SAMPLE REPORTS (25 total: 10 RSST, 8 RAMI, 7 DGI)
// ================================================================
// User IDs reference:
//   1: admin.dev (superviseur, Siège)
//   2: agent.dev (agent, UR21)
//   3: manager.dev (manager, Siège)
//   4: chsct.dev (chsct, Siège)
//   5: nathalie.rousseau (superviseur, UR25)
//   6: marc.benjamin (superviseur, UR21)
//   7: jean.dupont (agent, UR25)
//   8: pierre.moreau (agent, UR39)
//   9: sophie.robert (agent, UR71)
//  10: claude.richard (agent, UR89)
//  11: anne.brun (agent, UR58)
//  12: luc.petit (agent, UR70)
//  13: isabelle.durand (agent, UR90)
//  14: marie.leroy (agent, Siège)

$sampleReports = [
    // =============== RSST reports (10) ===============
    // nouveau: 3, en_cours: 3, traite: 2, abandonne: 2
    [
        'type' => 'rsst',
        'objet' => 'Problème de ventilation dans les bureaux du 2ème étage',
        'description' => "La ventilation dans les bureaux du 2ème étage est défaillante depuis plusieurs semaines. Les agents signalent des maux de tête et une gêne respiratoire. Le système de climatisation n'a pas été entretenu depuis plus d'un an. Une intervention urgente est nécessaire pour vérifier les conduits et remplacer les filtres.",
        'date_evenement' => '2026-02-15',
        'heure_evenement' => '09:30',
        'lieu' => 'Bureau 204, UR25 Doubs',
        'declarant_id' => 7,  // jean.dupont
        'site_id' => 3,       // UR25
        'etat' => 'nouveau',
    ],
    [
        'type' => 'rsst',
        'objet' => 'Escalier dégradé — risque de chute',
        'description' => "L'escalier menant au parking souterrain présente plusieurs marches fissurées et un garde-corps instable. Le risque de chute est important, surtout par temps de pluie. Des travaux de réparation sont nécessaires en urgence.",
        'date_evenement' => '2026-02-20',
        'heure_evenement' => '14:00',
        'lieu' => 'Escalier parking, Siège DREETS',
        'declarant_id' => 14, // marie.leroy
        'site_id' => 1,       // Siège
        'etat' => 'en_cours',
    ],
    [
        'type' => 'rsst',
        'objet' => 'Éclairage insuffisant dans la salle de réunion',
        'description' => "La salle de réunion A au rez-de-chaussée dispose d'un éclairage insuffisant pour travailler dans de bonnes conditions, notamment lors des présentations sur écran. Deux néons sont hs et n'ont pas été remplacés.",
        'date_evenement' => '2026-01-10',
        'heure_evenement' => '10:15',
        'lieu' => 'Salle de réunion A, UR21 Dijon',
        'declarant_id' => 2,  // agent.dev
        'site_id' => 2,       // UR21
        'etat' => 'traite',
        'reponse' => "Les néons ont été remplacés le 15/01/2026. L'éclairage est maintenant conforme aux normes.",
        'repondant_id' => 6,  // marc.benjamin (superviseur UR21)
        'date_reponse' => '2026-01-15 16:30:00',
    ],
    [
        'type' => 'rsst',
        'objet' => 'Chaise ergonomique cassée — douleurs dorsales',
        'description' => "Ma chaise de bureau a un vérin défectueux qui ne maintient plus la hauteur. Cela me provoque des douleurs dorsales depuis 2 semaines. Le service logistique a été prévenu mais aucune action n'a été menée.",
        'date_evenement' => '2026-03-01',
        'heure_evenement' => '08:45',
        'lieu' => 'Bureau 112, UR39 Lons-le-Saunier',
        'declarant_id' => 8,  // pierre.moreau
        'site_id' => 4,       // UR39
        'etat' => 'nouveau',
    ],
    [
        'type' => 'rsst',
        'objet' => 'Moisissure dans les sanitaires du 3ème étage',
        'description' => "Des traces de moisissure importantes sont apparues dans les sanitaires du 3ème étage. L'odeur est insupportable et plusieurs agents craignent des problèmes respiratoires. Un traitement antimoisissure et une ventilation adaptée sont nécessaires.",
        'date_evenement' => '2026-02-28',
        'heure_evenement' => '11:00',
        'lieu' => 'Sanitaires 3ème étage, UR58 Nevers',
        'declarant_id' => 11, // anne.brun
        'site_id' => 5,       // UR58
        'etat' => 'en_cours',
    ],
    [
        'type' => 'rsst',
        'objet' => 'Câbles électriques apparents dans le couloir',
        'description' => "Des câbles électriques sont apparents dans le couloir du rez-de-chaussée suite aux travaux de rénovation. Ils constituent un risque de trébuchement et un danger électrique. Un agent a déjà trébuché sans gravité.",
        'date_evenement' => '2026-01-25',
        'heure_evenement' => '15:30',
        'lieu' => 'Couloir RDC, UR71 Mâcon',
        'declarant_id' => 9,  // sophie.robert
        'site_id' => 7,       // UR71
        'etat' => 'traite',
        'reponse' => "Les câbles ont été sécurisés et passés dans des goulottes. Le couloir est maintenant conforme.",
        'repondant_id' => 1,  // admin.dev (superviseur Siège)
        'date_reponse' => '2026-02-05 09:00:00',
    ],
    [
        'type' => 'rsst',
        'objet' => 'Température excessive — climatisation HS',
        'description' => "La climatisation est en panne depuis 3 semaines. La température dans les bureaux dépasse 30°C. Les agents ne peuvent plus travailler dans des conditions acceptables. Des ventilateurs ont été demandés en urgence.",
        'date_evenement' => '2026-03-03',
        'heure_evenement' => '14:30',
        'lieu' => 'Open space 1er étage, UR89 Auxerre',
        'declarant_id' => 10, // claude.richard
        'site_id' => 8,       // UR89
        'etat' => 'nouveau',
    ],
    [
        'type' => 'rsst',
        'objet' => "Fuite d'eau plafond — bureau inutilisable",
        'description' => "Une fuite d'eau importante est apparue dans le plafond du bureau 305. L'eau s'infiltre le long des murs et a endommagé du matériel informatique. Le bureau est actuellement inutilisable.",
        'date_evenement' => '2026-01-15',
        'heure_evenement' => '07:45',
        'lieu' => 'Bureau 305, UR90 Belfort',
        'declarant_id' => 13, // isabelle.durand
        'site_id' => 9,       // UR90
        'etat' => 'abandonne',
    ],
    [
        'type' => 'rsst',
        'objet' => 'Surcharge électrique — multiprises en cascade',
        'description' => "Plusieurs postes de travail utilisent des multiprises branchées en cascade. Le tableau électrique disjoncte régulièrement. Le risque d'incendie est réel et le câblage doit être refait.",
        'date_evenement' => '2026-02-10',
        'heure_evenement' => '11:00',
        'lieu' => 'Open space 2ème étage, UR70 Vesoul',
        'declarant_id' => 12, // luc.petit
        'site_id' => 6,       // UR70
        'etat' => 'en_cours',
    ],
    [
        'type' => 'rsst',
        'objet' => 'Absence de signalisation sortie de secours',
        'description' => "Les panneaux de signalisation vers la sortie de secours sont manquants ou effacés. En cas d'évacuation d'urgence, les agents ne connaissent pas le chemin le plus court vers la sortie.",
        'date_evenement' => '2026-03-04',
        'heure_evenement' => '09:15',
        'lieu' => 'Couloir 1er étage, UR25 Besançon',
        'declarant_id' => 7,  // jean.dupont
        'site_id' => 3,       // UR25
        'etat' => 'abandonne',
    ],

    // =============== RAMI reports (8) — including 2 "pour le compte de" ===============
    // nouveau: 3, en_cours: 2, traite: 2, abandonne: 1
    [
        'type' => 'rami',
        'objet' => "Agression verbale par un usager lors d'un accueil",
        'description' => "Lors de l'accueil du public, un usager mécontent a proféré des menaces verbales à l'encontre de l'agent d'accueil. L'usager a crié, insulté et menacé physiquement l'agent avant de quitter les locaux. L'agent est très choqué par cet incident.",
        'date_evenement' => '2026-02-25',
        'heure_evenement' => '11:20',
        'lieu' => "Hall d'accueil, UR71 Mâcon",
        'declarant_id' => 9,  // sophie.robert
        'site_id' => 7,       // UR71
        'etat' => 'nouveau',
        'pour_compte_nom' => 'Lambert',
        'pour_compte_prenom' => 'Françoise',
    ],
    [
        'type' => 'rami',
        'objet' => 'Menaces reçues par téléphone',
        'description' => "Réception d'un appel téléphonique menaçant de la part d'un ancien demandeur d'emploi. La personne a proféré des menaces de mort et a mentionné qu'elle se rendrait sur les lieux. Le numéro a été signalé à la police. Le personnel est inquiet.",
        'date_evenement' => '2026-03-02',
        'heure_evenement' => '15:45',
        'lieu' => 'Standard téléphonique, UR89 Auxerre',
        'declarant_id' => 10, // claude.richard
        'site_id' => 8,       // UR89
        'etat' => 'en_cours',
    ],
    [
        'type' => 'rami',
        'objet' => "Incivilité récurrente dans l'open space",
        'description' => "Un agent adopte un comportement agressif et peu respectueux envers ses collègues de l'open space : insultes, cris, refus de coopérer. La situation dure depuis plusieurs semaines et dégrade le climat de travail.",
        'date_evenement' => '2026-01-20',
        'heure_evenement' => '09:00',
        'lieu' => 'Open space 3ème étage, Siège DREETS',
        'declarant_id' => 14, // marie.leroy
        'site_id' => 1,       // Siège
        'etat' => 'traite',
        'reponse' => "Un entretien avec l'agent concerné a été réalisé. Un suivi psychologique a été proposé. Un médiateur a été nommé pour améliorer le climat dans le service.",
        'repondant_id' => 1,  // admin.dev
        'date_reponse' => '2026-02-01 10:00:00',
    ],
    [
        'type' => 'rami',
        'objet' => 'Courrier de menace reçu au service',
        'description' => "Un courrier anonyme contenant des menaces à l'encontre du personnel a été reçu ce matin. Le contenu est violent et mentionne des représailles. Le courrier a été remis à la police. L'ensemble du service est choqué.",
        'date_evenement' => '2026-02-10',
        'heure_evenement' => '08:15',
        'lieu' => 'Service accueil, UR25 Besançon',
        'declarant_id' => 7,  // jean.dupont
        'site_id' => 3,       // UR25
        'etat' => 'traite',
        'reponse' => "Une plainte a été déposée. La sécurité du bâtiment a été renforcée avec mise en place d'un système de vidéosurveillance. Un soutien psychologique a été proposé au personnel.",
        'repondant_id' => 5,  // nathalie.rousseau (superviseur UR25)
        'date_reponse' => '2026-02-15 14:00:00',
    ],
    [
        'type' => 'rami',
        'objet' => 'Injures racistes envers un agent',
        'description' => "Un usager a tenu des propos racistes envers un agent lors d'un rendez-vous. L'agent est profondément choqué et blessé. L'usager a été exclu des locaux immédiatement.",
        'date_evenement' => '2026-03-04',
        'heure_evenement' => '10:30',
        'lieu' => "Bureau d'entretien, UR21 Dijon",
        'declarant_id' => 2,  // agent.dev
        'site_id' => 2,       // UR21
        'etat' => 'nouveau',
        'pour_compte_nom' => 'Diallo',
        'pour_compte_prenom' => 'Amadou',
    ],
    [
        'type' => 'rami',
        'objet' => 'Harcèlement moral par un supérieur hiérarchique',
        'description' => "Un agent signale des comportements de harcèlement moral de la part de son supérieur : dénigrement systématique, mise à l'écart des réunions, surcharge de travail, critiques incessantes. La situation perdure depuis 6 mois.",
        'date_evenement' => '2026-02-05',
        'heure_evenement' => '16:00',
        'lieu' => 'Service RH, UR39 Lons-le-Saunier',
        'declarant_id' => 8,  // pierre.moreau
        'site_id' => 4,       // UR39
        'etat' => 'en_cours',
    ],
    [
        'type' => 'rami',
        'objet' => 'Intrusion dans les bureaux après heures',
        'description' => "Un individu non identifié a été aperçu dans les bureaux après les heures d'ouverture. Il a été vu sortant d'un bureau administratif. Rien ne semble avoir été volé mais l'incident est préoccupant.",
        'date_evenement' => '2026-01-30',
        'heure_evenement' => '19:30',
        'lieu' => 'Bureaux administratifs, UR58 Nevers',
        'declarant_id' => 11, // anne.brun
        'site_id' => 5,       // UR58
        'etat' => 'abandonne',
    ],
    [
        'type' => 'rami',
        'objet' => 'Conduite agressive au parking',
        'description' => "Un usager a adopté une conduite agressive dans le parking de l'établissement, roulant à vive allure en klaxonnant et insultant les piétons. L'incident a failli causer un accident.",
        'date_evenement' => '2026-03-05',
        'heure_evenement' => '08:20',
        'lieu' => 'Parking, UR70 Vesoul',
        'declarant_id' => 12, // luc.petit
        'site_id' => 6,       // UR70
        'etat' => 'nouveau',
    ],

    // =============== DGI reports (7) ===============
    // nouveau: 2, en_cours: 2, traite: 2, abandonne: 1
    [
        'type' => 'dgi',
        'objet' => 'Fuite de gaz détectée dans les locaux',
        'description' => "Une forte odeur de gaz a été détectée dans les locaux de l'UR58 ce matin. L'évacuation a été réalisée immédiatement. Les pompiers ont été appelés et ont confirmé une fuite sur la chaudière. Les locaux sont actuellement interdits d'accès.",
        'date_evenement' => '2026-03-03',
        'heure_evenement' => '07:30',
        'lieu' => 'Chaufferie, UR58 Nevers',
        'declarant_id' => 11, // anne.brun
        'site_id' => 5,       // UR58
        'etat' => 'en_cours',
    ],
    [
        'type' => 'dgi',
        'objet' => "Plafond menaçant de s'effondrer",
        'description' => "Des fissures importantes sont apparues dans le plafond de la salle de documentation. Des morceaux de plâtre tombent régulièrement. Le risque d'effondrement partiel est réel. La pièce a été fermée mais le problème s'étend aux bureaux adjacents.",
        'date_evenement' => '2026-02-18',
        'heure_evenement' => '16:00',
        'lieu' => 'Salle de documentation, UR70 Vesoul',
        'declarant_id' => 12, // luc.petit
        'site_id' => 6,       // UR70
        'etat' => 'nouveau',
    ],
    [
        'type' => 'dgi',
        'objet' => "Exposition à l'amiante lors de travaux",
        'description' => "Des travaux de rénovation ont été réalisés sans mise en conformité préalable concernant l'amiante. Des agents ont été exposés pendant plusieurs jours avant que les travaux ne soient interrompus. Un suivi médical est nécessaire pour le personnel exposé.",
        'date_evenement' => '2026-01-05',
        'heure_evenement' => '10:00',
        'lieu' => 'Aile Est, Siège DREETS Besançon',
        'declarant_id' => 14, // marie.leroy
        'site_id' => 1,       // Siège
        'etat' => 'traite',
        'reponse' => "Les travaux ont été stoppés immédiatement. Un diagnostic amiante complet a été réalisé. Les agents exposés ont été orientés vers le service de médecine préventive. Des travaux de désamiantage sont prévus.",
        'repondant_id' => 1,  // admin.dev
        'date_reponse' => '2026-01-08 14:00:00',
    ],
    [
        'type' => 'dgi',
        'objet' => "Panne du système d'alarme incendie",
        'description' => "Le système d'alarme incendie est en panne depuis 48h. Aucune alarme ne se déclenche en cas d'incendie. Compte tenu de la présence de matières inflammables dans les archives, le danger est grave et imminent.",
        'date_evenement' => '2026-02-12',
        'heure_evenement' => '09:00',
        'lieu' => 'Local archives, UR21 Dijon',
        'declarant_id' => 2,  // agent.dev
        'site_id' => 2,       // UR21
        'etat' => 'traite',
        'reponse' => "Le système d'alarme a été réparé en urgence dans la journée. Un contrat de maintenance préventive a été signé avec un prestataire pour éviter toute récidive.",
        'repondant_id' => 6,  // marc.benjamin (superviseur UR21)
        'date_reponse' => '2026-02-12 17:00:00',
    ],
    [
        'type' => 'dgi',
        'objet' => "Porte de secours bloquée — impossibilité d'évacuation",
        'description' => "La porte de secours du bâtiment B est bloquée par un cadenas dont personne n'a la clé. En cas d'incendie, l'évacuation de 30 agents serait compromise. C'est un danger grave et imminent.",
        'date_evenement' => '2026-03-04',
        'heure_evenement' => '11:15',
        'lieu' => 'Bâtiment B, UR25 Besançon',
        'declarant_id' => 7,  // jean.dupont
        'site_id' => 3,       // UR25
        'etat' => 'en_cours',
    ],
    [
        'type' => 'dgi',
        'objet' => 'Fissures structurelles dans le mur porteur',
        'description' => "Des fissures importantes sont apparues dans le mur porteur du bâtiment principal. Elles s'élargissent jour après jour. Un expert en structure doit intervenir en urgence pour évaluer le risque d'effondrement.",
        'date_evenement' => '2026-01-20',
        'heure_evenement' => '13:30',
        'lieu' => 'Bâtiment principal, UR39 Lons-le-Saunier',
        'declarant_id' => 8,  // pierre.moreau
        'site_id' => 4,       // UR39
        'etat' => 'traite',
        'reponse' => "Un expert en structure a été mandaté. Le bâtiment a été évacué préventivement. Des travaux de consolidation ont été réalisés. Le bâtiment a été déclaré sûr.",
        'repondant_id' => 1,  // admin.dev
        'date_reponse' => '2026-02-10 11:00:00',
    ],
    [
        'type' => 'dgi',
        'objet' => "Installation électrique dégradée — risque d'incendie",
        'description' => "Le tableau électrique du sous-sol présente des signes de surchauffe (fils fondus, traces de brûlure). Le risque de court-circuit et d'incendie est élevé. L'installation date de plus de 30 ans et n'a jamais été rénovée.",
        'date_evenement' => '2026-02-01',
        'heure_evenement' => '08:00',
        'lieu' => 'Sous-sol, UR71 Mâcon',
        'declarant_id' => 9,  // sophie.robert
        'site_id' => 7,       // UR71
        'etat' => 'abandonne',
    ],
];

$reportCount = 0;
foreach ($sampleReports as $report) {
    // Generate reference
    $reportYear = (int) substr($report['date_evenement'], 0, 4);
    $reportYear2 = substr($report['date_evenement'], 2, 2);
    
    $seq = getNextSequence($pdo, $report['type'], $reportYear);
    $reference = generateReference($report['type'], $reportYear2, $seq);

    $stmt = $pdo->prepare("
        INSERT INTO reports (
            reference, type, objet, description, date_evenement, heure_evenement,
            lieu, declarant_id, declarant_nom, declarant_prenom,
            pour_compte_nom, pour_compte_prenom,
            site_id, etat, reponse, repondant_id, date_reponse
        ) VALUES (
            :reference, :type, :objet, :description, :date_evenement, :heure_evenement,
            :lieu, :declarant_id, :declarant_nom, :declarant_prenom,
            :pour_compte_nom, :pour_compte_prenom,
            :site_id, :etat, :reponse, :repondant_id, :date_reponse
        )
    ");

    // Get declarant info
    $declarant = getUserById($pdo, $report['declarant_id']);

    $stmt->execute([
        ':reference'         => $reference,
        ':type'              => $report['type'],
        ':objet'             => $report['objet'],
        ':description'       => $report['description'],
        ':date_evenement'    => $report['date_evenement'],
        ':heure_evenement'   => $report['heure_evenement'] ?? null,
        ':lieu'              => $report['lieu'] ?? null,
        ':declarant_id'      => $report['declarant_id'],
        ':declarant_nom'     => $declarant['nom'] ?? 'Inconnu',
        ':declarant_prenom'  => $declarant['prenom'] ?? 'Inconnu',
        ':pour_compte_nom'   => $report['pour_compte_nom'] ?? null,
        ':pour_compte_prenom'=> $report['pour_compte_prenom'] ?? null,
        ':site_id'           => $report['site_id'],
        ':etat'              => $report['etat'],
        ':reponse'           => $report['reponse'] ?? null,
        ':repondant_id'      => $report['repondant_id'] ?? null,
        ':date_reponse'      => $report['date_reponse'] ?? null,
    ]);

    // Insert response history for treated/in-progress reports
    if (!empty($report['reponse']) && !empty($report['repondant_id'])) {
        $nouvelEtat = $report['etat'];
        $stmt2 = $pdo->prepare("
            INSERT INTO report_responses (report_id, user_id, reponse, nouvel_etat)
            VALUES (:report_id, :user_id, :reponse, :nouvel_etat)
        ");
        $stmt2->execute([
            ':report_id'   => $pdo->lastInsertId(),
            ':user_id'     => $report['repondant_id'],
            ':reponse'     => $report['reponse'],
            ':nouvel_etat' => $nouvelEtat,
        ]);
    }

    $reportCount++;
    echo "  Created report: $reference ({$report['type']}, {$report['etat']})\n";
}

echo "\nCreated $reportCount sample reports.\n";

// ================================================================
// NOTIFICATION SETTINGS — Per-site emails for each UR + global
// ================================================================
$notifSettings = [
    // Per-site notifications for each UR
    [1, 'site', 'all', 'sst.siege@dreets.gouv.fr'],
    [1, 'site', 'rsst', 'rsst.siege@dreets.gouv.fr'],
    [2, 'site', 'all', 'ud21.sst@dreets.gouv.fr'],
    [2, 'site', 'rami', 'rami.ud21@dreets.gouv.fr'],
    [3, 'site', 'all', 'ud25.sst@dreets.gouv.fr'],
    [3, 'site', 'rsst', 'rsst.ud25@dreets.gouv.fr'],
    [4, 'site', 'all', 'ud39.sst@dreets.gouv.fr'],
    [4, 'site', 'rami', 'rami.ud39@dreets.gouv.fr'],
    [5, 'site', 'all', 'ud58.sst@dreets.gouv.fr'],
    [5, 'site', 'dgi', 'dgi.ud58@dreets.gouv.fr'],
    [6, 'site', 'all', 'ud70.sst@dreets.gouv.fr'],
    [7, 'site', 'all', 'ud71.sst@dreets.gouv.fr'],
    [7, 'site', 'rsst', 'rsst.ud71@dreets.gouv.fr'],
    [8, 'site', 'all', 'ud89.sst@dreets.gouv.fr'],
    [8, 'site', 'rami', 'rami.ud89@dreets.gouv.fr'],
    [9, 'site', 'all', 'ud90.sst@dreets.gouv.fr'],
    // Global notifications
    [null, 'global', 'dgi', 'alerte.dgi@dreets.gouv.fr'],
    [null, 'global', 'rami', 'alerte.rami@dreets.gouv.fr'],
    [null, 'global', 'rsst', 'alerte.rsst@dreets.gouv.fr'],
    [null, 'global', 'all', 'direction.sst@dreets.gouv.fr'],
];

$stmt = $pdo->prepare('INSERT INTO notification_settings (site_id, type, registry, email) VALUES (:site_id, :type, :registry, :email)');
foreach ($notifSettings as $setting) {
    $stmt->execute([
        ':site_id' => $setting[0],
        ':type'    => $setting[1],
        ':registry'=> $setting[2],
        ':email'   => $setting[3],
    ]);
}
echo "Created notification settings (" . count($notifSettings) . " entries).\n";

// ================================================================
// FINAL SUMMARY
// ================================================================
$totalUsers = $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
$totalReports = $pdo->query('SELECT COUNT(*) FROM reports')->fetchColumn();
$totalSites = $pdo->query('SELECT COUNT(*) FROM sites')->fetchColumn();
$totalResponses = $pdo->query('SELECT COUNT(*) FROM report_responses')->fetchColumn();

echo "\n=== Seed Complete ===\n";
echo "Sites: $totalSites\n";
echo "Users: $totalUsers\n";
echo "Reports: $totalReports\n";
echo "Responses: $totalResponses\n";

// Report breakdown
foreach (['rsst', 'rami', 'dgi'] as $type) {
    $stmt = $pdo->prepare("SELECT etat, COUNT(*) as cnt FROM reports WHERE type = :type GROUP BY etat");
    $stmt->execute([':type' => $type]);
    $breakdown = [];
    foreach ($stmt->fetchAll() as $row) {
        $breakdown[] = ETAT_LABELS[$row['etat']] . ': ' . $row['cnt'];
    }
    echo "  $type: " . implode(', ', $breakdown) . "\n";
}

// User breakdown
foreach (['superviseur', 'manager', 'agent', 'chsct'] as $role) {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE role = :role AND is_active = 1");
    $stmt->execute([':role' => $role]);
    echo "  $role: " . $stmt->fetchColumn() . "\n";
}

echo "Database: " . DB_PATH . "\n";
