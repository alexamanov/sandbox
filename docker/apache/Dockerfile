FROM httpd:2.4.51

COPY magento.apache.conf /usr/local/apache2/conf/magento.apache.conf

RUN echo "Include /usr/local/apache2/conf/magento.apache.conf" \
    >> /usr/local/apache2/conf/httpd.conf

#Configure permissions
RUN usermod -u 1000 www-data && groupmod -g 1000 www-data && \
    chsh -s /bin/bash www-data && chown -R www-data:www-data /usr/local/apache2

RUN usermod -aG sudo www-data

WORKDIR /usr/local/apache2
USER www-data