FROM mboyov/apache2-php74
LABEL maintainer="Mumtaz Ahmad"
RUN apt update && apt-get install -y git && apt install -y wget php-cli php-zip unzip && apt install -y php-xml && apt-get install -y php-gd && apt-get install -y php-mbstring && apt-get install -y php-curl
RUN apt-get install php8.0-mongo
RUN php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');" && php composer-setup.php --install-dir=/usr/local/bin --filename=composer
RUN git clone https://github.com/mahmad2504/laravel.git 
#WORKDIR /laravel
#RUN composer install
#COPY .env .
#RUN chmod a+x gitfetch.bat && ./gitfetch.bat && chmod a+x gitfetch.bat 
#RUN bash -c "php artisan serve --host 0.0.0.0"