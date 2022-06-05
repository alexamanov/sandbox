Build app
docker-compose build

Up
docker-compose up

Go to container:
docker exec -it php bash

Admin url: http://localhost/admin
credentials:
Username: admin
Password: admin@123


Phpmyadmin url: http://localhost:8085/
credentials:
Username: root
Password: test

Connect to DB via terminal
mysql -u root -p magento --protocol=tcp
passwod: test
