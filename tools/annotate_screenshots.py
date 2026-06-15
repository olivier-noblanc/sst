#!/usr/bin/env python3
"""
Add annotations (numbered callouts with arrows) to screenshot PNGs.
Each screenshot gets relevant callouts based on its content.
Annotations are drawn at FINAL image size (1280px wide) with readable fonts.
"""
from PIL import Image, ImageDraw, ImageFont
import os
import math
from pathlib import Path

SCREENSHOTS_DIR = Path(__file__).parent.parent / "public" / "screenshots"

# Colors
RED = (204, 51, 0)
WHITE = (255, 255, 255)
DARK = (51, 51, 51)
SEMI_WHITE = (255, 255, 255, 200)

def get_font(size=16):
    for fp in ['/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf',
               '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf',
               '/usr/share/fonts/truetype/liberation/LiberationSans-Bold.ttf',
               '/usr/share/fonts/truetype/liberation/LiberationSans-Regular.ttf']:
        if os.path.exists(fp):
            try: return ImageFont.truetype(fp, size)
            except: continue
    return ImageFont.load_default()

def draw_badge(draw, cx, cy, number, font):
    """Draw a red circle badge with white number."""
    r = 16
    draw.ellipse([cx-r, cy-r, cx+r, cy+r], fill=RED, outline=(153, 38, 0), width=2)
    text = str(number)
    bbox = font.getbbox(text)
    tw, th = bbox[2]-bbox[0], bbox[3]-bbox[1]
    draw.text((cx - tw//2, cy - th//2 - 1), text, fill=WHITE, font=font)

def draw_target(draw, x, y):
    """Draw a target circle around the point of interest."""
    r = 14
    draw.ellipse([x-r-4, y-r-4, x+r+4, y+r+4], outline=RED, width=3)
    draw.ellipse([x-r, y-r, x+r, y+r], outline=RED, width=2)

def draw_arrow(draw, x1, y1, x2, y2):
    """Draw an arrow from (x1,y1) to (x2,y2)."""
    draw.line([(x1, y1), (x2, y2)], fill=RED, width=2)
    angle = math.atan2(y2-y1, x2-x1)
    arrow_len = 10
    for da in [2.5, -2.5]:
        ax = x2 - arrow_len * math.cos(angle + da * 0.3)
        ay = y2 - arrow_len * math.sin(angle + da * 0.3)
        draw.line([(x2, y2), (int(ax), int(ay))], fill=RED, width=2)

def draw_callout(draw, x, y, number, font_badge, font_desc, description, img_width):
    """Draw a complete callout: target + arrow + badge + description."""
    # Target circle on the point of interest
    draw_target(draw, x, y)
    
    # Badge position: offset right and up, clamped to image bounds
    badge_x = min(x + 70, img_width - 200)
    badge_y = max(y - 50, 30)
    
    # If badge would be too close to the target, move it further
    if abs(badge_x - x) < 40 and abs(badge_y - y) < 40:
        badge_x = min(x + 100, img_width - 200)
        badge_y = max(y - 80, 30)
    
    # Arrow from badge to target
    draw_arrow(draw, badge_x, badge_y, x, y)
    
    # Badge (red circle with number)
    draw_badge(draw, badge_x, badge_y, number, font_badge)
    
    # Description text to the right of badge
    desc_x = badge_x + 22
    desc_y = badge_y - 10
    
    # White background rectangle behind text
    bbox = font_desc.getbbox(description)
    tw, th = bbox[2]-bbox[0], bbox[3]-bbox[1]
    padding = 4
    draw.rectangle(
        [desc_x - padding, desc_y - padding, 
         desc_x + tw + padding, desc_y + th + padding],
        fill=WHITE
    )
    draw.text((desc_x, desc_y), description, fill=DARK, font=font_desc)


# Annotations: (x_pct, y_pct, description)
# x_pct and y_pct are percentages of image dimensions (0-100)
ANNOTATIONS = {
    "cu1-accueil.png": [
        (50, 5, "Barre d'en-tête"),
        (12, 18, "Menu latéral"),
        (40, 25, "Carte RSST"),
        (40, 43, "Carte RAMI"),
        (40, 61, "Carte DGI"),
    ],
    "cu1-accueil-superviseur.png": [
        (50, 5, "Profil Superviseur"),
        (12, 18, "Menu élargi"),
        (40, 25, "3 registres"),
    ],
    "cu1-accueil-chsct.png": [
        (50, 5, "Profil CSA/CHSCT"),
        (12, 18, "Menu restreint"),
        (40, 25, "Consultation seule"),
    ],
    "cu2-creation-rsst.png": [
        (50, 8, "Type RSST"),
        (30, 18, "Date événement"),
        (30, 30, "Description"),
        (30, 45, "Gravité / Catégorie"),
        (50, 62, "Bouton Valider"),
    ],
    "cu3-creation-rami.png": [
        (50, 8, "Type RAMI"),
        (30, 18, "Pour le compte de"),
        (30, 32, "Nature auteur"),
        (30, 46, "Type d'acte"),
        (50, 62, "Description"),
    ],
    "cu4-creation-dgi.png": [
        (50, 6, "Type DGI"),
        (50, 12, "Avertissement DGI"),
        (30, 25, "Description danger"),
        (30, 42, "Mesures de protection"),
    ],
    "cu5-liste-signalements.png": [
        (25, 5, "Filtres registre / état"),
        (55, 5, "Recherche"),
        (65, 16, "Badges d'état"),
        (85, 16, "Actions"),
    ],
    "cu5-liste-signalements-sup.png": [
        (25, 5, "Vue superviseur"),
        (80, 16, "Répondre / Abandonner"),
        (65, 16, "Codes couleur état"),
    ],
    "cu5-voir-signalement.png": [
        (50, 5, "En-tête signalement"),
        (30, 16, "Info déclarant"),
        (30, 30, "Détails événement"),
        (30, 48, "Historique"),
        (88, 16, "Actions"),
    ],
    "cu5-voir-rami.png": [
        (50, 5, "Signalement RAMI"),
        (30, 18, "Pour le compte de"),
        (30, 32, "Nature / Type acte"),
    ],
    "cu5-voir-dgi.png": [
        (50, 5, "Signalement DGI"),
        (50, 11, "Procédure prioritaire"),
        (30, 25, "Mesures protection"),
    ],
    "cu5-modifier-signalement.png": [
        (50, 8, "Formulaire modification"),
        (30, 22, "Champs modifiables"),
        (50, 58, "Enregistrer"),
    ],
    "cu5-repondre-signalement.png": [
        (50, 8, "Réponse superviseur"),
        (30, 22, "Changement statut"),
        (30, 42, "Commentaire"),
        (50, 58, "Valider"),
    ],
    "cu6-statistiques.png": [
        (35, 5, "Filtres période"),
        (30, 16, "Évolution"),
        (70, 16, "Répartition registre"),
        (30, 42, "Par site"),
    ],
    "cu7-synthese.png": [
        (30, 5, "Sélection site"),
        (30, 16, "Par registre"),
        (30, 32, "Par état"),
        (70, 32, "Par type"),
    ],
    "cu8-export.png": [
        (30, 10, "Format CSV"),
        (30, 25, "Filtres export"),
        (50, 42, "Exporter"),
    ],
    "cu9-parametres.png": [
        (20, 5, "Onglet Application"),
        (48, 5, "Onglet SMTP"),
        (76, 5, "Onglet Notifications"),
        (30, 12, "Nom organisation"),
        (30, 20, "Label unités"),
    ],
    "cu10-utilisateurs.png": [
        (25, 7, "Recherche"),
        (55, 7, "Créer utilisateur"),
        (65, 18, "Rôles"),
        (88, 18, "Actions"),
    ],
    "cu11-journaux.png": [
        (25, 7, "Filtres"),
        (50, 18, "Entrées audit"),
        (85, 18, "Sévérité"),
    ],
    "cu12-aide.png": [
        (50, 3, "Page Documentation"),
    ],
    "cu13-preambule.png": [
        (50, 5, "Cadre RGPD"),
        (30, 16, "Références légales"),
    ],
    "cu14-journal-modifs.png": [
        (50, 3, "Historique modifications"),
    ],
    "cu15-choix-site.png": [
        (50, 8, "Choix de l'unité"),
        (50, 25, "Choix définitif"),
        (50, 45, "Confirmer"),
    ],
}

def main():
    # Use larger fonts for 1280px wide images
    font_badge = get_font(18)
    font_desc = get_font(15)
    
    annotated = 0
    for png_file in sorted(SCREENSHOTS_DIR.glob("*.png")):
        annotations = ANNOTATIONS.get(png_file.name, [])
        if not annotations:
            print(f"  SKIP: {png_file.name}")
            continue
        
        img = Image.open(png_file).convert("RGB")
        draw = ImageDraw.Draw(img)
        
        w, h = img.size
        for i, (x_pct, y_pct, desc) in enumerate(annotations, 1):
            x = int(w * x_pct / 100)
            y = int(h * y_pct / 100)
            draw_callout(draw, x, y, i, font_badge, font_desc, desc, w)
        
        img.save(png_file, "PNG", optimize=True)
        
        # Also copy to docs/screenshots
        import shutil
        dst = Path("/home/z/my-project/sst/docs/screenshots") / png_file.name
        shutil.copy2(png_file, dst)
        
        size_kb = png_file.stat().st_size / 1024
        print(f"  OK: {png_file.name} ({len(annotations)} annotations, {size_kb:.0f} KB)")
        annotated += 1
    
    print(f"\n{annotated} screenshots annotés")

if __name__ == "__main__":
    main()
