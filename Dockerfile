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

# Script de inicio para Render
RUN echo '#!/bin/bash\n\
set -e\n\
echo "🚀 Starting OrangeHRM for Portos International..."\n\
echo "📍 Location: Monterrey, N.L., México"\n\
echo "🕐 Timezone: $TZ"\n\
echo "🏢 Organization: ${ORANGEHRM_ORGANIZATION_NAME}"\n\
\n\
# Configurar puerto de Render PRIMERO\n\
PORT=${PORT:-10000}\n\
echo "🔌 Configuring Apache for port $PORT"\n\
sed -i "s/Listen 80/Listen $PORT/g" /etc/apache2/ports.conf\n\
sed -i "s/:80/:$PORT/g" /etc/apache2/sites-available/000-default.conf\n\
sed -i "s/VirtualHost \*:80/VirtualHost *:$PORT/g" /etc/apache2/sites-available/000-default.conf\n\
\n\
# Verificar configuración de Apache\n\
echo "🔍 Apache configuration:"\n\
grep "Listen" /etc/apache2/ports.conf\n\
grep "VirtualHost" /etc/apache2/sites-available/000-default.conf\n\
\n\
# Ejecutar setup inicial RÁPIDO\n\
echo "🔧 Running minimal setup..."\n\
if [ -f /var/www/html/scripts/setup-portos.sh ]; then\n\
    timeout 30 bash /var/www/html/scripts/setup-portos.sh || echo "⚠️ Setup timeout, continuing..."\n\
fi\n\
touch /var/www/html/.installed\n\
\n\
# Iniciar Apache EN PRIMER PLANO\n\
echo "✅ Starting Apache on port $PORT..."\n\
exec apache2-foreground' > /usr/local/bin/start.sh

RUN chmod +x /usr/local/bin/start.sh

EXPOSE 80

CMD ["/usr/local/bin/start.sh"]
