# Protocole de Vérification

Après modifications de code :

1. **Déléguer à @fixer** avec batch :
   - `php vendor/bin/phpstan analyse --memory-limit=1G`
   - `php vendor/bin/phpunit --no-coverage`
   - `git status`

2. **Utiliser ctx_batch_execute** pour les commandes de vérification parallèles :
   ```
   ctx_batch_execute(
     commands: [
       {label: "phpstan", command: "php vendor/bin/phpstan analyse --memory-limit=1G"},
       {label: "phpunit", command: "php vendor/bin/phpunit --no-coverage"},
       {label: "git status", command: "git status"}
     ],
     concurrency: 1
   )
   ```

3. **Réconcilier les résultats** avant de continuer :
   - PHPStan : 0 erreur requis
   - PHPUnit : 0 échec requis (dépréciations OK)
   - Git : working tree clean ou fichiers attendus modifiés

## Exceptions

- Modifications triviales (<5 lignes, 1 fichier) : vérification directe OK
- Refactor majeur : vérification obligatoire avant commit