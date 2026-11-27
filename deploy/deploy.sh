#!/usr/bin/env bash
# ============================================================================
#  demo-website 一键部署 / 重部署（五产品单入口演示站）
#
#  适用：Ubuntu 22.04/24.04 + Nginx + PHP 8.3-FPM，SQLite（无 MySQL/Redis 依赖）
#
#  用法（在服务器项目目录）：
#    ./deploy/deploy.sh             常规部署：装依赖、六库迁移、种子、缓存、权限、reload
#    ./deploy/deploy.sh demo        同上，并在空库里补灌 Shop 演示业务数据
#    ./deploy/deploy.sh check       只做健康检查（不写库、不改权限）
#    ./deploy/deploy.sh help        帮助
#
#  代码更新流程：rsync/git pull 到 $APP_DIR → ./deploy/deploy.sh
# ============================================================================

set -euo pipefail

APP_DIR="${APP_DIR:-/var/www/demo-website}"
PHP_BIN="${PHP_BIN:-$(command -v php8.3 || command -v php)}"
COMPOSER_BIN="${COMPOSER_BIN:-composer}"
WEB_USER="${WEB_USER:-www-data}"
WEB_GROUP="${WEB_GROUP:-www-data}"
FPM_SERVICE="${FPM_SERVICE:-php8.3-fpm}"

# 五个产品：S5(MES)、S6(HR) 完成后全部参与部署
PRODUCTS=(erp pm shop mes hr)
declare -A TITLE=( [erp]=Erp [pm]=Pm [shop]=Shop [mes]=Mes [hr]=Hr )

MODE="${1:-deploy}"
SUDO="$([ "$(id -u)" -eq 0 ] && echo "" || echo "sudo")"

log()  { echo -e "\033[1;36m[deploy]\033[0m $*"; }
ok()   { echo -e "\033[1;32m[ ok ]\033[0m $*"; }
warn() { echo -e "\033[1;33m[warn]\033[0m $*"; }
die()  { echo -e "\033[1;31m[fail]\033[0m $*" >&2; exit 1; }

cd "$APP_DIR" 2>/dev/null || die "项目目录不存在：$APP_DIR"
[ -f artisan ] || die "当前目录不是 Laravel 项目（缺少 artisan）"

case "$MODE" in
  help|-h|--help)
    grep -E '^#( |$)' "$0" | sed 's/^# \{0,1\}//'
    exit 0
    ;;
  deploy|demo|check) ;;
  *) die "未知模式：$MODE（可用：deploy / demo / check / help）" ;;
esac

# ============================================================================
#  check 模式：只读健康检查
# ============================================================================
if [ "$MODE" = "check" ]; then
  log "环境检查"
  "$PHP_BIN" -v | head -1
  "$PHP_BIN" artisan --version
  echo
  log "五个 SQLite 库"
  for f in erp pm shop mes hr portal; do
    p="database/${f}.sqlite"
    if [ -f "$p" ]; then
      printf '  %-14s %8s B  %s\n' "$p" "$(stat -c%s "$p")" "$([ -r "$p" ] && echo readable || echo NOT-READABLE)"
    else
      printf '  %-14s %s\n' "$p" "缺失"
    fi
  done
  echo
  log "各库表数"
  for c in "${PRODUCTS[@]}"; do
    n=$("$PHP_BIN" artisan tinker --execute="echo \Illuminate\Support\Facades\DB::connection('$c')->getSchemaBuilder()->getTableListing() ? count(\Illuminate\Support\Facades\DB::connection('$c')->getSchemaBuilder()->getTableListing()) : 0;" 2>/dev/null || echo '?')
    printf '  %-6s %s\n' "$c" "$n"
  done
  echo
  log "路由总数"
  "$PHP_BIN" artisan route:list --json --except-vendor 2>/dev/null | "$PHP_BIN" -r '$j=json_decode(stream_get_contents(STDIN),true); echo count($j);' || warn "route:list 失败"
  echo
  log "门户与五产品登录页可达性"
  for u in / /about /erp/login /pm/login /shop/login /mes/login /hr/login; do
    code=$(curl -s -o /dev/null -w '%{http_code}' -m 8 "http://127.0.0.1${u}" || echo 'ERR')
    printf '  %-14s %s\n' "$u" "$code"
  done
  exit 0
fi

# ============================================================================
#  正式部署
# ============================================================================
[ -f .env ] || die "缺少 .env —— 先执行： cp deploy/env.production.example .env && php artisan key:generate"

if grep -q '^APP_ENV=local' .env; then
  warn "当前 .env 是 APP_ENV=local，生产环境请改为 production 且 APP_DEBUG=false"
fi
if grep -q '^APP_DEBUG=true' .env; then
  warn "APP_DEBUG=true 在公网会暴露路径与 SQL，上线前务必改 false"
fi

# ---- 1. 依赖 ----
log "安装 Composer 依赖（--no-dev，生产优化）"
"$COMPOSER_BIN" install --no-dev --optimize-autoloader --no-interaction --no-progress

# ---- 2. 六库迁移（五产品 + 门户）----
log "迁移 portal 库（leads 线索表，app/Core/Database/Migrations）"
"$PHP_BIN" artisan migrate \
  --database=portal \
  --path="app/Core/Database/Migrations" \
  --force --no-interaction

for p in "${PRODUCTS[@]}"; do
  log "迁移 ${p} 库（app/Modules/${TITLE[$p]}/Database/Migrations）"
  "$PHP_BIN" artisan migrate \
    --database="$p" \
    --path="app/Modules/${TITLE[$p]}/Database/Migrations" \
    --force --no-interaction
done

# ---- 3. 种子 ----
for p in "${PRODUCTS[@]}"; do
  log "种子 ${p}（角色 / 权限 / 演示账号 / 基线数据）"
  "$PHP_BIN" artisan db:seed \
    --class="App\\Modules\\${TITLE[$p]}\\Database\\Seeders\\DatabaseSeeder" \
    --database="$p" --force --no-interaction
done

if [ "$MODE" = "demo" ]; then
  # Shop 的演示业务数据不在 DatabaseSeeder 里，且非幂等：仅当商品表为空时补灌
  cnt=$("$PHP_BIN" artisan tinker --execute="echo \App\Modules\Shop\Models\Product::count();" 2>/dev/null | tr -dc '0-9')
  if [ -z "$cnt" ] || [ "$cnt" -eq 0 ]; then
    log "补灌 Shop 演示业务数据（商品 / 仓库 / POS 订单 / 应收应付）"
    "$PHP_BIN" artisan db:seed --class="App\\Modules\\Shop\\Database\\Seeders\\DemoDataSeeder" --database=shop --force --no-interaction
    "$PHP_BIN" artisan db:seed --class="App\\Modules\\Shop\\Database\\Seeders\\AiSeeder" --database=shop --force --no-interaction
  else
    warn "Shop 已有 ${cnt} 个商品，跳过演示数据（要重灌请先清库）"
  fi
else
  warn "未补灌 Shop 业务数据；需要时执行 ./deploy/deploy.sh demo"
fi

# ---- 4. 存储与缓存 ----
"$PHP_BIN" artisan storage:link >/dev/null 2>&1 || true

log "重置 spatie 权限缓存（重建库后旧权限快照会让权限页全 403）"
"$PHP_BIN" artisan permission:cache-reset >/dev/null 2>&1 || warn "permission:cache-reset 失败（spatie 未装此命令时忽略）"

log "重建缓存（config / view / event；route 有闭包时跳过）"
"$PHP_BIN" artisan config:clear
"$PHP_BIN" artisan view:clear
"$PHP_BIN" artisan config:cache
"$PHP_BIN" artisan view:cache
"$PHP_BIN" artisan event:cache
"$PHP_BIN" artisan route:clear
if "$PHP_BIN" artisan route:cache >/dev/null 2>&1; then
  ok "route:cache 已生成"
else
  warn "route:cache 失败（存在闭包路由），保留路由动态解析，功能不受影响"
fi

# ---- 5. 权限 ----
log "设置目录属主与权限"
$SUDO chown -R "$WEB_USER:$WEB_GROUP" "$APP_DIR/storage" "$APP_DIR/bootstrap/cache" "$APP_DIR/database"
# SQLite 是"文件即库"：FPM 进程（组 $WEB_GROUP）必须能写库文件，还要能在 database/ 里建 -journal/-wal，
# 所以目录 770、库文件 660；750/640 会让登录、写业务数据全部报 "attempt to write a readonly database"。
chmod 770 "$APP_DIR/database"
chmod 660 "$APP_DIR"/database/*.sqlite 2>/dev/null || true
chmod g+s "$APP_DIR/database"
chmod 640 "$APP_DIR/.env"
chmod -R g+rwX "$APP_DIR/storage" "$APP_DIR/bootstrap/cache"

# ---- 6. 重载服务 ----
log "重载 PHP-FPM 与 Nginx"
$SUDO systemctl reload "$FPM_SERVICE"
$SUDO systemctl reload nginx 2>/dev/null || warn "nginx reload 失败，请手动检查配置"

ok "部署完成 → $("$PHP_BIN" artisan tinker --execute="echo config('app.url');" 2>/dev/null)"
log "自检：./deploy/deploy.sh check"

