# Manuel du Superviseur — Application SST DREETS BFC

## Introduction

Ce manuel s'adresse aux **superviseurs** de l'application SST (Santé et Sécurité au Travail) de la DREETS Bourgogne-Franche-Comté. En tant que superviseur, vous disposez de toutes les fonctionnalités de l'agent, plus des capacités d'administration : traitement des signalements, gestion des utilisateurs, configuration de l'application, export des données et impression.

## Droits du superviseur

| Fonctionnalité | Agent | Superviseur |
|---|---|---|
| Créer un signalement | Oui | Oui |
| Voir ses signalements | Oui | Oui |
| Modifier un signalement (non traité) | Oui | Oui |
| Voir les signalements de tous les sites | Non | **Oui** |
| Répondre à un signalement | Non | **Oui** |
| Abandonner un signalement | Non | **Oui** |
| Imprimer une fiche | Non | **Oui** |
| Synthèse des signalements | Non | **Oui** |
| Statistiques | Non | **Oui** |
| Exporter les données | Non | **Oui** |
| Gérer les utilisateurs | Non | **Oui** |
| Paramètres de l'application | Non | **Oui** |

## Traitement des signalements

### Répondre à un signalement

1. Consultez la liste des signalements ou suivez le lien dans l'e-mail de notification.
2. Ouvrez le signalement en cliquant sur sa référence.
3. Cliquez sur **« Répondre »**.
4. Saisissez votre réponse et choisissez le nouveau statut :
   - **En cours** : vous avez pris en charge le signalement et une action est en cours.
   - **Traité** : le signalement a été résolu avec une réponse finale.
5. L'agent déclarant reçoit une notification par e-mail.

### Abandonner un signalement

Si un signalement est un doublon ou ne relève pas du registre concerné :
1. Ouvrez le signalement.
2. Cliquez sur **« Abandonner »**.
3. Saisissez un motif d'abandon (ex : « Doublon du signalement rsst-25-003 »).
4. Le statut passe à **Abandonné**.

### Cycle de vie d'un signalement

```
Nouveau → En cours → Traité
                  → Abandonné
```

Seul le superviseur peut changer le statut. Un signalement en état « Nouveau » ou « En cours » peut être modifié par l'agent déclarant.

## Alerte délai de traitement

L'application peut alerter automatiquement les superviseurs lorsqu'un signalement reste à l'état « Nouveau » pendant trop longtemps. Le délai est configurable dans **Paramètres → Application → Délai d'alerte**.

- **0** : l'alerte est désactivée (par défaut).
- **N jours** : le script `tools/check_delays.php` enverra un e-mail aux superviseurs du site concerné pour tout signalement resté « Nouveau » plus de N jours.

Pour activer cette alerte, configurez un CRON quotidien :
```
0 8 * * * php /chemin/vers/sst/tools/check_delays.php
```

## Gestion des utilisateurs

### Accès à la gestion

Depuis le menu, accédez à **Utilisateurs**. Vous pouvez :
- Consulter la liste de tous les utilisateurs.
- Modifier le site d'affectation d'un agent.
- Attribuer ou retirer le rôle superviseur ou CSA/CHSCT.
- Désactiver un compte (l'agent ne peut plus se connecter).
- Réactiver un compte désactivé.

### Premier utilisateur superviseur

Si aucun superviseur n'existe, vous pouvez vous auto-promouvoir via **Paramètres → Application → Logins Windows des superviseurs**. Ajoutez votre login Windows (ex : `jean.martin`), séparé par des virgules si plusieurs. Ce mécanisme est utile pour la première installation.

### Création automatique des comptes

En production (IIS), les comptes sont créés automatiquement à la première connexion via l'authentification Windows. L'agent est alors redirigé vers la page **« Choisir mon site »**.

## Configuration de l'application

### Paramètres → Application

- **Nom de l'organisation** : affiché dans l'interface et les e-mails.
- **Nom complet** : affiché dans le préambule et les mentions RGPD.
- **Libellé des unités** : UR, UD, Direction... utilisé partout dans l'interface.
- **Logins Windows des superviseurs** : liste des logins auto-promus superviseur.
- **E-mail administrateur technique** : pour recevoir les erreurs critiques.
- **Contact DPO** : coordonnées affichées dans la mention RGPD du préambule.
- **Délai d'alerte** : nombre de jours avant alerte sur signalement non traité.
- **Durée de conservation** : en années, pour l'anonymisation automatique (0 = illimitée).

### Paramètres → Visibilité des signalements

Pour chaque registre (RSST, RAMI, DGI), choisissez parmi trois modes :
- **Confidentiel** : l'agent ne voit que ses propres signalements.
- **Choix de l'agent** : l'agent choisit au cas par cas (confidentiel par défaut).
- **Public** : tous les signalements du site sont visibles par tous les agents.

> **Note réglementaire** : Le décret n° 82-453 art. 3-2 prévoit que le RSST est tenu à la disposition de l'ensemble des agents. Un mode restrictif pour le RSST peut ne pas être conforme.

### Paramètres → Notifications par site

Pour chaque site, configurez les adresses e-mail qui recevront une notification lors de la création d'un signalement. Une adresse par ligne.

### Paramètres → Notifications globales

Adresses e-mail recevant les notifications pour tous les sites et tous les registres.

### Paramètres → Configuration SMTP

Configurez le serveur d'envoi d'e-mails (hôte, port, authentification, chiffrement). Utilisez le bouton **« Envoyer un e-mail de test »** pour vérifier la configuration.

### Paramètres → Gestion des sites

Ajoutez, modifiez, activez ou désactivez les sites (unités régionales). Les sites désactivés n'apparaissent plus dans les listes de choix, mais les signalements existants restent accessibles.

## Synthèse et statistiques

### Synthèse

La page **Synthèse** affiche le nombre de signalements par registre, par site et par état. C'est un tableau de bord rapide pour évaluer l'activité.

### Statistiques

La page **Statistiques** propose :
- Des KPI (total signalements, répartition par registre, par état).
- Un tableau par site avec le détail par registre.
- Pour les signalements RAMI : la répartition par **nature de l'auteur** (usager, collègue, hiérarchie, tiers) et par **type d'acte** (verbal, physique, moral, sexiste, autre) — ces données sont celles que le CSA/CHSCT demandera en séance.

## Export CSV

1. Accédez à la page **Export**.
2. Sélectionnez vos filtres : registre, site, agent, période, états.
3. Cliquez sur **« Exporter en CSV »**.
4. Le fichier CSV est téléchargé (compatible Excel, encodage UTF-8 avec BOM).

Le CSV inclut toutes les colonnes de signalement, les réponses, l'historique, et pour les RAMI : la nature de l'auteur et le type d'acte.

## Impression PDF

1. Ouvrez un signalement.
2. Cliquez sur **« Télécharger en PDF »**.
3. Un fichier PDF est généré et téléchargé, prêt pour impression ou archivage.

## Anonymisation automatique

Si une durée de conservation est configurée (paramètre **Durée de conservation**), les signalements traités ou abandonnés plus anciens que cette durée seront anonymisés automatiquement via le script `tools/anonymize_old_reports.php`. Les noms des déclarants et bénéficiaires sont remplacés par « Anonymisé ».

**Important** : La durée de conservation doit être validée par le DPO avant activation.

## Scripts CRON recommandés

| Script | Fréquence | Rôle |
|---|---|---|
| `tools/check_delays.php` | Quotidien (8h00) | Alerte sur signalements non traités |
| `tools/anonymize_old_reports.php` | Mensuel | Anonymisation des signalements anciens |

## Profils et droits récapitulatif

| Profil | Droits |
|---|---|
| **Agent** | Créer, voir, modifier ses signalements |
| **Superviseur** | Agent + répondre, abandonner, synthèse, statistiques, export, utilisateurs, paramètres, impression |
| **Membre CSA/CHSCT** | Agent + voir tous les sites, synthèse, statistiques, export (consultation uniquement) |

## Protection des données (RGPD)

Le préambule de l'application contient la mention d'information RGPD complète (art. 13) :
- Finalité, base légale, responsable, contact DPO, durée de conservation, droits, réclamation CNIL.
- Assurez-vous que le **contact DPO** est renseigné dans les paramètres pour que la mention soit complète.
