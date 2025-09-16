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

# Esperar a que la base de datos esté lista
echo "⏳ Esperando conexión a base de datos..."
until mysql -h"$ORANGEHRM_DATABASE_HOST" -u"$ORANGEHRM_DATABASE_USER" -p"$ORANGEHRM_DATABASE_PASSWORD" -e "SELECT 1" &> /dev/null
do
    echo -n "."
    sleep 2
done
echo " ✅ Conectado!"

# Crear base de datos si no existe
echo "📊 Preparando base de datos..."
mysql -h"$ORANGEHRM_DATABASE_HOST" -u"$ORANGEHRM_DATABASE_USER" -p"$ORANGEHRM_DATABASE_PASSWORD" <<EOF
CREATE DATABASE IF NOT EXISTS $ORANGEHRM_DATABASE_NAME CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish_ci;
USE $ORANGEHRM_DATABASE_NAME;
EOF

# Instalar esquema base de OrangeHRM
if [ -f "/var/www/html/installer/sql/orangehrm-schema.sql" ]; then
    echo "📋 Instalando esquema base de OrangeHRM..."
    mysql -h"$ORANGEHRM_DATABASE_HOST" -u"$ORANGEHRM_DATABASE_USER" -p"$ORANGEHRM_DATABASE_PASSWORD" "$ORANGEHRM_DATABASE_NAME" < /var/www/html/installer/sql/orangehrm-schema.sql
else
    echo "⚠️  No se encontró el archivo de esquema base"
fi

# Cargar estructura organizacional completa
if [ -f "/var/www/html/scripts/portos-structure.sql" ]; then
    echo "🏢 Cargando estructura organizacional de Portos..."
    mysql -h"$ORANGEHRM_DATABASE_HOST" -u"$ORANGEHRM_DATABASE_USER" -p"$ORANGEHRM_DATABASE_PASSWORD" "$ORANGEHRM_DATABASE_NAME" < /var/www/html/scripts/portos-structure.sql
    echo "✅ Estructura organizacional cargada!"
else
    # Configuración básica si no existe el archivo completo
    echo "🔧 Aplicando configuraciones básicas..."
    mysql -h"$ORANGEHRM_DATABASE_HOST" -u"$ORANGEHRM_DATABASE_USER" -p"$ORANGEHRM_DATABASE_PASSWORD" "$ORANGEHRM_DATABASE_NAME" <<'EOF'

-- Configuración mínima de la organización
INSERT INTO ohrm_organization_gen_info (name, country, phone, email) 
VALUES ('Portos International', 'MX', '+52 81 8123 4567', 'info@portosinternational.com')
ON DUPLICATE KEY UPDATE name=VALUES(name);

-- Usuario administrador
INSERT INTO ohrm_user (id, user_role_id, emp_number, user_name, user_password, date_entered, date_modified, created_by) 
VALUES (1, 1, 1, 'admin', MD5('admin123'), NOW(), NOW(), 1)
ON DUPLICATE KEY UPDATE user_name=VALUES(user_name);

EOF
fi

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