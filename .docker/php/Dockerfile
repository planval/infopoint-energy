FROM php:8.1.18-fpm

# install basic dependencies
RUN apt-get update && \
    apt-get install -y --no-install-recommends vim curl debconf subversion git apt-transport-https apt-utils \
    build-essential locales acl mailutils wget zip unzip \
    gnupg gnupg1 gnupg2 \
    zlib1g-dev \
    sudo \
    python \
    nano \
    ghostscript \
    binutils \
    libproj-dev \
    gdal-bin

# install node
RUN curl -sL https://deb.nodesource.com/setup_16.x | bash - && apt-get install -y nodejs

# install yarn
RUN wget --quiet -O - /tmp/pubkey.gpg https://dl.yarnpkg.com/debian/pubkey.gpg | sudo apt-key add - && \
    echo "deb https://dl.yarnpkg.com/debian/ stable main" | sudo tee /etc/apt/sources.list.d/yarn.list && \
    apt-get update && \
    apt-get install -y yarn

# set timezone
RUN printf '[PHP]\ndate.timezone = "Europe/Vienna"\n' > /usr/local/etc/php/conf.d/tzone.ini

# set memory_limit
RUN printf '[PHP]\nmemory_limit = 1G\n' > /usr/local/etc/php/conf.d/memory_limit.ini

# install pdo
RUN docker-php-ext-install pdo pdo_mysql

# install imagick
RUN apt-get update && apt-get install -y \
    libmagickwand-dev --no-install-recommends \
    && pecl install imagick \
	&& docker-php-ext-enable imagick

# allow imagick pdf execution
RUN s='<policy domain="coder" rights="none" pattern="PDF" />' \
    && r='<policy domain="coder" rights="read|write" pattern="PDF" />' \
    && sed -i -e "s@$s@$r@" /etc/ImageMagick-6/policy.xml

# install zip
RUN apt-get update -y && apt-get install -y zlib1g-dev libzip-dev
RUN docker-php-ext-install zip

# install intl
RUN apt-get update -y && apt-get install -y libicu-dev && docker-php-ext-configure intl && docker-php-ext-install intl

# install gd
RUN apt-get update -y && apt-get install -y libwebp-dev libjpeg62-turbo-dev libpng-dev libxpm-dev libfreetype6-dev zlib1g-dev && \
    docker-php-ext-configure gd && docker-php-ext-install gd

RUN curl -sSk https://getcomposer.org/installer | php -- --disable-tls && \
	mv composer.phar /usr/local/bin/composer

RUN curl -1sLf 'https://dl.cloudsmith.io/public/symfony/stable/setup.deb.sh' | sudo -E bash && \
    sudo apt install symfony-cli

RUN git config --global user.email "you@example.com" && git config --global user.name "Your Name"

RUN wget --no-check-certificate https://phar.phpunit.de/phpunit-6.5.3.phar && \
    mv phpunit*.phar phpunit.phar && \
    chmod +x phpunit.phar && \
    mv phpunit.phar /usr/local/bin/phpunit

# install mysql client
RUN apt-get update --allow-releaseinfo-change -y && apt-get install -y default-mysql-client

RUN mkdir -p /home/wwwroot/infopoint-energy/var
RUN chown -R www-data:www-data /home/wwwroot/infopoint-energy/var

RUN groupadd dev -g 999
RUN useradd dev -g dev -d /home/dev -m
RUN passwd -d dev

RUN rm -rf /var/lib/apt/lists/*
RUN echo "en_US.UTF-8 UTF-8" > /etc/locale.gen && \
    echo "fr_FR.UTF-8 UTF-8" >> /etc/locale.gen && \
    echo "de_DE.UTF-8 UTF-8" >> /etc/locale.gen && \
    locale-gen

RUN echo "dev ALL=(ALL) ALL" > /etc/sudoers

WORKDIR /home/wwwroot/infopoint-energy/

EXPOSE 9000
CMD ["php-fpm"]