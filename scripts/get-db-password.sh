#!/bin/bash

# Script simple para extraer la contraseña de DATABASE_URL
# Útil para el wizard de instalación manual

echo "🔐 EXTRACTOR DE CONTRASEÑA POSTGRESQL"
echo "===================================="
echo ""
echo "Este script extrae la contraseña de la DATABASE_URL de Render"
echo ""

# Si DATABASE_URL existe, extraer componentes
if [ ! -z "$DATABASE_URL" ]; then
    # Extraer contraseña de DATABASE_URL
    # Formato: postgres://usuario:contraseña@host:puerto/database
    
    DB_PASS=$(echo $DATABASE_URL | sed 's/.*:\/\/[^:]*:\([^@]*\)@.*/\1/')
    
    echo "📊 DATABASE_URL detectada!"
    echo ""
    echo "🔑 CONTRASEÑA: $DB_PASS"
    echo ""
    echo "📝 Copia esta contraseña y úsala en el wizard de instalación"
    echo ""
else
    echo "❌ DATABASE_URL no encontrada"
    echo ""
    echo "Para ejecutar este script en Render:"
    echo "1. Conéctate al shell del servicio"
    echo "2. Ejecuta: /var/www/html/scripts/get-db-password.sh"
fi