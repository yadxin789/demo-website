#!/bin/bash
# S18 线上部署：组织表迁移 + 组织演示数据 + 缓存重建
set -e
cd /var/www/demo-website
SUDO="sudo -S -p ''"
run_as_www() { echo Yad555883 | sudo -S -p '' -u www-data env HOME=/tmp "$@"; }

echo '== migrate pm'
run_as_www php8.3 artisan migrate --database=pm --path=app/Modules/Pm/Database/Migrations --force
echo '== migrate shop'
run_as_www php8.3 artisan migrate --database=shop --path=app/Modules/Shop/Database/Migrations --force

echo '== seed pm org'
run_as_www php8.3 artisan db:seed --class='App\Modules\Pm\Database\Seeders\OrgDemoSeeder' --force
echo '== seed shop org'
run_as_www php8.3 artisan db:seed --class='App\Modules\Shop\Database\Seeders\OrgDemoSeeder' --force

echo '== caches'
run_as_www php8.3 artisan config:cache
run_as_www php8.3 artisan view:clear
run_as_www php8.3 artisan route:clear
echo Yad555883 | sudo -S -p '' chown -R www-data:www-data /var/www/demo-website/storage /var/www/demo-website/bootstrap/cache
echo Yad555883 | sudo -S -p '' systemctl reload php8.3-fpm
echo Yad555883 | sudo -S -p '' systemctl reload nginx
echo DONE
