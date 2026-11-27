#!/bin/bash
# 线上冒烟：各产品登录后抽查关键页面（只读 GET），统计 HTTP 码与 <td 数量
# 用法：bash /tmp/smoke-live.sh
set -u
BASE=http://127.0.0.1
J=$(mktemp -d)
pass=0; fail=0

login() { # $1=产品 $2=登录字段 $3=账号 $4=密码 $5=提交路径
  local prod=$1 field=$2 acct=$3 pwd=$4 action=$5
  rm -f "$J/$prod.cookie"
  code=$(curl -s -o /dev/null -c "$J/$prod.cookie" "$BASE/$prod/login")
  token=$(curl -s -c "$J/$prod.cookie" -b "$J/$prod.cookie" "$BASE/$prod/login" \
    | grep -o 'name="_token" value="[^"]*"' | head -1 | sed 's/.*value="//;s/"//')
  code=$(curl -s -o /dev/null -w '%{http_code}' -c "$J/$prod.cookie" -b "$J/$prod.cookie" \
    -L -d "_token=$token" -d "$field=$acct" -d "password=$pwd" "$BASE/$action")
  echo "  登录 $prod → $code"
}

check() { # $1=产品 $2=路径
  local prod=$1 path=$2
  body=$(curl -s -b "$J/$prod.cookie" "$BASE/$path")
  http=$(curl -s -o /dev/null -w '%{http_code}' -b "$J/$prod.cookie" "$BASE/$path")
  td=$(printf '%s' "$body" | grep -o '<td' | wc -l)
  if [ "$http" = "200" ]; then
    printf '  OK  %-42s http=%s td=%s\n' "$path" "$http" "$td"
    pass=$((pass+1))
  else
    printf '  ERR %-42s http=%s\n' "$path" "$http"
    fail=$((fail+1))
  fi
}

echo '== ERP'
login erp account admin 123456 erp/login
for p in erp/home erp/sales/quotes erp/sales/orders erp/purchase/orders erp/production/orders \
         erp/inventory erp/materials erp/quality/inspections erp/finance/invoices erp/finance/payments \
         erp/hr/employees erp/rnd/projects erp/design/id-files erp/design/pkg-files erp/approvals erp/trash \
         erp/announcements erp/notifications erp/todos erp/ai/chat erp/wecom erp/support \
         erp/ecom/platforms erp/product/brands erp/production/routings erp/knowledge erp/reports; do check erp "$p"; done

echo '== ERP 后台'
for p in erp/admin erp/admin/users erp/admin/roles erp/admin/ops erp/admin/settings \
         erp/admin/departments erp/admin/positions erp/admin/ai-guide erp/admin/master-data; do check erp "$p"; done

echo '== PM'
login pm username admin 123456 pm/login
for p in pm/dashboard pm/project pm/project/my-projects pm/task/my-tasks pm/task/my-audit-tasks \
         pm/task/my-tasks/pending-execution pm/notification pm/todo pm/knowledge pm/file \
         pm/file/resource-files pm/support/department-files pm/project-template pm/admin/users \
         pm/admin/roles pm/admin/collab-trash; do check pm "$p"; done

echo '== PM 后台'
for p in pm/admin pm/admin/departments pm/admin/positions pm/admin/ai-guide pm/admin/ops pm/admin/settings; do check pm "$p"; done

echo '== SHOP'
login shop username admin 123456 shop/login
for p in shop/dashboard shop/products shop/purchase-orders shop/sales-returns shop/profit-stock \
         shop/receipts shop/putaway shop/pos shop/expenses shop/payment-gateways shop/financial-reports \
         shop/reconciliations shop/customers shop/suppliers shop/warehouses shop/categories; do check shop "$p"; done

echo '== SHOP 后台'
for p in shop/admin shop/admin/users shop/admin/departments shop/admin/positions \
         shop/admin/roles shop/admin/ai-guide shop/admin/ops shop/admin/settings; do check shop "$p"; done

echo '== MES'
login mes account admin 123456 mes/login
for p in mes/dashboard mes/work-orders mes/kanban mes/quality/inspections mes/quality/defects \
         mes/equipment mes/maintenance/plans mes/master/materials mes/master/boms mes/master/routings \
         mes/my-tasks mes/trace mes/reports mes/system/users mes/system/roles; do check mes "$p"; done

echo '== MES 后台'
for p in mes/admin mes/admin/users mes/admin/departments mes/admin/positions \
         mes/admin/roles mes/admin/ai-guide mes/admin/ops mes/admin/settings; do check mes "$p"; done

echo '== HR'
login hr account admin 123456 hr/login
for p in hr/dashboard hr/employees hr/org/departments hr/org/positions hr/attendance/records \
         hr/leaves hr/payroll/runs hr/performance/reviews hr/recruit/requisitions hr/system/users hr/system/roles; do check hr "$p"; done

echo '== HR 后台'
for p in hr/admin hr/admin/users hr/admin/departments hr/admin/positions \
         hr/admin/roles hr/admin/ai-guide hr/admin/ops hr/admin/settings; do check hr "$p"; done

echo
echo "通过 $pass 项，失败 $fail 项"
rm -rf "$J"
