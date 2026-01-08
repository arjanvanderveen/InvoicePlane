FROM alpine:latest
LABEL org.opencontainers.image.authors="avdveen@palanthir.nl"

ENV PHP_VERSION="84" \
    IP_SOURCE="https://github.com/InvoicePlane/InvoicePlane/releases/download" \
    IP_VERSION="v1.6.3" \
    MYSQL_HOST="mariadb_10_4" \
    MYSQL_USER="root" \
    MYSQL_PASSWORD="my-secret-pw" \
    MYSQL_DB="invoiceplane" \
    MYSQL_PORT="3306" \
    IP_URL="http://127.0.0.1" \
    HOST_URL="127.0.0.1" \
    DISABLE_SETUP="false"

RUN apk update                             \
    &&  apk add nginx php${PHP_VERSION} php${PHP_VERSION}-fpm php${PHP_VERSION}-session \
    php${PHP_VERSION}-gd php${PHP_VERSION}-mbstring php${PHP_VERSION}-mysqli php${PHP_VERSION}-openssl \
    php${PHP_VERSION}-xml php${PHP_VERSION}-dom php${PHP_VERSION}-intl php${PHP_VERSION}-bcmath php${PHP_VERSION}-iconv \
    php${PHP_VERSION}-simplexml php${PHP_VERSION}-tokenizer php${PHP_VERSION}-xmlwriter php${PHP_VERSION}-phar \
    php${PHP_VERSION}-curl composer curl vim yarn git supervisor \
    && mkdir -p /var/www/html/ \
    && mkdir -p /run/nginx; \
    [ -f /usr/bin/php ] && rm -f /usr/bin/php; \
    ln -s /usr/bin/php${PHP_VERSION} /usr/bin/php;

# copy invoiceplane sources to web dir
COPY . /var/www/html/

RUN mkdir /app && \
    cd /var/www/html && \
    cp /var/www/html/docker/start.sh /app/start.sh && \
    chmod +x /app/start.sh && \
    cp /var/www/html/docker/get_ip_cron_key.php /app/get_ip_cron_key.php && \
    chmod +x /app/get_ip_cron_key.php && \
    cp /var/www/html/docker/php.ini /etc/php${PHP_VERSION}/php.ini && \
    cp /var/www/html/docker/php_fpm_site.conf /etc/php${PHP_VERSION}/php-fpm.d/www.conf && \
    cp /var/www/html/docker/nginx_site.conf /etc/nginx/http.d/default.conf && \
    rm -rf /var/www/html/docker && \
    /usr/bin/php${PHP_VERSION} /usr/bin/composer.phar update && \
    yarn install && \
    yarn build

# add the themes available
RUN cd /tmp && \
    git clone https://github.com/InvoicePlane/InvoicePlane-Themes && \
    cp -r /tmp/InvoicePlane-Themes/v1/* /var/www/html/assets/ && \
    rm -rf /var/www/html/assets/README.md /var/www/html/assets/DEVELOPMENT.md

# add the translations available
ADD ${IP_SOURCE}/${IP_VERSION}/${IP_VERSION}.zip /tmp/

#cleanup
RUN cd /tmp && \
    unzip /tmp/${IP_VERSION}.zip && \
    cp -r ip/application/language/* /var/www/html/application/language/

# set the user rights straight and clean up
RUN chown -R nobody:nginx /var/www/html/* && \
    rm -rf /var/cache/apk/* \
    rm -rf /tmp/*

WORKDIR /var/www/html

VOLUME /var/www/html/uploads
EXPOSE 80
ENTRYPOINT ["/app/start.sh"]
CMD ["supervisord", "--nodaemon", "--configuration", "/etc/supervisord.conf"]

## Health Check
HEALTHCHECK --interval=1m --timeout=3s --start-period=5s \
  CMD curl -f http://127.0.0.1/index.php || exit 1
