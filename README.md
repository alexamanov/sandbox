## Build app
docker-compose build

## Up
docker-compose up

## Go to container:
docker exec -it php bash

## Admin url:
http://localhost/admin

## Admin credentials:
Username: admin
<br>
Password: admin@123
<br>

## Phpmyadmin url:
http://localhost:8085/

## Phpmyadmin credentials:
Username: root
<br>
Password: **test**

## Connect to DB via terminal
mysql -u root -p magento --protocol=tcp

passwod: **test**

## Mailhog url:
http://localhost:8025/

## Remove docker containers
docker rm apache
<br>
docker rm pma
<br>
docker rm php
<br>
docker rm mailhog
<br>
docker rm mysql
<br>
docker rm es7.9
<br>
