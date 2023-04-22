FROM python:3

WORKDIR /usr/src/app

RUN apt-get update -y

RUN apt-get install firefox-esr nginx php php-fpm php-curl php-dom -y

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


ENTRYPOINT ["entrypoint.sh" ]

