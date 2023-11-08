FROM python:3

WORKDIR /usr/src/app

RUN apt-get update -y

RUN apt-get install firefox-esr nginx php php-fpm php-curl php-dom cron wget -y

RUN wget https://github.com/mozilla/geckodriver/releases/download/v0.31.0/geckodriver-v0.31.0-linux32.tar.gz

RUN tar -xf geckodriver-v0.31.0-linux32.tar.gz

RUN rm geckodriver-v0.31.0-linux32.tar.gz

RUN mv geckodriver /usr/bin/

RUN git clone https://github.com/Crypto90/anime-loads.git

RUN mv anime-loads/* .

RUN rm -rf anime-loads

RUN mv entrypoint.sh /usr/local/bin/

RUN chmod +x /usr/local/bin/entrypoint.sh

RUN pip install --no-cache-dir -r requirements.txt

RUN mv www/nginx.conf /etc/nginx/sites-available/default

RUN rm -rf /var/www/html/*

RUN mv www/* /var/www/html/

RUN mkdir /var/www/.cache

RUN mkdir /var/www/.mozilla

RUN chown www-data:www-data /var/www/.cache

RUN chown www-data:www-data /var/www/.mozilla

# Add a cron job directly into the container
RUN echo '* * * * * wget --output-document=/dev/null --timeout=30 "http://127.0.0.1/index.php?processqueue=1"' > /etc/cron.d/cronjob

# Give execution rights to the cron job
RUN chmod 0644 /etc/cron.d/cronjob

EXPOSE 80

ENTRYPOINT ["entrypoint.sh" ]

