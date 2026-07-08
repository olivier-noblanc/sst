<?php
/** RespondToReportCommand — DTO pour la réponse à un signalement. */

class RespondToReportCommand
{
    public function __construct(
        public readonly string $reponse,
        public readonly string $nouvelEtat,
        public readonly array $attachment = [],
    ) {}

    public static function fromPost(array $post): self
    {
        $attachment = [];
        if (!empty($_FILES['attachment']['name'])) {
            $attachment = [
                'blob' => file_get_contents($_FILES['attachment']['tmp_name']),
                'name' => $_FILES['attachment']['name'],
                'mime' => $_FILES['attachment']['type'],
            ];
        }
        return new self(
            reponse: trim($post['reponse'] ?? ''),
            nouvelEtat: $post['nouvel_etat'] ?? ETAT_EN_COURS,
            attachment: $attachment,
        );
    }
}