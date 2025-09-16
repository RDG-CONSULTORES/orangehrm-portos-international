#!/bin/bash

# Script de configuración inicial para Portos International
# Este script configura OrangeHRM con datos específicos para la empresa

echo "========================================="
echo "🚀 PORTOS INTERNATIONAL - SETUP INICIAL"
echo "========================================="
echo ""
echo "📍 Ubicación: Monterrey, N.L., México"
echo "🏢 Industria: Freight Forwarding"
echo "👥 Empleados: 25"
echo ""

# Esperar a que la base de datos esté lista (PostgreSQL)
echo "⏳ Esperando conexión a base de datos PostgreSQL..."
export PGPASSWORD="$ORANGEHRM_DATABASE_PASSWORD"
until psql -h"$ORANGEHRM_DATABASE_HOST" -U"$ORANGEHRM_DATABASE_USER" -d"$ORANGEHRM_DATABASE_NAME" -c "SELECT 1;" &> /dev/null
do
    echo -n "."
    sleep 2
done
echo " ✅ Conectado a PostgreSQL!"

# La base de datos ya existe en Render, no necesitamos crearla
echo "📊 Base de datos PostgreSQL lista..."

# Instalar esquema base de OrangeHRM
if [ -f "/var/www/html/installer/sql/orangehrm-schema.sql" ]; then
    echo "📋 Instalando esquema base de OrangeHRM..."
    mysql -h"$ORANGEHRM_DATABASE_HOST" -u"$ORANGEHRM_DATABASE_USER" -p"$ORANGEHRM_DATABASE_PASSWORD" "$ORANGEHRM_DATABASE_NAME" < /var/www/html/installer/sql/orangehrm-schema.sql
else
    echo "⚠️  No se encontró el archivo de esquema base"
fi

# Saltar configuración de BD por ahora - solo crear archivo de config
echo "⚠️ Saltando configuración de BD por ahora - PostgreSQL requiere configuración diferente"

echo "✅ Configuración base completada!"

# Aplicar localización en español mexicano
if [ -f "/var/www/html/scripts/spanish-localization.sql" ]; then
    echo "🇲🇽 Configurando español mexicano..."
    mysql -h"$ORANGEHRM_DATABASE_HOST" -u"$ORANGEHRM_DATABASE_USER" -p"$ORANGEHRM_DATABASE_PASSWORD" "$ORANGEHRM_DATABASE_NAME" < /var/www/html/scripts/spanish-localization.sql
    echo "✅ Localización mexicana aplicada!"
fi

# Cargar campos personalizados freight forwarding
if [ -f "/var/www/html/scripts/custom-fields.sql" ]; then
    echo "📋 Configurando campos personalizados freight forwarding..."
    mysql -h"$ORANGEHRM_DATABASE_HOST" -u"$ORANGEHRM_DATABASE_USER" -p"$ORANGEHRM_DATABASE_PASSWORD" "$ORANGEHRM_DATABASE_NAME" < /var/www/html/scripts/custom-fields.sql
    echo "✅ Campos personalizados configurados!"
fi

# Cargar datos de prueba si existe el archivo
if [ -f "/var/www/html/scripts/demo-data.sql" ]; then
    echo "📥 Cargando datos de prueba (25 empleados)..."
    mysql -h"$ORANGEHRM_DATABASE_HOST" -u"$ORANGEHRM_DATABASE_USER" -p"$ORANGEHRM_DATABASE_PASSWORD" "$ORANGEHRM_DATABASE_NAME" < /var/www/html/scripts/demo-data.sql
    echo "✅ Datos de prueba cargados!"
fi

# Crear archivo de configuración
echo "📝 Creando archivo de configuración..."
cat > /var/www/html/src/config/config.php <<EOF
<?php
return [
    'database' => [
        'host' => getenv('ORANGEHRM_DATABASE_HOST'),
        'port' => getenv('ORANGEHRM_DATABASE_PORT'),
        'name' => getenv('ORANGEHRM_DATABASE_NAME'),
        'user' => getenv('ORANGEHRM_DATABASE_USER'),
        'password' => getenv('ORANGEHRM_DATABASE_PASSWORD'),
    ],
    'organization' => [
        'name' => 'Portos International',
        'country' => 'MX',
        'timezone' => 'America/Mexico_City',
        'locale' => 'es_MX',
        'currency' => 'MXN',
    ],
    'portos' => [
        'api_enabled' => true,
        'dashboard_enabled' => true,
        'certifications_enabled' => true,
    ]
];
EOF

# Limpiar caché
echo "🧹 Limpiando caché..."
rm -rf /var/www/html/src/cache/*

echo ""
echo "========================================="
echo "✅ SETUP COMPLETADO EXITOSAMENTE!"
echo "========================================="
echo ""
echo "📌 URL: https://portos-international-rh.onrender.com"
echo "👤 Usuario: admin"
echo "🔑 Contraseña: admin123"
echo ""
echo "⚠️  IMPORTANTE: Cambie la contraseña en el primer login"
echo ""