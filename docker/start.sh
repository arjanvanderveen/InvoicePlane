#!/bin/sh

ln -s /dev/stdout /var/log/php${PHP_VERSION}/error.log
ln -s /dev/stdout /var/log/nginx/access.log
ln -s /dev/stdout /var/log/nginx/error.log


if [ ! -f "/var/www/html/ipconfig.php" ]
then
  cp /var/www/html/ipconfig.php.example /var/www/html/ipconfig.php
fi

[ -n "$IP_URL" ] && sed -i -e "s!IP_URL=\$!IP_URL=${IP_URL}!" /var/www/html/ipconfig.php
[ -n "$ENABLE_DEBUG" ] && sed -i -e "s/ENABLE_DEBUG=.*/ENABLE_DEBUG=${ENABLE_DEBUG}/" /var/www/html/ipconfig.php
[ -n "$DISABLE_SETUP" ] && sed -i -e "s/DISABLE_SETUP=.*/DISABLE_SETUP=${DISABLE_SETUP}/" /var/www/html/ipconfig.php
[ -n "$REMOVE_INDEXPHP" ] && sed -i -e "s/REMOVE_INDEXPHP=.*/REMOVE_INDEXPHP=${REMOVE_INDEXPHP}/" /var/www/html/ipconfig.php

[ -n "$MYSQL_HOST" ] && sed -i -e "s/^DB_HOSTNAME=$/DB_HOSTNAME=${MYSQL_HOST}/" /var/www/html/ipconfig.php
[ -n "$MYSQL_USER" ] && sed -i -e "s/^DB_USERNAME=$/DB_USERNAME=${MYSQL_USER}/" /var/www/html/ipconfig.php
[ -n "$MYSQL_PASSWORD" ] && sed -i -e "s/^DB_PASSWORD=$/DB_PASSWORD=${MYSQL_PASSWORD}/" /var/www/html/ipconfig.php
[ -n "$MYSQL_DB" ] && sed -i -e "s/^DB_DATABASE=$/DB_DATABASE=${MYSQL_DB}/" /var/www/html/ipconfig.php
[ -n "$MYSQL_PORT" ] && sed -i -e "s/^DB_PORT=$/DB_PORT=${MYSQL_PORT}/" /var/www/html/ipconfig.php

[ -n "$SESS_EXPIRATION" ] && sed -i -e "s/^SESS_EXPIRATION=$/SESS_EXPIRATION=${SESS_EXPIRATION}/" /var/www/html/ipconfig.php
[ -n "$SESS_MATCH_IP" ] && sed -i -e "s/^SESS_MATCH_IP=$/SESS_MATCH_IP=${SESS_MATCH_IP}/" /var/www/html/ipconfig.php
[ -n "$ENABLE_INVOICE_DELETION" ] && sed -i -e "s/^ENABLE_INVOICE_DELETION=$/ENABLE_INVOICE_DELETION=${ENABLE_INVOICE_DELETION}/" /var/www/html/ipconfig.php
[ -n "$DISABLE_READ_ONLY" ] && sed -i -e "s/^DISABLE_READ_ONLY=$/DISABLE_READ_ONLY=${DISABLE_READ_ONLY}/" /var/www/html/ipconfig.php

if [ $(grep 'PROXY_IPS' /var/www/html/ipconfig.php) -eq 1 ]
then
  [ -n "$PROXY_IPS" ] && sed -i -e "s!PROXY_IPS=\$!PROXY_IPS=${PROXY_IPS}!" /var/www/html/ipconfig.php
else
  echo "PROXY_IPS=${PROXY_IPS}" >> /var/www/html/ipconfig.php
fi

# set the config option SESS_MATCH_IP to False since it's giving problem accessing invoiceplane using ipv6
sed -i 's/SESS_MATCH_IP=true/SESS_MATCH_IP=false/' /var/www/html/ipconfig.php

# fix GLOB_BRACE bugs in index.php https://github.com/InvoicePlane/InvoicePlane/issues/1304
sed -i 's/array_map(\x27unlink\x27, glob(UPLOADS_TEMP_FOLDER \. \x27\*\.{pdf,xml}\x27, \\GLOB_BRACE));/\$files = array_merge(\n    glob(UPLOADS_TEMP_FOLDER \. "\*\.pdf"),\n    glob(UPLOADS_TEMP_FOLDER \. "\*\.xml")\n);\narray_map("unlink", \$files);/' /var/www/html/index.php

### CRITICAL
if [ -n "$SETUP_COMPLETED" ]; then
    [ -n "$ENCRYPTION_KEY" ] && sed -i -e "s/ENCRYPTION_KEY=.*/ENCRYPTION_KEY=${ENCRYPTION_KEY}/" /var/www/html/ipconfig.php;
    [ -n "$ENCRYPTION_CIPHER" ] && sed -i -e "s/ENCRYPTION_CIPHER=.*/ENCRYPTION_CIPHER=${ENCRYPTION_CIPHER}/" /var/www/html/ipconfig.php;
    sed -i -e "s/SETUP_COMPLETED=.*/SETUP_COMPLETED=${SETUP_COMPLETED}/" /var/www/html/ipconfig.php;
fi

[[ ! -d "/var/www/html/application/logs/" ]] && mkdir -p "/var/www/html/application/logs/"

### Setup custom uploads
if [ ! -f "/var/www/html/uploads/index.html" ]
then
  cp -r /tmp/ip/uploads/* /var/www/html/uploads/
fi

### Setup custom CSS
if [ ! -f "/var/www/html/assets/core/css/custom.css" ]
then
  cp -r /tmp/ip/assets/core/css/* /var/www/html/assets/core/css/
fi

### Setup custom views
if [ ! -f "/var/www/html/application/views/index.html" ]
then
  cp -r /tmp/ip/application/views/* /var/www/html/application/views/
fi

# set file user rights
chown nobody:nginx /var/www/html/ipconfig.php;
chown -R nobody:nginx /var/www/html/uploads;
chown -R nobody:nginx /var/www/html/assets/core/css;
chown -R nobody:nginx /var/www/html/application/views;

# create supervisord.conf
cat << EOF > /etc/supervisord.conf
[supervisord]
nodaemon = true
pidfile = /run/supervisord.pid
#logfile = /dev/stdout
logfile = /var/log/supervisord.log
loglevel = info
user = root
stdout_maxbytes=0
stderr_maxbytes=0
stdout_logfile_maxbytes = 0
stderr_logfile_maxbytes = 0

[program:cron]
command=/usr/sbin/crond -f
user=root
autostart=true
autorestart=true
redirect_stderr=true
stdout_logfile=/dev/stdout  ; Redirect stdout to console
stderr_logfile=/dev/stderr  ; Redirect stderr to console
stdout_logfile_maxbytes=0   ; Disable log rotation (since we’re using console)
stderr_logfile_maxbytes=0   ; Disable log rotation for stderr

[program:nginx]
command=/usr/sbin/nginx -g "daemon off;"
user=root
autostart=true
autorestart=true
redirect_stderr=true
stdout_logfile=/dev/stdout  ; Redirect stdout to console
stderr_logfile=/dev/stderr  ; Redirect stderr to console
stdout_logfile_maxbytes=0   ; Disable log rotation (since we’re using console)
stderr_logfile_maxbytes=0   ; Disable log rotation for stderr

[program:php-fpm${PHP_VERSION}]
command=/usr/sbin/php-fpm${PHP_VERSION} --nodaemonize
user=root
autostart=true
autorestart=true
redirect_stderr=true
stdout_logfile=/dev/stdout  ; Redirect stdout to console
stderr_logfile=/dev/stderr  ; Redirect stderr to console
stdout_logfile_maxbytes=0   ; Disable log rotation (since we’re using console)
stderr_logfile_maxbytes=0   ; Disable log rotation for stderr
EOF

# create test crontab
cron_key=`/app/get_ip_cron_key.php`
cat << EOF > /etc/crontabs/root
# run this command every day as 4 AM to run the recurring invoices task on invoiceplane
0 4 * * * /usr/bin/wget -O - http://localhost/invoices/cron/recur/$cron_key > /dev/stdout 2>&1
EOF

exec "$@"
