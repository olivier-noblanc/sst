<?php
/**
 * Settings Tab: Paramètres de l'application
 *
 * Variables attendues: $csrfToken
 */
?>
<form method="POST" action="<?php echo url('settings'); ?>">
    <input type="hidden" name="csrf_token" value="<?php echo e($csrfToken); ?>">
    <input type="hidden" name="tab" value="app">

    <div class="card">
        <h3 class="card__title">&#x2699;&#xFE0F; Paramètres de l'application</h3>
        <p class="text-muted text-small mb-5">Configurez les paramètres généraux de l'application.</p>

        <div class="form-group">
            <label>Version de l'application</label>
            <div class="form-control-readonly"><?php echo e(getAppVersion()); ?></div>
            <small class="text-muted block mt-1" id="hint_app_version">
                La version est lue automatiquement depuis le fichier CHANGELOG.md. 
                Pour la modifier, mettez à jour la première entrée du changelog.
            </small>
        </div>

        <div class="form-group">
            <label for="app_nom_organisation">Nom de l'organisation</label>
            <input type="text" id="app_nom_organisation" name="app_nom_organisation" class="form-control"
                   value="<?php echo e(getConfig('app_nom_organisation', 'DREETS BFC')); ?>"
                   placeholder="DREETS BFC">
        </div>

        <div class="form-group">
            <label for="app_nom_complet">Nom complet</label>
            <input type="text" id="app_nom_complet" name="app_nom_complet" class="form-control"
                   value="<?php echo e(getConfig('app_nom_complet', 'DREETS Bourgogne-Franche-Comté')); ?>"
                   placeholder="DREETS Bourgogne-Franche-Comté">
        </div>

        <div class="form-group">
            <label for="app_label_unite">Libellé des unités</label>
            <input type="text" id="app_label_unite" name="app_label_unite" class="form-control"
                   value="<?php echo e(getConfig('app_label_unite', 'UR')); ?>"
                   placeholder="UR">
            <small class="text-muted block mt-1">
                Exemple : UR, UD, Direction... Ce libellé est utilisé partout dans l'application.
            </small>
        </div>

        <div class="form-group">
            <label for="app_superviseur_usernames">Logins Windows des superviseurs (liste explicite)</label>
            <input type="text" id="app_superviseur_usernames" name="app_superviseur_usernames" class="form-control"
                   value="<?php echo e(getConfig('app_superviseur_usernames', '')); ?>"
                   placeholder="jean.martin, sophie.dupont">
            <small class="text-muted block mt-1">
                Séparés par des virgules. Ces utilisateurs seront automatiquement promus <strong>Superviseur</strong>
                immédiatement (dès la prochaine page consultée). Utile pour désigner les premiers
                superviseurs sans avoir à passer par la base de données.
            </small>
        </div>

        <div class="separator">
            <h4 class="card__subtitle">&#x1F4DE; Hotline d'aide</h4>
            <p class="text-muted text-small mb-3">Affiche un numéro de téléphone d'aide en haut de la page d'aide, visible par tous les utilisateurs. Laissez vide pour afficher le message par défaut (« Contactez votre administrateur au poste interne »).</p>
            <div class="form-group">
                <label for="app_hotline_number">Numéro de hotline</label>
                <input type="text" id="app_hotline_number" name="app_hotline_number" class="form-control"
                       value="<?php echo e(getConfig('app_hotline_number', '')); ?>"
                       placeholder="01 23 45 67 89 ou poste 1234">
                <small class="text-muted block mt-1">
                    Ce numéro sera affiché en gros dans la page Aide. Laissez vide pour désactiver la hotline
                    et afficher le message générique « Contactez votre administrateur au poste interne ».
                </small>
            </div>
        </div>

        <div class="separator">
            <h4 class="card__subtitle">&#x1F512; Délégué à la Protection des Données (DPO)</h4>
            <p class="text-muted text-small mb-3">Les coordonnées du DPO sont affichées dans la mention RGPD du <a href="<?php echo url('preamble'); ?>">Préambule</a>, conformément à l'article 13 du RGPD.</p>
            <div class="form-group">
                <label for="app_dpo_contact">Contact DPO</label>
                <input type="text" id="app_dpo_contact" name="app_dpo_contact" class="form-control"
                       value="<?php echo e(getConfig('app_dpo_contact', '')); ?>"
                       placeholder="dpo@dreets-bfc.gouv.fr — M. Jean Martin, Délégué à la Protection des Données">
                <small class="text-muted block mt-1">
                    Adresse e-mail et/ou nom du DPO. Ce texte apparaît dans la mention d'information RGPD
                    du Préambule, à la ligne « Contact DPO ». Laissez vide pour afficher le message par défaut.
                </small>
            </div>
        </div>

        <div class="separator">
            <h4 class="card__subtitle">&#x1F4E7; Administrateur technique</h4>
            <p class="text-muted text-small mb-3">Les erreurs critiques (Fatal, Parse, etc.) seront automatiquement envoyées par e-mail à cette adresse pour un diagnostic rapide. Laissez vide pour désactiver.</p>
            <div class="form-group">
                <label for="app_admin_email">E-mail administrateur technique</label>
                <input type="email" id="app_admin_email" name="app_admin_email" class="form-control"
                       value="<?php echo e(getConfig('app_admin_email', '')); ?>"
                       placeholder="admin.tech@dreets-bfc.gouv.fr">
                <small class="text-muted block mt-1">
                    Une même erreur ne déclenche qu'un seul e-mail toutes les 5 minutes pour éviter le spam.
                    Consultez le <a href="<?php echo url('logs'); ?>">Journal d'erreurs</a> pour voir toutes les entrées.
                </small>
            </div>
        </div>

        <div class="separator">
            <h4 class="card__subtitle">&#x1F41B; Affichage des erreurs PHP</h4>
            <p class="text-muted text-small mb-3">En production, les erreurs PHP sont masquées par défaut pour des raisons de sécurité. Activez cette option pour afficher toutes les erreurs à l'écran, utile pour le diagnostic.</p>
            <div class="form-group">
                <label class="toggle-switch-label">
                    <input type="checkbox" name="app_display_errors" id="app_display_errors" value="1"
                           class="toggle-switch__input"
                           <?php echo getConfig('app_display_errors', '') === '1' ? 'checked' : ''; ?>>
                    <span class="toggle-switch" aria-hidden="true"></span>
                    <span>Afficher les erreurs PHP à l'écran (même en production)</span>
                </label>
                <small class="text-muted block mt-1" id="hint_display_errors">
                    <strong>&#x26A0;&#xFE0F; Attention :</strong> cette option affiche les erreurs PHP brutes (warnings, notices, fatal errors) directement dans les pages.
                    Utile pour le débogage, mais désactivez-la en utilisation normale — les erreurs peuvent contenir des informations sensibles
                    (chemins de fichiers, requêtes SQL, variables internes). Les erreurs restent toujours enregistrées dans le
                    <a href="<?php echo url('logs'); ?>">journal</a> et envoyées par e-mail à l'administrateur technique, que cette option soit activée ou non.
                </small>
            </div>
        </div>

        <div class="separator">
            <h4 class="card__subtitle">&#x1F514; Registres actifs</h4>
            <p class="text-muted text-small mb-3">Activez ou désactivez les registres RAMI et DGI. Le registre RSST est toujours actif. Les registres désactivés n'apparaissent plus dans le menu, l'accueil, l'aide, les formulaires, les statistiques et les exports.</p>
            <div class="form-group">
                <label class="toggle-switch-label">
                    <input type="checkbox" name="app_registry_rami_enabled" id="app_registry_rami_enabled" value="1"
                           class="toggle-switch__input"
                           <?php echo getConfig('app_registry_rami_enabled', REGISTRY_RAMI_ENABLED_DEFAULT ? '1' : '0') === '1' ? 'checked' : ''; ?>>
                    <span class="toggle-switch" aria-hidden="true"></span>
                    <span>Activer le registre RAMI (Agressions, Menaces, Incivilités)</span>
                </label>
            </div>
            <div class="form-group">
                <label class="toggle-switch-label">
                    <input type="checkbox" name="app_registry_dgi_enabled" id="app_registry_dgi_enabled" value="1"
                           class="toggle-switch__input"
                           <?php echo getConfig('app_registry_dgi_enabled', REGISTRY_DGI_ENABLED_DEFAULT ? '1' : '0') === '1' ? 'checked' : ''; ?>>
                    <span class="toggle-switch" aria-hidden="true"></span>
                    <span>Activer le registre DGI (Danger Grave et Imminent)</span>
                </label>
            </div>
            <div class="form-group" id="dgi-notif-csa-group">
                <label class="toggle-switch-label">
                    <input type="checkbox" name="app_dgi_notify_csa" id="app_dgi_notify_csa" value="1"
                           class="toggle-switch__input"
                           <?php echo getConfig('app_dgi_notify_csa', '1') === '1' ? 'checked' : ''; ?>>
                    <span class="toggle-switch" aria-hidden="true"></span>
                    <span>Notifier le <?php echo e(getConfig('app_role_label_chsct', 'Membre FS/CSA')); ?> lors d'un signalement DGI</span>
                </label>
                <small class="text-muted block mt-1">
                    Conformément à l'article L4131-2 du Code du travail, le <?php echo e(getConfig('app_role_label_chsct', 'Membre FS/CSA')); ?>
                    doit être informé de tout signalement relatif à un danger grave et imminent.
                    Si activé, les membres <?php echo e(getConfig('app_role_label_chsct', 'Membre FS/CSA')); ?> recevront un e-mail de notification
                    pour chaque nouveau signalement DGI.
                </small>
            </div>
        </div>

        <div class="separator">
            <h4 class="card__subtitle">&#x1F465; Noms des rôles</h4>
            <p class="text-muted text-small mb-3">Personnalisez le nom affiché pour chaque rôle dans toute l'application (badge, aide, formulaires...). Par exemple : « Membre FS/CSA » au lieu de « Membre CSA/CHSCT ».</p>
            <div class="form-group">
                <label for="app_role_label_agent">Nom du rôle Agent</label>
                <input type="text" id="app_role_label_agent" name="app_role_label_agent" class="form-control"
                       value="<?php echo e(getConfig('app_role_label_agent', 'Agent')); ?>"
                       placeholder="Agent">
            </div>
            <div class="form-group">
                <label for="app_role_label_superviseur">Nom du rôle Superviseur</label>
                <input type="text" id="app_role_label_superviseur" name="app_role_label_superviseur" class="form-control"
                       value="<?php echo e(getConfig('app_role_label_superviseur', 'Superviseur')); ?>"
                       placeholder="Superviseur">
            </div>
            <div class="form-group">
                <label for="app_role_label_chsct">Nom du rôle FS/CSA</label>
                <input type="text" id="app_role_label_chsct" name="app_role_label_chsct" class="form-control"
                       value="<?php echo e(getConfig('app_role_label_chsct', 'Membre FS/CSA')); ?>"
                       placeholder="Membre FS/CSA">
            </div>
        </div>

        <div class="separator">
            <h4 class="card__subtitle">&#x1F512; Visibilité des signalements</h4>
            <p class="text-muted text-small mb-3">Détermine quels signalements les agents peuvent consulter dans chaque registre. Les superviseurs et membres du <?php echo e(getRoleLabelShort('chsct')); ?> voient toujours tous les signalements.</p>

            <?php
            $registries = [
                'rsst' => ['label' => 'RSST — Registre de Santé et Sécurité au Travail', 'default' => 'public', 'legal' => 'Décret n° 82-453 art. 3-2 : registre consultable par tout agent. La transparence est recommandée.'],
                'rami' => ['label' => 'RAMI — Registre des Agressions, Menaces et Incivilités', 'default' => '', 'legal' => 'Données sensibles (art. 9 RGPD) : le mode confidentiel ou choix de l\'agent est recommandé.'],
                'dgi'  => ['label' => 'DGI — Danger Grave et Imminent', 'default' => '', 'legal' => 'Articles L4131-1 et D4132-1 du Code du travail : le formalisme du registre spécial peut justifier un mode restrictif.'],
            ];
foreach ($registries as $type => $info):
    $configKey = 'app_report_visibility_' . $type;
    $currentValue = getConfig($configKey, '');
    // Fallback to global if per-registry key is empty
    if ($currentValue === '') {
        $currentValue = getConfig('app_report_visibility', 'agent_choice');
    }
    $currentValue = normalizeVisibilityValue($currentValue);
    ?>
            <fieldset class="form-group visibility-radios" id="visibility-radios-<?php echo e($type); ?>">
                <legend class="visibility-legend"><?php echo e($info['label']); ?></legend>
                <div class="visibility-radios">
                    <label class="visibility-radio-label">
                        <input type="radio" name="<?php echo e($configKey); ?>" value="confidential"
                               <?php echo $currentValue === 'confidential' ? 'checked' : ''; ?>>
                        <div>
                            <strong>Confidentiel</strong> <span class="text-muted text-small">(le plus restrictif)</span>
                            <div class="text-muted text-small mt-2px">L'agent ne voit que ses propres signalements.</div>
                        </div>
                    </label>
                    <label class="visibility-radio-label">
                        <input type="radio" name="<?php echo e($configKey); ?>" value="agent_choice"
                               <?php echo $currentValue === 'agent_choice' ? 'checked' : ''; ?>>
                        <div>
                            <strong>Choix de l'agent</strong> <span class="text-muted text-small">(confidentiel par défaut)</span>
                            <div class="text-muted text-small mt-2px">L'agent choisit la visibilité de chaque signalement. Par défaut confidentiel.</div>
                        </div>
                    </label>
                    <label class="visibility-radio-label">
                        <input type="radio" name="<?php echo e($configKey); ?>" value="public"
                               <?php echo $currentValue === 'public' ? 'checked' : ''; ?>>
                        <div>
                            <strong>Visibilité publique</strong>
                            <div class="text-muted text-small mt-2px">Tous les signalements du site sont visibles par tous les agents du site.</div>
                        </div>
                    </label>
                </div>
                <?php if ($type === 'rsst' && $currentValue !== 'public'): ?>
                <div class="info-panel agent-visibility-warning info-panel--warning">
                    &#x26A0;&#xFE0F; <strong>Avertissement réglementaire :</strong> Le décret n° 82-453 art. 3-2 prévoit que le RSST est tenu à la disposition de l'ensemble des agents. Un mode restrictif peut ne pas être conforme à cette obligation de transparence.
                </div>
                <?php endif; ?>
                <div class="info-panel info-panel--info">
                    &#x2139;&#xFE0F; <?php echo e($info['legal']); ?>
                </div>
            </fieldset>
            <?php endforeach; ?>

            <div class="info-panel agent-visibility-warning">
                &#x2139;&#xFE0F; <strong>Information :</strong> Quel que soit le mode, les superviseurs et les membres du <?php echo e(getRoleLabelShort('chsct')); ?> voient tous les signalements, y compris les confidentiels.
            </div>
        </div>
    </div>

    <div class="form-actions">
        <button type="submit" class="btn btn--success">Enregistrer les modifications</button>
        <a href="<?php echo url('settings', ['tab' => 'app']); ?>" class="btn btn--outline">Annuler</a>
    </div>
</form>
