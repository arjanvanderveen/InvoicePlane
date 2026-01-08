#!/usr/bin/php
<?php
$mysqli = new mysqli($_ENV['MYSQL_HOST'], $_ENV['MYSQL_USER'], $_ENV['MYSQL_PASSWORD'], $_ENV['MYSQL_DB']);
$mysql_result = $mysqli->query("SELECT setting_value FROM ip_settings WHERE setting_key = 'cron_key' ");
$mysql_row = $mysql_result->fetch_assoc();
$cron_key = $mysql_row['setting_value'];
echo "$cron_key";
