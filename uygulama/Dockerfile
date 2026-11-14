# =====================================================================
# Yemekhane · uygulama servisi (PHP 8.3 + Apache, Web API)
# 1. aşama: Composer ile sınıf yükleyicisini (PSR-4 autoload) üretir.
# 2. aşama: yalnızca çalışma için gerekenler; Composer son imaja girmez.
# =====================================================================
FROM composer:2.8 AS autoload
WORKDIR /derleme
COPY composer.json ./
COPY src/ src/
RUN composer dump-autoload --no-dev --optimize --classmap-authoritative

FROM php:8.3-apache-bookworm

RUN docker-php-ext-install pdo_mysql opcache \
 && pecl install redis-6.1.0 \
 && docker-php-ext-enable redis \
 && rm -rf /tmp/pear

COPY docker/php-uretim.ini /usr/local/etc/php/conf.d/zz-yemekhane.ini
COPY docker/apache-ports.conf /etc/apache2/ports.conf
COPY docker/apache-uygulama.conf /etc/apache2/sites-available/000-default.conf

# Apache root olmadan çalışacağı için çalışma klasörleri www-data'ya verilir
RUN chown -R www-data:www-data /var/run/apache2 /var/lock/apache2 /var/log/apache2

WORKDIR /var/www/uygulama
COPY --from=autoload /derleme/vendor/ vendor/
COPY public/ public/
COPY src/ src/

USER www-data
EXPOSE 8000

# Sağlık kontrolü uygulamanın kendi uç noktasına gider: veritabanları ve Redis'e ulaşılamıyorsa 500 döner
HEALTHCHECK --interval=10s --timeout=5s --start-period=20s --retries=5 \
    CMD php -r 'exit(@file_get_contents("http://127.0.0.1:8000/api/saglik") === false ? 1 : 0);'
