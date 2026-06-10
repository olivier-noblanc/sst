# Changelog — Application SST DREETS BFC

Toutes les modifications notables de ce projet sont documentées dans ce fichier.

## [1.1.0] — 2026-06-10

### Sécurité — Corrections de confidentialité

- **Vulnérabilité critique corrigée** : le défaut de `app_agent_visibility` passe de `'all'` à `'site'`. Par défaut, un agent ne voit plus que les signalements de son site, conformément à la documentation (help.php).
- **Contrôle d'accès renforcé** : ajout de `canAccessReport()` dans helpers.php. Les pages `report_view.php` et `report_print.php` vérifient désormais systématiquement que l'utilisateur a le droit d'accéder au signalement (déclarant, superviseur, manager ou CHSCT), quelle que soit la configuration de visibilité.
- **Rôle Manager corrigé** : le manager ne peut plus répondre aux signalements (retiré de `$canRespond` dans `report_list.php`, `report_card.php`, `report_respond.php` et `report_respond_handler.php`), conforme au tableau des droits dans help.php.
- **Abandon de signalement corrigé** : l'abandon est désormais réservé au superviseur (conforme à help.php), et non plus au déclarant. Corrigé dans `report_abandon.php`, `report_abandon_handler.php`, `report_card.php` et `report_list.php`. Le bouton « Abandonner » apparaît maintenant dans la liste des signalements pour les superviseurs.
- **Option 'all' supprimée des paramètres** : l'option « Tous les signalements » n'est plus proposée dans les paramètres de visibilité agent. Seules les options « Son site » (par défaut) et « Ses propres signalements » sont disponibles.
- **Commentaire de code corrigé** : `respondToReport()` documenté comme réservé au superviseur (pas au manager).

### Fonctionnalités métier ajoutées

- **Réactivation d'utilisateur** : ajout d'un bouton « Réactiver » dans la liste des utilisateurs (`users.php`) et le profil utilisateur (`user_view.php`) pour les comptes désactivés. La fonction DB `reactivateUser()` était déjà présente mais sans UI. Ajout du handler `user_reactivate_handler.php`.
- **Modification de site** : ajout d'un bouton « Modifier » dans l'onglet « Gestion des sites » permettant de changer le code, le nom et le département d'un site. La fonction DB `updateSite()` était déjà présente mais sans UI. Ajout de la page `site_edit.php` et du handler `site_edit_handler.php`.

### Code mort supprimé

- **`updateUserRole()`** : supprimée de `user_queries.php` — couverte par `updateUser()`.
- **`updateUserSite()`** : supprimée de `user_queries.php` — couverte par `updateUser()`.
- **`agentSeesOnlyOwn()`** : supprimée de `helpers.php` — remplacée par `getAgentVisibility()`.

### Documentation

- **Section confidentialité ajoutée** dans help.php : tableau explicatif des règles d'accès aux signalements par rôle.
- **Avertissement de confidentialité** ajouté dans la description du profil Agent dans help.php.

### Technique

- Ajout de `user_reactivate` et `site_edit` dans le routeur (`index.php`).
- Ajout des handlers correspondants dans la dispatch table.

---

## [1.0.0] — 2025-06-05

### Première version

- Application SST DREETS BFC complète.
- 4 profils utilisateurs : Agent, Manager, Superviseur, CHSCT.
- 3 registres : RSST, RAMI, DGI.
- Authentification IIS Windows (prod) / mock login (dev).
- Notifications par e-mail, configuration SMTP.
- Statistiques, synthèse, export CSV.
- Gestion des utilisateurs et des sites.
