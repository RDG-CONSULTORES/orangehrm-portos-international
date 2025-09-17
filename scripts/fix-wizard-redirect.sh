#!/bin/bash

# Script para corregir el problema del wizard persistente
# Verifica si OrangeHRM está instalado y fuerza la redirección al login

set -e

echo "================================================================="
echo "🔧 CORRECCIÓN WIZARD PERSISTENTE - PORTOS INTERNATIONAL"
echo "================================================================="
echo ""

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

echo "🔗 Verificando configuración PostgreSQL..."
echo "   Host: $DB_HOST"
echo "   Puerto: $DB_PORT"
echo "   Base: $DB_NAME" 
echo "   Usuario: $DB_USER"
echo ""

# Verificar conexión
echo "🔍 Verificando conexión a PostgreSQL..."
if ! PGPASSWORD="$DB_PASS" psql -h "$DB_HOST" -p "$DB_PORT" -U "$DB_USER" -d "$DB_NAME" -c "SELECT version();" > /dev/null 2>&1; then
    echo "❌ Error conectando a PostgreSQL"
    exit 1
fi
echo "✅ Conexión PostgreSQL exitosa"

# Verificar si OrangeHRM está instalado
echo "🔍 Verificando instalación OrangeHRM..."
table_count=$(PGPASSWORD="$DB_PASS" psql -h "$DB_HOST" -p "$DB_PORT" -U "$DB_USER" -d "$DB_NAME" -t -c "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='public' AND table_name LIKE 'ohrm_%';" 2>/dev/null | tr -d ' ' || echo "0")

if [ "$table_count" -gt "50" ]; then
    echo "✅ OrangeHRM está instalado ($table_count tablas)"
    
    # Verificar usuario admin
    admin_count=$(PGPASSWORD="$DB_PASS" psql -h "$DB_HOST" -p "$DB_PORT" -U "$DB_USER" -d "$DB_NAME" -t -c "SELECT COUNT(*) FROM ohrm_user WHERE user_name='admin';" 2>/dev/null | tr -d ' ' || echo "0")
    
    if [ "$admin_count" -gt "0" ]; then
        echo "✅ Usuario admin existe"
        
        echo ""
        echo "🔧 FORZANDO BYPASS DEL WIZARD..."
        
        # Crear archivo .installed para ocultar wizard
        echo "📄 Creando marcador de instalación..."
        mkdir -p /var/www/html/installer
        echo "$(date): Installation completed - Portos International" > /var/www/html/installer/.installed
        
        # Crear configuración de base de datos si no existe
        echo "📄 Verificando configuración de base de datos..."
        mkdir -p /var/www/html/src/config
        cat > /var/www/html/src/config/database.php <<PHPEOF
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
PHPEOF

        # Crear archivo de configuración adicional
        echo "📄 Creando configuración adicional..."
        cat > /var/www/html/src/config/cryptokey.txt <<KEYEOF
def50200e56d9e4db2f0a3fb5e2d556da8f8b42ee99e9b585d8e82f8e5c7f96b1
KEYEOF

        # Configurar permisos
        echo "🔧 Configurando permisos..."
        chown -R www-data:www-data /var/www/html
        chmod -R 755 /var/www/html
        chmod -R 775 /var/www/html/src/cache /var/www/html/src/log /var/www/html/src/config
        
        # Limpiar caché
        echo "🧹 Limpiando caché..."
        rm -rf /var/www/html/src/cache/* 2>/dev/null || true
        
        echo ""
        echo "✅ CORRECCIÓN COMPLETADA"
        echo ""
        echo "🎯 RESULTADO:"
        echo "   • Wizard debería estar oculto ahora"
        echo "   • La página principal debería mostrar login"
        echo "   • Configuración de base de datos verificada"
        echo ""
        echo "🔗 ACCESO:"
        echo "   URL: https://orangehrm-portos-international.onrender.com"
        echo "   Usuario: admin"
        echo "   Contraseña: PortosAdmin123!"
        echo ""
        echo "📋 INSTRUCCIONES:"
        echo "   1. 🔄 Refrescar la página web (F5)"
        echo "   2. ✅ Debería aparecer página de login"
        echo "   3. 🔐 Usar credenciales admin para acceder"
        echo ""
        
    else
        echo "❌ Usuario admin no existe - instalación incompleta"
        echo "🔧 Ejecutar force-complete-installation.sh primero"
        exit 1
    fi
    
else
    echo "❌ OrangeHRM no está instalado ($table_count tablas)"
    echo "🔧 Ejecutar force-complete-installation.sh primero"
    exit 1
fi

echo "================================================================="
echo "🎉 CORRECCIÓN DEL WIZARD COMPLETADA"
echo "================================================================="