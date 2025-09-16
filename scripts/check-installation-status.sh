#!/bin/bash

# Script para verificar el estado de la instalación de Portos International

set -e

echo "================================================="
echo "🔍 VERIFICANDO ESTADO DE INSTALACIÓN"
echo "================================================="
echo ""

# Función para mostrar spinner
show_spinner() {
    local pid=$1
    local delay=0.1
    local spinstr='|/-\'
    while [ "$(ps a | awk '{print $1}' | grep $pid)" ]; do
        local temp=${spinstr#?}
        printf " [%c]  " "$spinstr"
        local spinstr=$temp${spinstr%"$temp"}
        sleep $delay
        printf "\b\b\b\b\b\b"
    done
    printf "    \b\b\b\b"
}

# Verificar si existe el log de instalación
if [ -f "/var/log/portos-installation.log" ]; then
    echo "📋 Log de instalación encontrado"
    echo ""
    
    # Verificar si el proceso está corriendo
    if pgrep -f "auto-install-portos.sh" > /dev/null; then
        echo "⏳ La instalación está EN PROGRESO..."
        echo ""
        echo "🔄 El script auto-install-portos.sh está ejecutándose"
        echo ""
        
        # Mostrar últimas líneas del log
        echo "📊 Progreso actual:"
        echo "-------------------"
        tail -n 10 /var/log/portos-installation.log 2>/dev/null || echo "   (esperando contenido del log...)"
        echo ""
        
        echo "⚠️  Por favor ESPERA 1-2 minutos más para que complete"
        echo ""
        echo "💡 Sugerencia: Refresca la página en 60 segundos"
        echo "   El wizard desaparecerá cuando la instalación termine"
        
    else
        echo "✅ El proceso de instalación ha TERMINADO"
        echo ""
        
        # Verificar si fue exitoso
        if grep -q "INSTALACIÓN COMPLETADA EXITOSAMENTE" /var/log/portos-installation.log 2>/dev/null; then
            echo "🎉 ¡Instalación EXITOSA!"
            echo ""
            echo "🔧 Acciones requeridas:"
            echo "   1. Refresca la página web AHORA (F5)"
            echo "   2. Deberías ver la página de login de OrangeHRM"
            echo "   3. Accede con:"
            echo "      Usuario: admin"
            echo "      Contraseña: PortosAdmin123!"
            echo ""
            
            # Verificar archivo de instalación completa
            if [ -f "/var/www/html/PORTOS_INSTALLATION_COMPLETE.txt" ]; then
                echo "📄 Reporte completo disponible en:"
                echo "   /var/www/html/PORTOS_INSTALLATION_COMPLETE.txt"
            fi
            
        else
            echo "❌ La instalación encontró problemas"
            echo ""
            echo "📋 Últimas líneas del log:"
            echo "-------------------------"
            tail -n 20 /var/log/portos-installation.log 2>/dev/null || echo "No hay log disponible"
            echo ""
            echo "🔧 Acciones sugeridas:"
            echo "   1. Revisar el log completo: cat /var/log/portos-installation.log"
            echo "   2. Intentar instalación manual vía wizard"
        fi
    fi
else
    echo "⚠️  No se encontró log de instalación"
    echo ""
    echo "Verificando si OrangeHRM está instalado..."
    
    # Verificar si existe config de OrangeHRM
    if [ -f "/var/www/html/src/config/database.php" ] || [ -d "/var/www/html/src/cache" ]; then
        echo "✅ OrangeHRM parece estar instalado"
        echo ""
        echo "🔧 Prueba refrescar la página (F5)"
        echo "   Si el wizard persiste, puede requerir instalación manual"
    else
        echo "❌ OrangeHRM NO está instalado"
        echo ""
        echo "🔧 El wizard debería permitirte completar la instalación"
        echo "   Usa las credenciales de PostgreSQL proporcionadas"
    fi
fi

echo ""
echo "================================================="
echo "📊 INFORMACIÓN ADICIONAL"
echo "================================================="

# Verificar servicios
echo ""
echo "🌐 Apache Status:"
if pgrep apache2 > /dev/null; then
    echo "   ✅ Apache está corriendo"
else
    echo "   ❌ Apache NO está corriendo"
fi

# Verificar archivos importantes
echo ""
echo "📁 Archivos clave:"
[ -f "/var/www/html/index.php" ] && echo "   ✅ index.php presente" || echo "   ❌ index.php faltante"
[ -d "/var/www/html/src/vendor" ] && echo "   ✅ Composer dependencies OK" || echo "   ❌ Composer dependencies faltantes"
[ -d "/var/www/html/installer/client/dist" ] && echo "   ✅ Vue.js installer OK" || echo "   ❌ Vue.js installer faltante"

echo ""
echo "🕐 Hora actual del servidor: $(date)"
echo ""