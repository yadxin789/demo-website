#!/bin/bash
# 线上渲染态文案复查：抓页面 HTML，搜索真实公司/品牌残留
set -u
BASE=http://127.0.0.1
J=$(mktemp -d)

login() {
  local prod=$1 field=$2 acct=$3 pwd=$4 action=$5
  rm -f "$J/$prod.cookie"
  token=$(curl -s -c "$J/$prod.cookie" "$BASE/$prod/login" | grep -o 'name="_token" value="[^"]*"' | head -1 | sed 's/.*value="//;s/"//')
  curl -s -o /dev/null -c "$J/$prod.cookie" -b "$J/$prod.cookie" -L \
    -d "_token=$token" -d "$field=$acct" -d "password=$pwd" "$BASE/$action"
}

PAT='有限公司|集团|华瑞|精工精密|DDWL|悦家|村田|世强|华强|国巨|恒创|智软|科技有限公司'

fetch_all() {
  local prod=$1; shift
  for p in "$@"; do
    curl -s -b "$J/$prod.cookie" "$BASE/$p" | grep -oE "$PAT" | sort | uniq -c | sed "s|^|  $p: |"
  done
}

login erp account admin 123456 erp/login
for p in erp/login erp/home erp/reports erp/sales/quotes erp/hr/employees; do
  curl -s -b "$J/erp.cookie" "$BASE/$p" | grep -oE "$PAT" | sort | uniq -c | sed "s|^|  ERP $p: |"
done

login pm username admin 123456 pm/login
for p in pm/dashboard pm/project; do
  curl -s -b "$J/pm.cookie" "$BASE/$p" | grep -oE "$PAT" | sort | uniq -c | sed "s|^|  PM $p: |"
done

login shop username admin 123456 shop/login
for p in shop/dashboard shop/products; do
  curl -s -b "$J/shop.cookie" "$BASE/$p" | grep -oE "$PAT" | sort | uniq -c | sed "s|^|  SHOP $p: |"
done

login mes account admin 123456 mes/login
for p in mes/dashboard mes/work-orders; do
  curl -s -b "$J/mes.cookie" "$BASE/$p" | grep -oE "$PAT" | sort | uniq -c | sed "s|^|  MES $p: |"
done

login hr account admin 123456 hr/login
for p in hr/dashboard hr/employees; do
  curl -s -b "$J/hr.cookie" "$BASE/$p" | grep -oE "$PAT" | sort | uniq -c | sed "s|^|  HR $p: |"
done

# 未登录页（门户 + 各登录页）
for p in / /erp/login /pm/login /shop/login /mes/login /hr/login; do
  curl -s "$BASE$p" | grep -oE "$PAT" | sort | uniq -c | sed "s|^|  ANON $p: |"
done

echo '复查完成（无输出即代表无残留）'
rm -rf "$J"
