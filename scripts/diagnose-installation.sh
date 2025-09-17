#!/bin/bash

# Script de diagnóstico para identificar por qué la instalación PostgreSQL no completa
# Analiza logs, base de datos y archivos de configuración

set -e

echo "================================================================="
echo "🔍 DIAGNÓSTICO DE INSTALACIÓN PORTOS INTERNATIONAL"
echo "================================================================="
echo ""
echo "⏰ Tiempo actual: $(date)"
echo ""

# Variables de base de datos
DB_URL=${DATABASE_URL:-""}
if [ -n "$DB_URL" ]; then
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
fi

# DIAGNÓSTICO 1: Verificar procesos en ejecución
echo "🔄 DIAGNÓSTICO 1: Procesos de instalación"
echo "========================================="
if pgrep -f "auto-install-portos.sh" > /dev/null; then
    echo "✅ Script auto-install-portos.sh está corriendo"
    echo "   PID: $(pgrep -f 'auto-install-portos.sh')"
elif pgrep -f "fix-mysql-error-install-postgresql.sh" > /dev/null; then
    echo "✅ Script fix-mysql-error-install-postgresql.sh está corriendo"
    echo "   PID: $(pgrep -f 'fix-mysql-error-install-postgresql.sh')"
elif pgrep -f "setup-portos.sh" > /dev/null; then
    echo "✅ Script setup-portos.sh está corriendo"
    echo "   PID: $(pgrep -f 'setup-portos.sh')"
else
    echo "❌ Ningún script de instalación está corriendo actualmente"
fi
echo ""

# DIAGNÓSTICO 2: Logs de instalación
echo "🗂️ DIAGNÓSTICO 2: Logs de instalación"
echo "======================================"
for log in "/var/log/portos-installation.log" "/var/log/portos-fix-installation.log" "/var/log/portos-setup.log"; do
    if [ -f "$log" ]; then
        echo "📄 $log encontrado:"
        file_size=$(wc -l < "$log" 2>/dev/null || echo "0")
        echo "   Tamaño: $file_size líneas"
        echo "   Últimas 5 líneas:"
        tail -n 5 "$log" 2>/dev/null | sed 's/^/      /'
        echo ""
        
        # Buscar errores específicos
        if grep -q "MySQL server has gone away" "$log" 2>/dev/null; then
            echo "   ❌ Error MySQL detectado en este log"
        fi
        if grep -q "INSTALACIÓN COMPLETADA EXITOSAMENTE" "$log" 2>/dev/null; then
            echo "   ✅ Instalación exitosa reportada en este log"
        fi
        if grep -q "ERROR\|Failed\|failed\|Error" "$log" 2>/dev/null; then
            echo "   ⚠️ Errores detectados:"
            grep -i "error\|failed" "$log" | tail -n 3 | sed 's/^/      /'
        fi
    else
        echo "❌ $log no encontrado"
    fi
done
echo ""

# DIAGNÓSTICO 3: Estado de la base de datos
echo "🗄️ DIAGNÓSTICO 3: Estado de la base de datos"
echo "============================================="
if [ -n "$DB_URL" ]; then
    # Verificar conexión
    if PGPASSWORD="$DB_PASS" psql -h "$DB_HOST" -p "$DB_PORT" -U "$DB_USER" -d "$DB_NAME" -c "SELECT version();" > /dev/null 2>&1; then
        echo "✅ Conexión PostgreSQL exitosa"
        
        # Verificar si OrangeHRM está instalado
        table_count=$(PGPASSWORD="$DB_PASS" psql -h "$DB_HOST" -p "$DB_PORT" -U "$DB_USER" -d "$DB_NAME" -t -c "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='public' AND table_name LIKE 'ohrm_%';" 2>/dev/null | tr -d ' ' || echo "0")
        
        if [ "$table_count" -gt "0" ]; then
            echo "✅ Base de datos OrangeHRM instalada - $table_count tablas encontradas"
            
            # Verificar usuario admin
            admin_exists=$(PGPASSWORD="$DB_PASS" psql -h "$DB_HOST" -p "$DB_PORT" -U "$DB_USER" -d "$DB_NAME" -t -c "SELECT COUNT(*) FROM ohrm_user WHERE user_name='admin';" 2>/dev/null | tr -d ' ' || echo "0")
            if [ "$admin_exists" -gt "0" ]; then
                echo "✅ Usuario admin existe en la base de datos"
            else
                echo "❌ Usuario admin NO existe en la base de datos"
            fi
            
            # Verificar configuración de organización
            org_exists=$(PGPASSWORD="$DB_PASS" psql -h "$DB_HOST" -p "$DB_PORT" -U "$DB_USER" -d "$DB_NAME" -t -c "SELECT COUNT(*) FROM ohrm_organization_gen_info WHERE name LIKE '%Portos%';" 2>/dev/null | tr -d ' ' || echo "0")
            if [ "$org_exists" -gt "0" ]; then
                echo "✅ Configuración Portos International encontrada"
            else
                echo "❌ Configuración Portos International NO encontrada"
            fi
        else
            echo "❌ Base de datos NO contiene tablas OrangeHRM"
        fi
    else
        echo "❌ No se puede conectar a PostgreSQL"
    fi
else
    echo "❌ DATABASE_URL no configurada"
fi
echo ""

# DIAGNÓSTICO 4: Archivos de configuración OrangeHRM
echo "📁 DIAGNÓSTICO 4: Archivos de configuración OrangeHRM"
echo "====================================================="
config_files=(
    "/var/www/html/src/config/database.php"
    "/var/www/html/src/config/database.yaml"
    "/var/www/html/src/config/database.yml"
    "/var/www/html/installer/.installed"
    "/var/www/html/src/config/cryptokey.txt"
)

for file in "${config_files[@]}"; do
    if [ -f "$file" ]; then
        echo "✅ $file existe"
        if [[ "$file" == *"database.php" ]]; then
            echo "   Contenido:"
            head -n 10 "$file" 2>/dev/null | sed 's/^/      /' || echo "      (no se puede leer)"
        fi
    else
        echo "❌ $file NO existe"
    fi
done
echo ""

# DIAGNÓSTICO 5: Estado del instalador web
echo "🌐 DIAGNÓSTICO 5: Estado del instalador web"
echo "==========================================="
installer_dirs=(
    "/var/www/html/installer"
    "/var/www/html/installer/client/dist"
    "/var/www/html/public"
)

for dir in "${installer_dirs[@]}"; do
    if [ -d "$dir" ]; then
        echo "✅ $dir existe"
        file_count=$(ls -1 "$dir" 2>/dev/null | wc -l)
        echo "   Archivos: $file_count"
    else
        echo "❌ $dir NO existe"
    fi
done

# Verificar index.php principal
if [ -f "/var/www/html/index.php" ]; then
    echo "✅ /var/www/html/index.php existe"
else
    echo "❌ /var/www/html/index.php NO existe"
fi

if [ -f "/var/www/html/installer/index.php" ]; then
    echo "✅ /var/www/html/installer/index.php existe"
else
    echo "❌ /var/www/html/installer/index.php NO existe"
fi
echo ""

# DIAGNÓSTICO 6: Detección del problema
echo "🎯 DIAGNÓSTICO 6: Análisis del problema"
echo "======================================"

# Determinar estado actual
if [ -f "/var/log/portos-fix-installation.log" ]; then
    if grep -q "REPARACIÓN COMPLETADA EXITOSAMENTE" "/var/log/portos-fix-installation.log" 2>/dev/null; then
        echo "✅ ESTADO: Reparación PostgreSQL completada exitosamente"
        echo "   🔧 ACCIÓN: El usuario debería refrescar la página"
        echo "   🎯 RESULTADO: Debería ver login en lugar del wizard"
    elif pgrep -f "fix-mysql-error-install-postgresql.sh" > /dev/null; then
        echo "🔄 ESTADO: Reparación PostgreSQL en progreso"
        echo "   ⏳ ACCIÓN: Esperar 1-2 minutos más"
        echo "   🎯 RESULTADO: Monitorear log para completar"
    else
        echo "❌ ESTADO: Reparación PostgreSQL falló o incompleta"
        echo "   🔧 ACCIÓN: Revisar errores en el log"
        echo "   🎯 RESULTADO: Investigar problema específico"
    fi
elif [ -f "/var/log/portos-installation.log" ]; then
    if grep -q "INSTALACIÓN COMPLETADA EXITOSAMENTE" "/var/log/portos-installation.log" 2>/dev/null; then
        echo "✅ ESTADO: Instalación completada exitosamente"
        echo "   🔧 ACCIÓN: El usuario debería refrescar la página"
    elif pgrep -f "auto-install-portos.sh" > /dev/null; then
        echo "🔄 ESTADO: Instalación en progreso"
        echo "   ⏳ ACCIÓN: Esperar 2-3 minutos más"
    else
        echo "❌ ESTADO: Instalación falló o incompleta"
        echo "   🔧 ACCIÓN: Revisar errores en el log"
    fi
else
    echo "❌ ESTADO: No hay logs de instalación disponibles"
    echo "   🔧 ACCIÓN: Verificar por qué no se ejecutaron los scripts"
fi

echo ""
echo "================================================================="
echo "📊 RESUMEN DEL DIAGNÓSTICO"
echo "================================================================="

# Calcular score de completitud
score=0
total_checks=6

# Check 1: Proceso corriendo
if pgrep -f "install-portos\|setup-portos" > /dev/null; then
    echo "✅ [1/6] Proceso de instalación: ACTIVO"
    score=$((score + 1))
else
    echo "❌ [1/6] Proceso de instalación: INACTIVO"
fi

# Check 2: Logs disponibles
if [ -f "/var/log/portos-installation.log" ] || [ -f "/var/log/portos-fix-installation.log" ]; then
    echo "✅ [2/6] Logs de instalación: DISPONIBLES"
    score=$((score + 1))
else
    echo "❌ [2/6] Logs de instalación: NO DISPONIBLES"
fi

# Check 3: Conexión BD
if [ -n "$DB_URL" ] && PGPASSWORD="$DB_PASS" psql -h "$DB_HOST" -p "$DB_PORT" -U "$DB_USER" -d "$DB_NAME" -c "SELECT 1;" > /dev/null 2>&1; then
    echo "✅ [3/6] Conexión PostgreSQL: EXITOSA"
    score=$((score + 1))
else
    echo "❌ [3/6] Conexión PostgreSQL: FALLIDA"
fi

# Check 4: Tablas OrangeHRM
if [ -n "$DB_URL" ] && [ "$(PGPASSWORD="$DB_PASS" psql -h "$DB_HOST" -p "$DB_PORT" -U "$DB_USER" -d "$DB_NAME" -t -c "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='public' AND table_name LIKE 'ohrm_%';" 2>/dev/null | tr -d ' ' || echo '0')" -gt "0" ]; then
    echo "✅ [4/6] Tablas OrangeHRM: INSTALADAS"
    score=$((score + 1))
else
    echo "❌ [4/6] Tablas OrangeHRM: NO INSTALADAS"
fi

# Check 5: Archivos config
if [ -f "/var/www/html/src/config/database.php" ]; then
    echo "✅ [5/6] Configuración OrangeHRM: CREADA"
    score=$((score + 1))
else
    echo "❌ [5/6] Configuración OrangeHRM: NO CREADA"
fi

# Check 6: Instalador web
if [ -d "/var/www/html/installer/client/dist" ]; then
    echo "✅ [6/6] Instalador Vue.js: CONSTRUIDO"
    score=$((score + 1))
else
    echo "❌ [6/6] Instalador Vue.js: NO CONSTRUIDO"
fi

echo ""
echo "📈 PUNTUACIÓN: $score/$total_checks checks pasados"

if [ $score -eq $total_checks ]; then
    echo "🎉 RESULTADO: Sistema completamente funcional"
    echo "   🎯 RECOMENDACIÓN: Refrescar página web para acceder"
elif [ $score -ge 4 ]; then
    echo "⚠️ RESULTADO: Sistema casi funcional"
    echo "   🎯 RECOMENDACIÓN: Revisar checks fallidos y reintentar"
elif [ $score -ge 2 ]; then
    echo "🔧 RESULTADO: Sistema parcialmente funcional"
    echo "   🎯 RECOMENDACIÓN: Ejecutar script de reparación"
else
    echo "❌ RESULTADO: Sistema no funcional"
    echo "   🎯 RECOMENDACIÓN: Instalación manual necesaria"
fi

echo ""
echo "================================================================="
echo ""