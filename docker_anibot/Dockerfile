FROM python:3

WORKDIR /usr/src/app

RUN apt-get update -y

RUN apt-get install firefox-esr nginx php php-fpm php-curl php-dom -y

RUN wget https://github.com/mozilla/geckodriver/releases/download/v0.31.0/geckodriver-v0.31.0-linux32.tar.gz

RUN tar -xf geckodriver-v0.31.0-linux32.tar.gz

RUN rm geckodriver-v0.31.0-linux32.tar.gz

RUN mv geckodriver /usr/bin/

RUN git clone https://github.com/Pfuenzle/anime-loads.git

RUN mv anime-loads/* .

RUN rm -rf anime-loads

RUN pip install --no-cache-dir -r requirements.txt



RUN rm /etc/nginx/sites-enabled/default

RUN mv www/nginx.conf /etc/nginx/sites-available/nginx.conf

RUN ln -s /etc/nginx/sites-available/nginx.conf /etc/nginx/sites-enabled/nginx.conf

RUN rm -rf /var/www/html/*

RUN mv www/* /var/www/html/

RUN mkdir /config/anime_cover

RUN chmod 777 /config/anime_cover

RUN ln -s /config/anime_cover /var/www/html/anime_cover

RUN chmod 755 /var/www/html/anime_cover

CMD service php7.4-fpm start && nginx -g "daemon off;"




ENTRYPOINT ["python", "./anibot.py", "--configfile", "/config/ani.json", "--docker" ]
