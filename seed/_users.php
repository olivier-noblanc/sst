<?php
// Additional users (10 users to add to the 3 default ones = 13 total)
$testUsers = [
    // 2 superviseurs (UR25, UR21)
    ['username' => 'nathalie.rousseau', 'nom' => 'Rousseau',  'prenom' => 'Nathalie', 'email' => 'nathalie.rousseau@dreets.gouv.fr', 'role' => 'superviseur', 'site_id' => 3],
    ['username' => 'marc.benjamin',     'nom' => 'Benjamin',  'prenom' => 'Marc',     'email' => 'marc.benjamin@dreets.gouv.fr',    'role' => 'superviseur', 'site_id' => 2],
    // 5 agents (one per UR: 25, 21, 39, 71, 89)
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
echo "Created " . count($testUsers) . " additional test users (13 total with defaults).\n";
