#!/bin/bash
# ============================================================
# FARMACIA VIDAPLUS — Script de respaldo automatizado
# Archivo: sql/06_backup.sh
# Uso:    ./06_backup.sh [tipo]
#         tipo = full (default) | schema | data
# ============================================================
# Requisitos:
#   - mysqldump en el PATH (o definir MYSQLDUMP abajo)
#   - config/mysql-backup.cnf con las credenciales
# ============================================================

# ------------------------------------------------------------
# CONFIGURACIÓN (ajustar según el entorno)
# ------------------------------------------------------------
set -u  # error si se usa variable no definida

# Detección de rutas relativas al script
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_DIR="$(dirname "$SCRIPT_DIR")"
BACKUP_DIR="$PROJECT_DIR/backups"
CNF_FILE="$PROJECT_DIR/config/mysql-backup.cnf"
LOG_DIR="$BACKUP_DIR/logs"
LOG_FILE="$LOG_DIR/backup.log"

# Base de datos
DB_NAME="vidaplus"

# Retención (días)
RETENCION_DIAS=30

# mysqldump — si no está en PATH, descomenta la ruta de Laragon
if command -v mysqldump &> /dev/null; then
    MYSQLDUMP="mysqldump"
# elif [ -f "/c/laragon/bin/mysql/mysql-8.4.3-winx64/bin/mysqldump.exe" ]; then
#     MYSQLDUMP="/c/laragon/bin/mysql/mysql-8.4.3-winx64/bin/mysqldump.exe"
else
    echo "ERROR: mysqldump no encontrado en PATH." >&2
    echo "       Configura MYSQLDUMP con la ruta completa." >&2
    exit 1
fi

# Tipo de backup
TIPO="${1:-full}"
case "$TIPO" in
    full)   OPCIONES="--routines --triggers --events" ;;
    schema) OPCIONES="--no-data --routines --triggers --events" ;;
    data)   OPCIONES="--no-create-info" ;;
    *)      echo "ERROR: tipo '$TIPO' inválido. Usa: full | schema | data" >&2; exit 1 ;;
esac

# ------------------------------------------------------------
# FUNCIONES
# ------------------------------------------------------------
log() {
    local nivel="$1"; shift
    local mensaje="$*"
    local ts=$(date '+%Y-%m-%d %H:%M:%S')
    echo "[$ts] [$nivel] $mensaje" | tee -a "$LOG_FILE"
}

# Trap de errores
trap 'log "ERROR" "Fallo en la línea $LINENO. Abortando."; exit 1' ERR

# ------------------------------------------------------------
# PREPARACIÓN
# ------------------------------------------------------------
mkdir -p "$BACKUP_DIR" "$LOG_DIR"

# Verificar credenciales
if [ ! -f "$CNF_FILE" ]; then
    log "ERROR" "No se encontró $CNF_FILE"
    exit 1
fi

# Verificar conexión a MySQL
if ! mysql --defaults-extra-file="$CNF_FILE" -e "USE $DB_NAME" 2>/dev/null; then
    log "ERROR" "No se puede conectar a MySQL o la BD $DB_NAME no existe."
    exit 1
fi

# Espacio en disco (mínimo 500 MB libres)
ESPACIO_KB=$(df -k "$BACKUP_DIR" | awk 'NR==2 {print $4}')
if [ "$ESPACIO_KB" -lt 512000 ]; then
    log "WARN" "Espacio libre bajo: $((ESPACIO_KB/1024)) MB"
fi

# ------------------------------------------------------------
# EJECUTAR BACKUP
# ------------------------------------------------------------
TIMESTAMP=$(date '+%Y-%m-%d_%H-%M-%S')
ARCHIVO="$BACKUP_DIR/${DB_NAME}_${TIPO}_${TIMESTAMP}.sql.gz"

log "INFO" "=== Iniciando backup tipo '$TIPO' ==="
log "INFO" "Archivo destino: $ARCHIVO"

INICIO=$(date +%s)

# mysqldump:
#   --single-transaction: snapshot consistente sin bloquear (InnoDB)
#   --quick:             no carga todo en memoria
#   --set-gtid-purged=OFF: sin info de replicación (no usamos GTID)
#   --hex-blob:           binarios en hexadecimal (seguro para UTF-8)
mysqldump \
    --defaults-extra-file="$CNF_FILE" \
    --single-transaction \
    --quick \
    --set-gtid-purged=OFF \
    --hex-blob \
    --default-character-set=utf8mb4 \
    $OPCIONES \
    "$DB_NAME" | gzip > "$ARCHIVO"

FIN=$(date +%s)
DURACION=$((FIN - INICIO))

# ------------------------------------------------------------
# VERIFICAR EL BACKUP
# ------------------------------------------------------------
if [ ! -f "$ARCHIVO" ]; then
    log "ERROR" "El archivo de backup no se creó."
    exit 1
fi

TAMANO_BYTES=$(stat -c%s "$ARCHIVO" 2>/dev/null || stat -f%z "$ARCHIVO" 2>/dev/null)
if [ "$TAMANO_BYTES" -lt 1024 ]; then
    log "ERROR" "El archivo de backup es demasiado pequeño ($TAMANO_BYTES bytes)."
    rm -f "$ARCHIVO"
    exit 1
fi

# Verificar que el .gz no esté corrupto
if ! gzip -t "$ARCHIVO" 2>/dev/null; then
    log "ERROR" "El archivo .gz está corrupto."
    exit 1
fi

TAMANO_KB=$((TAMANO_BYTES / 1024))
log "INFO" "Backup verificado: ${TAMANO_KB} KB, duración ${DURACION}s"

# ------------------------------------------------------------
# LIMPIEZA DE BACKUPS ANTIGUOS
# ------------------------------------------------------------
log "INFO" "Limpiando backups con más de $RETENCION_DIAS días..."
find "$BACKUP_DIR" -name "${DB_NAME}_*.sql.gz" -type f -mtime +$RETENCION_DIAS -delete 2>/dev/null
ELIMINADOS=$(find "$BACKUP_DIR" -name "${DB_NAME}_*.sql.gz" -type f -mtime +$RETENCION_DIAS 2>/dev/null | wc -l)
log "INFO" "Limpieza completada."

# ------------------------------------------------------------
# RESUMEN
# ------------------------------------------------------------
TOTAL_BACKUPS=$(find "$BACKUP_DIR" -name "${DB_NAME}_*.sql.gz" -type f | wc -l)
log "INFO" "=== Backup completado ==="
log "INFO" "Total de backups disponibles: $TOTAL_BACKUPS"

# Salida para que el llamador PHP pueda parsear
echo "OK|$ARCHIVO|$TAMANO_KB|$DURACION"
exit 0