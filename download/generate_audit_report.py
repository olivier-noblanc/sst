#!/usr/bin/env python3
"""
Audit de Conformite — Application SST DREETS BFC
Rapport PDF genere via ReportLab
"""
import os, sys
from reportlab.lib.pagesizes import A4
from reportlab.lib.units import inch, cm, mm
from reportlab.lib import colors
from reportlab.lib.styles import ParagraphStyle, getSampleStyleSheet
from reportlab.lib.enums import TA_LEFT, TA_CENTER, TA_JUSTIFY, TA_RIGHT
from reportlab.platypus import (
    SimpleDocTemplate, Paragraph, Spacer, Table, TableStyle,
    PageBreak, KeepTogether, HRFlowable
)
from reportlab.pdfbase import pdfmetrics
from reportlab.pdfbase.ttfonts import TTFont
from reportlab.pdfbase.pdfmetrics import registerFontFamily

# ── Font Registration ──
pdfmetrics.registerFont(TTFont('DejaVuSans', '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf'))
pdfmetrics.registerFont(TTFont('DejaVuSansBold', '/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf'))
pdfmetrics.registerFont(TTFont('LiberationSerif', '/usr/share/fonts/truetype/liberation/LiberationSerif-Regular.ttf'))
pdfmetrics.registerFont(TTFont('LiberationSans', '/usr/share/fonts/truetype/liberation/LiberationSans-Regular.ttf'))
registerFontFamily('DejaVuSans', normal='DejaVuSans', bold='DejaVuSansBold')
registerFontFamily('LiberationSerif', normal='LiberationSerif', bold='LiberationSerif')
registerFontFamily('LiberationSans', normal='LiberationSans', bold='LiberationSans')

# ── Palette ──
ACCENT       = colors.HexColor('#562bd5')
TEXT_PRIMARY  = colors.HexColor('#282624')
TEXT_MUTED    = colors.HexColor('#8c8780')
BG_SURFACE   = colors.HexColor('#e8e5e0')
BG_PAGE      = colors.HexColor('#f2f1ef')
TABLE_HEADER_COLOR = ACCENT
TABLE_HEADER_TEXT  = colors.white
TABLE_ROW_EVEN     = colors.white
TABLE_ROW_ODD      = BG_SURFACE

# Semantic colors for audit
COLOR_CONFORM = colors.HexColor('#16a34a')
COLOR_MINOR   = colors.HexColor('#d97706')
COLOR_MAJOR   = colors.HexColor('#dc2626')
COLOR_INFO    = colors.HexColor('#2563eb')

# ── Page setup ──
PAGE_W, PAGE_H = A4
LEFT_MARGIN = 1.0 * inch
RIGHT_MARGIN = 1.0 * inch
TOP_MARGIN = 0.8 * inch
BOTTOM_MARGIN = 0.8 * inch
CONTENT_W = PAGE_W - LEFT_MARGIN - RIGHT_MARGIN

# ── Styles ──
styles = getSampleStyleSheet()

title_style = ParagraphStyle(
    'DocTitle', fontName='LiberationSerif', fontSize=28, leading=36,
    alignment=TA_LEFT, textColor=ACCENT, spaceAfter=6
)
subtitle_style = ParagraphStyle(
    'DocSubtitle', fontName='LiberationSerif', fontSize=14, leading=20,
    alignment=TA_LEFT, textColor=TEXT_MUTED, spaceAfter=18
)
h1_style = ParagraphStyle(
    'H1', fontName='LiberationSerif', fontSize=18, leading=24,
    alignment=TA_LEFT, textColor=ACCENT, spaceBefore=18, spaceAfter=10
)
h2_style = ParagraphStyle(
    'H2', fontName='LiberationSerif', fontSize=14, leading=19,
    alignment=TA_LEFT, textColor=TEXT_PRIMARY, spaceBefore=14, spaceAfter=8
)
h3_style = ParagraphStyle(
    'H3', fontName='LiberationSerif', fontSize=12, leading=16,
    alignment=TA_LEFT, textColor=TEXT_PRIMARY, spaceBefore=10, spaceAfter=6
)
body_style = ParagraphStyle(
    'Body', fontName='LiberationSerif', fontSize=10.5, leading=16,
    alignment=TA_JUSTIFY, textColor=TEXT_PRIMARY, spaceAfter=6
)
body_left_style = ParagraphStyle(
    'BodyLeft', fontName='LiberationSerif', fontSize=10.5, leading=16,
    alignment=TA_LEFT, textColor=TEXT_PRIMARY, spaceAfter=6
)
muted_style = ParagraphStyle(
    'Muted', fontName='LiberationSerif', fontSize=9, leading=13,
    alignment=TA_LEFT, textColor=TEXT_MUTED, spaceAfter=4
)
code_style = ParagraphStyle(
    'Code', fontName='DejaVuSans', fontSize=9, leading=13,
    alignment=TA_LEFT, textColor=colors.HexColor('#555555'),
    leftIndent=12, spaceAfter=4
)
th_style = ParagraphStyle(
    'TH', fontName='LiberationSerif', fontSize=9.5, leading=13,
    alignment=TA_CENTER, textColor=TABLE_HEADER_TEXT
)
td_style = ParagraphStyle(
    'TD', fontName='LiberationSerif', fontSize=9.5, leading=13,
    alignment=TA_LEFT, textColor=TEXT_PRIMARY
)
td_center_style = ParagraphStyle(
    'TDCenter', fontName='LiberationSerif', fontSize=9.5, leading=13,
    alignment=TA_CENTER, textColor=TEXT_PRIMARY
)

# Badge styles
conform_style = ParagraphStyle(
    'Conform', fontName='LiberationSerif', fontSize=9, leading=12,
    alignment=TA_CENTER, textColor=COLOR_CONFORM
)
minor_style = ParagraphStyle(
    'Minor', fontName='LiberationSerif', fontSize=9, leading=12,
    alignment=TA_CENTER, textColor=COLOR_MINOR
)
major_style = ParagraphStyle(
    'Major', fontName='LiberationSerif', fontSize=9, leading=12,
    alignment=TA_CENTER, textColor=COLOR_MAJOR
)

def make_table(data, col_widths, has_header=True):
    """Create a styled table."""
    t = Table(data, colWidths=col_widths, hAlign='CENTER')
    style_cmds = [
        ('VALIGN', (0, 0), (-1, -1), 'MIDDLE'),
        ('LEFTPADDING', (0, 0), (-1, -1), 6),
        ('RIGHTPADDING', (0, 0), (-1, -1), 6),
        ('TOPPADDING', (0, 0), (-1, -1), 5),
        ('BOTTOMPADDING', (0, 0), (-1, -1), 5),
        ('GRID', (0, 0), (-1, -1), 0.5, TEXT_MUTED),
    ]
    if has_header:
        style_cmds.append(('BACKGROUND', (0, 0), (-1, 0), TABLE_HEADER_COLOR))
        style_cmds.append(('TEXTCOLOR', (0, 0), (-1, 0), TABLE_HEADER_TEXT))
        for i in range(1, len(data)):
            if i % 2 == 0:
                style_cmds.append(('BACKGROUND', (0, i), (-1, i), TABLE_ROW_ODD))
            else:
                style_cmds.append(('BACKGROUND', (0, i), (-1, i), TABLE_ROW_EVEN))
    t.setStyle(TableStyle(style_cmds))
    return t


# ── Build Document ──
output_path = '/home/z/my-project/download/audit-conformite-sst.pdf'

doc = SimpleDocTemplate(
    output_path,
    pagesize=A4,
    leftMargin=LEFT_MARGIN,
    rightMargin=RIGHT_MARGIN,
    topMargin=TOP_MARGIN,
    bottomMargin=BOTTOM_MARGIN,
    title='Audit de Conformite - Application SST DREETS BFC',
    author='Z.ai',
    creator='Z.ai',
)

story = []

# ═══════════════════════════════════════════
# COVER / TITLE
# ═══════════════════════════════════════════
story.append(Spacer(1, 80))
story.append(HRFlowable(width="100%", thickness=2, color=ACCENT, spaceAfter=16))
story.append(Paragraph('<b>Audit de Conformite</b>', title_style))
story.append(Paragraph('Application SST DREETS BFC — Version 1.1.0', subtitle_style))
story.append(Spacer(1, 10))
story.append(Paragraph('Confrontation du code implante (v1.1.0) avec la documentation PDF d\'origine (Manuels Utilisateur et Superviseur, revision 1.0 — 28/02/2018) et la documentation interne (help.php, SPEC.md, CHANGELOG.md).', body_style))
story.append(Spacer(1, 8))
story.append(Paragraph('Date de l\'audit : 10 juin 2026', muted_style))
story.append(Paragraph('Auditeur : Z.ai (analyse automatisee)', muted_style))
story.append(HRFlowable(width="100%", thickness=1, color=TEXT_MUTED, spaceBefore=20, spaceAfter=20))

# ═══════════════════════════════════════════
# SYNTHESE EXECUTIVE
# ═══════════════════════════════════════════
story.append(Paragraph('<b>1. Synthese executive</b>', h1_style))

story.append(Paragraph(
    'L\'audit de conformite compare les regles metier documentees dans les manuels PDF officiels '
    '(DIRECCTE Auvergne Rhone-Alpes, revision 1.0) avec l\'implementation reelle du code PHP de '
    'l\'application SST en version 1.1.0. Les corrections de securite et de confidentialite de la '
    'v1.1.0 (CHANGELOG.md) ont ete prises en compte comme etat actuel du code. La documentation '
    'interne help.php est utilisee comme reference complementaire car elle definit les permissions '
    'de chaque role de maniere plus detaillee que les PDF.',
    body_style))

story.append(Spacer(1, 8))

# Summary table
summary_data = [
    [Paragraph('<b>Categorie</b>', th_style),
     Paragraph('<b>Conforme</b>', th_style),
     Paragraph('<b>Non-conforme mineur</b>', th_style),
     Paragraph('<b>Non-conforme majeur</b>', th_style)],
    [Paragraph('Confidentialite / acces aux signalements', td_style),
     Paragraph('4', conform_style),
     Paragraph('1', minor_style),
     Paragraph('1', major_style)],
    [Paragraph('Permissions par role (RBAC)', td_style),
     Paragraph('8', conform_style),
     Paragraph('1', minor_style),
     Paragraph('0', major_style)],
    [Paragraph('Fonctionnalites metier vs DB', td_style),
     Paragraph('2', conform_style),
     Paragraph('0', minor_style),
     Paragraph('0', major_style)],
    [Paragraph('Code mort / stubs / TODOs', td_style),
     Paragraph('3', conform_style),
     Paragraph('0', minor_style),
     Paragraph('0', major_style)],
    [Paragraph('Documentation interne', td_style),
     Paragraph('2', conform_style),
     Paragraph('2', minor_style),
     Paragraph('0', major_style)],
    [Paragraph('<b>Total</b>', td_style),
     Paragraph('<b>19</b>', conform_style),
     Paragraph('<b>4</b>', minor_style),
     Paragraph('<b>1</b>', major_style)],
]
story.append(make_table(summary_data, [CONTENT_W*0.40, CONTENT_W*0.20, CONTENT_W*0.20, CONTENT_W*0.20]))
story.append(Spacer(1, 12))

story.append(Paragraph(
    'Le code de la v1.1.0 est globalement <b>conforme</b> aux regles metier documentees. '
    'La seule non-conformite majeure concerne le bouton « Imprimer la fiche » visible par tous les roles '
    'dans report_card.php, alors que help.php reserve cette fonction au Superviseur uniquement. '
    'Les non-conformites mineures portent sur le role Manager (acces en consultation auquel les PDF ne '
    'font pas reference) et la documentation interne SPEC.md qui n\'a pas ete mise a jour pour la v1.1.0.',
    body_style))

# ═══════════════════════════════════════════
# METHODOLOGIE
# ═══════════════════════════════════════════
story.append(Paragraph('<b>2. Methodologie</b>', h1_style))

story.append(Paragraph(
    'L\'audit suit une methodologie en trois temps. Premierement, extraction des regles metier depuis '
    'les documents de reference : les deux manuels PDF (Manuel Utilisateur et Manuel Superviseur, tous '
    'les deux en revision 1.0 du 28/02/2018), la page help.php de l\'application qui documente les '
    '4 profils et leurs droits, et le CHANGELOG.md qui enregistre les modifications de la v1.1.0. '
    'Deuxiemement, analyse statique du code source PHP : lecture exhaustive des fichiers de requetes '
    'SQL, des pages de vue, des handlers de formulaire, des templates et du routeur. Troisiemement, '
    'confrontation systematique entre les regles documentees et l\'implementation reelle, avec '
    'qualification de chaque ecart en conforme, non-conformite mineure ou non-conformite majeure.',
    body_style))

story.append(Spacer(1, 6))
story.append(Paragraph(
    'Les deux manuels PDF sont quasiment identiques (meme structure, memes captures d\'ecran). '
    'La regle cle est en page 8 : « Les profils Superviseurs et CHSCT auront acces aux signalements '
    'de l\'ensemble des sites. » Cela implique que les agents n\'ont acces qu\'a leur site, et que '
    'seuls Superviseur et CHSCT voient tout. Le role Manager n\'est pas mentionne dans les PDF.',
    body_style))

# ═══════════════════════════════════════════
# REGLES DE CONFIDENTIALITE
# ═══════════════════════════════════════════
story.append(Paragraph('<b>3. Regles de confidentialite des signalements</b>', h1_style))

story.append(Paragraph('<b>3.1 Source PDF (Manuels DIRECCTE)</b>', h2_style))
story.append(Paragraph(
    'La documentation PDF est la source de reference primaire. Elle etablit deux regles fondamentales. '
    'Premierement, la page 8 des deux manuels indique : « Vous pouvez visualiser la liste des '
    'signalements pour chaque registre de votre site. Remarque : Les profils Superviseurs et CHSCT '
    'auront acces aux signalements de l\'ensemble des sites. » Cette phrase definit implicitement que '
    'l\'Agent voit uniquement les signalements de son site, et que seuls les Superviseurs et CHSCT '
    'ont une visibilite multi-sites. Deuxiemement, la page 10 precise : « Vous ne pourrez modifier '
    'que les signalements dont vous etes l\'auteur (Et ceci quelque soit le registre). » La modification '
    'est donc reservee au declarant, quel que soit son role.',
    body_style))

story.append(Paragraph(
    'Le role « Manager » n\'apparait nulle part dans les manuels PDF. Seuls trois roles y sont '
    'mentionnes : Agent, Superviseur et CHSCT. Le Manager est une addition de l\'implementation qui '
    'n\'a pas de correspondance directe dans la documentation d\'origine.',
    body_style))

story.append(Paragraph('<b>3.2 Source help.php (Documentation interne)</b>', h2_style))
story.append(Paragraph(
    'La page help.php de l\'application definit 4 profils avec des droits detailles. Elle complete '
    'la documentation PDF en precisant les permissions de chaque role, y compris le Manager qui y est '
    'decrit comme un « profil de consultation elargie » qui voit tous les sites, accede a la Synthese, '
    'aux Statistiques et a l\'Export, mais ne peut <b>pas</b> repondre aux signalements ni gerer les '
    'utilisateurs. Le CHSCT a les memes droits de consultation que le Manager. La section '
    '« Confidentialite des signalements » dans help.php precise : « Le signalement est confidentiel. '
    'Seuls le declarant, les superviseurs et les membres du CHSCT peuvent y acceder selon leur role. '
    'Les managers ont egalement acces en consultation. »',
    body_style))

story.append(Paragraph('<b>3.3 Conformite du code (v1.1.0)</b>', h2_style))

# Confidentiality checks table
conf_data = [
    [Paragraph('<b>Regle</b>', th_style),
     Paragraph('<b>Source</b>', th_style),
     Paragraph('<b>Attendu</b>', th_style),
     Paragraph('<b>Code (v1.1.0)</b>', th_style),
     Paragraph('<b>Statut</b>', th_style)],
    [Paragraph('Agent voit son site uniquement', td_style),
     Paragraph('PDF p.8 + help.php', td_style),
     Paragraph('Visibilite = site (defaut)', td_style),
     Paragraph('getAgentVisibility() retourne "site" par defaut', td_style),
     Paragraph('Conforme', conform_style)],
    [Paragraph('Superviseur voit tous les sites', td_style),
     Paragraph('PDF p.8', td_style),
     Paragraph('canSeeAllSites() = true', td_style),
     Paragraph('canSeeAllSites() retourne true pour superviseur', td_style),
     Paragraph('Conforme', conform_style)],
    [Paragraph('CHSCT voit tous les sites', td_style),
     Paragraph('PDF p.8', td_style),
     Paragraph('canSeeAllSites() = true', td_style),
     Paragraph('canSeeAllSites() retourne true pour chsct', td_style),
     Paragraph('Conforme', conform_style)],
    [Paragraph('Manager voit tous les sites', td_style),
     Paragraph('help.php', td_style),
     Paragraph('Consultation elargie', td_style),
     Paragraph('canSeeAllSites() retourne true pour manager', td_style),
     Paragraph('Conforme', conform_style)],
    [Paragraph('Option "all" retiree', td_style),
     Paragraph('help.php + securite', td_style),
     Paragraph('Plus d\'option "tous" dans parametres', td_style),
     Paragraph('Seuls "site" et "own" dans settings.php', td_style),
     Paragraph('Conforme', conform_style)],
    [Paragraph('canAccessReport() enforce', td_style),
     Paragraph('help.php', td_style),
     Paragraph('Verification dans view + print', td_style),
     Paragraph('Les deux pages appellent canAccessReport()', td_style),
     Paragraph('Conforme', conform_style)],
]
story.append(make_table(conf_data, [CONTENT_W*0.18, CONTENT_W*0.14, CONTENT_W*0.20, CONTENT_W*0.30, CONTENT_W*0.18]))
story.append(Spacer(1, 8))

# Non-conformity: Print button
story.append(Paragraph('<b>3.4 Non-conformite majeure : bouton « Imprimer la fiche »</b>', h2_style))
story.append(Paragraph(
    'Le tableau des droits dans help.php (ligne 207-210) indique clairement que l\'impression est '
    'reservee au Superviseur uniquement (croix rouge pour Agent, Manager et CHSCT). Or, dans le '
    'template report_card.php a la ligne 157, le bouton « Imprimer la fiche » est affiche sans '
    'aucune restriction de role. Il est visible par tout utilisateur ayant acces a la vue d\'un '
    'signalement, y compris les agents, managers et membres CHSCT. La page report_print.php elle-meme '
    'verifie l\'acces via canAccessReport(), ce qui est correct pour la confidentialite, mais ne '
    'limite pas l\'impression au seul role Superviseur. Il s\'agit d\'une <b>non-conformite majeure</b> '
    'car elle contredit explicitement le tableau des droits documente dans help.php. Pour la corriger, '
    'il faudrait conditionner l\'affichage du bouton a un role Superviseur dans report_card.php, '
    'et ajouter une verification de role dans report_print.php.',
    body_style))

# Non-conformity: Manager not in PDF
story.append(Paragraph('<b>3.5 Non-conformite mineure : role Manager absent des PDF</b>', h2_style))
story.append(Paragraph(
    'Le role Manager n\'existe pas dans la documentation PDF d\'origine. Il a ete ajoute dans '
    'l\'implementation et documente uniquement dans help.php. Ce n\'est pas une erreur de code '
    '(le Manager a bien les permissions decrites dans help.php), mais c\'est un ecart par rapport '
    'aux manuels officiels DIRECCTE. Si les PDF sont la reference contractuelle, le role Manager '
    'devrait etre officialise dans une mise a jour de la documentation utilisateur, ou a defaut, '
    'sa presence devrait etre signalee comme extension par rapport au cahier des charges initial. '
    'En l\'etat, un auditeur externe referant aux seuls PDF considererait que ce role est hors perimetre.',
    body_style))

# ═══════════════════════════════════════════
# PERMISSIONS PAR ROLE (RBAC)
# ═══════════════════════════════════════════
story.append(Paragraph('<b>4. Permissions par role (RBAC)</b>', h1_style))

story.append(Paragraph(
    'Cette section confronte systematiquement chaque permission documentee dans help.php avec '
    'l\'implementation reelle du code PHP. Les verifications portent sur les fichiers de page '
    '(requireRole), les handlers, les templates et les fonctions d\'acces.',
    body_style))

# RBAC table
rbac_data = [
    [Paragraph('<b>Fonctionnalite</b>', th_style),
     Paragraph('<b>Agent</b>', th_style),
     Paragraph('<b>Superviseur</b>', th_style),
     Paragraph('<b>Manager</b>', th_style),
     Paragraph('<b>CHSCT</b>', th_style),
     Paragraph('<b>Code conforme ?</b>', th_style)],
    [Paragraph('Creer un signalement', td_style),
     Paragraph('Oui', td_center_style), Paragraph('Oui', td_center_style),
     Paragraph('Oui', td_center_style), Paragraph('Oui', td_center_style),
     Paragraph('Conforme', conform_style)],
    [Paragraph('Voir ses signalements', td_style),
     Paragraph('Oui', td_center_style), Paragraph('Oui', td_center_style),
     Paragraph('Oui', td_center_style), Paragraph('Oui', td_center_style),
     Paragraph('Conforme', conform_style)],
    [Paragraph('Modifier (non traite)', td_style),
     Paragraph('Oui', td_center_style), Paragraph('Oui', td_center_style),
     Paragraph('Oui', td_center_style), Paragraph('Oui', td_center_style),
     Paragraph('Conforme', conform_style)],
    [Paragraph('Voir tous les sites', td_style),
     Paragraph('Non', td_center_style), Paragraph('Oui', td_center_style),
     Paragraph('Oui', td_center_style), Paragraph('Oui', td_center_style),
     Paragraph('Conforme', conform_style)],
    [Paragraph('Repondre', td_style),
     Paragraph('Non', td_center_style), Paragraph('Oui', td_center_style),
     Paragraph('Non', td_center_style), Paragraph('Non', td_center_style),
     Paragraph('Conforme', conform_style)],
    [Paragraph('Abandonner', td_style),
     Paragraph('Non', td_center_style), Paragraph('Oui', td_center_style),
     Paragraph('Non', td_center_style), Paragraph('Non', td_center_style),
     Paragraph('Conforme', conform_style)],
    [Paragraph('Imprimer', td_style),
     Paragraph('Non', td_center_style), Paragraph('Oui', td_center_style),
     Paragraph('Non', td_center_style), Paragraph('Non', td_center_style),
     Paragraph('Non-conforme', major_style)],
    [Paragraph('Synthese', td_style),
     Paragraph('Non', td_center_style), Paragraph('Oui', td_center_style),
     Paragraph('Oui', td_center_style), Paragraph('Oui', td_center_style),
     Paragraph('Conforme', conform_style)],
    [Paragraph('Statistiques', td_style),
     Paragraph('Non', td_center_style), Paragraph('Oui', td_center_style),
     Paragraph('Oui', td_center_style), Paragraph('Oui', td_center_style),
     Paragraph('Conforme', conform_style)],
    [Paragraph('Export', td_style),
     Paragraph('Non', td_center_style), Paragraph('Oui', td_center_style),
     Paragraph('Oui', td_center_style), Paragraph('Oui', td_center_style),
     Paragraph('Conforme', conform_style)],
    [Paragraph('Gerer utilisateurs', td_style),
     Paragraph('Non', td_center_style), Paragraph('Oui', td_center_style),
     Paragraph('Non', td_center_style), Paragraph('Non', td_center_style),
     Paragraph('Conforme', conform_style)],
    [Paragraph('Parametres', td_style),
     Paragraph('Non', td_center_style), Paragraph('Oui', td_center_style),
     Paragraph('Non', td_center_style), Paragraph('Non', td_center_style),
     Paragraph('Conforme', conform_style)],
]
story.append(make_table(rbac_data, [CONTENT_W*0.22, CONTENT_W*0.10, CONTENT_W*0.14, CONTENT_W*0.12, CONTENT_W*0.10, CONTENT_W*0.18]))
story.append(Spacer(1, 6))
story.append(Paragraph(
    'Sur 12 fonctionnalites testees, 11 sont conformes aux droits definis dans help.php. '
    'La seule non-conformite concerne l\'impression, deja detaillee en section 3.4.',
    body_style))

# ═══════════════════════════════════════════
# FONCTIONNALITES METIER
# ═══════════════════════════════════════════
story.append(Paragraph('<b>5. Fonctionnalites metier vs fonctions DB</b>', h1_style))

story.append(Paragraph(
    'L\'audit precedent (v1.0.0) avait identifie deux fonctions DB sans interface utilisateur : '
    'reactivateUser() et updateSite(). Ces lacunes ont ete corrigees dans la v1.1.0.',
    body_style))

feat_data = [
    [Paragraph('<b>Fonction DB</b>', th_style),
     Paragraph('<b>UI presente ?</b>', th_style),
     Paragraph('<b>Handler</b>', th_style),
     Paragraph('<b>Statut</b>', th_style)],
    [Paragraph('reactivateUser()', td_style),
     Paragraph('Oui — users.php (bouton "Reactiver") + user_view.php', td_style),
     Paragraph('user_reactivate_handler.php', td_style),
     Paragraph('Conforme', conform_style)],
    [Paragraph('updateSite()', td_style),
     Paragraph('Oui — settings.php (bouton "Modifier") + site_edit.php', td_style),
     Paragraph('site_edit_handler.php', td_style),
     Paragraph('Conforme', conform_style)],
]
story.append(make_table(feat_data, [CONTENT_W*0.18, CONTENT_W*0.40, CONTENT_W*0.24, CONTENT_W*0.18]))
story.append(Spacer(1, 6))
story.append(Paragraph(
    'Les deux fonctionnalites manquantes de la v1.0.0 sont desormais completement implementees '
    'avec leur interface utilisateur, leur handler de formulaire et leur validation CSRF. '
    'Le handler de reactivation verifie que l\'utilisateur est bien inactif avant de le reactiver, '
    'et le handler de modification de site verifie l\'unicite du code et la presence des champs requis.',
    body_style))

# ═══════════════════════════════════════════
# CODE MORT / STUBS / TODOS
# ═══════════════════════════════════════════
story.append(Paragraph('<b>6. Code mort, stubs et TODOs</b>', h1_style))

story.append(Paragraph(
    'L\'analyse statique du code source confirme que le nettoyage effectue en v1.1.0 est complet. '
    'Les trois fonctions orphelines identifiees dans l\'audit precedent (updateUserRole, updateUserSite, '
    'agentSeesOnlyOwn) ont ete supprimees du code PHP et remplacees par updateUser() et '
    'getAgentVisibility(). Aucun fichier PHP ne reference plus ces fonctions supprimees. Aucun stub '
    'alert("...a venir") n\'existe dans le code. Aucun commentaire TODO, FIXME, HACK ou XXX '
    'n\'a ete trouve. Le seul point residuel est que le fichier SPEC.md (specification technique) '
    'reference encore updateUserRole() et updateUserSite() comme fonctions existantes, ce qui est '
    'une obsolescence documentaire et non un probleme de code.',
    body_style))

dead_data = [
    [Paragraph('<b>Element</b>', th_style),
     Paragraph('<b>Statut</b>', th_style),
     Paragraph('<b>Detail</b>', th_style)],
    [Paragraph('alert("...a venir") stubs', td_style),
     Paragraph('Aucun trouve', conform_style),
     Paragraph('Zero occurrence dans tout le code PHP', td_style)],
    [Paragraph('TODO / FIXME / HACK', td_style),
     Paragraph('Aucun trouve', conform_style),
     Paragraph('Zero occurrence dans tout le code PHP', td_style)],
    [Paragraph('updateUserRole()', td_style),
     Paragraph('Supprime (v1.1.0)', conform_style),
     Paragraph('Remplacee par updateUser() — aucun appel restant', td_style)],
    [Paragraph('updateUserSite()', td_style),
     Paragraph('Supprime (v1.1.0)', conform_style),
     Paragraph('Remplacee par updateUser() — aucun appel restant', td_style)],
    [Paragraph('agentSeesOnlyOwn()', td_style),
     Paragraph('Supprime (v1.1.0)', conform_style),
     Paragraph('Remplacee par getAgentVisibility() — aucun appel restant', td_style)],
    [Paragraph('mail.php "stub" (README)', td_style),
     Paragraph('Etiquette erronee', minor_style),
     Paragraph('README.md ligne 74 qualifie mail.php de "stub" mais il contient 296 lignes de code SMTP complet', td_style)],
]
story.append(make_table(dead_data, [CONTENT_W*0.22, CONTENT_W*0.18, CONTENT_W*0.60]))
story.append(Spacer(1, 8))

story.append(Paragraph('<b>6.1 Non-conformite mineure : SPEC.md obsolete</b>', h2_style))
story.append(Paragraph(
    'Le fichier SPEC.md (specification technique v1.0) n\'a pas ete mis a jour pour la v1.1.0. '
    'Il contient toujours les references aux fonctions supprimees (updateUserRole, updateUserSite), '
    'indique que le Manager peut repondre aux signalements (alors que ce droit a ete retire en v1.1.0), '
    'et indique que l\'abandon est reserve au declarant (alors qu\'il est desormais reserve au '
    'Superviseur). Ces inexactitudes dans SPEC.md sont des non-conformites mineures de documentation '
    'qui pourraient induire en erreur un developpeur consultant la spec technique au lieu du code. '
    'Il est recommande de mettre a jour SPEC.md pour refleter l\'etat reel du code v1.1.0, ou a '
    'defaut d\'ajouter un avertissement en en-tete indiquant que ce document est anterieur aux '
    'corrections de securite.',
    body_style))

# ═══════════════════════════════════════════
# VERSIONNING ET CHANGELOG
# ═══════════════════════════════════════════
story.append(Paragraph('<b>7. Versionning et changelog</b>', h1_style))

story.append(Paragraph(
    'L\'application dispose d\'un fichier CHANGELOG.md qui documente les versions. La v1.0.0 (date '
    'du 2025-06-05) correspond a la premiere version, et la v1.1.0 (2026-06-10) documente '
    'detailleement les corrections de securite, les fonctionnalites ajoutees, le code mort supprime '
    'et les modifications techniques. Le changelog est bien structure avec des sections par type de '
    'changement (Securite, Fonctionnalites, Code mort, Documentation, Technique). Chaque entree '
    'identifie les fichiers modifies. Cette qualite de changelog est conforme aux bonnes pratiques.',
    body_style))

story.append(Paragraph(
    'Cependant, il n\'existe pas de mecanisme de versionning automatique (git tags, semver dans '
    'composer.json, ou constante de version dans le code). La version n\'est affichee nulle part '
    'dans l\'interface utilisateur. Un utilisateur ne peut pas savoir quelle version de l\'application '
    'il utilise sans consulter le fichier CHANGELOG.md sur le serveur. Il serait recommande d\'ajouter '
    'une constante APP_VERSION dans config.php et d\'afficher la version dans le footer de l\'application.',
    body_style))

# ═══════════════════════════════════════════
# RECOMMANDATIONS
# ═══════════════════════════════════════════
story.append(Paragraph('<b>8. Recommandations</b>', h1_style))

story.append(Paragraph('<b>8.1 Priorite haute — Non-conformite majeure</b>', h2_style))

story.append(Paragraph(
    '<b>Corriger le bouton « Imprimer la fiche »</b> : dans templates/report_card.php, ligne 157, '
    'conditionner l\'affichage du lien d\'impression au role Superviseur uniquement. Ajouter '
    'egalement une verification de role dans report_print.php pour empecher l\'acces direct par URL. '
    'Le code corrige serait :',
    body_style))
story.append(Paragraph(
    'if (in_array($userRole, [\'superviseur\'])): ... bouton Imprimer ... endif',
    code_style))

story.append(Spacer(1, 8))
story.append(Paragraph('<b>8.2 Priorite moyenne — Non-conformites mineures</b>', h2_style))

story.append(Paragraph(
    '<b>Mettre a jour SPEC.md</b> : supprimer les references aux fonctions supprimees '
    '(updateUserRole, updateUserSite), corriger les permissions du Manager (pas de reponse), '
    'corriger l\'abandon (reserve au Superviseur, pas au declarant). Ajouter un avertissement '
    'si une refonte complete est reportee.',
    body_style))

story.append(Spacer(1, 4))
story.append(Paragraph(
    '<b>Corriger README.md</b> : remplacer le label « stub » pour mail.php par une description '
    'accurate (« Notifications email (client SMTP complet) »).',
    body_style))

story.append(Spacer(1, 4))
story.append(Paragraph(
    '<b>Officialiser le role Manager dans la documentation PDF</b> : soit mettre a jour les manuels '
    'DIRECCTE pour inclure ce role, soit documenter explicitement qu\'il s\'agit d\'une extension '
    'par rapport au cahier des charges initial.',
    body_style))

story.append(Spacer(1, 8))
story.append(Paragraph('<b>8.3 Priorite basse — Ameliorations</b>', h2_style))

story.append(Paragraph(
    '<b>Ajouter une constante de version</b> : definir APP_VERSION = \'1.1.0\' dans config.php '
    'et l\'afficher dans le footer. Cela permet aux utilisateurs et aux auditeurs d\'identifier '
    'la version en cours sans acceder aux fichiers serveur.',
    body_style))

story.append(Spacer(1, 4))
story.append(Paragraph(
    '<b>Ajouter un avertissement dans help.php</b> : mentionner que le role Manager est une '
    'extension par rapport aux manuels PDF d\'origine, pour tracer cet ecart documentaire.',
    body_style))

# ═══════════════════════════════════════════
# TABLEAU RECAPITULATIF
# ═══════════════════════════════════════════
story.append(Paragraph('<b>9. Tableau recapitulatif des constats</b>', h1_style))

recap_data = [
    [Paragraph('<b>N.</b>', th_style),
     Paragraph('<b>Constat</b>', th_style),
     Paragraph('<b>Fichier(s)</b>', th_style),
     Paragraph('<b>Severite</b>', th_style),
     Paragraph('<b>Action</b>', th_style)],
    [Paragraph('1', td_center_style),
     Paragraph('Bouton « Imprimer » visible par tous les roles', td_style),
     Paragraph('report_card.php:157, report_print.php', td_style),
     Paragraph('Majeure', major_style),
     Paragraph('Conditionner au role Superviseur', td_style)],
    [Paragraph('2', td_center_style),
     Paragraph('Role Manager absent des PDF officiels', td_style),
     Paragraph('Manuels PDF vs help.php', td_style),
     Paragraph('Mineure', minor_style),
     Paragraph('Officialiser dans la doc ou documenter l\'ecart', td_style)],
    [Paragraph('3', td_center_style),
     Paragraph('SPEC.md non mis a jour pour v1.1.0', td_style),
     Paragraph('SPEC.md:148, 351-353, 818-819', td_style),
     Paragraph('Mineure', minor_style),
     Paragraph('Mettre a jour SPEC.md', td_style)],
    [Paragraph('4', td_center_style),
     Paragraph('README.md qualifie mail.php de "stub" a tort', td_style),
     Paragraph('README.md:74', td_style),
     Paragraph('Mineure', minor_style),
     Paragraph('Corriger le label', td_style)],
    [Paragraph('5', td_center_style),
     Paragraph('Pas de version affichee dans l\'UI', td_style),
     Paragraph('config.php, footer', td_style),
     Paragraph('Suggestion', ParagraphStyle('Info', fontName='LiberationSerif', fontSize=9, leading=12, alignment=TA_CENTER, textColor=COLOR_INFO)),
     Paragraph('Ajouter constante APP_VERSION', td_style)],
]
story.append(make_table(recap_data, [CONTENT_W*0.05, CONTENT_W*0.28, CONTENT_W*0.22, CONTENT_W*0.12, CONTENT_W*0.33]))

story.append(Spacer(1, 18))
story.append(HRFlowable(width="100%", thickness=0.5, color=TEXT_MUTED, spaceBefore=12, spaceAfter=12))
story.append(Paragraph(
    'Fin du rapport d\'audit. Les fichiers sources analyses sont disponibles dans le repertoire '
    '/home/z/my-project/upload/sst_extracted/sst/. Les documents de reference PDF sont dans le meme '
    'repertoire : DIRECCTE-SST-MANUEL-utilisateur (1).pdf et DIRECCTE-SST-MANUEL-Superviseur (1).pdf.',
    muted_style))

# ── Build ──
doc.build(story)
print(f"PDF generated: {output_path}")
