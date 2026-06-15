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

SCREENSHOTS = [
    "cu1-accueil.html",
    "cu1-accueil-superviseur.html",
    "cu1-accueil-chsct.html",
    "cu2-creation-rsst.html",
    "cu3-creation-rami.html",
    "cu4-creation-dgi.html",
    "cu4-repondre-signalement.html",
    "cu4-modifier-signalement.html",
    "cu5-liste-signalements-sup.html",
    "consultation-liste-signalements.html",
    "consultation-voir-rsst.html",
    "consultation-voir-rami.html",
    "consultation-voir-dgi.html",
    "cu6-statistiques.html",
    "cu7-synthese.html",
    "cu8-export.html",
    "cu9-parametres.html",
    "cu10-utilisateurs.html",
    "cu11-journaux.html",
    "cu12-aide.html",
    "cu13-preambule.html",
    "cu14-journal-modifs.html",
    "cu15-choix-site.html",
]

def main():
    OUTPUT_DIR.mkdir(parents=True, exist_ok=True)
    
    with sync_playwright() as p:
        browser = p.chromium.launch()
        context = browser.new_context(
            viewport={"width": 1280, "height": 900},
            device_scale_factor=1.0,  # 1:1 pixel ratio for final size
        )
        page = context.new_page()
        
        captured = 0
        for html_file in SCREENSHOTS:
            src = SCREENSHOTS_DIR / html_file
            png_name = html_file.replace(".html", ".png")
            dst_out = OUTPUT_DIR / png_name
            dst_docs = SCREENSHOTS_DIR / png_name
            
            if not src.exists():
                print(f"  SKIP: {html_file} (fichier introuvable)")
                continue
            
            try:
                page.goto(f"file://{src.resolve()}", wait_until="networkidle", timeout=15000)
                
                # Limit max screenshot height to 3000px
                page_height = page.evaluate("document.body.scrollHeight")
                viewport_height = min(page_height, 3000)
                
                page.screenshot(path=str(dst_out), full_page=True, clip={"x": 0, "y": 0, "width": 1280, "height": min(page_height, 3000)})
                
                from PIL import Image
                img = Image.open(dst_out)
                w, h = img.size
                print(f"  OK: {png_name} ({w}x{h})")
                
                # Copy to docs/screenshots too
                import shutil
                shutil.copy2(dst_out, dst_docs)
                
                captured += 1
            except Exception as e:
                print(f"  ERREUR: {html_file}: {e}")
        
        browser.close()
    
    print(f"\n{captured}/{len(SCREENSHOTS)} captures reussies")

if __name__ == "__main__":
    main()
