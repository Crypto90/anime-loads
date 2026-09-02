#!/bin/bash

# Start PHP-FPM (find the exact version installed)
php_service=$(ls /etc/init.d/ | grep php | grep fpm)
service $php_service start

# Update Nginx config to point to the correct PHP-FPM socket
sleep 1
SOCK=$(ls /run/php/ | grep fpm.sock | head -n 1)
if [ -n "$SOCK" ]; then
    sed -i "s/php[0-9.]*-fpm\.sock/$SOCK/g" /etc/nginx/sites-available/default
fi

# Start Nginx
/etc/init.d/nginx start

# Setup Crontab for processing queue if needed
echo '* * * * * root wget --output-document=/dev/null --timeout=30 "http://127.0.0.1/index.php?processqueue=1"' > /etc/cron.d/cronjob
echo '*/5 * * * * root python3 /usr/src/app/auto_mover.py >> /var/log/auto_mover.log 2>&1' >> /etc/cron.d/cronjob
chmod 0644 /etc/cron.d/cronjob

# Start Cron
service cron start

# Ensure config directory has right permissions so PHP can write config.json to it
chown -R www-data:www-data /config
chmod 775 /config

# Ensure ani.json exists so anibot.py doesn't crash
if [ ! -f "/config/ani.json" ]; then
    echo '{"settings": {"jdhost": "http://127.0.0.1", "hoster": "", "browserengine": "Firefox", "browserlocation": "/usr/bin/firefox", "pushbullet_apikey": "", "timedelay": 15, "myjd_user": "", "myjd_pw": "", "myjd_device": "", "jd_deprecated": 0, "jd_deprecatedport": 3128, "al_user": "", "al_pass": ""}, "anime": []}' > /config/ani.json
    chown www-data:www-data /config/ani.json
    chmod 666 /config/ani.json
fi

# Ensure ani_paused.json exists
if [ ! -f "/config/ani_paused.json" ]; then
    echo '{"anime": []}' > /config/ani_paused.json
    chown www-data:www-data /config/ani_paused.json
    chmod 666 /config/ani_paused.json
fi

# Symlinks in /usr/src/app so backend tools always find unified state files
ln -sf /config/ani.json /usr/src/app/ani.json
ln -sf /config/ani_paused.json /usr/src/app/ani_paused.json

# Supervised background daemon for anibot
echo "Starting anibot daemon supervisor..."
(
    while true; do
        echo "[$(date)] Starting anibot.py..." >> /usr/src/app/docker_live_output.log
        cd /var/www/html && python3 -u anibot.py --docker --configfile /config/ani.json >> /usr/src/app/docker_live_output.log 2>&1
        echo "[$(date)] anibot.py exited with code $?. Restarting in 10s..." >> /usr/src/app/docker_live_output.log
        sleep 10
    done
) &

# Fallback keep-alive for the container
tail -f /dev/null

