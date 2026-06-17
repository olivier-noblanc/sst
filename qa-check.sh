#!/bin/bash
#
# QA Check — Run all code quality tools
# Usage: bash qa-check.sh [--fix]
#
# --fix: auto-fix what can be fixed (PHP-CS-Fixer only)
#

set -e
cd "$(dirname "$0")"

FIX_MODE=false
if [ "$1" = "--fix" ]; then
    FIX_MODE=true
fi

echo "═══════════════════════════════════════════════════════════"
echo "  QA Check — Application SST DREETS BFC"
echo "═══════════════════════════════════════════════════════════"
echo ""

# ─── 1. PHPStan ──────────────────────────────────────────────
echo "🔍 [1/4] PHPStan (static analysis, level 6)..."
if [ -f vendor/bin/phpstan ]; then
    php -d memory_limit=512M vendor/bin/phpstan analyse -l 6 src/ handlers/ --error-format=table 2>&1 || true
else
    echo "   ⚠️  Not installed. Run: composer require --dev phpstan/phpstan"
fi
echo ""

# ─── 2. PHP-CS-Fixer ────────────────────────────────────────
echo "🎨 [2/4] PHP-CS-Fixer (coding style PSR-12)..."
if [ -f vendor/bin/php-cs-fixer ]; then
    if [ "$FIX_MODE" = true ]; then
        php vendor/bin/php-cs-fixer fix --diff --verbose 2>&1 || true
    else
        php vendor/bin/php-cs-fixer fix --dry-run --diff --verbose 2>&1 || true
    fi
else
    echo "   ⚠️  Not installed. Run: composer require --dev friendsofphp/php-cs-fixer"
fi
echo ""

# ─── 3. Psalm ────────────────────────────────────────────────
echo "🔒 [3/4] Psalm (type analysis + taint detection)..."
if [ -f vendor/bin/psalm ]; then
    php vendor/bin/psalm --no-cache 2>&1 || true
    echo ""
    echo "🔒 [3b] Psalm taint analysis..."
    php vendor/bin/psalm --taint-analysis --no-cache 2>&1 || true
else
    echo "   ⚠️  Not installed. Run: composer require --dev vimeo/psalm"
fi
echo ""

# ─── 4. PHPMD ────────────────────────────────────────────────
echo "📊 [4/4] PHPMD (mess detector)..."
if [ -f vendor/bin/phpmd ]; then
    php vendor/bin/phpmd src/ text phpmd.xml 2>&1 || true
else
    echo "   ⚠️  Not installed. Run: composer require --dev phpmd/phpmd"
fi
echo ""

echo "═══════════════════════════════════════════════════════════"
echo "  QA Check complete."
echo "═══════════════════════════════════════════════════════════"
