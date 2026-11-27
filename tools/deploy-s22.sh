#!/bin/bash
set -e
cd /var/www/demo-website
echo Yad555883 | sudo -S -p '' tar -xzf /tmp/deploy-s22.tar.gz -C /var/www/demo-website
echo "extracted"
echo Yad555883 | sudo -S -p '' chown -R www-data:www-data /var/www/demo-website/app /var/www/demo-website/config /var/www/demo-website/resources /var/www/demo-website/tools /var/www/demo-website/docs /var/www/demo-website/routes
echo Yad555883 | sudo -S -p '' sudo -u www-data env HOME=/tmp php8.3 artisan migrate --path=app/Modules/Pm/Database/Migrations --database=pm --force
echo Yad555883 | sudo -S -p '' sudo -u www-data env HOME=/tmp php8.3 artisan view:clear
echo Yad555883 | sudo -S -p '' sudo -u www-data env HOME=/tmp php8.3 artisan config:clear
echo Yad555883 | sudo -S -p '' sudo -u www-data env HOME=/tmp php8.3 artisan route:clear
echo Yad555883 | sudo -S -p '' systemctl reload php8.3-fpm
echo "deployed"
