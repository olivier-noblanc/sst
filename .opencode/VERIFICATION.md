# Protocole de Vérification

## Routage des tâches de vérification

| Tâche | Agent | Pourquoi |
|-------|-------|----------|
| **Vérification (phpstan + phpunit + git status)** | **@stagiaire** | Exécution simple, coût très faible, pas de décision nécessaire |
| **Correction d'erreurs de type** | **@fixer** | Besoin de comprendre le contexte, les signatures, la logique |
| **Refactor >5 fichiers** | **@fixer** (ou multiple en parallèle) | Modification complexe, scope large |

## Après modifications de code

1. **Déléguer à @stagiaire** avec les commandes :
   - `php vendor/bin/phpstan analyse --memory-limit=1G`
   - `php vendor/bin/phpunit --no-coverage`
   - `git status`

2. **Critères de validation** :
   - PHPStan : 0 erreur requis
   - PHPUnit : 0 échec requis (dépréciations OK)
   - Git : working tree clean ou fichiers attendus modifiés

## Exceptions

- **Modifications triviales** (<5 lignes, 1 fichier) : vérification directe OK
- **Refactor majeur** : vérification @stagiaire obligatoire avant commit