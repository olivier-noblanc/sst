#!/bin/bash
# SST DREETS BFC — Screenshot generator using agent-browser + real PHP app
# Prerequisites: PHP server running on 0.0.0.0:8200

set -e

REAL_IP=$(hostname -I | awk '{print $1}')
BASE="http://${REAL_IP}:8200"
DIR="/home/z/my-project/sst-repo/docs/screenshots"
AB="agent-browser"

mkdir -p "$DIR"

echo "=== SST DREETS BFC — Screenshot Generator ==="
echo "Base URL: $BASE"
echo "Output dir: $DIR"

screenshot() {
    local name="$1"
    local path="$DIR/$name"
    echo "📸 $name"
    "$AB" screenshot --full "$path" 2>&1 | head -1
    if [ -f "$path" ]; then
        local size=$(wc -c < "$path")
        echo "  → ${size} bytes"
    else
        echo "  → FAILED"
    fi
}

# ============================================================
# AGENT screenshots
# ============================================================
echo ""
echo "--- Agent (agent.dev) ---"

# Login as agent
"$AB" navigate "$BASE/index.php?page=login" >/dev/null 2>&1
"$AB" type 'input[name="username"]' "agent.dev" >/dev/null 2>&1
"$AB" type 'input[name="password"]' "test" >/dev/null 2>&1
"$AB" click 'button[type="submit"]' >/dev/null 2>&1
sleep 2

# Handle site selection if needed
SITE_SELECT=$("$AB" eval 'document.querySelector("select[name=site_id]") ? "yes" : "no"' 2>/dev/null | tail -1)
if echo "$SITE_SELECT" | grep -q "yes"; then
    echo "  → Selecting site..."
    "$AB" select 'select[name="site_id"]' "1" >/dev/null 2>&1
    "$AB" click 'button[type="submit"]' >/dev/null 2>&1
    sleep 2
fi

# CU1 — Accueil
screenshot "cu1-accueil.png"

# CU1 — Formulaire RSST
"$AB" navigate "$BASE/?page=report_create&type=rsst" >/dev/null 2>&1
sleep 1
# Fill fields
"$AB" type 'input[name="date_evenement"]' "2025-06-10" >/dev/null 2>&1
"$AB" type 'input[name="heure_evenement"]' "14:30" >/dev/null 2>&1
"$AB" type 'input[name="lieu"]' "Bâtiment principal, 2e étage, escalier B" >/dev/null 2>&1
"$AB" type 'input[name="objet"]' "Rampe d'escalier desserrée - risque de chute" >/dev/null 2>&1
"$AB" type 'textarea[name="description"]' "La rampe de l'escalier B au 2e étage est desserrée. Un agent a failli chuter." >/dev/null 2>&1
sleep 0.5
screenshot "cu1-formulaire-rsst.png"

# CU2 — Formulaire RAMI
"$AB" navigate "$BASE/?page=report_create&type=rami" >/dev/null 2>&1
sleep 1
"$AB" click 'input[name="pour_compte"]' >/dev/null 2>&1
sleep 0.3
"$AB" type 'input[name="pour_compte_prenom"]' "Pierre" >/dev/null 2>&1
"$AB" type 'input[name="pour_compte_nom"]' "Dupont" >/dev/null 2>&1
"$AB" select 'select[name="nature_auteur"]' "usager" >/dev/null 2>&1
"$AB" select 'select[name="type_acte"]' "verbal" >/dev/null 2>&1
"$AB" type 'input[name="date_evenement"]' "2025-06-10" >/dev/null 2>&1
"$AB" type 'input[name="objet"]' "Agression verbale par un usager" >/dev/null 2>&1
"$AB" type 'textarea[name="description"]' "Témoin d'une agression verbale envers mon collègue Pierre Dupont par un usager." >/dev/null 2>&1
sleep 0.5
screenshot "cu2-formulaire-rami.png"

# CU3 — Formulaire DGI
"$AB" navigate "$BASE/?page=report_create&type=dgi" >/dev/null 2>&1
sleep 1
"$AB" type 'input[name="date_evenement"]' "2025-06-10" >/dev/null 2>&1
"$AB" type 'input[name="lieu"]' "UR25 Doubs, Bâtiment annexe, local archives" >/dev/null 2>&1
"$AB" type 'input[name="objet"]' "Fuite de gaz dans les locaux" >/dev/null 2>&1
"$AB" type 'textarea[name="description"]' "Fuite de gaz importante. Danger grave et imminent nécessitant une intervention immédiate." >/dev/null 2>&1
sleep 0.5
screenshot "cu3-formulaire-dgi.png"

# Pages générales
"$AB" navigate "$BASE/?page=preamble" >/dev/null 2>&1
sleep 1
screenshot "page-preambule.png"

"$AB" navigate "$BASE/?page=help" >/dev/null 2>&1
sleep 1
screenshot "page-aide.png"

# ============================================================
# SUPERVISEUR screenshots
# ============================================================
echo ""
echo "--- Superviseur (admin.dev) ---"

# Login as admin
"$AB" navigate "$BASE/index.php?page=login" >/dev/null 2>&1
sleep 1
"$AB" type 'input[name="username"]' "admin.dev" >/dev/null 2>&1
"$AB" type 'input[name="password"]' "test" >/dev/null 2>&1
"$AB" click 'button[type="submit"]' >/dev/null 2>&1
sleep 2

# Handle site selection
SITE_SELECT=$("$AB" eval 'document.querySelector("select[name=site_id]") ? "yes" : "no"' 2>/dev/null | tail -1)
if echo "$SITE_SELECT" | grep -q "yes"; then
    "$AB" select 'select[name="site_id"]' "1" >/dev/null 2>&1
    "$AB" click 'button[type="submit"]' >/dev/null 2>&1
    sleep 2
fi

# CU4 — Liste signalements (pour répondre)
"$AB" navigate "$BASE/?page=report_list&type=rsst" >/dev/null 2>&1
sleep 1
screenshot "cu4-repondre.png"

# CU5 — Liste RAMI (pour abandonner)
"$AB" navigate "$BASE/?page=report_list&type=rami" >/dev/null 2>&1
sleep 1
screenshot "cu5-abandonner.png"

# CU6 — Synthèse
"$AB" navigate "$BASE/?page=synthesis" >/dev/null 2>&1
sleep 1
screenshot "cu6-synthese.png"

# CU6 — Statistiques
"$AB" navigate "$BASE/?page=statistics" >/dev/null 2>&1
sleep 1
screenshot "cu6-statistiques.png"

# CU7 — Utilisateurs
"$AB" navigate "$BASE/?page=users" >/dev/null 2>&1
sleep 1
screenshot "cu7-utilisateurs.png"

# ============================================================
# Summary
# ============================================================
echo ""
echo "=== SUMMARY ==="
ls -la "$DIR"/*.png 2>/dev/null | awk '{print $5, $NF}' | while read size name; do
    printf "  %-40s %s bytes\n" "$(basename $name)" "$size"
done

echo ""
echo "Done!"
