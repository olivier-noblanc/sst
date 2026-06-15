#!/usr/bin/env python3
"""
Add annotations (numbered callouts with arrows) to screenshot PNGs.
Uses Playwright to detect REAL element positions from HTML, then draws
annotations at the correct pixel coordinates on the captured PNGs.

Workflow:
  1. capture_screenshots.py  →  public/screenshots/*.png + docs/screenshots/*.png
  2. annotate_screenshots.py →  reads HTML positions via Playwright,
                                 annotates PNGs in public/screenshots/,
                                 copies to docs/screenshots/
"""
from PIL import Image, ImageDraw, ImageFont
from playwright.sync_api import sync_playwright
import os
import math
import shutil
from pathlib import Path

BASE_DIR = Path(__file__).parent.parent
HTML_DIR = BASE_DIR / "docs" / "screenshots"
SCREENSHOTS_DIR = BASE_DIR / "public" / "screenshots"
DOCS_DIR = BASE_DIR / "docs" / "screenshots"

# Colors
RED = (204, 51, 0)
WHITE = (255, 255, 255)
DARK = (51, 51, 51)


def get_font(size=16):
    for fp in ['/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf',
               '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf',
               '/usr/share/fonts/truetype/liberation/LiberationSans-Bold.ttf',
               '/usr/share/fonts/truetype/liberation/LiberationSans-Regular.ttf']:
        if os.path.exists(fp):
            try:
                return ImageFont.truetype(fp, size)
            except Exception:
                continue
    return ImageFont.load_default()


def draw_badge(draw, cx, cy, number, font):
    r = 16
    draw.ellipse([cx - r, cy - r, cx + r, cy + r], fill=RED, outline=(153, 38, 0), width=2)
    text = str(number)
    bbox = font.getbbox(text)
    tw, th = bbox[2] - bbox[0], bbox[3] - bbox[1]
    draw.text((cx - tw // 2, cy - th // 2 - 1), text, fill=WHITE, font=font)


def draw_target(draw, x, y):
    r = 14
    draw.ellipse([x - r - 4, y - r - 4, x + r + 4, y + r + 4], outline=RED, width=3)
    draw.ellipse([x - r, y - r, x + r, y + r], outline=RED, width=2)


def draw_arrow(draw, x1, y1, x2, y2):
    draw.line([(x1, y1), (x2, y2)], fill=RED, width=2)
    angle = math.atan2(y2 - y1, x2 - x1)
    arrow_len = 10
    for da in [2.5, -2.5]:
        ax = x2 - arrow_len * math.cos(angle + da * 0.3)
        ay = y2 - arrow_len * math.sin(angle + da * 0.3)
        draw.line([(x2, y2), (int(ax), int(ay))], fill=RED, width=2)


def draw_callout(draw, x, y, number, font_badge, font_desc, description, img_width, img_height):
    draw_target(draw, x, y)

    badge_x = min(x + 70, img_width - 200)
    badge_y = max(y - 50, 30)

    if abs(badge_x - x) < 40 and abs(badge_y - y) < 40:
        badge_x = min(x + 100, img_width - 200)
        badge_y = max(y - 80, 30)

    if badge_y > img_height - 30:
        badge_y = max(y - 80, 30)

    draw_arrow(draw, badge_x, badge_y, x, y)
    draw_badge(draw, badge_x, badge_y, number, font_badge)

    desc_x = badge_x + 22
    desc_y = badge_y - 10

    bbox = font_desc.getbbox(description)
    tw, th = bbox[2] - bbox[0], bbox[3] - bbox[1]
    padding = 4
    draw.rectangle(
        [desc_x - padding, desc_y - padding,
         desc_x + tw + padding, desc_y + th + padding],
        fill=WHITE
    )
    draw.text((desc_x, desc_y), description, fill=DARK, font=font_desc)


# ──────────────────────────────────────────────────────────────────────
# CSS selectors for each screenshot's annotated elements.
# Playwright will return the bounding box center of each matched element.
# Format:  filename → list of (CSS_selector, description)
# ──────────────────────────────────────────────────────────────────────
ELEMENT_ANNOTATIONS = {
    "cu1-accueil.html": [
        ("header.header", "Barre d'en-tête"),
        ("nav.sidebar", "Menu latéral"),
        (".registry-card--rsst", "Carte RSST"),
        (".registry-card--rami", "Carte RAMI"),
        (".registry-card--dgi", "Carte DGI"),
    ],
    "cu1-accueil-superviseur.html": [
        (".badge--superviseur", "Profil Superviseur"),
        ("nav.sidebar", "Menu élargi"),
        (".registry-cards", "3 registres"),
    ],
    "cu1-accueil-chsct.html": [
        (".badge--chsct", "Profil CSA/CHSCT"),
        ("nav.sidebar", "Menu restreint"),
        (".registry-cards", "Consultation seule"),
    ],
    "cu2-creation-rsst.html": [
        (".card--rsst h2, h2.mb-4", "Type RSST"),
        ("#date_evenement", "Date événement"),
        ("#description", "Description"),
        ("#site_id", "Site / Unité"),
        (".form-actions .btn--primary", "Bouton Valider"),
    ],
    "cu3-creation-rami.html": [
        (".card--rami h2, h2.mb-4", "Type RAMI"),
        ("#pour_compte", "Pour le compte de"),
        ("#nature_auteur", "Nature auteur"),
        ("#type_acte", "Type d'acte"),
        ("#description", "Description"),
    ],
    "cu4-creation-dgi.html": [
        (".card--dgi h2, h2.mb-4", "Type DGI"),
        (".card--dgi", "Formulaire DGI"),
        ("#description", "Description danger"),
        ("#lieu", "Lieu / Mesures"),
    ],
    "cu4-repondre-signalement.html": [
        (".card--rsst .card__subtitle", "Résumé signalement"),
        (".report-detail__table", "Détails signalement"),
        ("#nouvel_etat", "Changement statut"),
        ("#reponse", "Commentaire"),
        (".form-actions .btn--primary", "Valider"),
    ],
    "cu4-modifier-signalement.html": [
        (".card--rsst h2", "Modification"),
        ("form .form-group", "Champs modifiables"),
        (".confidential-toggle", "Confidentialité"),
        (".form-actions .btn--rsst", "Enregistrer"),
    ],
    "cu5-liste-signalements-sup.html": [
        (".btn-float-right", "Nouveau signalement"),
        ("#site", "Filtre Site (UR)"),
        ("input#q", "Recherche"),
        (".badge--nouveau, .badge--en-cours", "Badges d'état"),
        (".badge--confidential, .badge--public", "Visibilité"),
        (".btn--sm.btn--primary", "Répondre"),
    ],
    "consultation-liste-signalements.html": [
        (".btn-float-right", "Nouveau signalement"),
        (".filter-bar", "Filtres état / recherche"),
        ("input#q", "Recherche"),
        (".badge--nouveau, .badge--en-cours", "Badges d'état"),
        (".badge--confidential, .badge--public", "Visibilité"),
        (".btn-group", "Actions"),
    ],
    "consultation-voir-rsst.html": [
        (".report-detail__header h2", "Signalement RSST"),
        (".report-detail__table", "Détails signalement"),
        (".btn-group .badge--nouveau", "État"),
        (".badge--confidential", "Visibilité"),
        (".btn--danger", "Abandonner"),
    ],
    "consultation-voir-rami.html": [
        (".report-detail__header h2", "Signalement RAMI"),
        (".report-detail__table", "Détails signalement"),
        (".report-detail__table tbody tr:nth-child(9) th", "Pour le compte de"),
        (".card h3", "Réponses"),
    ],
    "consultation-voir-dgi.html": [
        (".report-detail__header h2", "Signalement DGI"),
        (".danger-panel", "Procédure prioritaire"),
        (".report-detail__table", "Détails signalement"),
        (".card h3", "Réponses"),
    ],
    "cu6-statistiques.html": [
        (".filter-bar", "Filtres période"),
        (".indicateur-card--rsst", "Indicateur RSST"),
        (".indicateur-card--rami", "Indicateur RAMI"),
        (".indicateur-card--dgi", "Indicateur DGI"),
    ],
    "cu7-synthese.html": [
        (".filter-bar", "Filtres"),
        ("th.synthesis-th-rsst", "Colonne RSST"),
        ("th.synthesis-th-rami", "Colonne RAMI"),
        ("th.synthesis-th-dgi", "Colonne DGI"),
    ],
    "cu8-export.html": [
        ("#type", "Type registre"),
        (".date-range", "Plage de dates"),
        (".form-actions .btn--primary", "Exporter"),
    ],
    "cu9-parametres.html": [
        (".tab-bar .settings-tab:nth-child(1)", "Onglet Application"),
        (".tab-bar .settings-tab:nth-child(2)", "Onglet SMTP"),
        (".tab-bar .settings-tab:nth-child(3)", "Onglet Notifications"),
        (".card:first-of-type .card__subtitle", "Paramètres site"),
        (".form-actions .btn--success", "Enregistrer"),
    ],
    "cu10-utilisateurs.html": [
        (".form--inline", "Recherche"),
        (".btn--primary", "Rechercher"),
        ("table th:nth-child(4)", "Rôles"),
        ("table th:last-child", "Actions"),
    ],
    "cu11-journaux.html": [
        (".tab-bar", "Filtres onglets"),
        (".log-entry--info", "Entrées info"),
        (".log-entry--fatal", "Entrées fatales"),
    ],
    "cu12-aide.html": [
        ("h1.page-title", "Page Documentation"),
    ],
    "cu13-preambule.html": [
        ("h1.page-title", "Cadre juridique"),
        (".card--rsst", "Registre RSST"),
    ],
    "cu14-journal-modifs.html": [
        (".changelog-content h2:first-of-type", "Dernière version"),
    ],
    "cu15-choix-site.html": [
        (".choose-site-welcome", "Bienvenue"),
        ("#site_id", "Choix de l'unité"),
        (".btn--full", "Confirmer"),
    ],
}


def get_element_positions(page, html_file):
    """
    Use Playwright to find the center positions of annotated elements.
    Returns list of (x, y, description).
    """
    selectors = ELEMENT_ANNOTATIONS.get(html_file, [])
    if not selectors:
        return []

    results = []
    for selector, description in selectors:
        try:
            pos = page.evaluate("""(selector) => {
                const parts = selector.split(',').map(s => s.trim());
                for (const part of parts) {
                    try {
                        const el = document.querySelector(part);
                        if (el) {
                            const rect = el.getBoundingClientRect();
                            if (rect.width > 0 && rect.height > 0) {
                                return {
                                    x: Math.round(rect.x + rect.width / 2),
                                    y: Math.round(rect.y + rect.height / 2),
                                    w: Math.round(rect.width),
                                    h: Math.round(rect.height),
                                    found: true
                                };
                            }
                        }
                    } catch(e) {}
                }
                return { found: false };
            }""", selector)

            if pos and pos.get('found'):
                results.append((pos['x'], pos['y'], description))
            else:
                print(f"    ⚠ NOT FOUND: {selector}")
        except Exception as e:
            print(f"    ⚠ ERROR: {selector}: {e}")

    return results


def main():
    SCREENSHOTS_DIR.mkdir(parents=True, exist_ok=True)

    font_badge = get_font(18)
    font_desc = get_font(15)

    annotated = 0

    with sync_playwright() as p:
        browser = p.chromium.launch()
        context = browser.new_context(
            viewport={"width": 1280, "height": 900},
            device_scale_factor=1.0,
        )
        page = context.new_page()

        for html_file in sorted(ELEMENT_ANNOTATIONS.keys()):
            html_path = HTML_DIR / html_file
            png_name = html_file.replace(".html", ".png")
            png_path = SCREENSHOTS_DIR / png_name

            if not html_path.exists():
                print(f"  SKIP: {html_file} (HTML introuvable)")
                continue

            if not png_path.exists():
                print(f"  SKIP: {png_name} (PNG introuvable)")
                continue

            try:
                page.goto(f"file://{html_path.resolve()}", wait_until="networkidle", timeout=15000)
            except Exception as e:
                print(f"  ERREUR: {html_file}: {e}")
                continue

            positions = get_element_positions(page, html_file)

            if not positions:
                print(f"  SKIP: {png_name} (aucun élément trouvé)")
                continue

            img = Image.open(png_path).convert("RGB")
            draw = ImageDraw.Draw(img)
            w, h = img.size

            for i, (x, y, desc) in enumerate(positions, 1):
                x = max(20, min(x, w - 20))
                y = max(20, min(y, h - 20))
                draw_callout(draw, x, y, i, font_badge, font_desc, desc, w, h)

            img.save(png_path, "PNG", optimize=True)

            dst = DOCS_DIR / png_name
            shutil.copy2(png_path, dst)

            size_kb = png_path.stat().st_size / 1024
            print(f"  OK: {png_name} ({len(positions)} annotations, {size_kb:.0f} KB)")
            annotated += 1

        browser.close()

    print(f"\n{annotated} screenshots annotés avec positions réelles")


if __name__ == "__main__":
    main()
