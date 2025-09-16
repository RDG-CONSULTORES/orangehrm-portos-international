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

# Aplicar configuraciones personalizadas para Portos
echo "🔧 Aplicando configuraciones personalizadas..."
mysql -h"$ORANGEHRM_DATABASE_HOST" -u"$ORANGEHRM_DATABASE_USER" -p"$ORANGEHRM_DATABASE_PASSWORD" "$ORANGEHRM_DATABASE_NAME" <<'EOF'

-- Configuración de la organización
INSERT INTO ohrm_organization_gen_info (name, address, zip_code, city, state, country, phone, email, note) 
VALUES (
    'Portos International',
    'Monterrey, Nuevo León',
    '64000',
    'Monterrey',
    'Nuevo León',
    'MX',
    '+52 81 1234 5678',
    'info@portosinternational.com',
    'Freight Forwarder especializado en comercio internacional México-USA'
) ON DUPLICATE KEY UPDATE name=VALUES(name);

-- Estructura organizacional de Portos International
INSERT INTO ohrm_subunit (id, name, unit_id, description, lft, rgt, level) VALUES
(1, 'Portos International', '', 'Headquarters', 1, 14, 0),
(2, 'Administración', '1', 'Gestión administrativa y recursos humanos', 2, 3, 1),
(3, 'Operaciones Marítimas', '1', 'Gestión de carga marítima y documentación', 4, 5, 1),
(4, 'Operaciones Aéreas', '1', 'Gestión de carga aérea y coordinación', 6, 7, 1),
(5, 'Operaciones Terrestres', '1', 'Transporte terrestre y cross-border', 8, 9, 1),
(6, 'Comercial/Customer Service', '1', 'Ventas y servicio al cliente', 10, 11, 1),
(7, 'Compliance/Legal', '1', 'Cumplimiento normativo y asuntos legales', 12, 13, 1)
ON DUPLICATE KEY UPDATE name=VALUES(name);

-- Puestos específicos de freight forwarding
INSERT INTO ohrm_job_title (id, job_title, job_description, note, is_deleted) VALUES
(1, 'Gerente General', 'Dirección general de la empresa', 'Reporta a casa matriz', 0),
(2, 'Coordinador RH', 'Gestión de recursos humanos', 'Administración', 0),
(3, 'Asistente Administrativo', 'Apoyo administrativo general', 'Administración', 0),
(4, 'Manager Operaciones Marítimas', 'Gestión de operaciones marítimas', 'Ocean freight', 0),
(5, 'Senior Ocean Coordinator', 'Coordinación senior carga marítima', 'LCL/FCL', 0),
(6, 'Ocean Coordinator', 'Coordinación carga marítima', 'LCL/FCL', 0),
(7, 'Manager Operaciones Aéreas', 'Gestión de operaciones aéreas', 'Air freight', 0),
(8, 'Senior Air Coordinator', 'Coordinación senior carga aérea', 'Import/Export', 0),
(9, 'Air Coordinator', 'Coordinación carga aérea', 'Import/Export', 0),
(10, 'Cross-Border Coordinator', 'Coordinación cruces fronterizos', 'USA-MEX', 0),
(11, 'Trucking Coordinator', 'Coordinación transporte terrestre', 'FTL/LTL', 0),
(12, 'Documentation Specialist', 'Especialista en documentación', 'BL/AWB', 0),
(13, 'Sales Manager', 'Gestión comercial y ventas', 'B2B', 0),
(14, 'Account Manager', 'Gestión de cuentas clave', 'Key accounts', 0),
(15, 'Customer Service Specialist', 'Atención al cliente', 'Tracking', 0),
(16, 'Compliance Officer', 'Oficial de cumplimiento', 'CTPAT/OEA', 0),
(17, 'Customs Specialist', 'Especialista aduanal', 'Clasificación', 0)
ON DUPLICATE KEY UPDATE job_title=VALUES(job_title);

-- Configuración de días festivos México 2025
INSERT INTO ohrm_holiday (id, description, date, length, recurring, operational_country) VALUES
(1, 'Año Nuevo', '2025-01-01', 0, 1, 484),
(2, 'Día de la Constitución', '2025-02-03', 0, 1, 484),
(3, 'Natalicio de Benito Juárez', '2025-03-17', 0, 1, 484),
(4, 'Jueves Santo', '2025-04-17', 0, 0, 484),
(5, 'Viernes Santo', '2025-04-18', 0, 0, 484),
(6, 'Día del Trabajo', '2025-05-01', 0, 1, 484),
(7, 'Día de la Independencia', '2025-09-16', 0, 1, 484),
(8, 'Revolución Mexicana', '2025-11-17', 0, 1, 484),
(9, 'Navidad', '2025-12-25', 0, 1, 484)
ON DUPLICATE KEY UPDATE description=VALUES(description);

-- Campos personalizados para certificaciones freight forwarding
INSERT INTO ohrm_custom_field (field_num, name, type, screen, extra_data) VALUES
(1, 'IATA Certification', 1, 'personal', 'a:2:{s:13:"drop_down_values";s:7:"Yes,No";}'),
(2, 'IATA Expiry Date', 0, 'personal', NULL),
(3, 'Customs Broker License', 0, 'personal', NULL),
(4, 'Customs License Expiry', 0, 'personal', NULL),
(5, 'Dangerous Goods Cert', 1, 'personal', 'a:2:{s:13:"drop_down_values";s:29:"None,Level 1,Level 2,Level 3";}'),
(6, 'DG Cert Expiry', 0, 'personal', NULL),
(7, 'CTPAT Certification', 1, 'personal', 'a:2:{s:13:"drop_down_values";s:7:"Yes,No";}'),
(8, 'CTPAT Expiry', 0, 'personal', NULL),
(9, 'English Level', 1, 'personal', 'a:2:{s:13:"drop_down_values";s:36:"Basic,Intermediate,Advanced,Native";}'),
(10, 'Trade Lanes Experience', 0, 'personal', NULL),
(11, 'Customer Portfolio', 0, 'personal', NULL),
(12, 'Incoterms Knowledge', 1, 'personal', 'a:2:{s:13:"drop_down_values";s:26:"Beginner,Intermediate,Expert";}'),
(13, 'CargoWise User ID', 0, 'personal', NULL),
(14, 'Cost Center Code', 0, 'contact', NULL),
(15, 'Monthly Revenue Target', 0, 'contact', NULL)
ON DUPLICATE KEY UPDATE name=VALUES(name);

-- Configuración del usuario administrador
INSERT INTO ohrm_user (id, user_role_id, emp_number, user_name, user_password, date_entered, date_modified, created_by) VALUES
(1, 1, 1, 'admin', MD5('admin123'), NOW(), NOW(), 1)
ON DUPLICATE KEY UPDATE user_name=VALUES(user_name);

-- Configuración de idioma español
UPDATE ohrm_i18n_language SET name = 'Español (México)' WHERE code = 'es';

-- Activar módulos necesarios
UPDATE ohrm_module SET status = 1 WHERE name IN ('core', 'admin', 'pim', 'leave', 'time', 'attendance', 'recruitment');

EOF

echo "✅ Configuración base completada!"

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