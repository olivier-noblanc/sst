#!/usr/bin/env python3
"""
Capture HTML screenshots as PNG images for the SST help page.
Uses Playwright to render each HTML file and save as a screenshot.
"""
import os
import sys
from pathlib import Path
from playwright.sync_api import sync_playwright

SCREENSHOTS_DIR = Path(__file__).parent.parent / "docs" / "screenshots"
OUTPUT_DIR = Path(__file__).parent.parent / "public" / "screenshots"

# Unique screenshots (deduplicated from help.php)
SCREENSHOTS = [
    ("cu1-accueil.html", "Page d'accueil de l'agent"),
    ("cu2-creation-rsst.html", "Formulaire de création RSST"),
    ("cu3-creation-rami.html", "Formulaire de création RAMI"),
    ("cu4-creation-dgi.html", "Formulaire de création DGI"),
    ("cu5-liste-signalements.html", "Liste des signalements (agent)"),
    ("cu5-liste-signalements-sup.html", "Liste des signalements (superviseur)"),
    ("cu5-voir-signalement.html", "Vue détaillée d'un signalement RSST"),
    ("cu5-voir-rami.html", "Vue détaillée d'un signalement RAMI"),
    ("cu5-voir-dgi.html", "Vue détaillée d'un signalement DGI"),
    ("cu5-modifier-signalement.html", "Formulaire de modification"),
    ("cu5-repondre-signalement.html", "Formulaire de réponse superviseur"),
    ("cu6-statistiques.html", "Page des statistiques"),
    ("cu7-synthese.html", "Page de synthèse"),
    ("cu8-export.html", "Page d'export"),
    ("cu9-parametres.html", "Page des paramètres"),
    ("cu10-utilisateurs.html", "Gestion des utilisateurs"),
    ("cu11-journaux.html", "Journaux d'audit"),
    ("cu12-aide.html", "Page de documentation"),
    ("cu13-preambule.html", "Page Préambule RGPD"),
    ("cu14-journal-modifs.html", "Historique des modifications"),
    ("cu15-choix-site.html", "Page de choix du site"),
    ("cu1-accueil-superviseur.html", "Page d'accueil superviseur"),
    ("cu1-accueil-chsct.html", "Page d'accueil CSA/CHSCT"),
]

def main():
    OUTPUT_DIR.mkdir(parents=True, exist_ok=True)
    
    with sync_playwright() as p:
        browser = p.chromium.launch()
        context = browser.new_context(
            viewport={"width": 1280, "height": 900},
            device_scale_factor=1.5,  # Crisp screenshots
        )
        page = context.new_page()
        
        captured = 0
        for html_file, description in SCREENSHOTS:
            src = SCREENSHOTS_DIR / html_file
            png_name = html_file.replace(".html", ".png")
            dst = OUTPUT_DIR / png_name
            
            if not src.exists():
                print(f"  SKIP: {html_file} (fichier introuvable)")
                continue
            
            try:
                # Load the HTML file
                page.goto(f"file://{src.resolve()}", wait_until="networkidle", timeout=15000)
                
                # Take full page screenshot
                page.screenshot(path=str(dst), full_page=True)
                
                # Get actual size for info
                from PIL import Image
                img = Image.open(dst)
                w, h = img.size
                print(f"  OK: {png_name} ({w}x{h})")
                captured += 1
            except Exception as e:
                print(f"  ERREUR: {html_file}: {e}")
        
        browser.close()
    
    print(f"\n{captured}/{len(SCREENSHOTS)} captures reussies")
    print(f"Repertoire: {OUTPUT_DIR}")

if __name__ == "__main__":
    main()
