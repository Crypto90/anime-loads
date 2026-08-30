#!/bin/bash

# Start Nginx
/etc/init.d/nginx start

# Start PHP-FPM (find the exact version installed)
php_service=$(ls /etc/init.d/ | grep php | grep fpm)
service $php_service start

# Update Nginx config to point to the correct PHP-FPM socket
sleep 1
SOCK=$(ls /run/php/ | grep fpm.sock | head -n 1)
if [ -n "$SOCK" ]; then
    sed -i "s/php[0-9.]*-fpm\.sock/$SOCK/g" /etc/nginx/sites-available/default
    /etc/init.d/nginx reload
fi

# Setup Crontab for processing queue if needed
echo '* * * * * root wget --output-document=/dev/null --timeout=30 "http://127.0.0.1/index.php?processqueue=1"' > /etc/cron.d/cronjob
echo '*/5 * * * * root python3 /usr/src/app/auto_mover.py >> /var/log/auto_mover.log 2>&1' >> /etc/cron.d/cronjob
chmod 0644 /etc/cron.d/cronjob

# Start Cron
service cron start

# Ensure config directory has right permissions so PHP can write config.json to it
chown www-data:www-data /config

# Ensure ani.json exists so anibot.py doesn't crash
if [ ! -f "/config/ani.json" ]; then
    echo '{"settings": {"jdhost": "http://127.0.0.1", "hoster": "", "browserengine": "Firefox", "browserlocation": "/usr/bin/firefox", "pushbullet_apikey": "", "timedelay": 15, "myjd_user": "", "myjd_pw": "", "myjd_device": "", "jd_deprecated": 0, "jd_deprecatedport": 3128, "al_user": "", "al_pass": ""}}' > /config/ani.json
    chown www-data:www-data /config/ani.json
    chmod 666 /config/ani.json
fi

# Ensure Python scripts looking for config/ani.json can find it
if [ ! -d "/var/www/html/config" ] && [ ! -L "/var/www/html/config" ]; then
    ln -s /config /var/www/html/config
fi

# We can run anibot in the foreground to keep the container alive
echo "Starting anibot..."
cd /var/www/html
python3 anibot.py --docker

# Fallback keep-alive if anibot exits
tail -f /dev/null
