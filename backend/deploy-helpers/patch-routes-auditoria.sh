#!/bin/sh
# ============================================================
# Inserta la ruta Route::get('auditoria_hora_hora', ...) en
# routes/api.php DENTRO del container, justo despues de la
# ruta existente de "defectos/linea/{linea}" / armarAndonDefectosLineaHoraAHora.
#
# - Idempotente: si la ruta ya esta, no hace nada.
# - Quirurgico: NO sobreescribe el archivo entero, solo inserta 1 linea.
# - No toca ninguna otra ruta del archivo.
# ============================================================
set -e

ROUTES="/var/www/html/routes/api.php"
ANCLA="armarAndonDefectosLineaHoraAHora"

if [ ! -f "$ROUTES" ]; then
    echo "ERROR: no existe $ROUTES dentro del container."
    exit 1
fi

if grep -q "auditoria_hora_hora" "$ROUTES"; then
    echo "  -> Ruta 'auditoria_hora_hora' ya estaba presente. No se modifica."
    exit 0
fi

if ! grep -q "$ANCLA" "$ROUTES"; then
    echo "ERROR: no encontre el ancla '$ANCLA' en $ROUTES."
    exit 1
fi

awk -v anchor="$ANCLA" '
{
    print
    if (!inserted && $0 ~ anchor) {
        print "        Route::get('\''auditoria_hora_hora'\'', [\\App\\Http\\Controllers\\AndonAuditoriaController::class, '\''reporte'\'']);"
        inserted = 1
    }
}
' "$ROUTES" > "$ROUTES.new"

mv "$ROUTES.new" "$ROUTES"
echo "  -> Ruta 'auditoria_hora_hora' agregada."
