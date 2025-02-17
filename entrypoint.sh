#!/bin/bash

# Start Nginx
/etc/init.d/nginx start

# Start PHP-FPM
#/etc/init.d/php7.4-fpm start

# Start Cron
service cron start

# Start anibot
python ./anibot.py --configfile /config/ani.json --docker
