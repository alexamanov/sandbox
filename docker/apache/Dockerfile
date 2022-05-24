FROM httpd:2.4.51

COPY magento.apache.conf /usr/local/apache2/conf/magento.apache.conf

RUN echo "Include /usr/local/apache2/conf/magento.apache.conf" \
    >> /usr/local/apache2/conf/httpd.conf