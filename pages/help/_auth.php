<!-- 7. Connexion -->
<div id="auth" class="card card--spaced content-section">
    <h2>Connexion</h2>
    <p class="help-description">La connexion fonctionne différemment selon l'environnement :</p>
    <div class="help-profiles-grid">
        <div class="help-auth-card--prod">
            <h4>🖥️ Ordinateur du travail</h4>
            <p class="help-description">
                🔑 Connexion automatique avec votre <strong>compte Windows</strong>. Pas de mot de passe à taper. Votre compte est créé à la première connexion.
                <br><br>
                ⬆️ Si votre identifiant figure dans la liste des superviseurs, vous êtes automatiquement promu Superviseur.
            </p>
        </div>
        <div class="help-auth-card--dev">
            <h4>🧪 Mode test</h4>
            <p class="help-description">
                Un <strong>formulaire de connexion</strong> permet de tester les profils :
                <ul class="help-feature-list">
                    <li><code>admin.dev</code> → superviseur</li>
                    <li><code>agent.dev</code> → agent</li>
                    <li><code>chsct.dev</code> → <?php echo e(getRoleLabelShort('chsct')); ?></li>
                </ul>
            </p>
        </div>
    </div>
</div>
