<?php

namespace App\Repository;

use App\Enum\ReportState;
use PDO;

/**
 * AnonymizationPolicy — seule source de vérité pour l'anonymisation RGPD.
 *
 * Avant ce fichier, UserRepository::anonymize() et ReportRepository::anonymize()
 * dupliquaient chacun leurs propres littéraux ('Anonymisé'/'Anonymé') et leur
 * propre liste de tables à toucher. C'est ce qui a permis au trou report_agents
 * de passer inaperçu : rien ne reliait "tables avec FK vers users(id)" et
 * "tables réellement anonymisées". Désormais :
 *
 * - Les valeurs n'existent qu'ici (ANONYMIZED_NAME / ANONYMIZED_FIRSTNAME).
 * - USER_ANONYMIZATION_TARGETS énumère explicitement chaque table liée à un
 *   utilisateur (hors users/reports, gérées à part car elles portent d'autres
 *   champs que la simple identité). Ajouter une table avec FK vers users(id)
 *   sans l'ajouter ici est le bug qu'on vient de corriger — la couvrir dans
 *   ce tableau, pas ailleurs, est ce que NoRawAnonymizationLiteralRule impose.
 */
final class AnonymizationPolicy
{
    public const string ANONYMIZED_NAME = 'Anonymisé';
    public const string ANONYMIZED_FIRSTNAME = 'Anonymé';
    public const string ANONYMIZED_USER_FIRSTNAME = 'Utilisateur';

    /**
     * Sentinelle d'email d'anonymisation (décision produit — invariant
     * users.email NOT NULL). Source de vérité UNIQUE — ne jamais dupliquer ce
     * littéral : migration, guard sendMail et notification référencent cette
     * constante. Le domaine .invalid (RFC 2606) ne peut jamais résoudre ni
     * recevoir ; le chokepoint sendMail neutralise tout envoi vers elle.
     */
    public const string ANONYMIZED_EMAIL = 'anonyme@anonyme.invalid';

    /**
     * Oracle — comparaison de sentinelle INSENSIBLE à la casse, source de
     * vérité unique : tous les guards (validation utilisateur, chokepoint
     * sendMail, sélection des destinataires) passent par cette méthode.
     */
    public static function isAnonymizedEmail(string $email): bool
    {
        return mb_strtolower(trim($email), 'UTF-8') === self::ANONYMIZED_EMAIL;
    }

    /**
     * Tables (hors users/reports) portant un user_id vers un utilisateur
     * anonymisé, et ce qu'il faut en faire. 'set_null' quand la ligne porte
     * un payload qui doit survivre (réponse, log) ; 'delete' quand la ligne
     * n'a pas d'autre raison d'exister que le lien lui-même (report_agents).
     *
     * @var list<array{table: string, column: string, mode: 'set_null'|'delete'}>
     */
    public const array USER_ANONYMIZATION_TARGETS = [
        ['table' => 'report_responses', 'column' => 'user_id', 'mode' => 'set_null'],
        ['table' => 'report_access_log', 'column' => 'user_id', 'mode' => 'set_null'],
        ['table' => 'report_agents', 'column' => 'user_id', 'mode' => 'delete'],
    ];

    /**
     * Anonymise le compte lui-même, ses signalements en tant que déclarant,
     * et toute trace de son identité ailleurs (réponses, logs de consultation,
     * rattachements à des signalements de tiers).
     *
     * Ne gère pas la transaction ni le rebuild reports_fts — reste à la charge
     * de l'appelant (UserRepository::anonymize()), qui les partage avec le
     * reste de son travail.
     */
    public function anonymizeUser(PDO $pdo, int $id): void
    {
        $stmt = $pdo->prepare('
            UPDATE users
            SET nom = :name, prenom = :user_firstname, email = :email,
                username = \'anonymized_\' || CAST(:id AS TEXT),
                is_active = 0, updated_at = datetime(\'now\')
            WHERE id = :id2
        ');
        $stmt->execute([
            ':name' => self::ANONYMIZED_NAME,
            ':user_firstname' => self::ANONYMIZED_USER_FIRSTNAME,
            ':email' => self::ANONYMIZED_EMAIL,
            ':id' => $id,
            ':id2' => $id,
        ]);

        $stmt = $pdo->prepare('
            UPDATE reports
            SET declarant_nom = :name, declarant_prenom = :firstname,
                telephone_mobile = NULL
            WHERE declarant_id = :id
        ');
        $stmt->execute([
            ':name' => self::ANONYMIZED_NAME,
            ':firstname' => self::ANONYMIZED_FIRSTNAME,
            ':id' => $id,
        ]);

        $stmt = $pdo->prepare('
            UPDATE reports SET repondant_id = NULL WHERE repondant_id = :id AND repondant_id IS NOT NULL
        ');
        $stmt->execute([':id' => $id]);

        foreach (self::USER_ANONYMIZATION_TARGETS as $target) {
            $sql = $target['mode'] === 'delete'
                ? "DELETE FROM {$target['table']} WHERE {$target['column']} = :id"
                : "UPDATE {$target['table']} SET {$target['column']} = NULL WHERE {$target['column']} = :id AND {$target['column']} IS NOT NULL";
            $pdo->prepare($sql)->execute([':id' => $id]);
        }
    }

    /**
     * Anonymise un signalement précis (rétention RGPD, hors compte utilisateur —
     * voir ReportRepository::findAnonymizable()/anonymize()). Le WHERE
     * (etat, declarant_nom déjà anonymisé) reste dans ReportRepository, seules
     * les valeurs sont partagées ici.
     */
    public function anonymizeReport(PDO $pdo, string $uuid): bool
    {
        $stmt = $pdo->prepare("
            UPDATE reports
            SET declarant_nom = :name,
                declarant_prenom = :firstname,
                pour_compte_nom = NULL,
                pour_compte_prenom = NULL,
                telephone_mobile = NULL,
                updated_at = datetime('now')
            WHERE uuid = :uuid
              AND etat IN ('" . ReportState::Traite->value . "', '" . ReportState::Abandonne->value . "')
              AND declarant_nom != :name2
        ");
        $stmt->execute([
            ':name' => self::ANONYMIZED_NAME,
            ':firstname' => self::ANONYMIZED_FIRSTNAME,
            ':uuid' => $uuid,
            ':name2' => self::ANONYMIZED_NAME,
        ]);
        return $stmt->rowCount() > 0;
    }
}
