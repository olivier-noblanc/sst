---
Task ID: 1
Agent: Main
Task: Migration mPDF → FPDF (zéro dépendance, zéro I/O disque)

Work Log:
- Téléchargé FPDF v1.9 (fpdf.php, 52 KB, fichier unique)
- Généré les fichiers de police DejaVu Sans (Unicode TrueType, cp1252) pour le support des accents français
- Réécrit pages/report_print.php entièrement avec l'API FPDF (Cell, MultiCell, Rect, Line, Header, Footer)
- Créé la classe SSTPDF étendant FPDF avec en-tête/pied de page personnalisés
- Ajouté les fonctions utilitaires : utf8ToCp1252(), drawBadge(), drawField(), drawMultiField(), drawSectionTitle(), drawHR()
- Mis à jour public/index.php : autoloader Composer optionnel
- Vidé composer.json de sa dépendance mPDF
- Mis à jour README.md : stack technique, installation, structure
- Mis à jour DEPLOY.md : extensions réduites, plus de Composer, plus de vendor/
- Mis à jour CHANGELOG.md : v2.5.0 avec détails de la migration
- Mis à jour update_sst.ps1 : suppression étape Composer, vérification FPDF
- Bump version : 2.4.1 → 2.5.0 dans config.php
- Créé test_fpdf.php : script de test autonome pour valider le rendu PDF

Stage Summary:
- FPDF v1.9 remplace mPDF 8.2 : zéro dépendance, zéro Composer, zéro I/O disque
- Extensions PHP réduites : sqlite3, pdo_sqlite, mbstring (plus besoin de gd, xml, curl, zip)
- Le PDF est généré entièrement en mémoire (buffer PHP), pas de fichiers temporaires
- Les accents français sont supportés via conversion UTF-8 → cp1252 + police DejaVu Sans
- Prêt pour test sur le serveur IIS avec `php test_fpdf.php`
