<?php
// APPLICATION
define('APPLICATION', 'Catalog');

// HTTP
define('HTTP_SERVER', 'http://opencart.test/');

// DIR
define('DIR_OPENCART', '/Users/valentyn/Sites/opencart4/');
define('DIR_APPLICATION', DIR_OPENCART . 'catalog/');
define('DIR_EXTENSION', DIR_OPENCART . 'extension/');
define('DIR_IMAGE', DIR_OPENCART . 'image/');
define('DIR_SYSTEM', DIR_OPENCART . 'system/');
define('DIR_STORAGE', DIR_SYSTEM . 'storage/');
define('DIR_LANGUAGE', DIR_APPLICATION . 'language/');
// Theme templates (custom theme folder)
define('DIR_TEMPLATE', DIR_APPLICATION . 'view/theme/manline/template/');
define('DIR_CONFIG', DIR_SYSTEM . 'config/');
define('DIR_CACHE', DIR_STORAGE . 'cache/');
define('DIR_DOWNLOAD', DIR_STORAGE . 'download/');
define('DIR_LOGS', DIR_STORAGE . 'logs/');
define('DIR_SESSION', DIR_STORAGE . 'session/');
define('DIR_UPLOAD', DIR_STORAGE . 'upload/');

// DB
define('DB_DRIVER', 'mysqli');
define('DB_HOSTNAME', 'localhost');
define('DB_USERNAME', 'root');
define('DB_PASSWORD', 'well4nl');
define('DB_DATABASE', 'oc_clear');
define('DB_PORT', '3306');
define('DB_PREFIX', 'man_');
define('DB_SSL_KEY', '');
define('DB_SSL_CERT', '');
define('DB_SSL_CA', '');

cat <<'EOF' > /tmp/prod-versions.sh
  #!/bin/bash
  echo '=== OS ==='
  lsb_release -a 2>/dev/null
  echo
  echo '=== PHP ==='
  php -v
  echo '--- extensions ---'
  php -m
  echo '--- key ini values ---'
  php -i | grep -E '^(memory_limit|upload_max_filesize|post_max_size|max_execution_time|opcache\.(enable|memory_consumption|max_accelerated_files|validate_timestamps))'
  echo
  echo '=== Composer ==='
  composer --version 2>/dev/null
  echo
  echo '=== Node + npm ==='
  node -v 2>/dev/null
  npm -v 2>/dev/null
  echo
  echo '=== MariaDB ==='
  mysql --version
  mysql -u root -p$(awk -F= '/^DB_PASSWORD/{print $2}' /var/www/worx.io/current/.env 2>/dev/null) -e "SHOW VARIABLES LIKE 'innodb_encrypt%';" 2>/dev/null
  echo
  echo '=== Elasticsearch ==='
  dpkg -l | grep elasticsearch
  curl -sk -u elastic:$(awk -F= '/^ELASTIC_PASSWORD/{print $2}' /var/www/worx.io/current/.env 2>/dev/null | tr -d '"') https://localhost:9200/ 2>/dev/null | head -20
  echo
  echo '=== nginx ==='
  nginx -v 2>&1
  echo '--- vhost worx.io ---'
  ls /etc/nginx/sites-enabled/
  cat /etc/nginx/sites-enabled/*worx* 2>/dev/null | head -50
  echo
  echo '=== Supervisor jobs ==='
  supervisorctl status 2>/dev/null
  echo '--- configs ---'
  ls /etc/supervisor/conf.d/
  echo
  echo '=== Cron (root + deploy) ==='
  crontab -l 2>/dev/null
  sudo -u deploy crontab -l 2>/dev/null
  ls /etc/cron.d/
  echo
  echo '=== sshd hardening ==='
  grep -E '^(PermitRootLogin|PasswordAuthentication|PubkeyAuthentication|AuthenticationMethods|AllowUsers)' /etc/ssh/sshd_config
  echo
  echo '=== UFW ==='
  ufw status verbose 2>/dev/null
  echo
  echo '=== php-fpm pool ==='
  cat /etc/php/*/fpm/pool.d/www.conf 2>/dev/null | grep -vE '^\s*[#;]|^\s*$' | head -30
  echo
  echo '=== prod .env (без секретов) ==='
  grep -E '^(APP_ENV|APP_DEBUG|APP_URL|LOG_CHANNEL|LOG_LEVEL|LOG_DAILY_DAYS|DB_CONNECTION|DB_HOST|DB_PORT|QUEUE_CONNECTION|CACHE_DRIVER|SESSION_DRIVER|SESSION_COOKIE|SESSION_LIFETIME|MAIL_MAILER|ELASTICSEARCH_HOSTS
  |ELASTIC_VERIFY_SSL|DEFENDER_SYNC_ENABLED|DEFENDER_SYNC_SCHEDULED)' /var/www/worx.io/current/.env 2>/dev/null
  EOF