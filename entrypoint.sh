#!/bin/bash

# Start Nginx
/etc/init.d/nginx start

# Start PHP-FPM
/etc/init.d/php-fpm start

# Start anibot
python ./anibot.py --configfile /config/ani.json --docker
