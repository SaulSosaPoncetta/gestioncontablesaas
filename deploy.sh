#!/usr/bin/env bash
# =============================================================================
# deploy.sh — Deploy manual desde la rama main (MiGestión)
#
# Uso, en el VPS y dentro de la carpeta del proyecto:
#     cd /var/www/<sistema>
#     bash deploy.sh
#
# Pasos: verifica el estado, pone el sitio en mantenimiento, baja los cambios
# de origin/main, instala dependencias, compila el frontend, migra la base,
# regenera cachés, ajusta permisos y vuelve a poner el sitio en línea.
# =============================================================================
set -Eeuo pipefail

BRANCH="main"
PHP_FPM="php8.4-fpm"
WEB_GROUP="www-data"

APP_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
cd "$APP_DIR"
APP_NAME="$(basename "$APP_DIR")"

paso()  { echo -e "\n\033[1;34m==> $1\033[0m"; }
ok()    { echo -e "\033[1;32m$1\033[0m"; }
aviso() { echo -e "\033[1;33m$1\033[0m"; }
error() { echo -e "\033[1;31m$1\033[0m"; }

MANTENIMIENTO=0
al_fallar() {
    error "\nEl deploy de $APP_NAME falló en la línea $1."
    if [ "$MANTENIMIENTO" -eq 1 ]; then
        php artisan up || true
        aviso "Se sacó el sitio del modo mantenimiento. Revisá el error antes de reintentar."
    fi
}
trap 'al_fallar $LINENO' ERR

INICIO=$(date +%s)
echo -e "\033[1mDeploy de $APP_NAME — $(date '+%d/%m/%Y %H:%M:%S')\033[0m"

# -----------------------------------------------------------------------------
paso "Verificando el proyecto"
[ -f artisan ] || { error "No se encontró 'artisan' en $APP_DIR."; exit 1; }
[ -f .env ]    || { error "Falta el archivo .env. Crealo antes del primer deploy."; exit 1; }

RAMA_ACTUAL="$(git rev-parse --abbrev-ref HEAD)"
if [ "$RAMA_ACTUAL" != "$BRANCH" ]; then
    error "El servidor está en la rama '$RAMA_ACTUAL' y el deploy es desde '$BRANCH'."
    exit 1
fi

if [ -n "$(git status --porcelain --untracked-files=no)" ]; then
    error "Hay archivos modificados en el servidor que no están en git:"
    git status --short --untracked-files=no
    error "Commitealos desde tu PC o descartalos con 'git checkout -- <archivo>' y reintentá."
    exit 1
fi
ok "OK: rama $BRANCH, .env presente, sin cambios locales."

# -----------------------------------------------------------------------------
paso "Poniendo el sitio en mantenimiento"
php artisan down --retry=15 --refresh=15 || true
MANTENIMIENTO=1

# -----------------------------------------------------------------------------
paso "Bajando cambios de origin/$BRANCH"
ANTES="$(git rev-parse --short HEAD)"
git fetch origin "$BRANCH"
git merge --ff-only "origin/$BRANCH"
DESPUES="$(git rev-parse --short HEAD)"

if [ "$ANTES" = "$DESPUES" ]; then
    aviso "No hay commits nuevos (sigue en $ANTES). Se continúa igual."
else
    ok "Actualizado $ANTES -> $DESPUES:"
    git log --oneline "$ANTES..$DESPUES"
fi

# -----------------------------------------------------------------------------
paso "Instalando dependencias PHP (composer)"
composer install --no-dev --optimize-autoloader --no-interaction --prefer-dist

# -----------------------------------------------------------------------------
if [ -f package.json ] && grep -q '"build"' package.json; then
    paso "Compilando frontend (npm)"
    if [ -f package-lock.json ]; then
        npm ci --no-audit --no-fund
    else
        npm install --no-audit --no-fund
    fi
    npm run build
else
    aviso "\nSin script 'build' en package.json: se saltea el frontend."
fi

# -----------------------------------------------------------------------------
paso "Migrando base de datos"
php artisan migrate --force

# -----------------------------------------------------------------------------
paso "Enlace de storage"
if [ -L public/storage ]; then
    ok "public/storage ya existe."
else
    php artisan storage:link
fi

# -----------------------------------------------------------------------------
paso "Regenerando cachés"
php artisan optimize:clear
php artisan optimize
php artisan view:cache

# -----------------------------------------------------------------------------
paso "Ajustando permisos de storage y bootstrap/cache"
find storage bootstrap/cache -user "$(whoami)" -exec chgrp "$WEB_GROUP" {} + -exec chmod ug+rwX {} + 2>/dev/null \
    || aviso "Algunos permisos no se pudieron ajustar (archivos de otro usuario). No suele ser un problema."

# -----------------------------------------------------------------------------
paso "Reiniciando workers y PHP-FPM"
php artisan queue:restart || true
if sudo -n true 2>/dev/null; then
    sudo systemctl reload "$PHP_FPM" && ok "$PHP_FPM recargado."
else
    aviso "No se recargó $PHP_FPM (pide contraseña de sudo). No es obligatorio."
fi

# -----------------------------------------------------------------------------
paso "Sacando el sitio de mantenimiento"
php artisan up
MANTENIMIENTO=0

FIN=$(date +%s)
ok "\nDeploy de $APP_NAME terminado en $((FIN - INICIO)) segundos (versión $DESPUES)."
