#!/bin/bash

# Script para corregir el acceso a DATABASE_URL desde PHP web
# Este script debe ejecutarse como parte del proceso de inicio

set -e

echo "================================================================="
echo "🔧 CORRECCIÓN ACCESO DATABASE_URL - PORTOS INTERNATIONAL"
echo "================================================================="
echo ""

# Verificar que DATABASE_URL esté disponible
if [ -z "$DATABASE_URL" ]; then
    echo "❌ ERROR: DATABASE_URL no está disponible en el shell"
    exit 1
fi

echo "✅ DATABASE_URL está disponible en shell"
echo "🔗 DATABASE_URL: ${DATABASE_URL:0:30}..."

# Crear archivo PHP con la configuración
echo "📄 Creando archivo de configuración PHP para acceso web..."

cat > /var/www/html/src/config/env.php <<PHPEOF
<?php
/**
 * Variables de entorno para acceso web
 * Generado automáticamente por fix-database-url-access.sh
 * $(date)
 */

// Variables de entorno del sistema
define('ENV_DATABASE_URL', '$DATABASE_URL');
define('ENV_PORT', '${PORT:-10000}');
define('ENV_TZ', '${TZ:-America/Mexico_City}');

// Función para obtener DATABASE_URL desde cualquier contexto
function getDatabaseUrl() {
    // Primero intentar desde variables de entorno estándar
    \$url = \$_ENV['DATABASE_URL'] ?? \$_SERVER['DATABASE_URL'] ?? getenv('DATABASE_URL');
    
    // Si no está disponible, usar la constante definida
    if (empty(\$url)) {
        \$url = defined('ENV_DATABASE_URL') ? ENV_DATABASE_URL : '';
    }
    
    return \$url;
}

// Función para parsear DATABASE_URL
function parseDatabaseUrl(\$url = null) {
    \$url = \$url ?: getDatabaseUrl();
    
    if (empty(\$url)) {
        return null;
    }
    
    \$matches = [];
    if (preg_match('/postgresql:\/\/([^:]+):([^@]+)@([^:]+):(\d+)\/(.+)/', \$url, \$matches)) {
        return [
            'user' => \$matches[1],
            'password' => \$matches[2],
            'host' => \$matches[3],
            'port' => \$matches[4],
            'dbname' => \$matches[5],
            'full_url' => \$url
        ];
    }
    
    return null;
}
PHPEOF

# Crear archivo de configuración Apache para pasar variables
echo "🔧 Configurando Apache para pasar variables de entorno..."

cat > /etc/apache2/conf-available/env-vars.conf <<APACHEEOF
# Configuración para pasar variables de entorno a PHP
# Generado por fix-database-url-access.sh

PassEnv DATABASE_URL
PassEnv PORT  
PassEnv TZ

# Alternativamente, setear las variables directamente
SetEnv DATABASE_URL "$DATABASE_URL"
SetEnv PORT "${PORT:-10000}"
SetEnv TZ "${TZ:-America/Mexico_City}"
APACHEEOF

# Habilitar la configuración
a2enconf env-vars 2>/dev/null || true

# Verificar configuración
echo "🔍 Verificando archivos creados..."

if [ -f "/var/www/html/src/config/env.php" ]; then
    echo "✅ env.php creado correctamente"
    echo "   Tamaño: $(stat -c%s /var/www/html/src/config/env.php) bytes"
else
    echo "❌ Error creando env.php"
    exit 1
fi

if [ -f "/etc/apache2/conf-available/env-vars.conf" ]; then
    echo "✅ Configuración Apache creada"
    echo "   Archivo: /etc/apache2/conf-available/env-vars.conf"
else
    echo "❌ Error creando configuración Apache"
    exit 1
fi

# Configurar permisos
echo "🔧 Configurando permisos..."
chown www-data:www-data /var/www/html/src/config/env.php
chmod 644 /var/www/html/src/config/env.php

echo ""
echo "✅ CORRECCIÓN COMPLETADA"
echo ""
echo "🎯 RESULTADO:"
echo "   • env.php: Configuración accesible desde PHP web"
echo "   • Apache: Variables de entorno configuradas"
echo "   • Permisos: Configurados correctamente"
echo ""
echo "🔄 SIGUIENTE PASO:"
echo "   • Reiniciar Apache para aplicar cambios"
echo "   • Probar manual-install.php"
echo ""

# Reiniciar Apache en background para no bloquear el script principal
(sleep 2 && service apache2 reload 2>/dev/null || true) &

echo "🎉 ¡CORRECCIÓN DATABASE_URL COMPLETADA!"
echo ""