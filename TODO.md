# Suivi des travaux — Application SST DREETS BFC

Dernière mise à jour : 2026-09-14 — état livré au lot final `9a1723b`, CI verte `34835467593`

Ce fichier distingue les travaux livrés, les constats historiques conservés pour traçabilité et les objectifs qui restent réellement à démontrer. Les statuts anciens contradictoires sont explicitement requalifiés ci-dessous ; ils ne constituent pas une seconde liste de tâches ouvertes.

---

## État livré vérifié

| Métrique | Valeur de référence |
|---|---|
| Tests PHPUnit | **1912 tests / 4870 assertions** |
| PHPStan | **0 erreur** |
| CI | **verte**, run `34835467593` |
| Lot final | commit `9a1723b` — restauration de l’architecture CI et rendu du changelog |
| Niveau PHPStan | 8 |
| E2E | validés par la CI verte, Firefox en shards |

Le lot final contient notamment le contrat des champs personnalisés, les corrections de repository/service associées, le rendu du changelog et la stabilisation de l’export. Les métriques plus anciennes (850, 1556, 1589, 1844 tests, etc.) sont historiques et ne doivent plus être utilisées comme état courant.

---

## Livraisons terminées et prouvées

### Champs personnalisés, migration et export

- ✅ **Champs personnalisés** : contrat `CustomFieldContract`, service et repository cohérents avec les registres custom ; livré et couvert dans le lot final `9a1723b`.
- ✅ **Migration et index** : création idempotente de `registry_field_values` et de son index de recherche, avec mise à niveau des contraintes uniques sans perte des index applicatifs.
- ✅ **Export des réponses par chunks** : chargement borné des réponses pour éviter la limite SQLite des variables SQL, y compris pour les exports volumineux.
- ✅ **Export des registres custom** : résolution du code de registre depuis le POST, champs dynamiques, historique des réponses et échappement CSV centralisé.

### Fiabilité des traitements

- ✅ **Retry Cron** : les tâches Cron rejouables disposent d’un verrou de prise atomique ; un second processus ne traite pas le même lot simultanément.
- ✅ **Notifications atomiques** : les notifications de création, réponse, réouverture, DGI et retard sont déclenchées après succès de l’opération et ne sont pas dispatchées après un échec ou une concurrence perdue.
- ✅ **Migration des contraintes et index** : migrations idempotentes, contrôles d’intégrité et reconstruction SQLite vérifiés sans retour silencieux sur erreur.

### Infection / mutation testing

- ✅ **Seuil MSI de la CI** : le seuil actuellement configuré et contrôlé par la CI est atteint ; le job Infection fait partie de la qualité livrée et le run `34835467593` est vert.
- 🟡 **Objectif historique MSI à 85 %** : **à revalider**. La CI prouve le seuil actuellement configuré, pas l’ancienne cible de 85 %. Ne pas présenter cette cible historique comme atteinte sans rapport Infection chiffré.

### E2E et CI

- ✅ **E2E** : les contradictions historiques « non exécuté », « traitement en cours » et « 4–6 échecs non résolus » sont obsolètes. Les scénarios Playwright sont couverts par la CI verte `34835467593`.
- ✅ **Isolation E2E** : la base de test et les sessions sont isolées du système de production.
- ✅ **Pipeline qualité** : lint, PHPStan, PHPUnit, architecture, règles custom, Infection et E2E sont intégrés au workflow livré.

### Corrections DDD A1–A8

Les huit actions de l’audit DDD du 2026-07-25 sont **terminées dans l’état livré**. Les anciennes formulations indiquant une action non livrée et les tableaux qui les présentent encore comme ouvertes sont historiques.

| Action | Livraison constatée |
|---|---|
| A1 — supprimer `RegistryCard.php` mort | ✅ DTO mort supprimé ; le service utilise le modèle effectivement consommé |
| A2 — magic string de `pages/help.php` | ✅ comparaison migrée vers `UserRole` |
| A3 — validation email de l’édition | ✅ validation centralisée via `ReportService` |
| A4 — typage des DTO métier | ✅ types booléens et enums appliqués aux DTO et appelants concernés |
| A5 — injection PDO de `NotificationService` | ✅ dépendance fournie par le container |
| A6 — SQL d’audit | ✅ accès encapsulé dans le repository d’audit |
| A7 — SQL Cron/anonymisation | ✅ accès migré vers les repositories/services avec retry atomique |
| A8 — compteur de DTO | ✅ documentation et métrique corrigées |

---

## Autres travaux déjà clos

Les priorités suivantes restent closes ; leurs détails historiques sont conservés dans l’historique de git et dans le changelog :

- ✅ PHPStan : réduction des erreurs jusqu’à 0, niveau 8, extensions et règles custom actives.
- ✅ Enums métier : `ReportState`, `ReportType`, `UserRole`, `VisibilityMode`, avec interdiction de `ReportType::from()` pour les codes custom.
- ✅ Refactorisation de `ReportRepository` et extraction des repositories spécialisés.
- ✅ Suppression des queries procédurales orphelines et des méthodes mortes identifiées.
- ✅ Règle `NoSqlOutsideRepositoryRule` : violations applicatives résiduelles traitées ou explicitement limitées aux migrations/legacy autorisés.
- ✅ Mode sans site : entrée `site_id = 0` convertie en `NULL` au repository ; lecture nullable préservée.
- ✅ CSRF, sessions, anonymisation RGPD, visibilité, exports et workflow réouvrir/répondre : corrections validées par les tests et la CI.
- ✅ Registres custom pleinement fonctionnels : labels, couleurs, champs, statistiques, synthèse, aide et exports dynamiques.
- ✅ DTOs readonly et migrations Array → DTO livrés sur les cibles prioritaires.

---

## Objectifs réellement ouverts

### O1 — Revalider l’objectif MSI historique de 85 %

Statut : 🟡 **à revalider**.

La preuve disponible établit le seuil CI livré, mais pas l’ancienne cible de 85 %. Pour fermer cet objectif, conserver un rapport Infection complet indiquant le MSI et le run correspondant ; à défaut, le laisser ouvert.

### O2 — Refactorings DDD non inclus dans A1–A8

Statut : 🟡 **ouvert, amélioration future**.

Les wrappers procéduraux historiques (`getConfig()`, `currentUser()`, `isRegistryEnabled()`, etc.) et les derniers points de legacy hors périmètre A1–A8 peuvent être migrés progressivement vers les services. Aucun statut de livraison ne doit être déduit de la seule conformité PHPStan.

### O3 — Nettoyage complémentaire des tests et de la duplication

Statut : 🟡 **ouvert, amélioration future**.

Les usages d’arrays justifiés (filtres, paramètres URL, collections simples) sont conservés. Seuls les nouveaux cas structurés nécessitant réellement un DTO doivent être traités ; aucune migration globale non démontrée n’est considérée comme faite.

---

## Historique requalifié — à ne pas rouvrir

Cette section conserve une trace concise des contradictions qui figuraient dans les versions précédentes de ce suivi.

- **Historique métriques** : les chiffres 1556/3904, 1589/4063, 1844/4683 et les autres mesures intermédiaires correspondent à des commits antérieurs. La référence actuelle est 1912/4870.
- **Historique E2E** : la limite « exécution réelle non vérifiée », les « 14 failures » et l’estimation « 4–6 bugs restants » précédaient les corrections de session, cookie, permissions et CI. Ils sont remplacés par la preuve CI `34835467593` verte.
- **Historique A1–A8** : les lignes qui annonçaient A3–A7 comme non livrées ou qui mélangeaient recommandations et livraisons sont obsolètes. Le tableau de livraison ci-dessus est l’état de référence.
- **Historique Infection** : les mesures locales 48,5 %, 51 % et ~57,4 % sont des baselines intermédiaires. Elles ne prouvent ni l’échec du seuil CI livré ni l’atteinte de la cible historique 85 %.
- **Historique audit CTO** : les lots de bugs critiques, High, Medium et Low ont été traités ou rendus obsolètes par les sessions ultérieures et la CI. Ils ne doivent pas être dupliqués comme tâches ouvertes ici.
- **Historique registre custom** : les phases P1/P2/P3 et les anciens bugs E2E de seed/labels sont clos ; les mentions « crash à la soumission » et « registres non modulaires » décrivent l’état avant les corrections.

---

## Références

- État livré : commit `9a1723b`.
- Validation CI : run `34835467593` (vert).
- Changelog des livraisons et des fixes Infection : `CHANGELOG.md`.
- Les anciens détails de commits restent consultables dans l’historique git ; ils ne remplacent pas les métriques et statuts de cette page.
