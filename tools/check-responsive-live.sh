#!/bin/bash
cd /var/www/demo-website
echo Yad555883 | sudo -S -p '' -u www-data env HOME=/tmp php8.3 artisan cache:clear >/dev/null
sleep 3
bash /tmp/smoke-live.sh 2>&1 | grep -E 'ERR|通过'
for u in / /erp/login /pm/login /shop/login /mes/login /hr/login; do
  code=$(curl -s -o /tmp/p.html -w '%{http_code}' "http://127.0.0.1$u")
  has=$(grep -c responsive.css /tmp/p.html)
  echo "$u http=$code responsive=$has"
done
