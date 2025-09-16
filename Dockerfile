# OrangeHRM Dockerfile optimizado para Render y Portos International
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
	/var/www/html/public; \
	chown -R www-data:www-data /var/www/html; \
	chmod -R 755 /var/www/html; \
	chmod -R 775 /var/www/html/src/cache /var/www/html/src/log /var/www/html/src/config

# Instalar Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Configurar VirtualHost para Render
RUN echo '<VirtualHost *:80>\n\
    ServerName portos-international-rh.onrender.com\n\
    DocumentRoot /var/www/html/public\n\
    <Directory /var/www/html/public>\n\
        Options Indexes FollowSymLinks\n\
        AllowOverride All\n\
        Require all granted\n\
        DirectoryIndex index.php index.html\n\
    </Directory>\n\
    ErrorLog ${APACHE_LOG_DIR}/error.log\n\
    CustomLog ${APACHE_LOG_DIR}/access.log combined\n\
</VirtualHost>' > /etc/apache2/sites-available/000-default.conf

# Script de inicio SIMPLE para Render
RUN echo '#!/bin/bash\n\
set -e\n\
\n\
# Configurar puerto INMEDIATAMENTE\n\
PORT=${PORT:-10000}\n\
echo "🚀 Portos International - Starting on port $PORT"\n\
\n\
# Configurar Apache para el puerto de Render\n\
echo "Listen $PORT" > /etc/apache2/ports.conf\n\
cat > /etc/apache2/sites-available/000-default.conf << EOF2\n\
<VirtualHost *:$PORT>\n\
    ServerName localhost\n\
    DocumentRoot /var/www/html\n\
    \n\
    # Configuración principal\n\
    <Directory /var/www/html>\n\
        Options Indexes FollowSymLinks\n\
        AllowOverride All\n\
        Require all granted\n\
        DirectoryIndex index.php index.html\n\
    </Directory>\n\
    \n\
    # Configuración para archivos estáticos del instalador\n\
    <Directory /var/www/html/installer>\n\
        Options Indexes FollowSymLinks\n\
        AllowOverride All\n\
        Require all granted\n\
    </Directory>\n\
    \n\
    # Configuración para archivos CSS/JS\n\
    <FilesMatch "\.(css|js|png|jpg|jpeg|gif|ico|svg|woff|woff2|ttf|eot)$">\n\
        Header unset Content-Type\n\
        Header set Content-Type ""\n\
    </FilesMatch>\n\
    \n\
    # Tipos MIME específicos\n\
    AddType text/css .css\n\
    AddType application/javascript .js\n\
    AddType image/png .png\n\
    AddType image/jpeg .jpg .jpeg\n\
    AddType image/gif .gif\n\
    AddType image/x-icon .ico\n\
    \n\
    ErrorLog \${APACHE_LOG_DIR}/error.log\n\
    CustomLog \${APACHE_LOG_DIR}/access.log combined\n\
</VirtualHost>\n\
EOF2\n\
\n\
# Verificar configuración\n\
echo "📋 Apache config:"\n\
cat /etc/apache2/ports.conf\n\
\n\
# Debug: Verificar archivos del instalador\n\
echo "🔍 Verificando archivos del instalador..."\n\
ls -la /var/www/html/installer/ | head -10\n\
if [ -d "/var/www/html/installer/client" ]; then\n\
    echo "📁 Directorio client encontrado:"\n\
    ls -la /var/www/html/installer/client/\n\
    if [ -d "/var/www/html/installer/client/dist" ]; then\n\
        echo "📁 Directorio dist encontrado:"\n\
        ls -la /var/www/html/installer/client/dist/ | head -10\n\
    else\n\
        echo "❌ Directorio dist NO existe"\n\
        echo "📂 Creando estructura dist básica..."\n\
        mkdir -p /var/www/html/installer/client/dist/css\n\
        mkdir -p /var/www/html/installer/client/dist/js\n\
        echo "/* Basic CSS fallback */" > /var/www/html/installer/client/dist/css/app.css\n\
        echo "/* Basic CSS fallback */" > /var/www/html/installer/client/dist/css/chunk-vendors.css\n\
        echo "// Basic JS fallback" > /var/www/html/installer/client/dist/js/app.js\n\
        echo "// Basic JS fallback" > /var/www/html/installer/client/dist/js/chunk-vendors.js\n\
        echo "✅ Archivos básicos creados"\n\
    fi\n\
else\n\
    echo "❌ Directorio client NO existe"\n\
fi\n\
\n\
# Ejecutar setup de Portos International en background\n\
echo "🔧 Ejecutando configuración de Portos International..."\n\
if [ -f "/var/www/html/scripts/setup-portos.sh" ]; then\n\
    chmod +x /var/www/html/scripts/setup-portos.sh\n\
    /var/www/html/scripts/setup-portos.sh > /var/log/portos-setup.log 2>&1 &\n\
    echo "✅ Setup iniciado en background - ver /var/log/portos-setup.log"\n\
else\n\
    echo "⚠️ Script de setup no encontrado, iniciando solo Apache"\n\
fi\n\
\n\
# Iniciar Apache INMEDIATAMENTE\n\
echo "🎯 Starting Apache on port $PORT NOW..."\n\
exec apache2-foreground' > /usr/local/bin/start.sh

RUN chmod +x /usr/local/bin/start.sh

EXPOSE 10000

CMD ["/usr/local/bin/start.sh"]
