FROM python:3

WORKDIR /usr/src/app

# Install dependencies
RUN apt-get update -y && \
    apt-get install -y firefox-esr nginx php php-fpm php-curl php-dom cron wget && \
    apt-get clean && \
    rm -rf /var/lib/apt/lists/*

# Install geckodriver
RUN wget https://github.com/mozilla/geckodriver/releases/download/v0.31.0/geckodriver-v0.31.0-linux64.tar.gz && \
    tar -xf geckodriver-v0.31.0-linux64.tar.gz && \
    rm geckodriver-v0.31.0-linux64.tar.gz && \
    mv geckodriver /usr/bin/

# Copy Python requirements
COPY requirements.txt ./
RUN pip install --no-cache-dir -r requirements.txt

# Create necessary directories for Firefox and www-data
RUN mkdir -p /var/www/.cache /var/www/.mozilla /config /downloads /video && \
    chown www-data:www-data /var/www/.cache /var/www/.mozilla /config

# Setup Nginx
COPY docker_config/nginx.conf /etc/nginx/sites-available/default
RUN rm -rf /var/www/html/*
COPY www/ /var/www/html/
RUN chown -R www-data:www-data /var/www/html/

# Copy background scripts
COPY docker_config/*.py ./
COPY docker_config/*.sh ./
RUN chmod +x *.sh

# Setup entrypoint and cron
COPY entrypoint.sh /usr/local/bin/
RUN chmod +x /usr/local/bin/entrypoint.sh

# Cronjobs will be initialized dynamically in entrypoint.sh or run from there
EXPOSE 80

ENTRYPOINT ["entrypoint.sh"]
