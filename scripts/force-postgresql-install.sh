#!/bin/bash

# Script para forzar instalación con PostgreSQL
# Bypasses el wizard y ejecuta instalación directa

set -e

echo "================================================"
echo "🔧 INSTALACIÓN FORZADA CON POSTGRESQL"
echo "================================================"
echo ""

# Variables de la base de datos PostgreSQL de Render
DB_HOST="dpg-cs0vj6rv2p9s73cu7gm0-a.oregon-postgres.render.com"
DB_PORT="5432"
DB_NAME="orangehrm_portos_international_t5x3"
DB_USER="orangehrm_portos_international_t5x3_user"
DB_PASS="A5xg14Ns2M4QUQ7bu0fE2GsU6WFzyOaX"

echo "📊 Configuración PostgreSQL:"
echo "   Host: $DB_HOST"
echo "   Puerto: $DB_PORT"
echo "   Base: $DB_NAME"
echo "   Usuario: $DB_USER"
echo ""

cd /var/www/html

# Crear archivo de configuración para PostgreSQL
echo "🔧 Configurando para PostgreSQL..."

# Crear configuración temporal para instalador
mkdir -p /tmp/orangehrm-install

cat > /tmp/orangehrm-install/installer-config.yml <<EOF
database:
  driver: pdo_pgsql
  host: $DB_HOST
  port: $DB_PORT
  name: $DB_NAME
  user: $DB_USER
  password: $DB_PASS
  charset: UTF8
EOF

# Verificar conexión PostgreSQL
echo "🔗 Verificando conexión a PostgreSQL..."
if PGPASSWORD="$DB_PASS" psql -h "$DB_HOST" -p "$DB_PORT" -U "$DB_USER" -d "$DB_NAME" -c "SELECT version();" > /dev/null 2>&1; then
    echo "✅ Conexión exitosa a PostgreSQL"
else
    echo "❌ Error conectando a PostgreSQL"
    exit 1
fi

echo ""
echo "🚀 Ejecutando instalación CLI con PostgreSQL..."

# Usar el comando CLI con driver PostgreSQL específico
php installer/console install:on-new-database \
    --db-type="pgsql" \
    --db-host="$DB_HOST" \
    --db-port="$DB_PORT" \
    --db-name="$DB_NAME" \
    --db-user="$DB_USER" \
    --db-password="$DB_PASS" \
    --admin-username="admin" \
    --admin-password="PortosAdmin123!" \
    --admin-first-name="Administrador" \
    --admin-last-name="Portos" \
    --admin-email="admin@portosinternational.com" \
    --organization-name="Portos International" \
    --country="MX" \
    --timezone="America/Mexico_City" \
    --language="es" \
    --registration-consent \
    --no-interaction

if [ $? -eq 0 ]; then
    echo ""
    echo "✅ INSTALACIÓN COMPLETADA EXITOSAMENTE!"
    echo ""
    echo "🌐 Acceso: https://orangehrm-portos-international.onrender.com"
    echo "👤 Usuario: admin"
    echo "🔑 Contraseña: PortosAdmin123!"
    echo ""
    
    # Aplicar configuraciones personalizadas
    echo "🎨 Aplicando configuraciones Portos International..."
    if [ -f "/var/www/html/scripts/sql/01_portos_organization.sql" ]; then
        PGPASSWORD="$DB_PASS" psql -h "$DB_HOST" -p "$DB_PORT" -U "$DB_USER" -d "$DB_NAME" -f /var/www/html/scripts/sql/01_portos_organization.sql
        echo "✅ Estructura organizacional aplicada"
    fi
    
    if [ -f "/var/www/html/scripts/sql/02_portos_employees.sql" ]; then
        PGPASSWORD="$DB_PASS" psql -h "$DB_HOST" -p "$DB_PORT" -U "$DB_USER" -d "$DB_NAME" -f /var/www/html/scripts/sql/02_portos_employees.sql
        echo "✅ Empleados demo importados"
    fi
    
    if [ -f "/var/www/html/scripts/sql/03_portos_custom_fields.sql" ]; then
        PGPASSWORD="$DB_PASS" psql -h "$DB_HOST" -p "$DB_PORT" -U "$DB_USER" -d "$DB_NAME" -f /var/www/html/scripts/sql/03_portos_custom_fields.sql
        echo "✅ Campos personalizados configurados"
    fi
    
    echo ""
    echo "🎉 SISTEMA PORTOS INTERNATIONAL LISTO!"
else
    echo ""
    echo "❌ Error en la instalación"
    echo "📋 Revisar logs en: /var/www/html/src/log/installer.log"
fi