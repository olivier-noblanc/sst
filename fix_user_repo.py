path = r'C:\Users\raver\source\repos\sst\src\Repository\UserRepository.php'
with open(path, 'r', encoding='utf-8') as f:
    content = f.read()

# Fix 1: Add telephone_mobile = NULL to reports UPDATE
old1 = """SET declarant_nom = 'Anonymisé', declarant_prenom = 'Utilisateur',
                    pour_compte_nom = CASE WHEN pour_compte_nom IS NOT NULL THEN 'Anonymisé' ELSE NULL END,
                    pour_compte_prenom = CASE WHEN pour_compte_prenom IS NOT NULL THEN 'Utilisateur' ELSE NULL END
                WHERE declarant_id = :id"""
new1 = """SET declarant_nom = 'Anonymisé', declarant_prenom = 'Utilisateur',
                    pour_compte_nom = CASE WHEN pour_compte_nom IS NOT NULL THEN 'Anonymisé' ELSE NULL END,
                    pour_compte_prenom = CASE WHEN pour_compte_prenom IS NOT NULL THEN 'Utilisateur' ELSE NULL END,
                    telephone_mobile = NULL
                WHERE declarant_id = :id"""
content = content.replace(old1, new1)

# Fix 2: Add report_responses anonymization before FTS rebuild
old2 = """            $stmt = $this->pdo->prepare('
                UPDATE reports SET repondant_id = NULL WHERE repondant_id = :id AND repondant_id IS NOT NULL
            ');
            $stmt->execute([':id' => $id]);

            try {"""
new2 = """            $stmt = $this->pdo->prepare('
                UPDATE reports SET repondant_id = NULL WHERE repondant_id = :id AND repondant_id IS NOT NULL
            ');
            $stmt->execute([':id' => $id]);

            $stmt = $this->pdo->prepare('
                UPDATE report_responses SET user_id = NULL WHERE user_id = :id AND user_id IS NOT NULL
            ');
            $stmt->execute([':id' => $id]);

            $stmt = $this->pdo->prepare('
                UPDATE report_access_log SET user_id = NULL WHERE user_id = :id AND user_id IS NOT NULL
            ');
            $stmt->execute([':id' => $id]);

            try {"""
content = content.replace(old2, new2)

with open(path, 'w', encoding='utf-8') as f:
    f.write(content)
print('Done')
