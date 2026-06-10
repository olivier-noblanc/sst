# Changelog — Application SST DREETS BFC

Toutes les modifications notables de ce projet sont documentées dans ce fichier.

## [1.2.0] — 2026-06-10

### Conformité DIRECCTE — Suppression du rôle Manager

- **Rôle Manager supprimé** : ce rôle n'existait pas dans la documentation DIRECCTE (manuels utilisateurs PDF). L'application ne comporte plus que 3 profils conformes : **Agent**, **Superviseur**, **CHSCT**. Toutes les références au rôle Manager ont été retirées du code (config, helpers, auth, pages, handlers, templates, CSS, schema, seed, promote).
- **help.php remis en conformité** avec les manuels PDF DIRECCTE : 3 profils uniquement, tableau des droits et cas d'usage mis à jour.
- **SPEC.md et README.md mis à jour** : 3 rôles documentés, suppression de toutes les références Manager.

### Système d'auto-promotion revu

- **Mécanisme de préfixe « adm. » supprimé** : l'auto-promotion basée sur un préfixe de login (ex: `adm.olivier.noblanc`) est retirée. Ce mécanisme n'était pas conforme à la documentation.
- **Liste explicite conservée** : le paramètre `app_admin_usernames` (renommé « Logins superviseur ») reste disponible dans Paramètres → Application. Il permet de configurer les logins Windows des premiers superviseurs, utiles pour une première installation.
- **Attribution par un Superviseur** : un Superviseur peut attribuer le rôle Superviseur à un autre utilisateur via la gestion des utilisateurs, conformément à la documentation.
- **Clé `app_admin_prefix` retirée** du handler de paramètres et de la migration.

### Visibilité agent — conformité renforcée

- **Option « all » définitivement supprimée** : le code ne permet plus la visibilité « tous les signalements » pour les agents. Seules les options `site` (défaut, conforme) et `own` (restriction) existent. La fonction `canAccessReport()` ne comporte plus de chemin `'all'`.
- **`canSeeAllSites()` simplifié** : retourne toujours `false` pour les agents, sans dépendre d'un paramètre de config.
- **`app_agent_visibility`** : la valeur par défaut dans le schema SQL passe de `'all'` à `'site'`. La clé obsolète `app_agent_see_only_own` n'est plus migrée.

### Technique

- `ROLE_LABELS` dans `config.php` : 3 entrées uniquement (agent, superviseur, chsct).
- `getRoleBadgeClass()` dans `helpers.php` : plus de cas `manager`.
- `checkAndPromoteUser()` dans `auth.php` : ne vérifie plus que la liste explicite, plus de préfixe.
- `determineProvisionRole()` dans `auth.php` : mécanisme de préfixe retiré, seule la liste explicite est vérifiée.
- `autoProvisionUser()` dans `auth.php` : suppression du stripping de préfixe pour le display name.
- Compte de test `manager.dev` retiré du seed et du formulaire de login.
- Sidebar : rôles mis à jour (superviseur/chsct au lieu de superviseur/manager/chsct).
- `settings.php` : champ « Préfixe de login administrateur » retiré, seul le champ « Logins superviseur » reste.
- `settings_handler.php` : ne traite plus `app_admin_prefix`.

---

## [1.1.0] — 2026-06-10

### Sécurité — Corrections de confidentialité

- **Vulnérabilité critique corrigée** : le défaut de `app_agent_visibility` passe de `'all'` à `'site'`. Par défaut, un agent ne voit plus que les signalements de son site, conformément à la documentation.
- **Contrôle d'accès renforcé** : ajout de `canAccessReport()` dans helpers.php. Les pages `report_view.php` et `report_print.php` vérifient désormais systématiquement que l'utilisateur a le droit d'accéder au signalement (déclarant, superviseur ou CHSCT), quelle que soit la configuration de visibilité.
- **Abandon de signalement corrigé** : l'abandon est désormais réservé au superviseur (conforme à la documentation), et non plus au déclarant. Corrigé dans `report_abandon.php`, `report_abandon_handler.php`, `report_card.php` et `report_list.php`. Le bouton « Abandonner » apparaît maintenant dans la liste des signalements pour les superviseurs.
- **Option 'all' supprimée des paramètres** : l'option « Tous les signalements » n'est plus proposée dans les paramètres de visibilité agent. Seules les options « Son site » (par défaut) et « Ses propres signalements » sont disponibles.

### Fonctionnalités métier ajoutées

- **Réactivation d'utilisateur** : ajout d'un bouton « Réactiver » dans la liste des utilisateurs (`users.php`) et le profil utilisateur (`user_view.php`) pour les comptes désactivés. La fonction DB `reactivateUser()` était déjà présente mais sans UI. Ajout du handler `user_reactivate_handler.php`.
- **Modification de site** : ajout d'un bouton « Modifier » dans l'onglet « Gestion des sites » permettant de changer le code, le nom et le département d'un site. La fonction DB `updateSite()` était déjà présente mais sans UI. Ajout de la page `site_edit.php` et du handler `site_edit_handler.php`.

### Code mort supprimé

- **`updateUserRole()`** : supprimée de `user_queries.php` — couverte par `updateUser()`.
- **`updateUserSite()`** : supprimée de `user_queries.php` — couverte par `updateUser()`.
- **`agentSeesOnlyOwn()`** : supprimée de `helpers.php` — remplacée par `getAgentVisibility()`.

### Documentation

- **Section confidentialité ajoutée** dans help.php : tableau explicatif des règles d'accès aux signalements par rôle.

### Technique

- Ajout de `user_reactivate` et `site_edit` dans le routeur (`index.php`).
- Ajout des handlers correspondants dans la dispatch table.

---

## [1.0.0] — 2025-06-05

### Première version

- Application SST DREETS BFC complète.
- 3 profils utilisateurs : Agent, Superviseur, CHSCT.
- 3 registres : RSST, RAMI, DGI.
- Authentification IIS Windows (prod) / mock login (dev).
- Notifications par e-mail, configuration SMTP.
- Statistiques, synthèse, export CSV.
- Gestion des utilisateurs et des sites.
