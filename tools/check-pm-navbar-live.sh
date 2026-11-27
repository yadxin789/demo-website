#!/bin/bash
B=http://127.0.0.1
J=/tmp/pm-cookie2.txt
rm -f $J
curl -s -c $J "$B/pm/login" -H "Host: 123.207.233.94" > /tmp/pm-login2.html
TOK=$(grep -o 'name="_token" value="[^"]*"' /tmp/pm-login2.html | head -1 | sed 's/.*value="//;s/"//')
curl -s -b $J -c $J -X POST "$B/pm/login" -H "Host: 123.207.233.94" -d "_token=$TOK&username=admin&password=123456" -o /dev/null
curl -s -b $J "$B/pm/admin" -H 'Host: 123.207.233.94' > /tmp/pm-admin.html
echo "len=$(wc -c < /tmp/pm-admin.html)"
for kw in "fa-bell" "user-avatar" "dropdown-menu" "AI 设置" "AI 智能助手" "AI 授权中心" "退出登录" "nav-link dropdown-toggle"; do
  echo "$kw => $(grep -c "$kw" /tmp/pm-admin.html)"
done
echo "--- navbar head 40 lines ---"
awk '/<nav class="navbar/{f=1} f{print NR": "$0}' /tmp/pm-admin.html | head -30
