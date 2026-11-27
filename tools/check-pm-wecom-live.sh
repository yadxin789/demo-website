#!/bin/bash
B=http://127.0.0.1
J=/tmp/pm-cookie.txt
rm -f $J
curl -s -c $J "$B/pm/login" -H "Host: 123.207.233.94" > /tmp/pm-login.html
TOK=$(grep -o 'name="_token" value="[^"]*"' /tmp/pm-login.html | head -1 | sed 's/.*value="//;s/"//')
curl -s -b $J -c $J -X POST "$B/pm/login" -H "Host: 123.207.233.94" -d "_token=$TOK&username=admin&password=123456" -o /dev/null
echo "dashboard: $(curl -s -b $J -o /tmp/pm-dash.html -w '%{http_code}' "$B/pm/dashboard" -H 'Host: 123.207.233.94')"
echo "sidebar 企业微信: $(grep -c '企业微信' /tmp/pm-dash.html)"
echo "sidebar 企微文件收件箱: $(grep -c '企微文件收件箱' /tmp/pm-dash.html)"
for u in admin/wecom/settings admin/wecom/message-logs profile/wecom wecom-file; do
  code=$(curl -s -b $J -o /tmp/pm-w.html -w '%{http_code}' "$B/pm/$u" -H 'Host: 123.207.233.94')
  echo "$u => $code len=$(wc -c < /tmp/pm-w.html) wecom_kw=$(grep -c '企业微信' /tmp/pm-w.html)"
done
curl -s -b $J "$B/pm/admin/wecom/settings" -H 'Host: 123.207.233.94' | grep -o 'http://123.207.233.94/pm/wecom/callback/event' | head -1
