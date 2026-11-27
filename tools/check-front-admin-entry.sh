#!/bin/bash
# 前台侧栏是否提供"系统管理"入口
set -u
BASE=http://127.0.0.1
J=$(mktemp -d)
login() {
  local prod=$1 field=$2 acct=$3 pwd=$4 action=$5
  rm -f "$J/$prod.cookie"
  curl -s -o /dev/null -c "$J/$prod.cookie" "$BASE/$prod/login"
  token=$(curl -s -c "$J/$prod.cookie" -b "$J/$prod.cookie" "$BASE/$prod/login" | grep -o 'name="_token" value="[^"]*"' | head -1 | sed 's/.*value="//;s/"//')
  curl -s -o /dev/null -c "$J/$prod.cookie" -b "$J/$prod.cookie" -L -d "_token=$token" -d "$field=$acct" -d "password=$pwd" "$BASE/$action"
}
probe() { local prod=$1 page=$2; echo "== $prod $page"; curl -s -b "$J/$prod.cookie" "$BASE/$page" | grep -oE 'href="[^"]*/(admin|system)[^"]*"' | sort -u | head -12; }

login erp account admin 123456 erp/login; probe erp erp/home
login pm username admin 123456 pm/login; probe pm pm/dashboard
login shop username admin 123456 shop/login; probe shop shop/dashboard
login mes account admin 123456 mes/login; probe mes mes/dashboard
login hr account admin 123456 hr/login; probe hr hr/dashboard
rm -rf "$J"
