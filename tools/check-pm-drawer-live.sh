#!/bin/bash
B=http://127.0.0.1
J=/tmp/pm-cookie3.txt
rm -f $J
curl -s -c $J "$B/pm/login" -H "Host: 123.207.233.94" > /tmp/pm-login3.html
TOK=$(grep -o 'name="_token" value="[^"]*"' /tmp/pm-login3.html | head -1 | sed 's/.*value="//;s/"//')
curl -s -b $J -c $J -X POST "$B/pm/login" -H "Host: 123.207.233.94" -d "_token=$TOK&username=admin&password=123456" -o /dev/null
curl -s -b $J "$B/pm/admin" -H 'Host: 123.207.233.94' > /tmp/pm-admin2.html
echo "len=$(wc -c < /tmp/pm-admin2.html)"
echo "AI 设置 (all)=$(grep -c 'AI 设置' /tmp/pm-admin2.html)  AI 授权中心=$(grep -c 'AI 授权中心' /tmp/pm-admin2.html)"
echo "bell=$(grep -c 'fa-bell' /tmp/pm-admin2.html)  avatar=$(grep -c 'user-avatar' /tmp/pm-admin2.html)  dropdown=$(grep -c 'dropdown-menu' /tmp/pm-admin2.html)"
# 抽屉区间
START=$(grep -n 'id="mobileSidebar"' /tmp/pm-admin2.html | head -1 | cut -d: -f1)
END=$(awk -v s="$START" 'NR>s && /<script src=/{print NR; exit}' /tmp/pm-admin2.html)
echo "drawer lines: $START..$END"
SEG=$(sed -n "${START},${END}p" /tmp/pm-admin2.html)
echo "$SEG" > /tmp/pm-drawer.html
echo "drawer AI 设置=$(grep -c 'AI 设置' /tmp/pm-drawer.html) 授权=$(grep -c 'AI 授权中心' /tmp/pm-drawer.html) 退出=$(grep -c '退出登录' /tmp/pm-drawer.html)"
echo "drawer items:"
sed "s/<[^>]*>//g" /tmp/pm-drawer.html | tr -s ' ' | grep -v '^ *$' | head -30
