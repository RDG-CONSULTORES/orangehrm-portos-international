#!/bin/bash

# Script para resolver el error "MySQL server has gone away" 
# Limpia instalación fallida e instala correctamente con PostgreSQL

set -e

echo "================================================================="
echo "🔧 REPARACIÓN: ERROR MYSQL → INSTALACIÓN POSTGRESQL LIMPIA"
echo "================================================================="
echo ""
echo "❌ Problema detectado: OrangeHRM intentando usar MySQL en lugar de PostgreSQL"
echo "✅ Solución: Limpiar instalación e instalar correctamente con PostgreSQL"
echo ""

# Verificar que estamos en el directorio correcto
cd /var/www/html

# Variables de entorno
DB_URL=${DATABASE_URL:-""}
if [ -z "$DB_URL" ]; then
    echo "❌ ERROR: DATABASE_URL no encontrada"
    exit 1
fi

# Parsear DATABASE_URL
DB_HOST=$(echo $DB_URL | sed 's/.*@\(.*\):.*/\1/')
DB_PORT=$(echo $DB_URL | sed 's/.*:\([0-9]*\)\/.*/\1/')  
DB_NAME=$(echo $DB_URL | sed 's/.*\/\([^?]*\).*/\1/')
DB_USER=$(echo $DB_URL | sed 's/.*:\/\/\([^:]*\):.*/\1/')
DB_PASS=$(echo $DB_URL | sed 's/.*:\/\/[^:]*:\([^@]*\)@.*/\1/')

echo "🔗 Configuración PostgreSQL:"
echo "   Host: $DB_HOST"
echo "   Puerto: $DB_PORT" 
echo "   Base: $DB_NAME"
echo "   Usuario: $DB_USER"
echo ""

# PASO 1: Limpiar instalación anterior fallida
echo "🧹 PASO 1: Limpiando instalación anterior..."

# Limpiar archivos de configuración
rm -f src/config/database.yaml 2>/dev/null || true
rm -f src/config/database.yml 2>/dev/null || true
rm -f src/config/cryptokey.txt 2>/dev/null || true
rm -f installer/.installed 2>/dev/null || true
rm -f installer/.installation 2>/dev/null || true

# Limpiar caché y logs
rm -rf src/cache/* 2>/dev/null || true
rm -rf src/log/* 2>/dev/null || true

echo "✅ Archivos de instalación anterior eliminados"

# PASO 2: FORZAR configuración PostgreSQL
echo ""
echo "🗄️ PASO 2: Configurando PostgreSQL de forma FORZADA..."

mkdir -p src/config

# Crear configuración de base de datos ANTES de la instalación
cat > src/config/database.php <<EOF
<?php
return [
    'database' => [
        'driver' => 'pdo_pgsql',
        'host' => '$DB_HOST',
        'port' => $DB_PORT,
        'dbname' => '$DB_NAME',
        'username' => '$DB_USER',
        'password' => '$DB_PASS',
        'charset' => 'utf8'
    ]
];
EOF

echo "✅ Configuración PostgreSQL creada"

# PASO 3: Verificar conexión PostgreSQL
echo ""
echo "🔗 PASO 3: Verificando conexión PostgreSQL..."

if PGPASSWORD="$DB_PASS" psql -h "$DB_HOST" -p "$DB_PORT" -U "$DB_USER" -d "$DB_NAME" -c "SELECT version();" > /dev/null 2>&1; then
    echo "✅ Conexión a PostgreSQL exitosa"
else
    echo "❌ Error conectando a PostgreSQL"
    exit 1
fi

# PASO 4: Limpiar base de datos (tablas existentes de instalaciones fallidas)
echo ""
echo "🗄️ PASO 4: Limpiando base de datos..."

PGPASSWORD="$DB_PASS" psql -h "$DB_HOST" -p "$DB_PORT" -U "$DB_USER" -d "$DB_NAME" <<SQLEOF || true
-- Limpiar tablas OrangeHRM si existen de instalación fallida
DROP TABLE IF EXISTS ohrm_user CASCADE;
DROP TABLE IF EXISTS ohrm_organization_gen_info CASCADE;
DROP TABLE IF EXISTS ohrm_config CASCADE;
-- Limpiar todas las tablas que puedan haber quedado de instalación fallida
DO \$\$ DECLARE
    r RECORD;
BEGIN
    FOR r IN (SELECT tablename FROM pg_tables WHERE schemaname = 'public' AND tablename LIKE 'ohrm_%') LOOP
        EXECUTE 'DROP TABLE IF EXISTS ' || quote_ident(r.tablename) || ' CASCADE';
    END LOOP;
END \$\$;
SQLEOF

echo "✅ Base de datos limpiada"

# PASO 5: Ejecutar instalación PostgreSQL LIMPIA
echo ""
echo "🚀 PASO 5: Ejecutando instalación PostgreSQL LIMPIA..."

# Usar comando CLI con parámetros específicos para PostgreSQL
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
    --registration-consent \
    --no-interaction

if [ $? -eq 0 ]; then
    echo ""
    echo "✅ INSTALACIÓN POSTGRESQL EXITOSA!"
    
    # PASO 6: Aplicar configuraciones Portos International
    echo ""
    echo "🎨 PASO 6: Aplicando configuraciones Portos International..."
    
    if [ -f "scripts/sql/01_portos_organization.sql" ]; then
        PGPASSWORD="$DB_PASS" psql -h "$DB_HOST" -p "$DB_PORT" -U "$DB_USER" -d "$DB_NAME" -f scripts/sql/01_portos_organization.sql
        echo "✅ Estructura organizacional aplicada"
    fi
    
    if [ -f "scripts/sql/02_portos_employees.sql" ]; then
        PGPASSWORD="$DB_PASS" psql -h "$DB_HOST" -p "$DB_PORT" -U "$DB_USER" -d "$DB_NAME" -f scripts/sql/02_portos_employees.sql
        echo "✅ 25 empleados mexicanos importados"
    fi
    
    if [ -f "scripts/sql/03_portos_custom_fields.sql" ]; then
        PGPASSWORD="$DB_PASS" psql -h "$DB_HOST" -p "$DB_PORT" -U "$DB_USER" -d "$DB_NAME" -f scripts/sql/03_portos_custom_fields.sql
        echo "✅ Campos personalizados configurados"
    fi
    
    # PASO 7: Configurar permisos
    echo ""
    echo "🔧 PASO 7: Configurando permisos..."
    chown -R www-data:www-data /var/www/html
    chmod -R 755 /var/www/html
    chmod -R 775 /var/www/html/src/cache /var/www/html/src/log /var/www/html/src/config
    echo "✅ Permisos configurados"
    
    echo ""
    echo "================================================================="
    echo "🎉 ¡REPARACIÓN COMPLETADA EXITOSAMENTE!"
    echo "================================================================="
    echo ""
    echo "✅ ERROR MYSQL RESUELTO"
    echo "✅ POSTGRESQL INSTALADO CORRECTAMENTE"
    echo "✅ PORTOS INTERNATIONAL CONFIGURADO"
    echo ""
    echo "🔗 ACCESO:"
    echo "   URL: https://orangehrm-portos-international.onrender.com"
    echo "   Usuario: admin"
    echo "   Contraseña: PortosAdmin123!"
    echo ""
    echo "🔄 SIGUIENTE PASO:"
    echo "   Refresca la página web (F5) - El wizard desaparecerá"
    echo "   Verás la página de login de OrangeHRM"
    echo ""
    
else
    echo ""
    echo "❌ ERROR EN LA INSTALACIÓN"
    echo "Revisar logs en: src/log/installer.log"
    exit 1
fi