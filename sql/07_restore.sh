#!/bin/bash
# ============================================================
# FARMACIA VIDAPLUS — Script de restauración
# Archivo: sql/07_restore.sh
# Uso:    ./07_restore.sh <archivo.sql.gz>
# ============================================================

set -u

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_DIR="$(dirname "$SCRIPT_DIR")"
BACKUP_DIR="$PROJECT_DIR/backups"
CNF_FILE="$PROJECT_DIR/config/mysql-backup.cnf"
LOG_DIR="$BACKUP_DIR/logs"
LOG_FILE="$LOG_DIR/restore.log"
DB_NAME="vidaplus"

# mysqldump / mysql
if command -v mysql &> /dev/null; then
    MYSQL="mysql"
    MYSQLDUMP="mysqldump"
else
    echo "ERROR: mysql no encontrado en PATH." >&2
    exit 1
fi

log() {
    local nivel="$1"; shift
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] [$nivel] $*" | tee -a "$LOG_FILE"
}

# ------------------------------------------------------------
# VALIDACIONES
# ------------------------------------------------------------
if [ $# -ne 1 ]; then
    echo "Uso: $0 <archivo.sql.gz>"
    echo ""
    echo "Backups disponibles:"
    ls -lh "$BACKUP_DIR"/*.sql.gz 2>/dev/null || echo "  (ninguno)"
    exit 1
fi

ARCHIVO="$1"
if [ ! -f "$ARCHIVO" ]; then
    # Intentar resolver como nombre relativo a backups/
    if [ -f "$BACKUP_DIR/$ARCHIVO" ]; then
        ARCHIVO="$BACKUP_DIR/$ARCHIVO"
    else
        echo "ERROR: archivo no encontrado: $ARCHIVO" >&2
        exit 1
    fi
fi

if [ ! -f "$CNF_FILE" ]; then
    echo "ERROR: no se encontró $CNF_FILE" >&2
    exit 1
fi

mkdir -p "$LOG_DIR"

# ------------------------------------------------------------
# CONFIRMACIÓN
# ------------------------------------------------------------
echo ""
echo "════════════════════════════════════════════════════════"
echo " ⚠️  ADVERTENCIA — RESTAURACIÓN DE BASE DE DATOS"
echo "════════════════════════════════════════════════════════"
echo " Archivo:   $ARCHIVO"
echo " Base:      $DB_NAME"
echo " Host:      localhost"
echo ""
echo " Esto ELIMINARÁ todos los datos actuales de $DB_NAME"
echo " y los reemplazará con los del archivo."
echo "════════════════════════════════════════════════════════"
echo ""
read -p "Escribe la palabra RESTAURAR para continuar: " confirmacion

if [ "$confirmacion" != "RESTAURAR" ]; then
    echo "Operación cancelada."
    exit 0
fi

# ------------------------------------------------------------
# BACKUP DE SEGURIDAD ANTES DE RESTAURAR
# ------------------------------------------------------------
log "INFO" "Creando backup de seguridad del estado actual..."
PRE_BACKUP="$BACKUP_DIR/${DB_NAME}_prerestore_$(date '+%Y-%m-%d_%H-%M-%S').sql.gz"

$MYSQLDUMP \
    --defaults-extra-file="$CNF_FILE" \
    --single-transaction \
    --quick \
    --set-gtid-purged=OFF \
    --routines --triggers --events \
    "$DB_NAME" | gzip > "$PRE_BACKUP"

if [ ! -s "$PRE_BACKUP" ]; then
    log "ERROR" "No se pudo crear el backup de seguridad."
    exit 1
fi
log "INFO" "Backup de seguridad: $PRE_BACKUP"

# ------------------------------------------------------------
# RESTAURAR
# ------------------------------------------------------------
log "INFO" "Restaurando desde $ARCHIVO..."

INICIO=$(date +%s)

gunzip < "$ARCHIVO" | $MYSQL --defaults-extra-file="$CNF_FILE" "$DB_NAME"

FIN=$(date +%s)
DURACION=$((FIN - INICIO))

if [ $? -ne 0 ]; then
    log "ERROR" "Fallo en la restauración. Conservando backup de seguridad."
    exit 1
fi

log "INFO" "Restauración completada en ${DURACION}s."
log "INFO" "Verificando tablas..."

TABLAS=$($MYSQL --defaults-extra-file="$CNF_FILE" -N -e "
    SELECT COUNT(*) FROM information_schema.TABLES
    WHERE TABLE_SCHEMA = '$DB_NAME' AND TABLE_TYPE = 'BASE TABLE'
")

log "INFO" "Tablas restauradas: $TABLAS"

if [ "$TABLAS" -lt 10 ]; then
    log "WARN" "Menos tablas de las esperadas (se esperaban ≥ 14)."
fi

echo ""
echo "✓ Restauración completada"
echo "  Backup de seguridad: $PRE_BACKUP"
echo ""