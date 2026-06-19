<?php

/**
 * Auth Provision — Application SST DREETS BFC
 *
 * Auto-provision function for new users.
 * Split from auth.php for readability.
 */

/**
 * Auto-provision a new user from their Windows login.
 * Generates display name from username (e.g. "jean.martin" → Jean Martin).
 * Checks superviseur username list for auto-promotion.
 *
 * @param PDO    $pdo       Database connection
 * @param string $username  The username (clean, without domain)
 * @return array<string, mixed>|null
 */
function autoProvisionUser(PDO $pdo, string $username): ?array
{
    // Determine role: check if username is in superviseur list
    $role = determineProvisionRole($pdo, $username);

    // Generate a display name from the username (e.g. "olivier.noblanc" → Olivier Noblanc)
    $parts = explode('.', $username);
    $prenom = ucfirst($parts[0]);
    $nom = ucfirst($parts[1] ?? 'Utilisateur');
    // If there are more than 2 parts, join them for the last name
    if (count($parts) > 2) {
        $nom = ucfirst($parts[1]) . ' ' . ucfirst($parts[2]);
    }

    $stmt = $pdo->prepare(
        'INSERT INTO users (username, nom, prenom, email, role, site_id) 
         VALUES (:username, :nom, :prenom, :email, :role, :site_id)'
    );
    $stmt->execute([
        ':username' => $username,
        ':nom'      => $nom,
        ':prenom'   => $prenom,
        ':email'    => $username . '@dreets.gouv.fr',
        ':role'     => $role,
        ':site_id'  => null,  // NULL = agent must choose their site on first login
    ]);

    $userId = (int) $pdo->lastInsertId();
    $stmt = $pdo->prepare(
        userSelectWithSite() . ' WHERE u.id = :id'
    );
    $stmt->execute([':id' => $userId]);
    $user = $stmt->fetch();

    return $user ?: null;
}
