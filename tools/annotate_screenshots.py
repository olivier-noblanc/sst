#!/usr/bin/env python3
"""
Add annotations (numbered callouts with arrows) to screenshot PNGs.
Each screenshot gets relevant callouts based on its content.
"""
from PIL import Image, ImageDraw, ImageFont
import os
from pathlib import Path

SCREENSHOTS_DIR = Path(__file__).parent.parent / "public" / "screenshots"

# Colors
COLOR_ACCENT = (204, 51, 0)     # Dark orange-red for callout circles
COLOR_ARROW = (204, 51, 0)      # Same for arrows
COLOR_LABEL_BG = (204, 51, 0)   # Red background for number badges
COLOR_LABEL_TEXT = (255, 255, 255)  # White text on badges
COLOR_DESC_TEXT = (51, 51, 51)  # Dark gray for description text

def get_font(size=16):
    """Get a font that supports French characters."""
    font_paths = [
        '/usr/share/fonts/truetype/chinese/NotoSansSC[wght].ttf',
        '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf',
        '/usr/share/fonts/truetype/liberation/LiberationSans-Regular.ttf',
    ]
    for fp in font_paths:
        if os.path.exists(fp):
            try:
                return ImageFont.truetype(fp, size)
            except Exception:
                continue
    return ImageFont.load_default()

def draw_callout(draw, x, y, number, font_badge, font_desc, description, img_width):
    """Draw a numbered callout: circle + arrow pointing to (x,y) + description text."""
    radius = 18
    # Semi-transparent white circle around the target point
    for r in range(radius + 8, radius + 2, -1):
        alpha = max(0, 180 - (r - radius - 2) * 30)
        draw.ellipse([x - r, y - r, x + r, y + r], fill=None, outline=COLOR_ACCENT, width=2)
    
    # Arrow from number badge to target
    badge_size = 22
    # Place badge to the right and above if possible
    badge_x = min(x + 60, img_width - 100)
    badge_y = max(y - 60, 30)
    
    # Draw arrow line
    draw.line([(badge_x, badge_y + badge_size//2), (x, y)], fill=COLOR_ARROW, width=3)
    # Arrowhead
    import math
    angle = math.atan2(y - badge_y, x - badge_x)
    arrow_len = 12
    for da in [2.5, -2.5]:
        ax = x - arrow_len * math.cos(angle + da * 0.3)
        ay = y - arrow_len * math.sin(angle + da * 0.3)
        draw.line([(x, y), (ax, ay)], fill=COLOR_ARROW, width=3)
    
    # Draw badge (red circle with white number)
    draw.ellipse([badge_x - badge_size//2, badge_y - badge_size//2,
                  badge_x + badge_size//2, badge_y + badge_size//2],
                 fill=COLOR_LABEL_BG)
    # Number text
    num_text = str(number)
    bbox = font_badge.getbbox(num_text)
    tw = bbox[2] - bbox[0]
    th = bbox[3] - bbox[1]
    draw.text((badge_x - tw//2, badge_y - th//2 - 2), num_text, fill=COLOR_LABEL_TEXT, font=font_badge)
    
    # Description text next to badge
    desc_x = badge_x + badge_size//2 + 8
    desc_y = badge_y - 8
    # White background behind description text
    bbox = font_desc.getbbox(description)
    tw = bbox[2] - bbox[0]
    th = bbox[3] - bbox[1]
    draw.rectangle([desc_x - 3, desc_y - 2, desc_x + tw + 3, desc_y + th + 2], fill=(255, 255, 255, 220))
    draw.text((desc_x, desc_y), description, fill=COLOR_DESC_TEXT, font=font_desc)

# Annotations for each screenshot: list of (x_pct, y_pct, description)
# x_pct and y_pct are percentages of image dimensions (0-100)
ANNOTATIONS = {
    "cu1-accueil.png": [
        (50, 6, "Barre d'en-tête (logo, nom, déconnexion)"),
        (15, 22, "Menu latéral (navigation)"),
        (55, 22, "Carte RSST — Signaler un événement"),
        (55, 40, "Carte RAMI — Signaler pour un collègue"),
        (55, 58, "Carte DGI — Danger grave et imminent"),
    ],
    "cu1-accueil-superviseur.png": [
        (50, 6, "Profil Superviseur (badge orange)"),
        (15, 22, "Menu élargi (traitement, synthèse, export...)"),
        (55, 22, "Accès rapide aux 3 registres"),
    ],
    "cu1-accueil-chsct.png": [
        (50, 6, "Profil CSA/CHSCT (badge vert)"),
        (15, 22, "Menu restreint (consultation uniquement)"),
        (55, 22, "Consultation des registres"),
    ],
    "cu2-creation-rsst.png": [
        (50, 12, "Type de registre : RSST"),
        (30, 25, "Date de l'événement"),
        (30, 38, "Description détaillée"),
        (30, 52, "Gravité et catégorie"),
        (50, 68, "Bouton de validation"),
    ],
    "cu3-creation-rami.png": [
        (50, 12, "Type de registre : RAMI"),
        (30, 25, "Champ « Pour le compte de »"),
        (30, 40, "Nature de l'auteur"),
        (30, 55, "Type d'acte"),
        (50, 70, "Description de l'événement"),
    ],
    "cu4-creation-dgi.png": [
        (50, 10, "Type de registre : DGI"),
        (50, 16, "Bandeau d'avertissement — procédure prioritaire"),
        (30, 30, "Description du danger"),
        (30, 50, "Mesures de protection demandées"),
    ],
    "cu5-liste-signalements.png": [
        (25, 8, "Filtres par registre et état"),
        (50, 8, "Barre de recherche"),
        (70, 20, "Badges d'état (En cours, Traité...)"),
        (85, 20, "Actions (Voir, Modifier)"),
    ],
    "cu5-liste-signalements-sup.png": [
        (25, 8, "Vue superviseur — tous les sites"),
        (85, 20, "Actions Répondre / Abandonner"),
        (70, 20, "Badges d'état avec codes couleur"),
    ],
    "cu5-voir-signalement.png": [
        (50, 8, "En-tête du signalement (type, numéro, état)"),
        (30, 22, "Informations déclarant"),
        (30, 38, "Détails de l'événement"),
        (30, 55, "Historique des actions"),
        (85, 22, "Actions disponibles"),
    ],
    "cu5-voir-rami.png": [
        (50, 8, "Signalement RAMI"),
        (30, 25, "Champ « Pour le compte de »"),
        (30, 40, "Nature auteur et type acte"),
    ],
    "cu5-voir-dgi.png": [
        (50, 8, "Signalement DGI"),
        (50, 14, "Bandeau procédure prioritaire"),
        (30, 30, "Mesures de protection"),
    ],
    "cu5-modifier-signalement.png": [
        (50, 10, "Formulaire de modification"),
        (30, 25, "Champs modifiables"),
        (50, 65, "Bouton Enregistrer"),
    ],
    "cu5-repondre-signalement.png": [
        (50, 10, "Formulaire de réponse (superviseur)"),
        (30, 30, "Changement de statut"),
        (30, 50, "Commentaire / réponse"),
        (50, 68, "Bouton Valider"),
    ],
    "cu6-statistiques.png": [
        (50, 8, "Filtres de période"),
        (30, 22, "Graphique d'évolution"),
        (70, 22, "Répartition par registre"),
        (30, 50, "Répartition par site"),
    ],
    "cu7-synthese.png": [
        (50, 8, "Sélection du site"),
        (30, 22, "Nombre de signalements par registre"),
        (30, 40, "Répartition par état"),
        (70, 40, "Détails par type"),
    ],
    "cu8-export.png": [
        (30, 15, "Sélection du format (CSV)"),
        (30, 30, "Filtres d'export"),
        (50, 50, "Bouton Exporter"),
    ],
    "cu9-parametres.png": [
        (25, 8, "Onglet Application"),
        (50, 8, "Onglet SMTP"),
        (75, 8, "Onglet Notifications"),
        (30, 18, "Nom de l'organisation"),
        (30, 28, "Label des unités (UR, UE...)"),
    ],
    "cu10-utilisateurs.png": [
        (25, 10, "Recherche d'utilisateur"),
        (50, 10, "Bouton Créer un utilisateur"),
        (70, 22, "Rôles (Agent, Superviseur, CSA/CHSCT)"),
        (85, 22, "Actions (Éditer, Voir)"),
    ],
    "cu11-journaux.png": [
        (25, 10, "Filtres par date et type"),
        (50, 22, "Entrées du journal d'audit"),
        (85, 22, "Niveau de sévérité"),
    ],
    "cu12-aide.png": [
        (50, 5, "Page de documentation (cette page)"),
    ],
    "cu13-preambule.png": [
        (50, 8, "Cadre juridique RGPD"),
        (30, 22, "Références légales"),
    ],
    "cu14-journal-modifs.png": [
        (50, 5, "Historique des modifications"),
    ],
    "cu15-choix-site.png": [
        (50, 10, "Sélection de l'unité (définitif)"),
        (50, 30, "Avertissement — choix irréversible"),
        (50, 50, "Bouton Confirmer"),
    ],
}

def main():
    font_badge = get_font(20)
    font_desc = get_font(14)
    
    annotated = 0
    for png_file in sorted(SCREENSHOTS_DIR.glob("*.png")):
        annotations = ANNOTATIONS.get(png_file.name, [])
        if not annotations:
            print(f"  SKIP: {png_file.name} (pas d'annotations)")
            continue
        
        img = Image.open(png_file).convert("RGBA")
        # Create overlay for annotations
        overlay = Image.new("RGBA", img.size, (0, 0, 0, 0))
        draw = ImageDraw.Draw(overlay)
        
        w, h = img.size
        for i, (x_pct, y_pct, desc) in enumerate(annotations, 1):
            x = int(w * x_pct / 100)
            y = int(h * y_pct / 100)
            draw_callout(draw, x, y, i, font_badge, font_desc, desc, w)
        
        # Composite overlay onto image
        result = Image.alpha_composite(img, overlay).convert("RGB")
        result.save(png_file, "PNG", quality=95)
        
        print(f"  OK: {png_file.name} ({len(annotations)} annotation(s))")
        annotated += 1
    
    print(f"\n{annotated} screenshots annotés")

if __name__ == "__main__":
    main()
