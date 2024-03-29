FROM php:7.2-cli-alpine3.12
## Utilities
RUN apk add --no-cache --update zip zlib-dev git python3 py3-pip nodejs npm keychain make gcc g++ pkgconfig pixman-dev cairo-dev pango-dev libjpeg-turbo-dev giflib-dev ldb-dev libldap openldap-dev ;
RUN npm install --global yarn

ADD https://github.com/mlocati/docker-php-extension-installer/releases/latest/download/install-php-extensions /usr/local/bin/
RUN chmod uga+x /usr/local/bin/install-php-extensions && sync && \
    install-php-extensions \
    zip \
    mysqli \
    intl \
    pdo_mysql \
    gd \
    bz2 \
    imagick \
    @composer \
    bcmath \
#    xdebug-3.1 \
    mcrypt \
    sodium \
    ldap \
    sockets;

ENV NODE_PACKAGE_URL  https://unofficial-builds.nodejs.org/download/release/v18.20.0/node-v18.20.0-linux-x64-musl.tar.gz

RUN apk add libstdc++
WORKDIR /opt
RUN wget $NODE_PACKAGE_URL
RUN mkdir -p /opt/nodejs
RUN tar -zxvf *.tar.gz --directory /opt/nodejs --strip-components=1
RUN rm *.tar.gz
RUN ln -s /opt/nodejs/bin/node /usr/local/bin/node
RUN ln -s /opt/nodejs/bin/npm /usr/local/bin/npm

ARG USER_ID
ARG GROUP_ID
RUN if [ ${USER_ID:-0} -ne 0 ] && [ ${GROUP_ID:-0} -ne 0 ]; then \
    deluser www-data &&\
    if getent group www-data ; then delgroup www-data; fi &&\
    addgroup -g ${GROUP_ID} -S www-data; \
    adduser -D -u ${USER_ID} -S -G www-data www-data \
    ;fi

WORKDIR /workspace
