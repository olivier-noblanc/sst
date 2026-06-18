#!/usr/bin/env python3
"""
Add annotations (numbered callouts with arrows) to screenshot PNGs.
Uses Playwright to detect REAL element positions from HTML, then draws
annotations at the correct pixel coordinates on the captured PNGs.

v3 — Full rectangle collision detection (fixes annotation overlap):
  - Each annotation's collision footprint includes badge circle AND description box
  - AABB rectangle overlap detection (not just point distance)
  - Bidirectional push: resolves in the direction of least overlap (horizontal or vertical)
  - Annotations sorted by target Y for deterministic top-to-bottom placement
  - Image height extended automatically if annotations overflow the bottom

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
import textwrap
from pathlib import Path

BASE_DIR = Path(__file__).parent.parent
HTML_DIR = BASE_DIR / "docs" / "screenshots"
SCREENSHOTS_DIR = BASE_DIR / "public" / "screenshots"
DOCS_DIR = BASE_DIR / "docs" / "screenshots"

# Colors
RED = (204, 51, 0)
RED_DARK = (153, 38, 0)
WHITE = (255, 255, 255)
DARK = (51, 51, 51)
LIGHT_BG = (255, 255, 240)  # light yellow-white for description background
LIGHT_BG_OUTLINE = (200, 200, 180)

# Annotation layout constants
BADGE_RADIUS = 14
ARROW_GAP = 6        # gap between target edge and arrow start
DESC_FONT_SIZE = 13
BADGE_FONT_SIZE = 16
BADGE_OFFSET_X = 60   # horizontal offset from target edge to badge center
BADGE_OFFSET_Y = -10  # vertical offset from target to badge center
TOP_MARGIN = 20       # top margin
MAX_DESC_WIDTH = 22    # max chars per line for description

# v3: collision padding — extra gap between annotation footprints
COLLISION_PAD = 10
MIN_BADGE_DIST = BADGE_RADIUS * 2 + 8  # 36px — minimum center-to-center distance


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
    """Draw a numbered circle badge."""
    r = BADGE_RADIUS
    draw.ellipse([cx - r, cy - r, cx + r, cy + r], fill=RED, outline=RED_DARK, width=2)
    text = str(number)
    bbox = font.getbbox(text)
    tw, th = bbox[2] - bbox[0], bbox[3] - bbox[1]
    draw.text((cx - tw // 2, cy - th // 2 - 1), text, fill=WHITE, font=font)


def draw_target(draw, cx, cy, w=0, h=0):
    """Draw a target indicator at the EDGE of an element."""
    r = 6
    draw.ellipse([cx - r - 2, cy - r - 2, cx + r + 2, cy + r + 2], outline=RED, width=2)


def draw_arrow(draw, x1, y1, x2, y2):
    """Draw an arrow from badge to target."""
    draw.line([(x1, y1), (x2, y2)], fill=RED, width=2)
    # Arrowhead at target end
    angle = math.atan2(y2 - y1, x2 - x1)
    arrow_len = 10
    for da in [2.5, -2.5]:
        ax = x2 - arrow_len * math.cos(angle + da * 0.3)
        ay = y2 - arrow_len * math.sin(angle + da * 0.3)
        draw.line([(x2, y2), (int(ax), int(ay))], fill=RED, width=2)


def draw_description(draw, x, y, text, font, img_width):
    """Draw a description text with background, wrapping if needed.
    Returns (text_x, text_y, box_w, box_h)."""
    lines = textwrap.wrap(text, width=MAX_DESC_WIDTH) if len(text) > MAX_DESC_WIDTH else [text]

    padding = 4
    line_height = font.getbbox("Ay")[3] + 6

    max_tw = 0
    for line in lines:
        bbox = font.getbbox(line)
        tw = bbox[2] - bbox[0]
        max_tw = max(max_tw, tw)

    total_h = line_height * len(lines)

    # Clamp text within image bounds
    text_x = x
    text_y = y
    if text_x + max_tw + padding * 2 > img_width:
        text_x = max(img_width - max_tw - padding * 2 - 10, 10)
    if text_x < 4:
        text_x = 4
    if text_y < 4:
        text_y = 4

    # Draw background rectangle
    draw.rectangle(
        [text_x - padding, text_y - padding,
         text_x + max_tw + padding, text_y + total_h + padding],
        fill=LIGHT_BG, outline=LIGHT_BG_OUTLINE, width=1
    )

    for i, line in enumerate(lines):
        draw.text((text_x, text_y + i * line_height), line, fill=DARK, font=font)

    return text_x, text_y, max_tw + padding * 2, total_h + padding * 2


def measure_description(desc, font):
    """Pre-compute the bounding box size of a description text block.
    Returns (width, height) of the full description box (including padding)."""
    lines = textwrap.wrap(desc, width=MAX_DESC_WIDTH) if len(desc) > MAX_DESC_WIDTH else [desc]
    padding = 4
    line_height = font.getbbox("Ay")[3] + 6

    max_tw = 0
    for line in lines:
        bbox = font.getbbox(line)
        tw = bbox[2] - bbox[0]
        max_tw = max(max_tw, tw)

    total_h = line_height * len(lines)
    return max_tw + padding * 2, total_h + padding * 2


def compute_footprint_for(bx_v, by_v, dw, dh, img_width, desc_left_offset, desc_top_offset):
    """Compute the AABB footprint for badge at (bx_v, by_v) with its description.
    Returns (fp_x1, fp_y1, fp_x2, fp_y2, desc_x, desc_y)."""
    desc_x_v = bx_v - desc_left_offset
    desc_y_v = by_v + desc_top_offset
    # Description may shift left to fit in image
    if desc_x_v + dw > img_width - 4:
        desc_x_v = max(img_width - dw - 10, 4)
    if desc_x_v < 4:
        desc_x_v = 4

    fp_x1 = min(bx_v - BADGE_RADIUS, desc_x_v) - COLLISION_PAD
    fp_y1 = by_v - BADGE_RADIUS - COLLISION_PAD
    fp_x2 = max(bx_v + BADGE_RADIUS, desc_x_v + dw) + COLLISION_PAD
    fp_y2 = desc_y_v + dh + COLLISION_PAD
    return fp_x1, fp_y1, fp_x2, fp_y2, desc_x_v, desc_y_v


def compute_badge_positions(positions, img_width, img_height, font_desc):
    """
    Compute non-overlapping badge+description positions for all annotations.

    v3 — Two-phase placement with global relaxation:
    Phase 1 — Sequential placement near targets with per-badge collision resolution
    Phase 2 — Global relaxation: iteratively resolve ALL pairwise overlaps

    Each annotation's footprint is an AABB rectangle covering badge circle + description box.
    Overlap detected via rectangle intersection. Resolution pushes in the direction of
    least overlap (horizontal or vertical).

    Returns list of (bx, by, tx, ty, desc_x, desc_y, desc) tuples.
    """
    n = len(positions)
    if n == 0:
        return []

    MARGIN = BADGE_RADIUS + 8
    DESC_LEFT_OFFSET = BADGE_RADIUS + 20   # description is offset left of badge center
    DESC_TOP_OFFSET = BADGE_RADIUS + 6     # description starts below badge bottom

    # Pre-compute description dimensions for each annotation
    desc_sizes = []
    for (cx, cy, desc, w, h) in positions:
        dw, dh = measure_description(desc, font_desc)
        desc_sizes.append((dw, dh))

    # ── Phase 1: Sequential placement near targets ──────────────────
    # Sort by target Y (top to bottom) for deterministic placement
    indexed = list(enumerate(positions))
    indexed.sort(key=lambda x: x[1][1])  # sort by cy

    # badges: dict  idx → {bx, by, tx, ty}
    badges = {}
    footprints = {}  # idx → (fp_x1, fp_y1, fp_x2, fp_y2)
    placed_indices = []  # ordered list of already-placed indices

    for idx, (cx, cy, desc, w, h) in indexed:
        dw, dh = desc_sizes[idx]

        # Decide badge position near target element
        elem_right = cx + w // 2
        elem_left = cx - w // 2

        if elem_right + BADGE_OFFSET_X + MARGIN < img_width:
            tx = min(elem_right + 4, img_width - 10)
            bx = elem_right + BADGE_OFFSET_X
        elif elem_left - BADGE_OFFSET_X - MARGIN > 0:
            tx = max(elem_left - 4, 10)
            bx = elem_left - BADGE_OFFSET_X
        else:
            tx = cx
            bx = cx

        ty = cy
        by = cy + BADGE_OFFSET_Y

        fp_x1, fp_y1, fp_x2, fp_y2, desc_x, desc_y = compute_footprint_for(
            bx, by, dw, dh, img_width, DESC_LEFT_OFFSET, DESC_TOP_OFFSET)

        # Per-badge collision resolution against already-placed badges
        for _ in range(80):
            moved = False
            for pidx in placed_indices:
                pfp = footprints[pidx]
                if fp_x1 < pfp[2] and fp_x2 > pfp[0] and \
                   fp_y1 < pfp[3] and fp_y2 > pfp[1]:
                    overlap_x = min(fp_x2, pfp[2]) - max(fp_x1, pfp[0])
                    overlap_y = min(fp_y2, pfp[3]) - max(fp_y1, pfp[1])

                    if overlap_y <= overlap_x:
                        center_other_y = (pfp[1] + pfp[3]) / 2
                        if by >= center_other_y:
                            by += overlap_y + 4
                        else:
                            by -= overlap_y + 4
                    else:
                        center_other_x = (pfp[0] + pfp[2]) / 2
                        if bx >= center_other_x:
                            bx += overlap_x + 4
                        else:
                            bx -= overlap_x + 4

                    fp_x1, fp_y1, fp_x2, fp_y2, desc_x, desc_y = compute_footprint_for(
                        bx, by, dw, dh, img_width, DESC_LEFT_OFFSET, DESC_TOP_OFFSET)
                    moved = True
            if not moved:
                break

        # Clamp badge within image bounds
        bx = max(MARGIN, min(bx, img_width - MARGIN))
        by = max(MARGIN, min(by, img_height - MARGIN))

        fp_x1, fp_y1, fp_x2, fp_y2, desc_x, desc_y = compute_footprint_for(
            bx, by, dw, dh, img_width, DESC_LEFT_OFFSET, DESC_TOP_OFFSET)

        badges[idx] = {'bx': bx, 'by': by, 'tx': tx, 'ty': ty}
        footprints[idx] = (fp_x1, fp_y1, fp_x2, fp_y2)
        placed_indices.append(idx)

    # ── Phase 2: Global relaxation — resolve ALL pairwise overlaps ──
    # This catches cases where sequential placement misses cross-interactions
    # (e.g., 3 badges at similar Y from horizontal row of elements)
    for global_iter in range(100):
        any_moved = False
        all_indices = list(badges.keys())

        for i in range(len(all_indices)):
            idx_i = all_indices[i]
            bi = badges[idx_i]
            fi = footprints[idx_i]
            dw_i, dh_i = desc_sizes[idx_i]

            for j in range(i + 1, len(all_indices)):
                idx_j = all_indices[j]
                fj = footprints[idx_j]

                # Check overlap
                if fi[0] < fj[2] and fi[2] > fj[0] and \
                   fi[1] < fj[3] and fi[3] > fj[1]:
                    overlap_x = min(fi[2], fj[2]) - max(fi[0], fj[0])
                    overlap_y = min(fi[3], fj[3]) - max(fi[1], fj[1])

                    if overlap_y <= overlap_x:
                        # Push apart vertically
                        if bi['by'] <= badges[idx_j]['by']:
                            bi['by'] -= (overlap_y / 2 + 4)
                            badges[idx_j]['by'] += (overlap_y / 2 + 4)
                        else:
                            bi['by'] += (overlap_y / 2 + 4)
                            badges[idx_j]['by'] -= (overlap_y / 2 + 4)
                    else:
                        # Push apart horizontally
                        if bi['bx'] <= badges[idx_j]['bx']:
                            bi['bx'] -= (overlap_x / 2 + 4)
                            badges[idx_j]['bx'] += (overlap_x / 2 + 4)
                        else:
                            bi['bx'] += (overlap_x / 2 + 4)
                            badges[idx_j]['bx'] -= (overlap_x / 2 + 4)

                    # Clamp both badges
                    bi['bx'] = max(MARGIN, min(bi['bx'], img_width - MARGIN))
                    bi['by'] = max(MARGIN, min(bi['by'], img_height - MARGIN))
                    badges[idx_j]['bx'] = max(MARGIN, min(badges[idx_j]['bx'], img_width - MARGIN))
                    badges[idx_j]['by'] = max(MARGIN, min(badges[idx_j]['by'], img_height - MARGIN))

                    # Recompute both footprints
                    dw_j, dh_j = desc_sizes[idx_j]
                    footprints[idx_i] = compute_footprint_for(
                        bi['bx'], bi['by'], dw_i, dh_i, img_width, DESC_LEFT_OFFSET, DESC_TOP_OFFSET)[:4]
                    footprints[idx_j] = compute_footprint_for(
                        badges[idx_j]['bx'], badges[idx_j]['by'], dw_j, dh_j, img_width,
                        DESC_LEFT_OFFSET, DESC_TOP_OFFSET)[:4]

                    fi = footprints[idx_i]
                    any_moved = True

        if not any_moved:
            break

    # ── Phase 3: Hard guarantee — enforce minimum badge center distance ──
    # Regardless of footprints, badge CIRCLES must not overlap.
    # Note: we do NOT clamp to img_height here — main() will extend the image
    # if badges go below the bottom edge. Only clamp left/right/top.
    for _ in range(100):
        any_moved = False
        all_indices = list(badges.keys())
        for i in range(len(all_indices)):
            for j in range(i + 1, len(all_indices)):
                idx_i = all_indices[i]
                idx_j = all_indices[j]
                bi = badges[idx_i]
                bj = badges[idx_j]
                dx = bi['bx'] - bj['bx']
                dy = bi['by'] - bj['by']
                dist = math.sqrt(dx * dx + dy * dy)
                if dist < MIN_BADGE_DIST:
                    # Need to push apart
                    if dist < 1:
                        # Coincident — push one down
                        bj['by'] += MIN_BADGE_DIST
                    else:
                        # Push apart proportionally along the axis of greater separation
                        push = (MIN_BADGE_DIST - dist) / 2 + 2
                        if abs(dy) >= abs(dx):
                            # Push vertically
                            if bi['by'] <= bj['by']:
                                bi['by'] -= push
                                bj['by'] += push
                            else:
                                bi['by'] += push
                                bj['by'] -= push
                        else:
                            # Push horizontally
                            if bi['bx'] <= bj['bx']:
                                bi['bx'] -= push
                                bj['bx'] += push
                            else:
                                bi['bx'] += push
                                bj['bx'] -= push
                    # Clamp: only left, right, and top — NOT bottom
                    # (image will be extended if needed)
                    bi['bx'] = max(MARGIN, min(bi['bx'], img_width - MARGIN))
                    bi['by'] = max(MARGIN, bi['by'])  # no bottom clamp
                    bj['bx'] = max(MARGIN, min(bj['bx'], img_width - MARGIN))
                    bj['by'] = max(MARGIN, bj['by'])  # no bottom clamp
                    # Recompute footprints
                    dw_i, dh_i = desc_sizes[idx_i]
                    dw_j, dh_j = desc_sizes[idx_j]
                    footprints[idx_i] = compute_footprint_for(
                        bi['bx'], bi['by'], dw_i, dh_i, img_width,
                        DESC_LEFT_OFFSET, DESC_TOP_OFFSET)[:4]
                    footprints[idx_j] = compute_footprint_for(
                        bj['bx'], bj['by'], dw_j, dh_j, img_width,
                        DESC_LEFT_OFFSET, DESC_TOP_OFFSET)[:4]
                    any_moved = True
        if not any_moved:
            break

    # ── Build final results ─────────────────────────────────────────
    result = []
    for i in range(n):
        b = badges[i]
        dw, dh = desc_sizes[i]
        _, _, _, _, desc_x, desc_y = compute_footprint_for(
            b['bx'], b['by'], dw, dh, img_width, DESC_LEFT_OFFSET, DESC_TOP_OFFSET)
        result.append((int(b['bx']), int(b['by']), int(b['tx']), int(b['ty']),
                       int(desc_x), int(desc_y), positions[i][2]))

    return result


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
        (".form-actions .btn--primary", "Bouton Envoyer"),
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
        (".form-actions .btn--primary", "Envoyer"),
    ],
    "cu4-modifier-signalement.html": [
        (".card--rsst h2", "Modification"),
        ("form .form-group", "Champs modifiables"),
        (".confidential-toggle", "Confidentialité"),
        (".form-actions .btn--rsst", "Enregistrer"),
    ],
    "cu5-liste-signalements-sup.html": [
        (".btn-float-right", "Nouveau signalement"),
        ("#site", "Filtre Site"),
        ("input#q", "Recherche"),
        (".badge--nouveau, .badge--en-cours", "Badges d'état"),
        (".badge--confidential, .badge--public", "Visibilité"),
        (".btn--sm.btn--primary", "Répondre"),
    ],
    "consultation-liste-signalements.html": [
        (".btn-float-right", "Nouveau signalement"),
        (".filter-bar", "Filtres et recherche"),
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
    Use Playwright to find the bounding box of annotated elements.
    Returns list of (center_x, center_y, description, width, height).
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
                results.append((pos['x'], pos['y'], description, pos['w'], pos['h']))
            else:
                print(f"    ⚠ NOT FOUND: {selector}")
        except Exception as e:
            print(f"    ⚠ ERROR: {selector}: {e}")

    return results


def main():
    SCREENSHOTS_DIR.mkdir(parents=True, exist_ok=True)

    font_badge = get_font(BADGE_FONT_SIZE)
    font_desc = get_font(DESC_FONT_SIZE)

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
            w, h = img.size
            draw = ImageDraw.Draw(img)

            # Compute non-overlapping badge+description positions (v3)
            badge_layouts = compute_badge_positions(positions, w, h, font_desc)

            # Check if any annotation extends below image — extend if needed
            max_y_needed = 0
            for (bx, by, tx, ty, desc_x, desc_y, desc) in badge_layouts:
                dw, dh = measure_description(desc, font_desc)
                max_y_needed = max(max_y_needed, desc_y + dh + COLLISION_PAD)
                max_y_needed = max(max_y_needed, by + BADGE_RADIUS + COLLISION_PAD)

            if max_y_needed > h:
                # Extend image with white space at the bottom
                extra = max_y_needed - h + 20  # 20px extra margin
                new_img = Image.new("RGB", (w, h + extra), (255, 255, 255))
                new_img.paste(img, (0, 0))
                img = new_img
                draw = ImageDraw.Draw(img)
                h = h + extra

            for i, (bx, by, tx, ty, desc_x, desc_y, desc) in enumerate(badge_layouts, 1):
                # Draw target at element edge
                draw_target(draw, tx, ty)

                # Draw arrow from badge to target
                draw_arrow(draw, bx, by, tx, ty)

                # Draw badge number
                draw_badge(draw, bx, by, i, font_badge)

                # Draw description at pre-computed position
                draw_description(draw, desc_x, desc_y, desc, font_desc, w)

            img.save(png_path, "PNG", optimize=True)

            dst = DOCS_DIR / png_name
            shutil.copy2(png_path, dst)

            size_kb = png_path.stat().st_size / 1024
            print(f"  OK: {png_name} ({len(positions)} annotations, {size_kb:.0f} KB)")
            annotated += 1

        browser.close()

    print(f"\n{annotated} screenshots annotés (v3 — sans chevauchement)")


if __name__ == "__main__":
    main()
