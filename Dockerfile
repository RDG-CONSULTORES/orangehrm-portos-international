# OrangeHRM Dockerfile optimizado para Render y Portos International con PostgreSQL forzado
FROM php:8.3-apache-bookworm

# Variables de entorno para México
ENV TZ=America/Mexico_City
ENV LANG=es_MX.UTF-8
ENV LC_ALL=es_MX.UTF-8

# Configuración PHP
RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"

# Instalar dependencias del sistema
RUN set -ex; \
	savedAptMark="$(apt-mark showmanual)"; \
	apt-get update; \
	apt-get install -y --no-install-recommends \
		libfreetype6-dev \
		libjpeg-dev \
		libpng-dev \
		libzip-dev \
		libldap2-dev \
		libicu-dev \
		libpq-dev \
		unzip \
		git \
		curl \
		locales \
		postgresql-client \
	; \
	\
	# Configurar timezone y locale
	ln -snf /usr/share/zoneinfo/$TZ /etc/localtime && echo $TZ > /etc/timezone; \
	sed -i 's/# es_MX.UTF-8/es_MX.UTF-8/' /etc/locale.gen; \
	locale-gen; \
	\
	# Configurar extensiones PHP
	docker-php-ext-configure gd --with-freetype --with-jpeg; \
	docker-php-ext-configure ldap \
	    --with-libdir=lib/$(uname -m)-linux-gnu/ \
	; \
	\
	docker-php-ext-install -j "$(nproc)" \
		gd \
		opcache \
		intl \
		pdo_mysql \
		pdo_pgsql \
		pgsql \
		mysqli \
		zip \
		ldap \
		bcmath \
	; \
	\
	# Limpiar
	apt-mark auto '.*' > /dev/null; \
	apt-mark manual $savedAptMark; \
	ldd "$(php -r 'echo ini_get("extension_dir");')"/*.so \
		| awk '/=>/ { so = $(NF-1); if (index(so, "/usr/local/") == 1) { next }; gsub("^/(usr/)?", "", so); print so }' \
		| sort -u \
		| xargs -r dpkg-query -S \
		| cut -d: -f1 \
		| sort -u \
		| xargs -rt apt-mark manual; \
	\
	apt-get purge -y --auto-remove -o APT::AutoRemove::RecommendsImportant=false; \
	rm -rf /var/cache/apt/archives; \
	rm -rf /var/lib/apt/lists/*

# Configurar OPCache
RUN { \
		echo 'opcache.memory_consumption=128'; \
		echo 'opcache.interned_strings_buffer=8'; \
		echo 'opcache.max_accelerated_files=4000'; \
		echo 'opcache.revalidate_freq=60'; \
		echo 'opcache.fast_shutdown=1'; \
		echo 'opcache.enable_cli=1'; \
	} > /usr/local/etc/php/conf.d/opcache-recommended.ini; \
	\
	if command -v a2enmod; then \
		a2enmod rewrite headers; \
	fi;

# Configurar PHP para México
RUN { \
		echo 'date.timezone = America/Mexico_City'; \
		echo 'intl.default_locale = es_MX'; \
		echo 'session.gc_maxlifetime = 3600'; \
		echo 'upload_max_filesize = 50M'; \
		echo 'post_max_size = 50M'; \
		echo 'memory_limit = 256M'; \
		echo 'max_execution_time = 300'; \
	} > /usr/local/etc/php/conf.d/portos-custom.ini

# Copiar código de OrangeHRM
WORKDIR /var/www
COPY --chown=www-data:www-data . /var/www/html/

# Crear directorios necesarios con permisos
RUN mkdir -p /var/www/html/src/cache \
	/var/www/html/src/log \
	/var/www/html/src/config \
	/var/www/html/scripts \
	/var/www/html/public \
	/var/www/html/installer; \
	chown -R www-data:www-data /var/www/html; \
	chmod -R 755 /var/www/html; \
	chmod -R 775 /var/www/html/src/cache /var/www/html/src/log /var/www/html/src/config

# Instalar Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Instalar Node.js y Yarn para construir el instalador Vue.js
RUN curl -fsSL https://deb.nodesource.com/setup_18.x | bash - && \
    apt-get install -y nodejs && \
    npm install -g yarn

# Construir el instalador Vue.js
WORKDIR /var/www/html/installer/client
RUN yarn install --frozen-lockfile && \
    yarn build && \
    ls -la dist/ && \
    echo "✅ Instalador Vue.js construido exitosamente"

# Instalar dependencias de Composer
WORKDIR /var/www/html
RUN composer install -d src --no-dev --optimize-autoloader && \
    echo "✅ Dependencias de Composer instaladas"

# Configurar OrangeHRM para PostgreSQL ANTES de instalación
RUN echo "🔧 Pre-configurando OrangeHRM para PostgreSQL..." && \
    mkdir -p src/config && \
    echo '<?php return ["database" => ["driver" => "pdo_pgsql"]];' > src/config/database.php

# Script de inicio optimizado para PostgreSQL
RUN echo '#!/bin/bash\n\
set -e\n\
\n\
# Configurar puerto INMEDIATAMENTE\n\
PORT=${PORT:-10000}\n\
echo "🚀 Portos International - Starting on port $PORT"\n\
\n\
# Configurar Apache\n\
echo "Listen $PORT" > /etc/apache2/ports.conf\n\
cat > /etc/apache2/sites-available/000-default.conf << EOF2\n\
<VirtualHost *:$PORT>\n\
    ServerName localhost\n\
    DocumentRoot /var/www/html\n\
    \n\
    <Directory /var/www/html>\n\
        Options Indexes FollowSymLinks\n\
        AllowOverride All\n\
        Require all granted\n\
        DirectoryIndex index.php index.html\n\
    </Directory>\n\
    \n\
    <Directory /var/www/html/installer>\n\
        Options Indexes FollowSymLinks\n\
        AllowOverride All\n\
        Require all granted\n\
    </Directory>\n\
    \n\
    # MIME types corregidos\n\
    <FilesMatch "\\.(css)$">\n\
        Header always set Content-Type "text/css"\n\
    </FilesMatch>\n\
    \n\
    <FilesMatch "\\.(js)$">\n\
        Header always set Content-Type "application/javascript"\n\
    </FilesMatch>\n\
    \n\
    AddType text/css .css\n\
    AddType application/javascript .js\n\
    AddType image/png .png\n\
    AddType image/jpeg .jpg .jpeg\n\
    AddType image/gif .gif\n\
    AddType image/x-icon .ico\n\
    \n\
    ErrorLog ${APACHE_LOG_DIR}/error.log\n\
    CustomLog ${APACHE_LOG_DIR}/access.log combined\n\
</VirtualHost>\n\
EOF2\n\
\n\
# Verificar instalación\n\
echo "🔍 Verificando componentes..."\n\
echo "📁 Instalador Vue.js: $(ls -la /var/www/html/installer/client/dist/ | wc -l) archivos"\n\
echo "📦 Composer vendor: $(ls -la /var/www/html/src/vendor/ | wc -l) directorios"\n\
\n\
# Sistema inteligente de instalación PostgreSQL\n\
echo "🎯 Iniciando sistema de instalación PostgreSQL inteligente..."\n\
\n\
# Función para verificar instalación completa\n\
check_installation_complete() {\n\
    if [ -f "/var/www/html/installer/.installed" ] && [ -f "/var/www/html/src/config/database.php" ]; then\n\
        if [ -n "$DATABASE_URL" ]; then\n\
            DB_HOST_CHECK=$(echo $DATABASE_URL | sed "s/.*@\\(.*\\):.*/\\1/")\n\
            DB_PORT_CHECK=$(echo $DATABASE_URL | sed "s/.*:\\([0-9]*\\)\\/.*/\\1/")\n\
            DB_NAME_CHECK=$(echo $DATABASE_URL | sed "s/.*\\/\\([^?]*\\).*/\\1/")\n\
            DB_USER_CHECK=$(echo $DATABASE_URL | sed "s/.*:\\/\\/\\([^:]*\\):.*/\\1/")\n\
            DB_PASS_CHECK=$(echo $DATABASE_URL | sed "s/.*:\\/\\/[^:]*:\\([^@]*\\)@.*/\\1/")\n\
            \n\
            table_count=$(PGPASSWORD="$DB_PASS_CHECK" psql -h "$DB_HOST_CHECK" -p "$DB_PORT_CHECK" -U "$DB_USER_CHECK" -d "$DB_NAME_CHECK" -t -c "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='"'"'public'"'"' AND table_name LIKE '"'"'ohrm_%'"'"';" 2>/dev/null | tr -d " " || echo "0")\n\
            \n\
            if [ "$table_count" -gt "50" ]; then\n\
                return 0  # Instalación completa\n\
            fi\n\
        fi\n\
    fi\n\
    return 1  # Instalación incompleta\n\
}\n\
\n\
# Ejecutar diagnóstico si está disponible\n\
if [ -f "/var/www/html/scripts/diagnose-installation.sh" ]; then\n\
    chmod +x /var/www/html/scripts/diagnose-installation.sh\n\
    echo "📊 Ejecutando diagnóstico..."\n\
    /var/www/html/scripts/diagnose-installation.sh > /var/log/portos-diagnostic.log 2>&1 &\n\
fi\n\
\n\
# Decidir qué script ejecutar basado en el estado\n\
if check_installation_complete; then\n\
    echo "✅ OrangeHRM ya está instalado y funcionando"\n\
    echo "🎯 Sistema listo para usar"\n\
elif [ -f "/var/log/portos-installation.log" ] && grep -q "MySQL server has gone away" /var/log/portos-installation.log 2>/dev/null; then\n\
    echo "🔧 Error MySQL detectado - ejecutando reparación PostgreSQL..."\n\
    if [ -f "/var/www/html/scripts/fix-mysql-error-install-postgresql.sh" ]; then\n\
        chmod +x /var/www/html/scripts/fix-mysql-error-install-postgresql.sh\n\
        /var/www/html/scripts/fix-mysql-error-install-postgresql.sh > /var/log/portos-fix-installation.log 2>&1 &\n\
        echo "✅ Reparación PostgreSQL iniciada"\n\
    fi\n\
elif [ -f "/var/www/html/scripts/force-complete-installation.sh" ]; then\n\
    echo "🔥 Ejecutando instalación forzada PostgreSQL..."\n\
    chmod +x /var/www/html/scripts/force-complete-installation.sh\n\
    /var/www/html/scripts/force-complete-installation.sh > /var/log/portos-force-installation.log 2>&1 &\n\
    echo "✅ Instalación forzada iniciada - garantiza compatibilidad PostgreSQL"\n\
    echo "⏳ Instalación completa en 4-5 minutos"\n\
elif [ -f "/var/www/html/scripts/auto-install-portos.sh" ]; then\n\
    echo "🚀 Ejecutando instalación automática estándar..."\n\
    chmod +x /var/www/html/scripts/auto-install-portos.sh\n\
    /var/www/html/scripts/auto-install-portos.sh > /var/log/portos-installation.log 2>&1 &\n\
    echo "✅ Instalación automática iniciada"\n\
    echo "⏳ Instalación completa en 2-3 minutos"\n\
else\n\
    echo "⚠️ Scripts de instalación no encontrados"\n\
    echo "🔧 Instalación manual requerida via web interface"\n\
    echo "💡 Usar credenciales PostgreSQL proporcionadas"\n\
fi\n\
\n\
# Hacer scripts de verificación disponibles\n\
chmod +x /var/www/html/scripts/*.sh 2>/dev/null || true\n\
\n\
echo "💡 Scripts disponibles para verificación:"\n\
echo "   /var/www/html/scripts/check-installation-status.sh"\n\
echo "   /var/www/html/scripts/diagnose-installation.sh"\n\
echo ""\n\
echo "📝 Logs de instalación:"\n\
echo "   /var/log/portos-diagnostic.log"\n\
echo "   /var/log/portos-force-installation.log"\n\
echo "   /var/log/portos-installation.log"\n\
echo ""\n\
\n\
# Iniciar Apache INMEDIATAMENTE\n\
echo "🎯 Starting Apache on port $PORT NOW..."\n\
exec apache2-foreground' > /usr/local/bin/start.sh

RUN chmod +x /usr/local/bin/start.sh

EXPOSE 10000

CMD ["/usr/local/bin/start.sh"]