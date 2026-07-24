<?php
use App\Repository\UserRepository;

// Sample reports seeder — data is in _reports_data.php
require __DIR__ . '/_reports_data.php';

$reportCount = 0;
foreach ($sampleReports as $report) {
    $reportYear = (int) substr($report['date_evenement'], 0, 4);
    $reportYear2 = substr($report['date_evenement'], 2, 2);

    $seq = getNextSequence($pdo, $report['type'], $reportYear);
    $reference = generateReference($report['type'], $reportYear2, $seq);

    $hex = bin2hex(random_bytes(16));
    $reportUuid = substr($hex, 0, 8) . '-' . substr($hex, 8, 4) . '-4' . substr($hex, 13, 3) . '-' . dechex((hexdec(substr($hex, 16, 2)) & 0x3F) | 0x80) . substr($hex, 18, 2) . '-' . substr($hex, 20, 12);

    $stmt = $pdo->prepare("
        INSERT INTO reports (
            uuid, reference, type, objet, description, date_evenement, heure_evenement,
            lieu, declarant_id, declarant_nom, declarant_prenom,
            pour_compte_nom, pour_compte_prenom,
            site_id, is_confidential, etat, reponse, repondant_id, date_reponse
        ) VALUES (
            :uuid, :reference, :type, :objet, :description, :date_evenement, :heure_evenement,
            :lieu, :declarant_id, :declarant_nom, :declarant_prenom,
            :pour_compte_nom, :pour_compte_prenom,
            :site_id, :is_confidential, :etat, :reponse, :repondant_id, :date_reponse
        )
    ");

    $declarant = \App\Repository\UserRepository::instance()->findById($report['declarant_id']);

    $stmt->execute([
        ':uuid'              => $reportUuid,
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
        ':is_confidential'   => $report['is_confidential'] ?? 0,
        ':etat'              => $report['etat'],
        ':reponse'           => $report['reponse'] ?? null,
        ':repondant_id'      => $report['repondant_id'] ?? null,
        ':date_reponse'      => $report['date_reponse'] ?? null,
    ]);

    // Insert response history for treated/in-progress reports
    if (!empty($report['reponse']) && !empty($report['repondant_id'])) {
        $nouvelEtat = $report['etat'];
        $stmt2 = $pdo->prepare("
            INSERT INTO report_responses (report_uuid, user_id, reponse, nouvel_etat)
            VALUES (:report_uuid, :user_id, :reponse, :nouvel_etat)
        ");
        $stmt2->execute([
            ':report_uuid'   => $reportUuid,
            ':user_id'     => $report['repondant_id'],
            ':reponse'     => $report['reponse'],
            ':nouvel_etat' => $nouvelEtat,
        ]);
    }

    $reportCount++;
    echo "  Created report: $reference ({$report['type']}, {$report['etat']})\n";
}

echo "\nCreated $reportCount sample reports.\n";
