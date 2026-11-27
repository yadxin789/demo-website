#!/bin/bash
# 后台页面业务入口扫描（线上，登录态）
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
scan() {
  local prod=$1; shift
  for p in "$@"; do
    hits=$(curl -s -b "$J/$prod.cookie" "$BASE/$p" | grep -oE "/(erp/(sales|purchase|production|inventory|quality|finance|materials)|pm/(project|task)|shop/(pos|products|purchase-orders|receipts|putaway)|mes/(work-orders|quality|equipment)|hr/(employees|attendance|payroll))" | sort -u | tr '\n' ' ')
    navs=$(curl -s -b "$J/$prod.cookie" "$BASE/$p" | grep -c 'class="navx')
    echo "  $p  navx=$navs  业务入口: ${hits:-无}"
  done
}

login erp account admin 123456 erp/login
scan erp erp/admin erp/admin/users erp/admin/departments erp/admin/roles erp/admin/ops erp/admin/settings
login pm username admin 123456 pm/login
scan pm pm/admin pm/admin/users pm/admin/departments pm/admin/positions pm/admin/roles pm/admin/ops
login shop username admin 123456 shop/login
scan shop shop/admin shop/admin/users shop/admin/departments shop/admin/positions shop/admin/roles
login mes account admin 123456 mes/login
scan mes mes/admin mes/admin/users mes/admin/departments mes/admin/roles
login hr account admin 123456 hr/login
scan hr hr/admin hr/admin/users hr/admin/departments hr/admin/roles
rm -rf "$J"
