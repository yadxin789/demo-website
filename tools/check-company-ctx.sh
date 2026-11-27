#!/bin/bash
# 抓取疑似残留文案的上下文片段
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
ctx() { grep -oE ".{0,40}$2.{0,40}" ; }

login erp account admin 123456 erp/login
echo '== ERP sales/quotes 有限公司'
curl -s -b "$J/erp.cookie" "$BASE/erp/sales/quotes" | tr '\n' ' ' | grep -oE '.{0,45}有限公司.{0,20}' | head -5
echo '== ERP sales/quotes 科技有限公司'
curl -s -b "$J/erp.cookie" "$BASE/erp/sales/quotes" | tr '\n' ' ' | grep -oE '.{0,45}科技有限公司.{0,20}' | head -3

login shop username admin 123456 shop/login
echo '== SHOP dashboard 悦家'
curl -s -b "$J/shop.cookie" "$BASE/shop/dashboard" | tr '\n' ' ' | grep -oE '.{0,45}悦家.{0,30}' | head -4

login hr account admin 123456 hr/login
echo '== HR employees 集团'
curl -s -b "$J/hr.cookie" "$BASE/hr/employees" | tr '\n' ' ' | grep -oE '.{0,35}集团.{0,25}' | head -6
rm -rf "$J"
