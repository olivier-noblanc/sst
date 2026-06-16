<?php
/**
 * Promote — Script CLI pour promouvoir un utilisateur
 * 
 * Usage:
 *   php promote.php <username> [role]
 * 
 * Exemples:
 *   php promote.php jean.martin superviseur
 *   php promote.php pierre.bernard chsct
 *   php promote.php sophie.dupont agent
 * 
 * Sans argument role, promeut en superviseur par défaut.
 * 
 * Ce script est utile pour le premier déploiement en production :
 *   1. Configurer IIS + Windows Authentication
 *   2. Se connecter une première fois (auto-provision en agent)
 *   3. En CLI : php promote.php jean.martin superviseur
 *   4. Recharger la page → jean.martin est maintenant superviseur
 */

// Only allow CLI execution
if (php_sapi_name() !== 'cli') {
    echo "Ce script ne peut être exécuté qu'en ligne de commande.\n";
    exit(1);
}

// Require explicit confirmation via environment variable
if (!isset($_SERVER['SST_CONFIRM_PROMOTE']) || $_SERVER['SST_CONFIRM_PROMOTE'] !== 'yes') {
    echo "Erreur : définissez SST_CONFIRM_PROMOTE=yes pour confirmer.\n";
    echo "Usage: SST_CONFIRM_PROMOTE=yes php promote.php <username> [role]\n";
    exit(1);
}

echo "=== DREETS BFC SST — Promotion d'utilisateur ===\n\n";

// Load config
require_once __DIR__ . '/src/config.php';
require_once __DIR__ . '/src/database.php';
require_once __DIR__ . '/src/helpers.php';
require_once __DIR__ . '/src/queries/user_queries.php';

$username = $argv[1] ?? null;
$role = $argv[2] ?? 'superviseur';

$validRoles = ['agent', 'superviseur', 'chsct'];

if (empty($username)) {
    echo "Usage: php promote.php <username> [role]\n";
    echo "Rôles valides: " . implode(', ', $validRoles) . "\n";
    echo "Par défaut: superviseur\n\n";
    echo "Exemples:\n";
    echo "  php promote.php jean.martin superviseur\n";
    echo "  php promote.php pierre.bernard chsct\n";
    exit(1);
}

if (!in_array($role, $validRoles)) {
    echo "Erreur: rôle '$role' invalide.\n";
    echo "Rôles valides: " . implode(', ', $validRoles) . "\n";
    exit(1);
}

$pdo = getDB();

// Find user
$stmt = $pdo->prepare('SELECT u.*, s.code as site_code, s.nom as site_nom FROM users u LEFT JOIN sites s ON u.site_id = s.id WHERE u.username = :username');
$stmt->execute([':username' => $username]);
$user = $stmt->fetch();

if (!$user) {
    echo "Erreur: utilisateur '$username' introuvable dans la base.\n";
    echo "Cet utilisateur doit d'abord se connecter via IIS pour être auto-provisionné.\n\n";
    exit(1);
}

$oldRole = $user['role'];

if ($oldRole === $role) {
    echo "L'utilisateur '$username' a déjà le rôle '$role'. Aucune modification.\n";
    exit(0);
}

// Update role
$stmt = $pdo->prepare('UPDATE users SET role = :role, updated_at = datetime("now") WHERE username = :username');
$stmt->execute([':role' => $role, ':username' => $username]);

echo "✓ Utilisateur '$username' promu : $oldRole → $role\n";
echo "  Nom complet : {$user['prenom']} {$user['nom']}\n";
echo "  Site        : {$user['site_code']} — {$user['site_nom']}\n";
echo "  Email       : {$user['email']}\n\n";

// Also suggest config list for future auto-promotion
$superviseurUsernames = getConfig('app_superviseur_usernames', '');
if (empty($superviseurUsernames)) {
    echo "Astuce: pour que les futurs utilisateurs soient aussi auto-promus superviseur, vous pouvez :\n";
    echo "  - Ajouter des logins dans Paramètres → 'Logins Windows des superviseurs'\n";
    echo "  - Ou exécuter : php promote.php <username> superviseur\n";
}

echo "Terminé.\n";
