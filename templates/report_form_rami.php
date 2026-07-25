<?php
/**
 * RAMI-specific form fields — included by report_form.php
 *
 * Variables (inherited from parent scope via require):
 *   $val        — Value resolver closure
 *   $formErrors — Array of field errors
 *   $isEdit     — Whether this is an edit form
 *   $report     — Existing report data for edit
 */
/** @var bool $isEdit */
/** @var \App\DTO\ReportData|null $report */
/** @var array<string, string> $formErrors */
/** @var callable(string, string=): string $val */
?>
            <div class="form-group form-grid__full">
                <label class="label--checkbox">
                    <input type="checkbox" name="pour_compte" id="pour_compte" value="1"
                           aria-controls="pour_compte_fields" aria-expanded="<?php echo ((bool) $val('pour_compte') || ($isEdit && !empty($report->pourCompteNom))) ? 'true' : 'false'; ?>"
                           <?php echo ((bool) $val('pour_compte') || ($isEdit && !empty($report->pourCompteNom))) ? 'checked' : ''; ?>>
                    Signaler pour le compte d'un autre agent
                </label>
            </div>

            <div id="pour_compte_fields" class="form-group form-grid__full pour-compte-fields">
                <div class="flex-row">
                    <div>
                        <label for="pour_compte_nom">Nom de l'agent</label>
                        <input type="text" id="pour_compte_nom" name="pour_compte_nom"
                               value="<?php echo e($val('pour_compte_nom')); ?>"
                               minlength="2" maxlength="100"
                               autocomplete="family-name"
                               <?php echo isset($formErrors['pour_compte_nom']) ? 'aria-describedby="err_pour_compte_nom" aria-invalid="true"' : ''; ?>>
                    </div>
                    <div>
                        <label for="pour_compte_prenom">Prénom de l'agent</label>
                        <input type="text" id="pour_compte_prenom" name="pour_compte_prenom"
                               value="<?php echo e($val('pour_compte_prenom')); ?>"
                               minlength="2" maxlength="100"
                               autocomplete="given-name">
                    </div>
                </div>
                <?php if (isset($formErrors['pour_compte_nom'])): ?>
                    <span class="form-error" id="err_pour_compte_nom"><?php echo e($formErrors['pour_compte_nom']); ?></span>
                <?php endif; ?>
            </div>

            <div class="form-group">
                <label for="nature_auteur">Nature de l'auteur</label>
                <select id="nature_auteur" name="nature_auteur">
                    <option value="">— Non renseigné —</option>
                    <option value="usager" <?php echo $val('nature_auteur') === 'usager' ? 'selected' : ''; ?>>Usager</option>
                    <option value="collegue" <?php echo $val('nature_auteur') === 'collegue' ? 'selected' : ''; ?>>Collègue</option>
                    <option value="hierarchie" <?php echo $val('nature_auteur') === 'hierarchie' ? 'selected' : ''; ?>>Hiérarchie</option>
                    <option value="tiers" <?php echo $val('nature_auteur') === 'tiers' ? 'selected' : ''; ?>>Tiers</option>
                </select>
                <span class="form-hint">Optionnel — utile pour les statistiques du <?php echo e(getRoleLabelShort('chsct')); ?>.</span>
            </div>

            <div class="form-group">
                <label for="type_acte">Type d'acte</label>
                <select id="type_acte" name="type_acte">
                    <option value="">— Non renseigné —</option>
                    <option value="verbal" <?php echo $val('type_acte') === 'verbal' ? 'selected' : ''; ?>>Verbal</option>
                    <option value="physique" <?php echo $val('type_acte') === 'physique' ? 'selected' : ''; ?>>Physique</option>
                    <option value="moral" <?php echo $val('type_acte') === 'moral' ? 'selected' : ''; ?>>Moral</option>
                    <option value="sexiste" <?php echo $val('type_acte') === 'sexiste' ? 'selected' : ''; ?>>Sexiste</option>
                    <option value="autre" <?php echo $val('type_acte') === 'autre' ? 'selected' : ''; ?>>Autre</option>
                </select>
                <span class="form-hint">Optionnel — utile pour les statistiques du <?php echo e(getRoleLabelShort('chsct')); ?>.</span>
            </div>
