# Manuel de l'Agent — Application SST DREETS BFC

## Introduction

Ce manuel s'adresse aux **agents** utilisant l'application SST (Santé et Sécurité au Travail) de la DREETS Bourgogne-Franche-Comté. L'application vous permet de signaler tout événement relatif à votre santé, votre sécurité ou votre intégrité physique et morale au travail.

## Première connexion

1. Accédez à l'application via votre navigateur (Firefox ou Chrome recommandé).
2. En production, vous êtes automatiquement authentifié via votre compte Windows.
3. Lors de votre première connexion, vous devez **choisir votre site** (unité régionale). Ce choix est **définitif** — seul un superviseur peut le modifier ensuite.

## Les 3 registres

L'application met à votre disposition trois registres distincts, chacun correspondant à un type de signalement :

### RSST — Registre de Santé et de Sécurité au Travail
Pour signaler tout événement lié à la santé ou la sécurité au travail : conditions de travail dangereuses, équipements défectueux, risques professionnels, problèmes d'ergonomie, conditions environnementales (bruit, éclairage, température). Ce registre dispose d'un champ spécifique **Lieu** pour localiser l'événement.

### RAMI — Registre des Actes d'Agressions, de Menaces et d'Incivilités
Pour signaler des agressions physiques ou verbales, des menaces, du harcèlement moral ou sexuel, des agissements sexistes, ou toute forme d'incivilité subie dans le cadre de vos fonctions. Ce registre dispose de champs spécifiques :
- **Pour le compte de** : si vous signalez pour un collègue qui ne peut pas le faire lui-même.
- **Nature de l'auteur** : usager, collègue, hiérarchie ou tiers (optionnel, mais recommandé pour les statistiques).
- **Type d'acte** : verbal, physique, moral, sexiste ou autre (optionnel, mais recommandé pour les statistiques).

### DGI — Registre de signalement d'un Danger Grave et Imminent
Pour signaler une situation de danger grave et imminent nécessitant une action immédiate : fuite de gaz, risque d'effondrement, exposition à un produit dangereux, etc. Ce registre bénéficie d'une **procédure accélérée** avec notification immédiate des superviseurs.

> **Clarification importante** : Le formulaire DGI vaut notification au sens de l'article L4131-1 du Code du travail (droit de retrait individuel). La consignation formelle sur le registre spécial (article D4132-1) relève du représentant CSA/CHSCT — il s'agit de deux actes juridiques distincts.

## Créer un signalement

1. Depuis l'accueil, cliquez sur **« Inscrire un signalement »** sur la carte du registre concerné (RSST, RAMI ou DGI).
2. Remplissez le formulaire :
   - **Date de l'événement** (obligatoire) : la date à laquelle l'événement s'est produit.
   - **Heure de l'événement** : l'heure approximative.
   - **Lieu** : le lieu précis de l'événement (bureau, étage, bâtiment...).
   - **Objet** (obligatoire) : un résumé court du signalement (100 caractères max).
   - **Description** (obligatoire) : le détail complet de la situation (20 000 caractères max).
   - **Pièce jointe** (optionnel) : une photo ou un document PDF (10 Mo max).
   - **Site** (obligatoire) : votre unité régionale.
3. Si le mode de visibilité est « Choix de l'agent », vous pouvez cocher ou décocher **« Signalement confidentiel »**. Par défaut, le signalement est confidentiel.
4. Cliquez sur **« Valider son signalement »**.
5. Votre signalement reçoit une référence unique (ex : `rsst-25-001`) et est enregistré avec le statut **Nouveau**.
6. Les superviseurs de votre site sont automatiquement notifiés par e-mail.

## Que se passe-t-il après mon signalement ?

- Votre signalement est envoyé aux superviseurs de votre site par notification e-mail.
- Un superviseur le prendra en charge et passera le statut à **En cours**, puis à **Traité** avec une réponse.
- Vous pouvez suivre l'avancement dans la liste des signalements de votre site.
- En cas d'absence de réponse prolongée, le superviseur en sera alerté automatiquement.

## Suivre mes signalements

- Depuis l'accueil, consultez la liste des signalements de votre site pour chaque registre.
- La visibilité des signalements des autres agents dépend du paramétrage choisi par le superviseur :
  - **Confidentiel** : vous ne voyez que vos propres signalements.
  - **Choix de l'agent** : chaque agent choisit la visibilité de ses signalements.
  - **Public** : tous les signalements du site sont visibles.

## Modifier un signalement

Vous pouvez modifier un signalement tant qu'il n'a pas été traité (statut **Nouveau** ou **En cours**) :
1. Ouvrez le signalement depuis la liste.
2. Cliquez sur **« Modifier »**.
3. Mettez à jour les informations et validez.

## Confidentialité

- Vos signalements confidentiels ne sont visibles que par vous, les superviseurs et les membres du CSA/CHSCT.
- Aucune sanction ne peut être prise à votre encontre pour avoir signalé une situation de danger.

## Protection des données (RGPD)

Conformément à l'article 13 du RGPD :
- **Finalité** : recueil et suivi des signalements en santé-sécurité au travail.
- **Base légale** : art. 6.1.e RGPD — mission d'intérêt public.
- **Responsable** : DREETS Bourgogne-Franche-Comté.
- **Vos droits** : accès, rectification, effacement, opposition. Contactez le DPO.
- **Réclamation** : vous pouvez saisir la CNIL (www.cnil.fr).

## Signaler pour un collègue (RAMI uniquement)

Si vous êtes témoin d'une agression envers un collègue qui ne peut pas signaler lui-même :
1. Créez un signalement RAMI.
2. Cochez **« Signaler pour le compte d'un autre agent »**.
3. Saisissez le prénom et le nom du bénéficiaire dans le champ texte libre.
4. Décrivez les faits de manière objective.

## Navigateurs compatibles

L'application est optimisée pour **Mozilla Firefox** et **Google Chrome** dans leurs versions récentes. L'utilisation d'Internet Explorer est déconseillée.
