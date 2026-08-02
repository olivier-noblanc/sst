### Task 2 : Fixer `CreateReportCommand`

**Files:**
- Modify: `src/DTO/CreateReportCommand.php:51,101`

- [ ] **Step 1 : Remplacer `@param array<string, mixed> $user` (ligne 51)**

Le `$user` contient : `id`, `nom`, `prenom` (de la session).
```php
/** @param array{id: int|string, nom: string, prenom: string} $user */
```

- [ ] **Step 2 : Remplacer `@return array<string, mixed>` sur `toArray()` (ligne 101)**

```php
/**
 * @return array{
 *     type: string,
 *     objet: string,
 *     description: string,
 *     dateEvenement: string,
 *     heureEvenement: ?string,
 *     lieu: ?string,
 *     declarantId: int,
 *     declarantNom: string,
 *     declarantPrenom: string,
 *     siteId: int,
 *     siteText: ?string,
 *     pole: ?string,
 *     serviceAffectation: ?string,
 *     telephoneMobile: ?string,
 *     isConfidential: bool,
 *     consentSyndicat: bool,
 *     natureAuteur: ?string,
 *     typeActe: ?string,
 *     pourCompteNom: ?string,
 *     pourComptePrenom: ?string,
 *     attachmentBlob: ?string,
 *     attachmentName: ?string,
 *     attachmentMime: ?string
 * }
 */
```

- [ ] **Step 3 : Vérifier PHPStan + Commit**

