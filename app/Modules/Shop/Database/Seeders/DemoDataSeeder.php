<?php

namespace App\Modules\Shop\Database\Seeders;

use App\Modules\Shop\Models\AccountEntry;
use App\Modules\Shop\Models\BankStatement;
use App\Modules\Shop\Models\Bill;
use App\Modules\Shop\Models\Invoice;
use App\Modules\Shop\Models\Journal;
use App\Modules\Shop\Models\Notification;
use App\Modules\Shop\Models\Payment;
use App\Modules\Shop\Models\PosOrder;
use App\Modules\Shop\Models\PosStation;
use App\Modules\Shop\Models\Product;
use App\Modules\Shop\Models\PurchaseOrder;
use App\Modules\Shop\Models\PurchaseReturn;
use App\Modules\Shop\Models\Receipt;
use App\Modules\Shop\Models\SalesOrder;
use App\Modules\Shop\Models\SalesReturn;
use App\Modules\Shop\Models\Scrap;
use App\Modules\Shop\Models\StockCount;
use App\Modules\Shop\Models\User;
use App\Modules\Shop\Services\Audit\AuditLogger;
use App\Modules\Shop\Services\NotificationService;
use App\Modules\Shop\Services\Pos\CartService;
use App\Modules\Shop\Services\Pos\CheckoutService;
use App\Modules\Shop\Services\Pos\SavedOrderService;
use App\Modules\Shop\Services\Pos\SessionService;
use App\Modules\Shop\Services\Pos\ShiftService;
use App\Modules\Shop\Services\Sales\ReturnService;
use App\Modules\Shop\Services\System\BackupService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * 演示数据：为 8 个演示账号生成一套可贯穿全部页面的业务样本。
 *
 * 只写入编码以 DM- 开头的主数据；如果已经存在演示商品，则直接跳过，保证可重复执行。
 * 数据按真实业务顺序生成：主数据 → 期初库存 → 采购/入库/应付 → 销售/应收
 * → 仓储作业 → POS 班次与订单 → 期间/银行对账 → 通知/审计 → 本地备份。
 */
class DemoDataSeeder extends Seeder
{
    private const P = 'DM-';

    private User $admin;
    private User $manager;
    private User $cashier;
    private User $sales;
    private User $warehouse;
    private User $purchase;
    private User $finance;
    private User $auditor;

    /** @var array<string, int> */
    private array $categoryIds = [];
    /** @var array<string, int> */
    private array $unitIds = [];
    /** @var array<string, int> */
    private array $taxIds = [];
    /** @var array<string, int> */
    private array $methodIds = [];
    /** @var array<string, int> */
    private array $warehouseIds = [];
    /** @var array<string, int> */
    private array $locationIds = [];
    /** @var array<string, int> */
    private array $supplierIds = [];
    /** @var array<string, int> */
    private array $customerIds = [];
    /** @var array<string, int> */
    private array $productIds = [];
    /** @var Collection<string, Product> */
    private Collection $products;
    /** @var array<string, int> */
    private array $accountIds = [];
    /** @var array<string, int> */
    private array $journalIds = [];

    public function run(): void
    {
        if (DB::table('products')->where('code', 'like', self::P.'%')->exists()) {
            $this->command?->warn('检测到已有演示商品（DM- 前缀），跳过本次演示数据生成。');

            return;
        }

        $this->loadUsers();

        DB::transaction(function (): void {
            $this->seedMasterData();
            $this->seedOpeningStock();
            $this->seedPurchaseChain();
            $this->seedSalesAndFinance();
            $this->seedWarehouseOperations();
            $this->seedPosHistory();
            $this->seedFiscalAndBank();
            $this->seedUserActivity();
        });

        Carbon::setTestNow();

        if (! app()->runningUnitTests()) {
            try {
                app(BackupService::class)->create('演示数据基线（8 个演示账号）', $this->admin->id);
            } catch (\Throwable $e) {
                $this->command?->warn('演示备份创建失败：'.$e->getMessage());
            }
        }

        $this->command?->info(sprintf(
            '演示数据完成：商品 %d、仓库 %d、供应商 %d、客户 %d、POS 订单 %d、销售订单 %d、应收 %d、应付 %d。',
            count($this->productIds),
            count($this->warehouseIds),
            count($this->supplierIds),
            count($this->customerIds),
            DB::table('pos_orders')->count(),
            DB::table('sales_orders')->count(),
            DB::table('invoices')->count(),
            DB::table('bills')->count()
        ));
    }

    private function loadUsers(): void
    {
        $this->admin = User::where('username', 'admin')->firstOrFail();
        $this->manager = User::where('username', 'manager')->firstOrFail();
        $this->cashier = User::where('username', 'cashier')->firstOrFail();
        $this->sales = User::where('username', 'sales')->firstOrFail();
        $this->warehouse = User::where('username', 'warehouse')->firstOrFail();
        $this->purchase = User::where('username', 'purchase')->firstOrFail();
        $this->finance = User::where('username', 'finance')->firstOrFail();
        $this->auditor = User::where('username', 'auditor')->firstOrFail();

        $this->accountIds = DB::table('accounts')->pluck('id', 'code')->map(fn ($id) => (int) $id)->all();
        $this->journalIds = DB::table('journals')->pluck('id', 'code')->map(fn ($id) => (int) $id)->all();
    }

    private function seedMasterData(): void
    {
        $now = now();

        // POS 挂账会自动生成应收单，需要 invoice 编号规则。
        if (! DB::table('sequences')->where('code', 'invoice')->exists()) {
            DB::table('sequences')->insert([
                'code' => 'invoice',
                'name' => '应收发票',
                'prefix' => 'INV',
                'suffix' => null,
                'padding' => 4,
                'next_number' => 1,
                'period_mode' => 'month',
                'last_reset_at' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $categories = [
            ['code' => self::P.'CAT-FOOD', 'name' => '食品饮料', 'parent' => null, 'sort' => 10, 'description' => '民生食品、饮料与粮油'],
            ['code' => self::P.'CAT-HOME', 'name' => '家清日用', 'parent' => null, 'sort' => 20, 'description' => '家庭清洁与日用百货'],
            ['code' => self::P.'CAT-APPLIANCE', 'name' => '小家电', 'parent' => null, 'sort' => 30, 'description' => '厨房与生活小家电'],
            ['code' => self::P.'CAT-DRINK', 'name' => '饮料水饮', 'parent' => self::P.'CAT-FOOD', 'sort' => 11],
            ['code' => self::P.'CAT-SNACK', 'name' => '休闲零食', 'parent' => self::P.'CAT-FOOD', 'sort' => 12],
            ['code' => self::P.'CAT-GRAIN', 'name' => '粮油调味', 'parent' => self::P.'CAT-FOOD', 'sort' => 13],
            ['code' => self::P.'CAT-CLEAN', 'name' => '清洁用品', 'parent' => self::P.'CAT-HOME', 'sort' => 21],
            ['code' => self::P.'CAT-PAPER', 'name' => '纸品家纺', 'parent' => self::P.'CAT-HOME', 'sort' => 22],
            ['code' => self::P.'CAT-KITCHEN', 'name' => '厨房电器', 'parent' => self::P.'CAT-APPLIANCE', 'sort' => 31],
        ];

        foreach ($categories as $category) {
            $parentId = $category['parent'] ? ($this->categoryIds[$category['parent']] ?? null) : null;
            $this->categoryIds[$category['code']] = DB::table('product_categories')->insertGetId([
                'parent_id' => $parentId,
                'code' => $category['code'],
                'name' => $category['name'],
                'description' => $category['description'] ?? null,
                'sort' => $category['sort'],
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        foreach ([
            ['code' => 'PCS', 'name' => '个', 'precision' => 0, 'base' => true],
            ['code' => 'BTL', 'name' => '瓶', 'precision' => 0, 'base' => false],
            ['code' => 'BAG', 'name' => '袋', 'precision' => 0, 'base' => false],
            ['code' => 'BOX', 'name' => '箱', 'precision' => 0, 'base' => false],
            ['code' => 'PKG', 'name' => '包', 'precision' => 0, 'base' => false],
            ['code' => 'KG', 'name' => '千克', 'precision' => 3, 'base' => false],
        ] as $unit) {
            $this->unitIds[$unit['code']] = DB::table('units')->insertGetId([
                'name' => $unit['name'],
                'code' => $unit['code'],
                'precision' => $unit['precision'],
                'is_base' => $unit['base'],
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        foreach ([
            ['code' => 'VAT13', 'name' => '增值税 13%', 'rate' => 13, 'account' => '2004'],
            ['code' => 'VAT9', 'name' => '增值税 9%', 'rate' => 9, 'account' => '2004'],
            ['code' => 'VAT0', 'name' => '免税/零税率', 'rate' => 0, 'account' => null],
        ] as $tax) {
            $this->taxIds[$tax['code']] = DB::table('taxes')->insertGetId([
                'name' => $tax['name'],
                'code' => $tax['code'],
                'rate' => $tax['rate'],
                'type' => 'exclusive',
                'account_id' => $tax['account'] ? $this->accountIds[$tax['account']] : null,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        foreach ([
            ['code' => 'CASH', 'name' => '现金', 'type' => 'cash', 'account' => '1004', 'default' => true],
            ['code' => 'WECHAT', 'name' => '微信支付', 'type' => 'electronic', 'account' => '1005', 'default' => false],
            ['code' => 'ALIPAY', 'name' => '支付宝', 'type' => 'electronic', 'account' => '1005', 'default' => false],
            ['code' => 'CARD', 'name' => '银行卡', 'type' => 'card', 'account' => '1005', 'default' => false],
            ['code' => 'CREDIT', 'name' => '客户挂账', 'type' => 'credit', 'account' => '1003', 'default' => false],
            ['code' => 'COUPON', 'name' => '储值券', 'type' => 'coupon', 'account' => '2005', 'default' => false],
        ] as $method) {
            $this->methodIds[$method['code']] = DB::table('payment_methods')->insertGetId([
                'name' => $method['name'],
                'code' => self::P.$method['code'],
                'type' => $method['type'],
                'account_id' => $this->accountIds[$method['account']],
                'is_default' => $method['default'],
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        foreach ([
            ['code' => self::P.'WH-MAIN', 'name' => '总仓', 'address' => '广州市白云区物流园 A 栋', 'default' => false],
            ['code' => self::P.'WH-STORE', 'name' => '门店仓', 'address' => '广州市天河路 88 号一层', 'default' => true],
            ['code' => self::P.'WH-RETURN', 'name' => '退货仓', 'address' => '广州市白云区物流园 B 栋', 'default' => false],
        ] as $warehouse) {
            $this->warehouseIds[$warehouse['code']] = DB::table('warehouses')->insertGetId([
                'code' => $warehouse['code'],
                'name' => $warehouse['name'],
                'address' => $warehouse['address'],
                'is_default' => $warehouse['default'],
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        foreach ([
            ['code' => 'A-01-01', 'name' => '总仓 A 区 01 位', 'wh' => self::P.'WH-MAIN', 'type' => 'storage'],
            ['code' => 'A-02-01', 'name' => '总仓 A 区 02 位', 'wh' => self::P.'WH-MAIN', 'type' => 'storage'],
            ['code' => 'B-01-01', 'name' => '总仓 B 区退货位', 'wh' => self::P.'WH-MAIN', 'type' => 'returns'],
            ['code' => 'STORE-01', 'name' => '门店销售区', 'wh' => self::P.'WH-STORE', 'type' => 'storage'],
            ['code' => 'STORE-02', 'name' => '门店收银区', 'wh' => self::P.'WH-STORE', 'type' => 'storage'],
            ['code' => 'RET-01', 'name' => '退货仓待检区', 'wh' => self::P.'WH-RETURN', 'type' => 'returns'],
        ] as $location) {
            $this->locationIds[$location['code']] = DB::table('locations')->insertGetId([
                'warehouse_id' => $this->warehouseIds[$location['wh']],
                'parent_id' => null,
                'code' => $location['code'],
                'name' => $location['name'],
                'type' => $location['type'],
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        foreach ([
            ['code' => self::P.'SUP-01', 'name' => '演示食品供应链（华南）有限公司', 'contact' => '陈经理', 'phone' => '020-88880001', 'address' => '广州市荔湾区食品产业园', 'tax' => '91440101DEMO001X'],
            ['code' => self::P.'SUP-02', 'name' => '演示日化贸易（华东）有限公司', 'contact' => '周经理', 'phone' => '021-66660002', 'address' => '上海市青浦区日化园', 'tax' => '91310118DEMO002X'],
            ['code' => self::P.'SUP-03', 'name' => '粤家电直供中心', 'contact' => '梁经理', 'phone' => '0757-26660003', 'address' => '佛山市顺德区家电城', 'tax' => '91440606DEMO003X'],
            ['code' => self::P.'SUP-04', 'name' => '演示粮油（华南）有限公司', 'contact' => '黄经理', 'phone' => '020-85550004', 'address' => '广州市黄埔区粮油市场', 'tax' => '91440112DEMO004X'],
        ] as $supplier) {
            $this->supplierIds[$supplier['code']] = DB::table('suppliers')->insertGetId([
                'code' => $supplier['code'],
                'name' => $supplier['name'],
                'contact_name' => $supplier['contact'],
                'phone' => $supplier['phone'],
                'email' => strtolower(str_replace('DEMO', 'demo', $supplier['tax'])).'@demo.example.com',
                'address' => $supplier['address'],
                'tax_no' => $supplier['tax'],
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        foreach ([
            ['code' => self::P.'CUS-01', 'name' => '散客会员', 'contact' => '—', 'phone' => '13800000001', 'level' => 'normal', 'credit' => 0, 'discount' => 0],
            ['code' => self::P.'CUS-02', 'name' => '张女士', 'contact' => '张女士', 'phone' => '13800000002', 'level' => 'silver', 'credit' => 2000, 'discount' => 2],
            ['code' => self::P.'CUS-03', 'name' => '李先生', 'contact' => '李先生', 'phone' => '13800000003', 'level' => 'gold', 'credit' => 5000, 'discount' => 5],
            ['code' => self::P.'CUS-04', 'name' => '华城物业采购部', 'contact' => '刘主管', 'phone' => '020-88880004', 'level' => 'gold', 'credit' => 10000, 'discount' => 3],
            ['code' => self::P.'CUS-05', 'name' => '阳光便利店', 'contact' => '王老板', 'phone' => '13900000005', 'level' => 'silver', 'credit' => 8000, 'discount' => 4],
            ['code' => self::P.'CUS-06', 'name' => '金卡会员王女士', 'contact' => '王女士', 'phone' => '13800000006', 'level' => 'gold', 'credit' => 3000, 'discount' => 6],
        ] as $customer) {
            $this->customerIds[$customer['code']] = DB::table('customers')->insertGetId([
                'code' => $customer['code'],
                'name' => $customer['name'],
                'contact_name' => $customer['contact'],
                'phone' => $customer['phone'],
                'email' => null,
                'address' => null,
                'tax_no' => null,
                'membership_level' => $customer['level'],
                'credit_limit' => $customer['credit'],
                'default_discount_rate' => $customer['discount'],
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        foreach ($this->productRows() as $product) {
            $this->productIds[$product['code']] = DB::table('products')->insertGetId([
                'code' => self::P.$product['code'],
                'barcode' => $product['barcode'],
                'name' => $product['name'],
                'category_id' => $this->categoryIds[$product['category']],
                'unit_id' => $this->unitIds[$product['unit']],
                'tax_id' => $this->taxIds[$product['tax']],
                'cost_method' => 'standard',
                'standard_cost' => $product['cost'],
                'avg_cost' => $product['cost'],
                'list_price' => $product['price'],
                'traceability' => $product['trace'],
                'shelf_life_days' => $product['shelf'] ?? null,
                'description' => $product['description'] ?? null,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        foreach ([
            ['name' => '门店零售价', 'type' => 'sale'],
            ['name' => '企业客户阶梯价', 'type' => 'sale'],
        ] as $priceList) {
            $listId = DB::table('price_lists')->insertGetId([
                'name' => $priceList['name'],
                'type' => $priceList['type'],
                'currency' => 'CNY',
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            foreach (['P01', 'P03', 'P06', 'P07', 'P11', 'P12'] as $productCode) {
                $product = $this->productObject($productCode);
                foreach ([1, 12] as $minQty) {
                    DB::table('price_list_items')->insert([
                        'price_list_id' => $listId,
                        'product_id' => $product->id,
                        'min_qty' => $minQty,
                        'price' => round((float) $product->list_price * ($minQty === 1 ? 1 : 0.94), 2),
                        'valid_from' => Carbon::today()->subDays(20)->toDateString(),
                        'valid_to' => Carbon::today()->addDays(160)->toDateString(),
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }
            }
        }

        DB::table('receipt_templates')->insert([
            [
                'name' => '默认 58mm 模板',
                'store_name' => '豆包百货示范店',
                'address' => '广州市天河路 88 号',
                'phone' => '020-88888888',
                'footer' => '谢谢惠顾，欢迎再次光临！',
                'show_discount' => true,
                'show_cashier' => true,
                'show_barcode' => false,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'name' => '80mm 明细模板',
                'store_name' => '豆包百货示范店',
                'address' => '广州市天河路 88 号',
                'phone' => '020-88888888',
                'footer' => '商品七日无理由退换（生鲜除外）',
                'show_discount' => true,
                'show_cashier' => true,
                'show_barcode' => true,
                'is_active' => false,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);

        $allMethods = array_values($this->methodIds);
        foreach ([
            ['code' => 'POS-01', 'name' => '门店一楼收银台', 'wh' => self::P.'WH-STORE', 'loc' => 'STORE-01', 'auto' => false],
            ['code' => 'POS-02', 'name' => '门店二号收银台', 'wh' => self::P.'WH-STORE', 'loc' => 'STORE-02', 'auto' => true],
        ] as $station) {
            DB::table('pos_stations')->insert([
                'code' => self::P.$station['code'],
                'name' => $station['name'],
                'warehouse_id' => $this->warehouseIds[$station['wh']],
                'default_location_id' => $this->locationIds[$station['loc']],
                'payment_method_ids' => json_encode($allMethods, JSON_UNESCAPED_UNICODE),
                'receipt_template' => 'default',
                'auto_print_receipt' => $station['auto'],
                'printer_driver' => 'escpos',
                'printer_address' => '192.168.10.20',
                'printer_port' => '9100',
                'paper_width' => 58,
                'encoding' => 'gbk',
                'cash_drawer_pin' => '0',
                'status' => 'active',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $employees = [
            [$this->admin, 'EMP-001', '信息管理员', '信息技术部'],
            [$this->manager, 'EMP-002', '门店店长', '运营部'],
            [$this->cashier, 'EMP-003', '收银员', '门店'],
            [$this->sales, 'EMP-004', '销售顾问', '销售部'],
            [$this->warehouse, 'EMP-005', '仓管员', '仓储部'],
            [$this->purchase, 'EMP-006', '采购专员', '采购部'],
            [$this->finance, 'EMP-007', '会计', '财务部'],
            [$this->auditor, 'EMP-008', '内审专员', '审计部'],
        ];
        foreach ($employees as [$user, $code, $position, $department]) {
            DB::table('employees')->insert([
                'user_id' => $user->id,
                'code' => $code,
                'name' => $user->name,
                'phone' => '1380000'.str_pad((string) $user->id, 4, '0', STR_PAD_LEFT),
                'position' => $position.' / '.$department,
                'status' => 'active',
                'hired_at' => Carbon::today()->subDays(120 + $user->id * 10)->toDateString(),
                'note' => '演示员工档案',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $this->products = Product::whereIn('id', array_values($this->productIds))
            ->get()
            ->keyBy('code');
    }

    /** @return array<int, array<string, mixed>> */
    private function productRows(): array
    {
        return [
            ['code' => 'P01', 'barcode' => '6900000000012', 'name' => '农夫山泉饮用天然水 550ml', 'category' => self::P.'CAT-DRINK', 'unit' => 'BTL', 'tax' => 'VAT9', 'cost' => 1.20, 'price' => 2.00, 'trace' => 'none', 'shelf' => 365],
            ['code' => 'P02', 'barcode' => '6900000000029', 'name' => '可口可乐 500ml', 'category' => self::P.'CAT-DRINK', 'unit' => 'BTL', 'tax' => 'VAT9', 'cost' => 2.20, 'price' => 3.50, 'trace' => 'none', 'shelf' => 270],
            ['code' => 'P03', 'barcode' => '6900000000036', 'name' => '伊利纯牛奶 250ml', 'category' => self::P.'CAT-DRINK', 'unit' => 'BOX', 'tax' => 'VAT9', 'cost' => 2.60, 'price' => 4.00, 'trace' => 'lot', 'shelf' => 30],
            ['code' => 'P04', 'barcode' => '6900000000043', 'name' => '乐事原味薯片 70g', 'category' => self::P.'CAT-SNACK', 'unit' => 'BAG', 'tax' => 'VAT13', 'cost' => 4.80, 'price' => 8.50, 'trace' => 'none', 'shelf' => 180],
            ['code' => 'P05', 'barcode' => '6900000000050', 'name' => '奥利奥夹心饼干 116g', 'category' => self::P.'CAT-SNACK', 'unit' => 'BAG', 'tax' => 'VAT13', 'cost' => 5.50, 'price' => 10.00, 'trace' => 'lot', 'shelf' => 180],
            ['code' => 'P06', 'barcode' => '6900000000067', 'name' => '金龙鱼东北大米 5kg', 'category' => self::P.'CAT-GRAIN', 'unit' => 'BAG', 'tax' => 'VAT9', 'cost' => 28.00, 'price' => 42.00, 'trace' => 'none', 'shelf' => 365],
            ['code' => 'P07', 'barcode' => '6900000000074', 'name' => '金龙鱼调和油 5L', 'category' => self::P.'CAT-GRAIN', 'unit' => 'BTL', 'tax' => 'VAT9', 'cost' => 52.00, 'price' => 79.90, 'trace' => 'none', 'shelf' => 540],
            ['code' => 'P08', 'barcode' => '6900000000081', 'name' => '立白洗洁精 1.5kg', 'category' => self::P.'CAT-CLEAN', 'unit' => 'BTL', 'tax' => 'VAT13', 'cost' => 8.00, 'price' => 12.80, 'trace' => 'none', 'shelf' => 720],
            ['code' => 'P09', 'barcode' => '6900000000098', 'name' => '维达抽纸 3 层 6 包', 'category' => self::P.'CAT-PAPER', 'unit' => 'PKG', 'tax' => 'VAT13', 'cost' => 9.00, 'price' => 15.90, 'trace' => 'none', 'shelf' => null],
            ['code' => 'P10', 'barcode' => '6900000000104', 'name' => '云南白药牙膏 120g', 'category' => self::P.'CAT-CLEAN', 'unit' => 'PCS', 'tax' => 'VAT13', 'cost' => 7.00, 'price' => 13.90, 'trace' => 'none', 'shelf' => 900],
            ['code' => 'P11', 'barcode' => '6900000000111', 'name' => '美的电热水壶 1.7L', 'category' => self::P.'CAT-KITCHEN', 'unit' => 'PCS', 'tax' => 'VAT13', 'cost' => 65.00, 'price' => 99.00, 'trace' => 'serial', 'shelf' => null],
            ['code' => 'P12', 'barcode' => '6900000000128', 'name' => '苏泊尔电饭煲 4L', 'category' => self::P.'CAT-KITCHEN', 'unit' => 'PCS', 'tax' => 'VAT13', 'cost' => 180.00, 'price' => 299.00, 'trace' => 'serial', 'shelf' => null],
            ['code' => 'P13', 'barcode' => '6900000000135', 'name' => '公牛插线板 3 米', 'category' => self::P.'CAT-KITCHEN', 'unit' => 'PCS', 'tax' => 'VAT13', 'cost' => 25.00, 'price' => 39.90, 'trace' => 'none', 'shelf' => null],
            ['code' => 'P14', 'barcode' => '6900000000142', 'name' => '南孚 5 号电池 2 粒装', 'category' => self::P.'CAT-HOME', 'unit' => 'PKG', 'tax' => 'VAT13', 'cost' => 1.50, 'price' => 2.50, 'trace' => 'none', 'shelf' => 720],
            ['code' => 'P15', 'barcode' => '6900000000159', 'name' => '德芙丝滑巧克力 43g', 'category' => self::P.'CAT-SNACK', 'unit' => 'PCS', 'tax' => 'VAT13', 'cost' => 4.50, 'price' => 7.50, 'trace' => 'lot', 'shelf' => 300],
            ['code' => 'P16', 'barcode' => '6900000000166', 'name' => '农夫山泉整箱 24 瓶', 'category' => self::P.'CAT-DRINK', 'unit' => 'BOX', 'tax' => 'VAT9', 'cost' => 18.00, 'price' => 28.00, 'trace' => 'none', 'shelf' => 365],
            ['code' => 'P17', 'barcode' => '6900000000173', 'name' => '洁丽雅纯棉毛巾', 'category' => self::P.'CAT-PAPER', 'unit' => 'PCS', 'tax' => 'VAT13', 'cost' => 6.00, 'price' => 12.00, 'trace' => 'none', 'shelf' => null],
            ['code' => 'P18', 'barcode' => '6900000000180', 'name' => '妙洁平板拖把', 'category' => self::P.'CAT-CLEAN', 'unit' => 'PCS', 'tax' => 'VAT13', 'cost' => 20.00, 'price' => 35.00, 'trace' => 'none', 'shelf' => null],
            ['code' => 'P19', 'barcode' => '6900000000197', 'name' => '海天生抽酱油 500ml', 'category' => self::P.'CAT-GRAIN', 'unit' => 'BTL', 'tax' => 'VAT9', 'cost' => 6.00, 'price' => 9.90, 'trace' => 'lot', 'shelf' => 540],
            ['code' => 'P20', 'barcode' => '6900000000203', 'name' => '康师傅红烧牛肉面', 'category' => self::P.'CAT-SNACK', 'unit' => 'BAG', 'tax' => 'VAT13', 'cost' => 2.80, 'price' => 4.50, 'trace' => 'lot', 'shelf' => 180],
            ['code' => 'P21', 'barcode' => '6900000000210', 'name' => '舒肤佳沐浴露 720ml', 'category' => self::P.'CAT-CLEAN', 'unit' => 'BTL', 'tax' => 'VAT13', 'cost' => 22.00, 'price' => 35.90, 'trace' => 'none', 'shelf' => 720],
            ['code' => 'P22', 'barcode' => '6900000000227', 'name' => '心相印湿巾 80 抽', 'category' => self::P.'CAT-PAPER', 'unit' => 'PKG', 'tax' => 'VAT13', 'cost' => 4.20, 'price' => 7.90, 'trace' => 'none', 'shelf' => 540],
            ['code' => 'P23', 'barcode' => '6900000000234', 'name' => '九阳豆浆机 1.2L', 'category' => self::P.'CAT-KITCHEN', 'unit' => 'PCS', 'tax' => 'VAT13', 'cost' => 160.00, 'price' => 259.00, 'trace' => 'serial', 'shelf' => null],
            ['code' => 'P24', 'barcode' => '6900000000241', 'name' => '本地散装花生 500g', 'category' => self::P.'CAT-SNACK', 'unit' => 'KG', 'tax' => 'VAT9', 'cost' => 8.00, 'price' => 13.80, 'trace' => 'none', 'shelf' => 120],
        ];
    }

    private function productObject(string $code): Product
    {
        $fullCode = str_starts_with($code, self::P) ? $code : self::P.$code;

        if (isset($this->products)) {
            return $this->products->get($fullCode) ?? Product::where('code', $fullCode)->firstOrFail();
        }

        return Product::where('code', $fullCode)->firstOrFail();
    }

    private function seedOpeningStock(): void
    {
        Carbon::setTestNow(Carbon::today()->subDays(60)->setTime(8, 0));

        $storeQty = [
            'P01' => 120, 'P02' => 96, 'P04' => 80, 'P06' => 45, 'P07' => 35,
            'P08' => 48, 'P09' => 60, 'P10' => 50, 'P13' => 40, 'P14' => 3,
            'P16' => 30, 'P17' => 30, 'P18' => 25, 'P21' => 24, 'P22' => 55,
            'P24' => 12,
        ];
        $mainQty = [
            'P01' => 240, 'P02' => 180, 'P04' => 150, 'P06' => 120, 'P07' => 90,
            'P08' => 100, 'P09' => 120, 'P10' => 90, 'P13' => 80, 'P14' => 0,
            'P16' => 60, 'P17' => 60, 'P18' => 50, 'P21' => 60, 'P22' => 90,
            'P24' => 30,
        ];

        foreach ($this->productIds as $code => $productId) {
            $short = str_replace(self::P, '', $code);
            $product = $this->productObject($short);

            if ($product->traceability === 'lot') {
                $lots = match ($short) {
                    'P03' => [
                        ['DM-LOT-MILK-A', 42, 12],
                        ['DM-LOT-MILK-B', 36, 75],
                    ],
                    'P05' => [['DM-LOT-COOKIE-A', 30, 40]],
                    'P15' => [['DM-LOT-CHOCO-A', 25, 120]],
                    'P19' => [['DM-LOT-SOY-A', 36, 150]],
                    'P20' => [['DM-LOT-NOODLE-A', 2, 20]],
                    default => [],
                };

                foreach ($lots as [$lotNo, $qty, $expiryDays]) {
                    $this->createLot($product, $lotNo, $this->supplierIds[self::P.'SUP-01'], Carbon::today()->subDays(55)->toDateString(), Carbon::today()->addDays($expiryDays)->toDateString());
                    $this->stockIn($product, $this->warehouseIds[self::P.'WH-STORE'], $this->locationIds['STORE-01'], $qty, $lotNo, null, 'demo_opening', null, '演示期初库存');
                }

                $mainLotQty = in_array($short, ['P03', 'P05', 'P15', 'P19'], true) ? 80 : 30;
                if ($short === 'P20') {
                    $mainLotQty = 0;
                }
                if ($mainLotQty > 0) {
                    $lotNo = match ($short) {
                        'P03' => 'DM-LOT-MILK-C',
                        'P05' => 'DM-LOT-COOKIE-B',
                        'P15' => 'DM-LOT-CHOCO-B',
                        'P19' => 'DM-LOT-SOY-B',
                        default => 'DM-LOT-'.$short.'-M',
                    };
                    $this->createLot($product, $lotNo, $this->supplierIds[self::P.'SUP-01'], Carbon::today()->subDays(50)->toDateString(), Carbon::today()->addDays(90)->toDateString());
                    $this->stockIn($product, $this->warehouseIds[self::P.'WH-MAIN'], $this->locationIds['A-01-01'], $mainLotQty, $lotNo, null, 'demo_opening', null, '演示期初库存');
                }

                continue;
            }

            if ($product->traceability === 'serial') {
                for ($i = 1; $i <= 8; $i++) {
                    $this->stockIn($product, $this->warehouseIds[self::P.'WH-STORE'], $this->locationIds['STORE-01'], 1, null, 'SN-'.$short.'-'.str_pad((string) $i, 3, '0', STR_PAD_LEFT), 'demo_opening', null, '演示期初库存');
                }
                for ($i = 9; $i <= 12; $i++) {
                    $this->stockIn($product, $this->warehouseIds[self::P.'WH-MAIN'], $this->locationIds['A-01-01'], 1, null, 'SN-'.$short.'-'.str_pad((string) $i, 3, '0', STR_PAD_LEFT), 'demo_opening', null, '演示期初库存');
                }
                continue;
            }

            $this->stockIn($product, $this->warehouseIds[self::P.'WH-STORE'], $this->locationIds['STORE-01'], $storeQty[$short] ?? 40, null, null, 'demo_opening', null, '演示期初库存');
            if (($mainQty[$short] ?? 100) > 0) {
                $this->stockIn($product, $this->warehouseIds[self::P.'WH-MAIN'], $this->locationIds['A-01-01'], $mainQty[$short] ?? 100, null, null, 'demo_opening', null, '演示期初库存');
            }
        }

        // 临期/过期批次，供批次预警页展示不同状态。
        $milk = $this->productObject('P03');
        $this->createLot($milk, 'DM-LOT-MILK-EXPIRED', $this->supplierIds[self::P.'SUP-01'], Carbon::today()->subDays(40)->toDateString(), Carbon::today()->subDays(5)->toDateString(), 'expired');
        $this->stockIn($milk, $this->warehouseIds[self::P.'WH-RETURN'], $this->locationIds['RET-01'], 8, 'DM-LOT-MILK-EXPIRED', null, 'demo_opening', null, '临期退回批次');

        $cookie = $this->productObject('P05');
        $this->createLot($cookie, 'DM-LOT-COOKIE-EXPIRED', $this->supplierIds[self::P.'SUP-01'], Carbon::today()->subDays(60)->toDateString(), Carbon::today()->subDays(3)->toDateString(), 'expired');
        $this->stockIn($cookie, $this->warehouseIds[self::P.'WH-RETURN'], $this->locationIds['RET-01'], 6, 'DM-LOT-COOKIE-EXPIRED', null, 'demo_opening', null, '过期待销毁批次');

        // 一条跨库位调拨流水，丰富库存流水页。
        $rice = $this->productObject('P06');
        $this->stockTransfer($rice, $this->warehouseIds[self::P.'WH-MAIN'], $this->locationIds['A-01-01'], $this->locationIds['A-02-01'], 10);

        // 期初库存要回填 60 天前，后续采购/销售/POS 必须回到真实今天。
        Carbon::setTestNow();
    }

    private function createLot(Product $product, string $lotNo, int $supplierId, string $receiptDate, string $expiryDate, string $status = 'active'): void
    {
        DB::table('lots')->insert([
            'product_id' => $product->id,
            'lot_no' => $lotNo,
            'supplier_id' => $supplierId,
            'receipt_date' => $receiptDate,
            'expiry_date' => $expiryDate,
            'status' => $status,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function stockIn(Product $product, int $warehouseId, int $locationId, float $qty, ?string $lotNo, ?string $serialNo, ?string $sourceType = null, ?int $sourceId = null, ?string $note = null, ?string $sourceCode = null): void
    {
        if ($qty <= 0) {
            return;
        }

        $this->applyStock($product, $warehouseId, $locationId, $qty, $lotNo, $serialNo, 'in', $sourceType, $sourceId, $note, $sourceCode);
    }

    private function stockOut(Product $product, int $warehouseId, int $locationId, float $qty, ?string $lotNo, ?string $serialNo, string $sourceType, ?int $sourceId = null, ?string $note = null, ?string $sourceCode = null): void
    {
        if ($qty <= 0) {
            return;
        }

        $this->applyStock($product, $warehouseId, $locationId, -$qty, $lotNo, $serialNo, 'out', $sourceType, $sourceId, $note, $sourceCode);
    }

    private function applyStock(Product $product, int $warehouseId, int $locationId, float $delta, ?string $lotNo, ?string $serialNo, string $direction, ?string $sourceType, ?int $sourceId, ?string $note, ?string $sourceCode): void
    {
        $query = DB::table('stock_levels')
            ->where('product_id', $product->id)
            ->where('warehouse_id', $warehouseId)
            ->where('location_id', $locationId)
            ->where('lot_no', $lotNo)
            ->where('serial_no', $serialNo);

        $level = $query->first();
        $before = $level ? (float) $level->qty : 0.0;
        $after = round($before + $delta, 4);

        if ($after < -0.0001) {
            throw new \RuntimeException("演示库存不足：{$product->code} 当前 {$before}，变动 {$delta}");
        }

        if ($level) {
            DB::table('stock_levels')->where('id', $level->id)->update([
                'qty' => $after,
                'avg_cost' => $product->standard_cost,
                'updated_at' => now(),
            ]);
        } else {
            DB::table('stock_levels')->insert([
                'product_id' => $product->id,
                'warehouse_id' => $warehouseId,
                'location_id' => $locationId,
                'lot_no' => $lotNo,
                'serial_no' => $serialNo,
                'qty' => $after,
                'reserved_qty' => 0,
                'avg_cost' => $product->standard_cost,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        DB::table('stock_movements')->insert([
            'product_id' => $product->id,
            'warehouse_id' => $warehouseId,
            'location_id' => $locationId,
            'lot_no' => $lotNo,
            'serial_no' => $serialNo,
            'direction' => $direction,
            'qty_before' => $before,
            'qty_after' => $after,
            'qty' => abs($delta),
            'unit_cost' => $product->standard_cost,
            'movement_date' => now(),
            'source_type' => $sourceType,
            'source_id' => $sourceId,
            'source_code' => $sourceCode,
            'operator_id' => $this->warehouse->id,
            'note' => $note,
            'created_at' => now(),
        ]);
    }

    private function stockTransfer(Product $product, int $warehouseId, int $fromLocationId, int $toLocationId, float $qty): void
    {
        $this->applyStock($product, $warehouseId, $fromLocationId, -$qty, null, null, 'transfer', 'demo_transfer', null, '演示库位调拨出', 'TR-DEMO-0001');
        $this->applyStock($product, $warehouseId, $toLocationId, $qty, null, null, 'transfer', 'demo_transfer', null, '演示库位调拨入', 'TR-DEMO-0001');
    }

    private function seedPurchaseChain(): void
    {
        $po1 = $this->createPurchaseOrder('PO-DEMO-0001', self::P.'SUP-04', self::P.'WH-MAIN', Carbon::today()->toDateString(), Carbon::today()->addDays(5)->toDateString(), PurchaseOrder::STATUS_DRAFT, [
            ['P06', 20, 28.00],
            ['P07', 10, 52.00],
        ], '门店补货计划，待审批', $this->purchase, null);

        $po2 = $this->createPurchaseOrder('PO-DEMO-0002', self::P.'SUP-02', self::P.'WH-MAIN', Carbon::today()->subDays(2)->toDateString(), Carbon::today()->addDays(2)->toDateString(), PurchaseOrder::STATUS_CONFIRMED, [
            ['P08', 40, 8.00],
            ['P09', 60, 9.00],
            ['P10', 30, 7.00],
        ], '日化用品周补货，已审批待到货', $this->purchase, $this->manager);

        $po3 = $this->createPurchaseOrder('PO-DEMO-0003', self::P.'SUP-02', self::P.'WH-MAIN', Carbon::today()->subDays(8)->toDateString(), Carbon::today()->subDays(1)->toDateString(), PurchaseOrder::STATUS_PARTIAL, [
            ['P08', 40, 8.00],
            ['P09', 60, 9.00],
        ], '分批到货，首批已入库', $this->purchase, $this->manager);

        $po4 = $this->createPurchaseOrder('PO-DEMO-0004', self::P.'SUP-01', self::P.'WH-MAIN', Carbon::today()->subDays(20)->toDateString(), Carbon::today()->subDays(18)->toDateString(), PurchaseOrder::STATUS_RECEIVED, [
            ['P19', 50, 6.00],
            ['P20', 30, 2.80],
        ], '粮油调味补货，已全部入库', $this->purchase, $this->manager);

        $po5 = $this->createPurchaseOrder('PO-DEMO-0005', self::P.'SUP-01', self::P.'WH-MAIN', Carbon::today()->subDays(35)->toDateString(), Carbon::today()->subDays(33)->toDateString(), PurchaseOrder::STATUS_CLOSED, [
            ['P03', 40, 2.60],
        ], '乳品补货，已结算关闭', $this->purchase, $this->manager);

        $this->createPurchaseOrder('PO-DEMO-0006', self::P.'SUP-03', self::P.'WH-MAIN', Carbon::today()->subDays(4)->toDateString(), Carbon::today()->addDays(6)->toDateString(), PurchaseOrder::STATUS_CANCELLED, [
            ['P11', 10, 65.00],
        ], '供应商缺货，采购单取消', $this->purchase, null);

        $receipt1 = $this->createReceipt('RC-DEMO-0001', $po3['id'], self::P.'SUP-02', Carbon::today()->subDays(6)->toDateString(), Receipt::STATUS_DONE, [
            ['P08', 20, 8.00, null, null, $po3['lines']['DM-P08']['id']],
            ['P09', 30, 9.00, null, null, $po3['lines']['DM-P09']['id']],
        ], '首批到货，验收合格');

        $receipt2 = $this->createReceipt('RC-DEMO-0002', $po4['id'], self::P.'SUP-01', Carbon::today()->subDays(18)->toDateString(), Receipt::STATUS_DONE, [
            ['P19', 50, 6.00, 'DM-LOT-SOY-R1', null, $po4['lines']['DM-P19']['id']],
            ['P20', 30, 2.80, 'DM-LOT-NOODLE-R1', null, $po4['lines']['DM-P20']['id']],
        ], '全部到货，批次已登记');

        $this->createReceipt('RC-DEMO-0003', null, self::P.'SUP-04', Carbon::today()->toDateString(), Receipt::STATUS_DRAFT, [
            ['P06', 12, 28.00, null, null, null],
        ], '临时到货，待验收');

        $receipt4 = $this->createReceipt('RC-DEMO-0004', $po5['id'], self::P.'SUP-01', Carbon::today()->subDays(33)->toDateString(), Receipt::STATUS_DONE, [
            ['P03', 40, 2.60, 'DM-LOT-MILK-R1', null, $po5['lines']['DM-P03']['id']],
        ], '乳品到货，冷链验收合格');

        $this->createPurchaseReturn('PR-DEMO-0001', $receipt2['id'], $po4['id'], self::P.'SUP-01', Carbon::today()->subDays(15)->toDateString(), PurchaseReturn::STATUS_DONE, '包装破损', [
            ['DM-P19', 3, 6.00, 'DM-LOT-SOY-R1', null, $receipt2['lines']['DM-P19']['id'], $po4['lines']['DM-P19']['id']],
        ], '破损商品退回供应商');

        $this->createPurchaseReturn('PR-DEMO-0002', $receipt4['id'], $po5['id'], self::P.'SUP-01', Carbon::today()->toDateString(), PurchaseReturn::STATUS_DRAFT, '临期退货', [
            ['DM-P03', 5, 2.60, 'DM-LOT-MILK-R1', null, $receipt4['lines']['DM-P03']['id'], $po5['lines']['DM-P03']['id']],
        ], '待供应商确认后出库');

        $bill1 = $this->createBill('BILL-DEMO-0001', self::P.'SUP-01', Carbon::today()->subDays(5)->toDateString(), Carbon::today()->addDays(20)->toDateString(), 900.00, 0, Bill::STATUS_OPEN, 'receipt', $receipt2['id']);
        $bill2 = $this->createBill('BILL-DEMO-0002', self::P.'SUP-02', Carbon::today()->subDays(15)->toDateString(), Carbon::today()->subDays(10)->toDateString(), 600.00, 200.00, Bill::STATUS_PARTIAL, 'receipt', $receipt1['id']);
        $bill3 = $this->createBill('BILL-DEMO-0003', self::P.'SUP-02', Carbon::today()->subDays(45)->toDateString(), Carbon::today()->subDays(40)->toDateString(), 1500.00, 0, Bill::STATUS_OPEN, 'manual', null);
        $bill4 = $this->createBill('BILL-DEMO-0004', self::P.'SUP-04', Carbon::today()->subDays(70)->toDateString(), Carbon::today()->subDays(65)->toDateString(), 1100.00, 0, Bill::STATUS_OPEN, 'manual', null);
        $bill5 = $this->createBill('BILL-DEMO-0005', self::P.'SUP-03', Carbon::today()->subDays(130)->toDateString(), Carbon::today()->subDays(125)->toDateString(), 700.00, 0, Bill::STATUS_OPEN, 'manual', null);
        $bill6 = $this->createBill('BILL-DEMO-0006', self::P.'SUP-01', Carbon::today()->subDays(25)->toDateString(), Carbon::today()->subDays(20)->toDateString(), 2000.00, 800.00, Bill::STATUS_PARTIAL, 'manual', null);
        $bill7 = $this->createBill('BILL-DEMO-0007', self::P.'SUP-02', Carbon::today()->subDays(90)->toDateString(), Carbon::today()->subDays(85)->toDateString(), 500.00, 0, Bill::STATUS_OPEN, 'manual', null);
        $bill8 = $this->createBill('BILL-DEMO-0008', self::P.'SUP-03', Carbon::today()->subDays(3)->toDateString(), Carbon::today()->addDays(15)->toDateString(), 400.00, 400.00, Bill::STATUS_PAID, 'manual', null);

        $bill2Payment = $this->createPayment('PAY-DEMO-P01', self::P.'SUP-02', Payment::DIRECTION_PAY, Carbon::today()->subDays(8)->toDateString(), 200.00, '网银转账', 'BILL-DEMO-0002');
        $this->allocatePayment($bill2Payment, null, $bill2['id'], 200.00);
        $bill6Payment = $this->createPayment('PAY-DEMO-P02', self::P.'SUP-01', Payment::DIRECTION_PAY, Carbon::today()->subDays(12)->toDateString(), 800.00, '网银转账', 'BILL-DEMO-0006');
        $this->allocatePayment($bill6Payment, null, $bill6['id'], 800.00);
        $bill8Payment = $this->createPayment('PAY-DEMO-P03', self::P.'SUP-03', Payment::DIRECTION_PAY, Carbon::today()->subDays(2)->toDateString(), 400.00, '网银转账', 'BILL-DEMO-0008');
        $this->allocatePayment($bill8Payment, null, $bill8['id'], 400.00);

        foreach ([$bill1, $bill3, $bill4, $bill5, $bill7] as $bill) {
            $this->updateBillStatus($bill);
        }
    }

    /**
     * @param  array<int, array{0:string,1:float,2:float}>  $items
     * @return array{id:int, lines:array<string, array{id:int, qty:float}>, totals:array<string, float>}
     */
    private function createPurchaseOrder(string $code, string $supplierCode, string $warehouseCode, string $date, ?string $expected, string $status, array $items, string $note, User $creator, ?User $approver): array
    {
        $lines = [];
        $totalQty = 0.0;
        $subtotal = 0.0;
        $taxTotal = 0.0;

        foreach ($items as [$productCode, $qty, $unitPrice]) {
            $product = $this->productObject($productCode);
            $net = round($qty * $unitPrice, 4);
            $tax = round($net * ((float) $product->tax?->rate ?? 0) / 100, 4);
            $lines[] = [
                'product' => $product,
                'qty' => $qty,
                'price' => $unitPrice,
                'net' => $net,
                'tax' => $tax,
            ];
            $totalQty += $qty;
            $subtotal += $net;
            $taxTotal += $tax;
        }

        $id = DB::table('purchase_orders')->insertGetId([
            'code' => $code,
            'supplier_id' => $this->supplierIds[$supplierCode],
            'warehouse_id' => $this->warehouseIds[$warehouseCode],
            'order_date' => $date,
            'expected_date' => $expected,
            'status' => $status,
            'total_qty' => round($totalQty, 4),
            'total_amount' => round($subtotal, 4),
            'discount_amount' => 0,
            'tax_amount' => round($taxTotal, 4),
            'grand_total' => round($subtotal + $taxTotal, 4),
            'note' => $note,
            'creator_id' => $creator->id,
            'approver_id' => $approver?->id,
            'approved_at' => $approver ? Carbon::parse($date)->setTime(10, 0) : null,
            'created_at' => Carbon::parse($date)->setTime(9, 0),
            'updated_at' => Carbon::parse($date)->setTime(9, 0),
        ]);

        $lineIds = [];
        foreach ($lines as $line) {
            $lineId = DB::table('purchase_order_lines')->insertGetId([
                'order_id' => $id,
                'product_id' => $line['product']->id,
                'tax_id' => $line['product']->tax_id,
                'qty' => $line['qty'],
                'unit_price' => $line['price'],
                'discount_rate' => 0,
                'subtotal' => $line['net'],
                'tax_amount' => $line['tax'],
                'line_note' => null,
                'created_at' => Carbon::parse($date)->setTime(9, 0),
                'updated_at' => Carbon::parse($date)->setTime(9, 0),
            ]);
            $lineIds[$line['product']->code] = ['id' => $lineId, 'qty' => $line['qty']];
        }

        return [
            'id' => $id,
            'lines' => $lineIds,
            'totals' => ['subtotal' => $subtotal, 'tax' => $taxTotal, 'grand' => $subtotal + $taxTotal],
        ];
    }

    /**
     * @param  array<int, array{0:string,1:float,2:float,3:?string,4:?string,5:?int}>  $items
     * @return array{id:int, lines:array<string, array{id:int}>}
     */
    private function createReceipt(string $code, ?int $poId, string $supplierCode, string $date, string $status, array $items, string $note): array
    {
        $id = DB::table('receipts')->insertGetId([
            'code' => $code,
            'purchase_order_id' => $poId,
            'supplier_id' => $this->supplierIds[$supplierCode],
            'warehouse_id' => $this->warehouseIds[self::P.'WH-MAIN'],
            'receipt_date' => $date,
            'status' => $status,
            'operator_id' => $this->warehouse->id,
            'note' => $note,
            'created_at' => Carbon::parse($date)->setTime(10, 0),
            'updated_at' => Carbon::parse($date)->setTime(10, 0),
        ]);

        $lineIds = [];
        foreach ($items as [$productCode, $qty, $unitCost, $lotNo, $serialNo, $poLineId]) {
            $product = $this->productObject($productCode);
            $lineId = DB::table('receipt_lines')->insertGetId([
                'receipt_id' => $id,
                'purchase_order_line_id' => $poLineId,
                'product_id' => $product->id,
                'location_id' => $this->locationIds['A-01-01'],
                'lot_no' => $lotNo,
                'serial_no' => $serialNo,
                'qty' => $qty,
                'unit_cost' => $unitCost,
                'line_note' => null,
                'created_at' => Carbon::parse($date)->setTime(10, 0),
                'updated_at' => Carbon::parse($date)->setTime(10, 0),
            ]);
            $lineIds[$product->code] = ['id' => $lineId];

            if ($status === Receipt::STATUS_DONE) {
                if ($lotNo) {
                    $this->createLot($product, $lotNo, $this->supplierIds[$supplierCode], $date, Carbon::parse($date)->addDays(90)->toDateString());
                }
                $this->stockIn($product, $this->warehouseIds[self::P.'WH-MAIN'], $this->locationIds['A-01-01'], $qty, $lotNo, $serialNo, 'receipt', $id, '采购入库 '.$code, $code);
            }
        }

        return ['id' => $id, 'lines' => $lineIds];
    }

    /**
     * @param  array<int, array{0:string,1:float,2:float,3:?string,4:?string,5:?int,6:?int}>  $items
     */
    private function createPurchaseReturn(string $code, ?int $receiptId, ?int $poId, string $supplierCode, string $date, string $status, string $reason, array $items, string $note): int
    {
        $id = DB::table('purchase_returns')->insertGetId([
            'code' => $code,
            'receipt_id' => $receiptId,
            'purchase_order_id' => $poId,
            'supplier_id' => $this->supplierIds[$supplierCode],
            'warehouse_id' => $this->warehouseIds[self::P.'WH-MAIN'],
            'return_date' => $date,
            'status' => $status,
            'reason' => $reason,
            'operator_id' => $this->warehouse->id,
            'note' => $note,
            'created_at' => Carbon::parse($date)->setTime(11, 0),
            'updated_at' => Carbon::parse($date)->setTime(11, 0),
        ]);

        foreach ($items as [$productCode, $qty, $unitCost, $lotNo, $serialNo, $lineId, $poLineId]) {
            $product = $this->productObject($productCode);
            DB::table('purchase_return_lines')->insert([
                'return_id' => $id,
                'receipt_line_id' => $lineId,
                'purchase_order_line_id' => $poLineId,
                'product_id' => $product->id,
                'location_id' => $this->locationIds['A-01-01'],
                'lot_no' => $lotNo,
                'serial_no' => $serialNo,
                'qty' => $qty,
                'unit_cost' => $unitCost,
                'reason' => $reason,
                'created_at' => Carbon::parse($date)->setTime(11, 0),
                'updated_at' => Carbon::parse($date)->setTime(11, 0),
            ]);

            if ($status === PurchaseReturn::STATUS_DONE) {
                $this->stockOut($product, $this->warehouseIds[self::P.'WH-MAIN'], $this->locationIds['A-01-01'], $qty, $lotNo, $serialNo, 'purchase_return', $id, '采购退货 '.$code, $code);
            }
        }

        return $id;
    }

    /** @return array{id:int, entry_id:int} */
    private function createBill(string $code, string $supplierCode, string $date, ?string $due, float $total, float $paid, string $status, string $sourceType, ?int $sourceId): array
    {
        $tax = round($total - $total / 1.13, 4);
        $net = round($total - $tax, 4);
        $entry = $this->postEntry(
            Carbon::parse($date)->toDateString(),
            'PURCHASE',
            'purchase_bill',
            $sourceId,
            $code,
            '采购应付 '.$code,
            [
                ['account_code' => '1001', 'direction' => 'debit', 'amount' => $net, 'memo' => '采购入库成本'],
                ['account_code' => '2003', 'direction' => 'debit', 'amount' => $tax, 'tag_id' => $this->taxIds['VAT13'], 'memo' => '进项税额'],
                ['account_code' => '2001', 'direction' => 'credit', 'amount' => $total, 'partner_id' => $this->supplierIds[$supplierCode], 'memo' => '应付供应商货款'],
            ],
            $this->finance
        );

        $id = DB::table('bills')->insertGetId([
            'code' => $code,
            'partner_id' => $this->supplierIds[$supplierCode],
            'entry_id' => $entry->id,
            'bill_date' => $date,
            'due_date' => $due,
            'status' => $status,
            'total_amount' => $total,
            'paid_amount' => $paid,
            'source_type' => $sourceType,
            'source_id' => $sourceId,
            'created_at' => Carbon::parse($date)->setTime(12, 0),
            'updated_at' => Carbon::parse($date)->setTime(12, 0),
        ]);

        return ['id' => $id, 'entry_id' => $entry->id];
    }

    private function updateBillStatus(array $bill): void
    {
        $row = DB::table('bills')->where('id', $bill['id'])->first();
        if (! $row || $row->status === Bill::STATUS_VOID || $row->status === Bill::STATUS_DRAFT) {
            return;
        }

        $status = match (true) {
            (float) $row->paid_amount <= 0 => Bill::STATUS_OPEN,
            (float) $row->paid_amount + 0.0001 >= (float) $row->total_amount => Bill::STATUS_PAID,
            default => Bill::STATUS_PARTIAL,
        };
        DB::table('bills')->where('id', $bill['id'])->update(['status' => $status, 'updated_at' => now()]);
    }

    private int $entryCounter = 0;

    private function postEntry(string $date, string $journalCode, string $sourceType, ?int $sourceId, string $sourceCode, string $description, array $lines, User $creator): AccountEntry
    {
        $journalId = $this->journalIds[$journalCode] ?? $this->journalIds['MISC'];
        $this->entryCounter++;

        $entry = AccountEntry::create([
            'code' => 'JV-DEMO-'.str_pad((string) $this->entryCounter, 4, '0', STR_PAD_LEFT),
            'journal_id' => $journalId,
            'entry_date' => $date,
            'status' => AccountEntry::STATUS_POSTED,
            'source_type' => $sourceType,
            'source_id' => $sourceId,
            'source_code' => $sourceCode,
            'description' => $description,
            'creator_id' => $creator->id,
            'posted_at' => Carbon::parse($date)->setTime(12, 0),
            'created_at' => Carbon::parse($date)->setTime(12, 0),
            'updated_at' => Carbon::parse($date)->setTime(12, 0),
        ]);

        $debit = 0.0;
        $credit = 0.0;
        $sort = 0;
        foreach ($lines as $line) {
            $accountId = $this->accountIds[$line['account_code']] ?? null;
            if (! $accountId) {
                throw new \RuntimeException('演示科目不存在：'.$line['account_code']);
            }

            $amount = round((float) $line['amount'], 4);
            if ($amount <= 0) {
                throw new \RuntimeException('演示凭证金额必须大于 0');
            }

            $entry->lines()->create([
                'account_id' => $accountId,
                'direction' => $line['direction'],
                'amount' => $amount,
                'tax_id' => $line['tax_id'] ?? $line['tag_id'] ?? null,
                'partner_id' => $line['partner_id'] ?? null,
                'memo' => $line['memo'] ?? null,
                'sort' => $sort++,
            ]);

            if ($line['direction'] === 'debit') {
                $debit += $amount;
            } else {
                $credit += $amount;
            }
        }

        if (abs($debit - $credit) > 0.0001) {
            throw new \RuntimeException("演示凭证借贷不平衡：{$description}，借 {$debit} 贷 {$credit}");
        }

        return $entry;
    }

    /** @return array{id:int, entry_id:int} */
    private function createPayment(string $code, string $partnerCode, string $direction, string $date, float $amount, string $reference, ?string $documentNo): array
    {
        $partnerId = $direction === Payment::DIRECTION_RECEIVE
            ? $this->customerIds[$partnerCode]
            : $this->supplierIds[$partnerCode];

        $entry = $this->postEntry(
            $date,
            'BANK',
            'payment',
            null,
            $code,
            ($direction === Payment::DIRECTION_RECEIVE ? '收到客户货款 ' : '支付供应商货款 ').$code,
            $direction === Payment::DIRECTION_RECEIVE
                ? [
                    ['account_code' => '1005', 'direction' => 'debit', 'amount' => $amount, 'memo' => '银行收款'],
                    ['account_code' => '1003', 'direction' => 'credit', 'amount' => $amount, 'partner_id' => $partnerId, 'memo' => '应收账款核销'],
                ]
                : [
                    ['account_code' => '2001', 'direction' => 'debit', 'amount' => $amount, 'partner_id' => $partnerId, 'memo' => '应付账款核销'],
                    ['account_code' => '1005', 'direction' => 'credit', 'amount' => $amount, 'memo' => '银行付款'],
                ],
            $this->finance
        );

        $id = DB::table('payments')->insertGetId([
            'code' => $code,
            'partner_id' => $partnerId,
            'direction' => $direction,
            'method_id' => $this->methodIds['CARD'],
            'account_id' => $this->accountIds['1005'],
            'amount' => $amount,
            'payment_date' => $date,
            'reference' => $reference,
            'status' => Payment::STATUS_CONFIRMED,
            'entry_id' => $entry->id,
            'operator_id' => $this->finance->id,
            'created_at' => Carbon::parse($date)->setTime(14, 0),
            'updated_at' => Carbon::parse($date)->setTime(14, 0),
        ]);

        return ['id' => $id, 'entry_id' => $entry->id];
    }

    private function allocatePayment(array $payment, ?int $invoiceId, ?int $billId, float $amount): void
    {
        DB::table('payment_allocations')->insert([
            'payment_id' => $payment['id'],
            'invoice_id' => $invoiceId,
            'bill_id' => $billId,
            'amount' => $amount,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function seedSalesAndFinance(): void
    {
        $customers = [self::P.'CUS-02', self::P.'CUS-03', self::P.'CUS-04', self::P.'CUS-05', self::P.'CUS-06'];
        $saleProducts = ['P01', 'P02', 'P04', 'P06', 'P08', 'P09', 'P10', 'P13', 'P16', 'P17', 'P21', 'P22'];

        $orders = [];
        for ($i = 13; $i >= 0; $i--) {
            $date = Carbon::today()->subDays($i)->toDateString();
            $first = $saleProducts[($i * 3) % count($saleProducts)];
            $second = $saleProducts[($i * 3 + 5) % count($saleProducts)];
            $items = [
                [$first, 2 + ($i % 4), null],
                [$second, 1 + ($i % 3), null],
            ];
            $code = 'SO-DEMO-'.str_pad((string) (14 - $i), 4, '0', STR_PAD_LEFT);
            $orders[] = $this->createSalesOrder($code, $customers[$i % count($customers)], $date, SalesOrder::STATUS_SHIPPED, $items, $this->sales, $this->manager, '门店批发销售，已出库');
        }

        $this->createSalesOrder('SO-DEMO-0015', self::P.'CUS-04', Carbon::today()->toDateString(), SalesOrder::STATUS_CONFIRMED, [
            ['P06', 6, null],
            ['P09', 4, null],
        ], $this->sales, $this->manager, '企业客户团购，待出库');

        $this->createSalesOrder('SO-DEMO-0016', self::P.'CUS-02', Carbon::today()->toDateString(), SalesOrder::STATUS_DRAFT, [
            ['P16', 3, null],
        ], $this->sales, null, '电话订货，待确认');

        $this->createSalesOrder('SO-DEMO-0017', self::P.'CUS-03', Carbon::today()->subDays(2)->toDateString(), SalesOrder::STATUS_CONFIRMED, [
            ['P13', 2, null],
        ], $this->sales, $this->manager, '等待仓库备货');

        $this->createSalesOrder('SO-DEMO-0018', self::P.'CUS-05', Carbon::today()->subDays(6)->toDateString(), SalesOrder::STATUS_CANCELLED, [
            ['P07', 2, null],
        ], $this->sales, null, '客户临时取消');

        $invoices = [
            ['INV-DEMO-0001', self::P.'CUS-02', Carbon::today()->subDays(3)->toDateString(), Carbon::today()->addDays(20)->toDateString(), 1280.00, 0],
            ['INV-DEMO-0002', self::P.'CUS-03', Carbon::today()->subDays(20)->toDateString(), Carbon::today()->subDays(5)->toDateString(), 860.00, 300.00],
            ['INV-DEMO-0003', self::P.'CUS-04', Carbon::today()->subDays(40)->toDateString(), Carbon::today()->subDays(35)->toDateString(), 2200.00, 0],
            ['INV-DEMO-0004', self::P.'CUS-05', Carbon::today()->subDays(70)->toDateString(), Carbon::today()->subDays(65)->toDateString(), 1540.00, 0],
            ['INV-DEMO-0005', self::P.'CUS-06', Carbon::today()->subDays(130)->toDateString(), Carbon::today()->subDays(125)->toDateString(), 900.00, 0],
            ['INV-DEMO-0006', self::P.'CUS-04', Carbon::today()->subDays(25)->toDateString(), Carbon::today()->subDays(20)->toDateString(), 3100.00, 1000.00],
            ['INV-DEMO-0007', self::P.'CUS-05', Carbon::today()->subDays(80)->toDateString(), Carbon::today()->subDays(75)->toDateString(), 480.00, 0],
            ['INV-DEMO-0008', self::P.'CUS-02', Carbon::today()->subDays(2)->toDateString(), Carbon::today()->addDays(10)->toDateString(), 700.00, 700.00],
        ];

        $invoiceRows = [];
        foreach ($invoices as [$code, $customerCode, $date, $due, $total, $paid]) {
            $invoiceRows[$code] = $this->createInvoice($code, $customerCode, $date, $due, $total, $paid);
        }

        $receive1 = $this->createPayment('PAY-DEMO-R01', self::P.'CUS-03', Payment::DIRECTION_RECEIVE, Carbon::today()->subDays(6)->toDateString(), 300.00, '客户回款', 'INV-DEMO-0002');
        $this->allocatePayment($receive1, $invoiceRows['INV-DEMO-0002']['id'], null, 300.00);
        $receive2 = $this->createPayment('PAY-DEMO-R02', self::P.'CUS-04', Payment::DIRECTION_RECEIVE, Carbon::today()->subDays(10)->toDateString(), 1000.00, '企业转账', 'INV-DEMO-0006');
        $this->allocatePayment($receive2, $invoiceRows['INV-DEMO-0006']['id'], null, 1000.00);

        $this->createSalesReturn('SR-DEMO-0001', SalesReturn::SOURCE_SALES_ORDER, $orders[10]['id'], null, self::P.'CUS-05', Carbon::today()->subDays(4)->toDateString(), SalesReturn::STATUS_DONE, '商品外包装破损', SalesReturn::REFUND_BANK, [
            ['P22', 2, 7.90, 0, 0],
        ], '客户退回湿巾两包，已退款');

        $this->createSalesReturn('SR-DEMO-0002', SalesReturn::SOURCE_SALES_ORDER, $orders[12]['id'], null, self::P.'CUS-03', Carbon::today()->toDateString(), SalesReturn::STATUS_DRAFT, '客户尺码/口味不合适', null, [
            ['P10', 1, 13.90, 0, 0],
        ], '待门店验收');
    }

    /**
     * @param  array<int, array{0:string,1:float,2:?float}>  $items
     * @return array{id:int, lines:array<string,int>}
     */
    private function createSalesOrder(string $code, string $customerCode, string $date, string $status, array $items, User $creator, ?User $approver, string $note): array
    {
        $lines = [];
        $totalQty = 0.0;
        $subtotal = 0.0;
        $taxTotal = 0.0;

        foreach ($items as [$productCode, $qty, $price]) {
            $product = $this->productObject($productCode);
            $unitPrice = $price ?? (float) $product->list_price;
            $net = round($qty * $unitPrice, 4);
            $tax = round($net * ((float) $product->tax?->rate ?? 0) / 100, 4);
            $lines[] = ['product' => $product, 'qty' => $qty, 'price' => $unitPrice, 'net' => $net, 'tax' => $tax];
            $totalQty += $qty;
            $subtotal += $net;
            $taxTotal += $tax;
        }

        $id = DB::table('sales_orders')->insertGetId([
            'code' => $code,
            'customer_id' => $this->customerIds[$customerCode],
            'warehouse_id' => $this->warehouseIds[self::P.'WH-STORE'],
            'order_date' => $date,
            'expected_date' => Carbon::parse($date)->addDays(2)->toDateString(),
            'status' => $status,
            'total_qty' => round($totalQty, 4),
            'total_amount' => round($subtotal, 4),
            'discount_amount' => 0,
            'tax_amount' => round($taxTotal, 4),
            'grand_total' => round($subtotal + $taxTotal, 4),
            'note' => $note,
            'creator_id' => $creator->id,
            'approver_id' => $approver?->id,
            'approved_at' => $approver ? Carbon::parse($date)->setTime(11, 0) : null,
            'created_at' => Carbon::parse($date)->setTime(10, 0),
            'updated_at' => Carbon::parse($date)->setTime(10, 0),
        ]);

        $lineIds = [];
        foreach ($lines as $line) {
            $lineId = DB::table('sales_order_lines')->insertGetId([
                'order_id' => $id,
                'product_id' => $line['product']->id,
                'lot_no' => null,
                'serial_no' => null,
                'tax_id' => $line['product']->tax_id,
                'qty' => $line['qty'],
                'unit_price' => $line['price'],
                'discount_rate' => 0,
                'subtotal' => $line['net'],
                'tax_amount' => $line['tax'],
                'line_note' => null,
                'created_at' => Carbon::parse($date)->setTime(10, 0),
                'updated_at' => Carbon::parse($date)->setTime(10, 0),
            ]);
            $lineIds[$line['product']->code] = $lineId;

            if ($status === SalesOrder::STATUS_SHIPPED) {
                $this->stockOut($line['product'], $this->warehouseIds[self::P.'WH-STORE'], $this->locationIds['STORE-01'], (float) $line['qty'], null, null, 'sales_order', $id, '销售出库 '.$code, $code);
            }
        }

        return ['id' => $id, 'lines' => $lineIds];
    }

    /** @return array{id:int, entry_id:int} */
    private function createInvoice(string $code, string $customerCode, string $date, ?string $due, float $total, float $paid): array
    {
        $tax = round($total - $total / 1.13, 4);
        $net = round($total - $tax, 4);
        $entry = $this->postEntry(
            $date,
            'SALE',
            'invoice',
            null,
            $code,
            '销售应收 '.$code,
            [
                ['account_code' => '1003', 'direction' => 'debit', 'amount' => $total, 'partner_id' => $this->customerIds[$customerCode], 'memo' => '应收客户货款'],
                ['account_code' => '4001', 'direction' => 'credit', 'amount' => $net, 'memo' => '主营业务收入'],
                ['account_code' => '2004', 'direction' => 'credit', 'amount' => $tax, 'tag_id' => $this->taxIds['VAT13'], 'memo' => '销项税额'],
            ],
            $this->finance
        );

        $status = match (true) {
            $paid <= 0 => Invoice::STATUS_OPEN,
            $paid + 0.0001 >= $total => Invoice::STATUS_PAID,
            default => Invoice::STATUS_PARTIAL,
        };

        $id = DB::table('invoices')->insertGetId([
            'code' => $code,
            'partner_id' => $this->customerIds[$customerCode],
            'entry_id' => $entry->id,
            'invoice_date' => $date,
            'due_date' => $due,
            'status' => $status,
            'total_amount' => $total,
            'paid_amount' => $paid,
            'source_type' => 'manual',
            'source_id' => null,
            'created_at' => Carbon::parse($date)->setTime(13, 0),
            'updated_at' => Carbon::parse($date)->setTime(13, 0),
        ]);

        return ['id' => $id, 'entry_id' => $entry->id];
    }

    /**
     * @param  array<int, array{0:string,1:float,2:float,3:float,4:float}>  $items
     */
    private function createSalesReturn(string $code, string $source, ?int $salesOrderId, ?int $posOrderId, ?string $customerCode, string $date, string $status, string $reason, ?string $refundMethod, array $items, string $note): int
    {
        $refundAmount = round(array_sum(array_map(fn (array $item) => (float) $item[4] > 0 ? (float) $item[4] : (float) $item[1] * (float) $item[2], $items)), 4);

        $id = DB::table('sales_returns')->insertGetId([
            'code' => $code,
            'source_type' => $source,
            'sales_order_id' => $salesOrderId,
            'pos_order_id' => $posOrderId,
            'customer_id' => $customerCode ? $this->customerIds[$customerCode] : null,
            'warehouse_id' => $this->warehouseIds[self::P.'WH-STORE'],
            'return_date' => $date,
            'status' => $status,
            'reason' => $reason,
            'refund_method' => $refundMethod,
            'refund_amount' => $refundAmount,
            'operator_id' => $this->sales->id,
            'note' => $note,
            'created_at' => Carbon::parse($date)->setTime(15, 0),
            'updated_at' => Carbon::parse($date)->setTime(15, 0),
        ]);

        foreach ($items as [$productCode, $qty, $unitPrice, $taxAmount, $lineTotal]) {
            $product = $this->productObject($productCode);
            $lineTotal = $lineTotal > 0 ? $lineTotal : round($qty * $unitPrice + $taxAmount, 4);
            DB::table('sales_return_lines')->insert([
                'return_id' => $id,
                'sales_order_line_id' => null,
                'pos_order_line_id' => null,
                'product_id' => $product->id,
                'location_id' => $this->locationIds['STORE-01'],
                'lot_no' => null,
                'serial_no' => null,
                'qty' => $qty,
                'unit_price' => $unitPrice,
                'tax_amount' => $taxAmount,
                'line_total' => $lineTotal,
                'reason' => $reason,
                'created_at' => Carbon::parse($date)->setTime(15, 0),
                'updated_at' => Carbon::parse($date)->setTime(15, 0),
            ]);

            if ($status === SalesReturn::STATUS_DONE) {
                $this->stockIn($product, $this->warehouseIds[self::P.'WH-STORE'], $this->locationIds['STORE-01'], $qty, null, null, 'sales_return', $id, '销售退货 '.$code, $code);
            }
        }

        return $id;
    }

    private function seedWarehouseOperations(): void
    {
        Carbon::setTestNow(Carbon::today()->subDays(10)->setTime(10, 0));

        $store = $this->warehouseIds[self::P.'WH-STORE'];
        $main = $this->warehouseIds[self::P.'WH-MAIN'];
        $storeLoc = $this->locationIds['STORE-01'];
        $mainLoc = $this->locationIds['A-01-01'];

        $dish = $this->productObject('P08');
        $book = (float) DB::table('stock_levels')->where('product_id', $dish->id)->where('warehouse_id', $store)->where('location_id', $storeLoc)->value('qty');
        $counted = max(0, $book - 2);
        $adjusted = DB::table('stock_counts')->insertGetId([
            'code' => 'PC-DEMO-0001',
            'warehouse_id' => $store,
            'location_id' => $storeLoc,
            'count_date' => Carbon::today()->subDays(10)->toDateString(),
            'status' => StockCount::STATUS_ADJUSTED,
            'operator_id' => $this->warehouse->id,
            'note' => '门店月度盘点，洗洁精盘亏 2 瓶',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->insertCountLine($adjusted, $dish, $storeLoc, null, null, $book, $counted, $dish->standard_cost, now());
        if ($book > $counted) {
            $this->stockOut($dish, $store, $storeLoc, $book - $counted, null, null, 'stock_count', $adjusted, '盘点调整 PC-DEMO-0001', 'PC-DEMO-0001');
        }

        $water = $this->productObject('P01');
        $waterBook = (float) DB::table('stock_levels')->where('product_id', $water->id)->where('warehouse_id', $store)->where('location_id', $storeLoc)->value('qty');
        $counting = DB::table('stock_counts')->insertGetId([
            'code' => 'PC-DEMO-0002',
            'warehouse_id' => $store,
            'location_id' => $storeLoc,
            'count_date' => Carbon::today()->toDateString(),
            'status' => StockCount::STATUS_COUNTING,
            'operator_id' => $this->warehouse->id,
            'note' => '门店抽盘进行中',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->insertCountLine($counting, $water, $storeLoc, null, null, $waterBook, null, $water->standard_cost, null);

        $rice = $this->productObject('P06');
        $riceBook = (float) DB::table('stock_levels')->where('product_id', $rice->id)->where('warehouse_id', $main)->where('location_id', $mainLoc)->value('qty');
        $draft = DB::table('stock_counts')->insertGetId([
            'code' => 'PC-DEMO-0003',
            'warehouse_id' => $main,
            'location_id' => $mainLoc,
            'count_date' => Carbon::today()->toDateString(),
            'status' => StockCount::STATUS_DRAFT,
            'operator_id' => $this->warehouse->id,
            'note' => '总仓大米待盘点',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->insertCountLine($draft, $rice, $mainLoc, null, null, $riceBook, null, $rice->standard_cost, null);

        $battery = $this->productObject('P14');
        $scrapDone = DB::table('scraps')->insertGetId([
            'code' => 'SC-DEMO-0001',
            'warehouse_id' => $store,
            'scrap_date' => Carbon::today()->subDays(3)->toDateString(),
            'status' => Scrap::STATUS_DONE,
            'loss_type' => Scrap::LOSS_EXPENSE,
            'reason' => '运输挤压损坏',
            'operator_id' => $this->warehouse->id,
            'note' => '电池外包装破损，无法销售',
            'created_at' => Carbon::today()->subDays(3)->setTime(16, 0),
            'updated_at' => Carbon::today()->subDays(3)->setTime(16, 0),
        ]);
        DB::table('scrap_lines')->insert([
            'scrap_id' => $scrapDone,
            'product_id' => $battery->id,
            'location_id' => $storeLoc,
            'lot_no' => null,
            'serial_no' => null,
            'qty' => 2,
            'unit_cost' => $battery->standard_cost,
            'loss_amount' => round(2 * (float) $battery->standard_cost, 4),
            'reason' => '外包装破损',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->stockOut($battery, $store, $storeLoc, 2, null, null, 'scrap', $scrapDone, '报废 SC-DEMO-0001', 'SC-DEMO-0001');

        $cookie = $this->productObject('P05');
        $scrapDraft = DB::table('scraps')->insertGetId([
            'code' => 'SC-DEMO-0002',
            'warehouse_id' => $this->warehouseIds[self::P.'WH-RETURN'],
            'scrap_date' => Carbon::today()->toDateString(),
            'status' => Scrap::STATUS_DRAFT,
            'loss_type' => Scrap::LOSS_EXPENSE,
            'reason' => '保质期到期',
            'operator_id' => $this->warehouse->id,
            'note' => '待审批后销毁',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('scrap_lines')->insert([
            'scrap_id' => $scrapDraft,
            'product_id' => $cookie->id,
            'location_id' => $this->locationIds['RET-01'],
            'lot_no' => 'DM-LOT-COOKIE-EXPIRED',
            'serial_no' => null,
            'qty' => 5,
            'unit_cost' => $cookie->standard_cost,
            'loss_amount' => round(5 * (float) $cookie->standard_cost, 4),
            'reason' => '过期销毁',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // 仓储作业回填到 10 天前，POS 历史需要从真实今天倒推。
        Carbon::setTestNow();
    }

    private function insertCountLine(int $countId, Product $product, int $locationId, ?string $lotNo, ?string $serialNo, float $book, ?float $counted, float $cost, ?Carbon $adjustedAt): void
    {
        $variance = $counted === null ? 0 : round($counted - $book, 4);
        DB::table('stock_count_lines')->insert([
            'count_id' => $countId,
            'product_id' => $product->id,
            'location_id' => $locationId,
            'lot_no' => $lotNo,
            'serial_no' => $serialNo,
            'book_qty' => $book,
            'counted_qty' => $counted,
            'variance_qty' => $variance,
            'unit_cost' => $cost,
            'variance_value' => round($variance * $cost, 4),
            'adjusted_at' => $adjustedAt,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function seedPosHistory(): void
    {
        // POS 小票号只有 RC + 流水，没有日期段；按日重置会在跨日时产生重复编号。
        // 演示数据跨 30 天，因此将小票序列改为全局递增，保证历史订单号唯一。
        DB::table('sequences')->where('code', 'pos_receipt')->update([
            'period_mode' => 'none',
            'next_number' => 1,
            'last_reset_at' => null,
            'updated_at' => now(),
        ]);

        // 凭证号同样是 JV + 流水，按日/月重置会产生重复号；POS 历史跨月生成时改为全局递增。
        DB::table('sequences')->where('code', 'voucher')->update([
            'period_mode' => 'none',
            'next_number' => 1,
            'last_reset_at' => null,
            'updated_at' => now(),
        ]);

        $station = PosStation::where('code', self::P.'POS-01')->firstOrFail();

        // POS 演示要跑满 30 天，先给收银台常售商品补足库存，避免历史单把现货卖空。
        foreach (['P01', 'P02', 'P04', 'P08', 'P09', 'P10', 'P13', 'P16', 'P17', 'P21', 'P22'] as $code) {
            $product = $this->productObject($code);
            $qty = (float) DB::table('stock_levels')
                ->where('product_id', $product->id)
                ->where('warehouse_id', $this->warehouseIds[self::P.'WH-STORE'])
                ->where('location_id', $this->locationIds['STORE-01'])
                ->value('qty');
            if ($qty < 200) {
                $this->stockIn($product, $this->warehouseIds[self::P.'WH-STORE'], $this->locationIds['STORE-01'], 200 - $qty, null, null, 'demo_pos_topup', null, 'POS 演示备货');
            }
        }
        $sessionService = app(SessionService::class);
        $checkout = app(CheckoutService::class);
        $shifts = app(ShiftService::class);
        $cart = app(CartService::class);
        $returns = app(ReturnService::class);

        $posProducts = ['P01', 'P02', 'P04', 'P08', 'P09', 'P10', 'P13', 'P16', 'P17', 'P21', 'P22'];
        $posToday = Carbon::today()->copy();
        $refundOrder = null;

        for ($i = 29; $i >= 0; $i--) {
            $date = $posToday->copy()->subDays($i);
            Carbon::setTestNow($date->copy()->setTime(9, 0));

            $session = $sessionService->open($station, $this->cashier->id, 200.00, '演示日开班');

            $orderCount = $i === 0 ? 3 : 2;
            for ($j = 0; $j < $orderCount; $j++) {
                Carbon::setTestNow($date->copy()->setTime(9 + $j, 10 + $j * 5));

                $lines = [];
                if ($i === 0 && $j === 0) {
                    $lines[] = $cart->resolveLine($this->productObject('P03'), 1, 0, 'DM-LOT-MILK-A');
                    $lines[] = $cart->resolveLine($this->productObject('P20'), 1, 0, 'DM-LOT-NOODLE-A');
                } elseif ($i === 0 && $j === 1) {
                    $lines[] = $cart->resolveLine($this->productObject('P11'), 1, 0, null, 'SN-P11-001');
                    $lines[] = $cart->resolveLine($this->productObject('P02'), 2, 0);
                } else {
                    $p1 = $posProducts[($i * 2 + $j) % count($posProducts)];
                    $p2 = $posProducts[($i * 2 + $j + 3) % count($posProducts)];
                    $lines[] = $cart->resolveLine($this->productObject($p1), 1 + (($i + $j) % 3), 0);
                    if ($p1 !== $p2) {
                        $lines[] = $cart->resolveLine($this->productObject($p2), 1 + (($i + $j) % 2), 0);
                    }
                }

                $totals = $cart->orderTotals($lines);
                $method = match (($i + $j) % 5) {
                    0 => 'WECHAT',
                    1 => 'CASH',
                    2 => 'CARD',
                    3 => 'ALIPAY',
                    default => 'WECHAT',
                };
                $customerId = null;
                $payments = [];

                if ($i === 0 && $j === 2) {
                    $method = 'CREDIT';
                    $customerId = $this->customerIds[self::P.'CUS-03'];
                    $payments[] = ['method_id' => $this->methodIds['CREDIT'], 'amount' => $totals['grand_total']];
                } elseif ($method === 'CASH') {
                    $payments[] = ['method_id' => $this->methodIds['CASH'], 'amount' => $totals['grand_total']];
                } elseif (($i + $j) % 2 === 0) {
                    $cash = floor($totals['grand_total']);
                    if ($cash > 0 && $cash < $totals['grand_total']) {
                        $payments[] = ['method_id' => $this->methodIds['CASH'], 'amount' => $cash];
                        $payments[] = ['method_id' => $this->methodIds['WECHAT'], 'amount' => round($totals['grand_total'] - $cash, 4)];
                    } else {
                        $payments[] = ['method_id' => $this->methodIds['WECHAT'], 'amount' => $totals['grand_total']];
                    }
                } else {
                    $payments[] = ['method_id' => $this->methodIds[$method], 'amount' => $totals['grand_total']];
                }

                $order = $checkout->checkout(
                    $session,
                    $lines,
                    $payments,
                    $customerId,
                    0,
                    null,
                    '演示 POS 订单 '.$date->format('m-d').' #'.($j + 1),
                    $this->cashier->id
                );

                if ($i === 2 && $j === 0) {
                    $refundOrder = $order;
                }
            }

            if ($i === 2 && $refundOrder) {
                $returnLines = $refundOrder->lines->map(fn ($line) => [
                    'pos_order_line_id' => $line->id,
                    'qty' => (float) $line->qty,
                    'reason' => '演示 POS 全额退货',
                ])->all();

                $posReturn = $returns->createReturn([
                    'source_type' => SalesReturn::SOURCE_POS_ORDER,
                    'pos_order_id' => $refundOrder->id,
                    'return_date' => $date->toDateString(),
                    'reason' => '顾客当日退货',
                    'refund_method' => SalesReturn::REFUND_CASH,
                    'operator_id' => $this->cashier->id,
                    'note' => 'POS 退货演示，整单原路退款',
                ], $returnLines);

                $returns->postReturn($posReturn);
            }

            if ($i > 0) {
                Carbon::setTestNow($date->copy()->setTime(18, 0));
                $summary = $shifts->summary($session);
                $shifts->close($session, $this->denominations((float) $summary['expected_cash']), $this->cashier->id, '演示日交班');

                // 历史会话的交班汇总由 ShiftService 回填，保证会话列表有销售/折扣/长短款。
            } else {
                $summary = $shifts->summary($session);
                DB::table('pos_sessions')->where('id', $session->id)->update([
                    'total_sales' => $summary['total_sales'],
                    'total_refund' => $summary['total_refund'],
                    'total_discount' => $summary['total_discount'],
                    'expected_cash' => $summary['expected_cash'],
                    'updated_at' => now(),
                ]);
            }
        }

        // 保留一张今天的挂单，方便演示取单。
        $savedCart = [];
        $savedCart[] = $cart->resolveLine($this->productObject('P01'), 2, 0);
        $savedCart[] = $cart->resolveLine($this->productObject('P04'), 1, 0);
        Carbon::setTestNow(Carbon::today()->setTime(10, 30));
        app(SavedOrderService::class)->save(
            $station->id,
            $this->cashier->id,
            $savedCart,
            $this->customerIds[self::P.'CUS-02'],
            null,
            '顾客临时离店，商品暂存'
        );
    }

    /** @return array<int, array{denomination:float,count_qty:int}> */
    private function denominations(float $expectedCash): array
    {
        $remaining = max(0, (int) round($expectedCash));
        $result = [];
        foreach ([100, 50, 20, 10, 5, 1] as $denomination) {
            if ($remaining <= 0) {
                break;
            }
            $qty = intdiv($remaining, $denomination);
            if ($qty > 0) {
                $result[] = ['denomination' => $denomination, 'count_qty' => $qty];
                $remaining -= $qty * $denomination;
            }
        }

        return $result;
    }

    private function seedFiscalAndBank(): void
    {
        $currentStart = Carbon::today()->startOfMonth();
        $currentEnd = Carbon::today()->endOfMonth();
        $previousStart = $currentStart->copy()->subMonthNoOverflow()->startOfMonth();
        $previousEnd = $currentStart->copy()->subDay()->endOfDay();
        $beforePreviousStart = $previousStart->copy()->subMonthNoOverflow()->startOfMonth();
        $beforePreviousEnd = $previousStart->copy()->subDay()->endOfDay();

        DB::table('fiscal_periods')->insert([
            [
                'name' => $beforePreviousStart->format('Y年m月'),
                'start_date' => $beforePreviousStart->toDateString(),
                'end_date' => $beforePreviousEnd->toDateString(),
                'status' => 'closed',
                'closed_at' => $previousStart->copy()->setTime(10, 0),
                'created_at' => $beforePreviousStart,
                'updated_at' => $previousStart,
            ],
            [
                'name' => $previousStart->format('Y年m月'),
                'start_date' => $previousStart->toDateString(),
                'end_date' => $previousEnd->toDateString(),
                'status' => 'closed',
                'closed_at' => $currentStart->copy()->setTime(10, 0),
                'created_at' => $previousStart,
                'updated_at' => $currentStart,
            ],
            [
                'name' => $currentStart->format('Y年m月'),
                'start_date' => $currentStart->toDateString(),
                'end_date' => $currentEnd->toDateString(),
                'status' => 'open',
                'closed_at' => null,
                'created_at' => $currentStart,
                'updated_at' => $currentStart,
            ],
        ]);

        // 银行流水：两条已匹配、两条待匹配，覆盖对账页的匹配/撤销场景。
        $receive = DB::table('payments')->where('code', 'PAY-DEMO-R02')->first();
        $pay = DB::table('payments')->where('code', 'PAY-DEMO-P02')->first();
        $receiveLine = $this->bankEntryLine((int) ($receive->entry_id ?? 0), 'debit');
        $payLine = $this->bankEntryLine((int) ($pay->entry_id ?? 0), 'credit');

        $matchedReceive = DB::table('bank_statements')->insertGetId([
            'account_id' => $this->accountIds['1005'],
            'statement_date' => Carbon::today()->subDays(10)->toDateString(),
            'direction' => BankStatement::DIRECTION_RECEIVE,
            'amount' => 1000.00,
            'counterparty' => '华城物业采购部',
            'reference' => 'PAY-DEMO-R02',
            'memo' => '客户网银回款',
            'status' => BankStatement::STATUS_MATCHED,
            'operator_id' => $this->finance->id,
            'note' => '已与应收账款核销单匹配',
            'created_at' => Carbon::today()->subDays(10)->setTime(16, 0),
            'updated_at' => Carbon::today()->subDays(10)->setTime(16, 0),
        ]);

        $matchedPay = DB::table('bank_statements')->insertGetId([
            'account_id' => $this->accountIds['1005'],
            'statement_date' => Carbon::today()->subDays(12)->toDateString(),
            'direction' => BankStatement::DIRECTION_PAY,
            'amount' => 800.00,
            'counterparty' => '演示食品供应链（华南）有限公司',
            'reference' => 'PAY-DEMO-P02',
            'memo' => '供应商货款',
            'status' => BankStatement::STATUS_MATCHED,
            'operator_id' => $this->finance->id,
            'note' => '已与应付账款核销单匹配',
            'created_at' => Carbon::today()->subDays(12)->setTime(16, 0),
            'updated_at' => Carbon::today()->subDays(12)->setTime(16, 0),
        ]);

        DB::table('bank_statements')->insert([
            [
                'account_id' => $this->accountIds['1005'],
                'statement_date' => Carbon::today()->subDays(1)->toDateString(),
                'direction' => BankStatement::DIRECTION_RECEIVE,
                'amount' => 350.00,
                'counterparty' => '未知汇款人',
                'reference' => 'BANK-UNMATCH-001',
                'memo' => '待认领的客户回款',
                'status' => BankStatement::STATUS_UNMATCHED,
                'operator_id' => null,
                'note' => '需要出纳确认往来单位',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'account_id' => $this->accountIds['1005'],
                'statement_date' => Carbon::today()->subDays(2)->toDateString(),
                'direction' => BankStatement::DIRECTION_PAY,
                'amount' => 120.00,
                'counterparty' => '顺丰速运',
                'reference' => 'BANK-UNMATCH-002',
                'memo' => '快递费支出',
                'status' => BankStatement::STATUS_UNMATCHED,
                'operator_id' => null,
                'note' => '待补录费用凭证',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        if ($receiveLine) {
            DB::table('account_reconciliations')->insert([
                'statement_id' => $matchedReceive,
                'entry_line_id' => $receiveLine,
                'account_id' => $this->accountIds['1005'],
                'amount' => 1000.00,
                'operator_id' => $this->finance->id,
                'note' => '按客户回款单匹配',
                'reconciled_at' => Carbon::today()->subDays(10)->setTime(16, 10),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
        if ($payLine) {
            DB::table('account_reconciliations')->insert([
                'statement_id' => $matchedPay,
                'entry_line_id' => $payLine,
                'account_id' => $this->accountIds['1005'],
                'amount' => 800.00,
                'operator_id' => $this->finance->id,
                'note' => '按供应商付款单匹配',
                'reconciled_at' => Carbon::today()->subDays(12)->setTime(16, 10),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    private function bankEntryLine(int $entryId, string $direction): ?int
    {
        $line = DB::table('account_entry_lines')
            ->join('accounts', 'accounts.id', '=', 'account_entry_lines.account_id')
            ->where('account_entry_lines.entry_id', $entryId)
            ->where('accounts.code', '1005')
            ->where('account_entry_lines.direction', $direction)
            ->select('account_entry_lines.id')
            ->first();

        return $line ? (int) $line->id : null;
    }

    private function seedUserActivity(): void
    {
        $notifications = [
            $this->admin->id => [
                ['system', 'info', '演示数据已初始化', '8 个演示账号的全站业务数据已生成，可逐页体验。', 'dashboard', 'demo-admin-01'],
                ['system', 'warning', '建议立即创建基线备份', '演示数据已准备完成，可在“本地备份”页创建快照。', 'backups.index', 'demo-admin-02'],
                ['finance', 'info', '本月应收余额提醒', '当前仍有账龄超过 90 天的应收，请关注资金回笼。', 'aging.index', 'demo-admin-03'],
            ],
            $this->manager->id => [
                ['purchase', 'warning', '采购单待审批', 'PO-DEMO-0002 已确认，另有草稿采购单待提交审批。', 'purchase-orders.index', 'demo-manager-01'],
                ['stock', 'warning', '临期商品提醒', '纯牛奶与奥利奥存在临期/过期批次，请安排处理。', 'lots.index', 'demo-manager-02'],
                ['system', 'info', '今日经营概览', '今日 POS 与销售订单均已产生数据，可查看仪表盘趋势。', 'dashboard', 'demo-manager-03'],
            ],
            $this->cashier->id => [
                ['system', 'info', '今日收银班次已开班', 'POS-01 当前为开班状态，可直接扫码收银。', 'pos.index', 'demo-cashier-01'],
                ['stock', 'warning', '收银台库存提示', '康师傅红烧牛肉面库存偏低，请及时补货。', 'pos.index', 'demo-cashier-02'],
                ['system', 'info', '有一张挂单待处理', '顾客临时离店，购物车已保存，可在 POS 取单。', 'pos.index', 'demo-cashier-03'],
            ],
            $this->sales->id => [
                ['finance', 'warning', '客户应收逾期', '华城物业与阳光便利店有逾期应收，请协助催收。', 'statements.index', 'demo-sales-01'],
                ['system', 'info', '销售退货待处理', 'SR-DEMO-0002 仍为草稿，请验收后提交。', 'sales-returns.index', 'demo-sales-02'],
                ['system', 'info', '今日销售订单', 'SO-DEMO-0015 已确认，等待仓库出库。', 'dashboard', 'demo-sales-03'],
            ],
            $this->warehouse->id => [
                ['stock', 'danger', '低库存预警', '电池与方便面库存已低于安全线。', 'movements.index', 'demo-warehouse-01'],
                ['stock', 'warning', '盘点任务进行中', 'PC-DEMO-0002 门店抽盘尚未录入实盘数量。', 'counts.index', 'demo-warehouse-02'],
                ['stock', 'warning', '过期批次待销毁', 'SC-DEMO-0002 报废单待审批，请勿继续销售。', 'scraps.index', 'demo-warehouse-03'],
            ],
            $this->purchase->id => [
                ['purchase', 'info', '采购到货计划', 'PO-DEMO-0002 预计两日内到货，请跟进供应商。', 'purchase-orders.index', 'demo-purchase-01'],
                ['purchase', 'warning', '收货单待验收', 'RC-DEMO-0003 仍为草稿，请核对数量后过账。', 'receipts.index', 'demo-purchase-02'],
                ['purchase', 'info', '采购退货待确认', 'PR-DEMO-0002 乳品临期退货待供应商确认。', 'purchase-returns.index', 'demo-purchase-03'],
            ],
            $this->finance->id => [
                ['finance', 'danger', '应收逾期提醒', '多张发票已进入 90 天以上账龄，请评估坏账风险。', 'aging.index', 'demo-finance-01'],
                ['finance', 'warning', '银行流水待匹配', '有 2 条银行流水尚未匹配凭证。', 'reconciliations.index', 'demo-finance-02'],
                ['finance', 'info', '本月期间待结账', '当前会计期间为开放状态，月末请执行关账。', 'closing.index', 'demo-finance-03'],
            ],
            $this->auditor->id => [
                ['system', 'info', '演示审计样本已生成', '各账号的操作留痕已写入，可按模块与操作人筛选。', 'audit-logs.index', 'demo-auditor-01'],
                ['system', 'warning', '关注 POS 折扣与退货', '演示数据包含折扣、退货与挂账，可抽查审计链路。', 'audit-logs.index', 'demo-auditor-02'],
                ['system', 'info', '备份基线已准备', '管理员已创建演示数据基线备份，可核对留痕。', 'backups.index', 'demo-auditor-03'],
            ],
        ];

        $service = app(NotificationService::class);
        foreach ($notifications as $userId => $items) {
            $user = User::findOrFail($userId);
            foreach ($items as $index => [$type, $level, $title, $content, $route, $dedupe]) {
                $notification = $service->sendToUser($user, $type, $level, $title, $content, $route, $dedupe);
                if ($notification) {
                    DB::table('notifications')->where('id', $notification->id)->update([
                        'created_at' => Carbon::now()->subDays($index + 1)->setTime(9 + $index, 0),
                        'updated_at' => Carbon::now()->subDays($index + 1)->setTime(9 + $index, 0),
                    ]);
                    if ($index === 0) {
                        DB::table('notifications')->where('id', $notification->id)->update([
                            'is_read' => true,
                            'read_at' => Carbon::now()->subDays(1)->setTime(10, 0),
                        ]);
                    }
                }
            }
        }

        // 每人三条登录历史，最后一条为当前会话，覆盖用户会话审计口径。
        foreach ([$this->admin, $this->manager, $this->cashier, $this->sales, $this->warehouse, $this->purchase, $this->finance, $this->auditor] as $index => $user) {
            for ($i = 3; $i >= 1; $i--) {
                $login = Carbon::now()->subDays($i)->setTime(8 + $index % 3, 15);
                DB::table('user_sessions')->insert([
                    'user_id' => $user->id,
                    'login_at' => $login,
                    'logout_at' => $i === 1 ? null : $login->copy()->addHours(8),
                    'ip_address' => '127.0.0.1',
                    'user_agent' => 'Mozilla/5.0 Demo Browser',
                    'session_token' => 'demo-'.$user->id.'-'.$i,
                    'created_at' => $login,
                    'updated_at' => $login,
                ]);
            }
            DB::table('users')->where('id', $user->id)->update([
                'last_login_at' => Carbon::now()->subHours(2),
                'last_login_ip' => '127.0.0.1',
                'updated_at' => now(),
            ]);
        }

        $audits = [
            [$this->admin, 'system', 'approve', '创建演示数据基线备份', 'backup-demo.zip', 3],
            [$this->admin, 'master', 'create', '初始化演示商品与仓库主数据', 'DM-P01', 2],
            [$this->manager, 'purchase', 'approve', '审批采购单 PO-DEMO-0002', 'PO-DEMO-0002', 2],
            [$this->manager, 'sales', 'approve', '审批销售订单 SO-DEMO-0001', 'SO-DEMO-0001', 3],
            [$this->cashier, 'pos', 'create', '完成演示 POS 日结订单', 'POS-01', 1],
            [$this->cashier, 'pos', 'discount', '演示整单折扣与挂账收款', 'POS-01', 2],
            [$this->sales, 'sales', 'create', '创建客户销售订单与退货单', 'SO-DEMO-0015', 2],
            [$this->sales, 'finance', 'export', '导出客户对账单演示数据', 'CUS-04', 3],
            [$this->warehouse, 'warehouse', 'update', '完成门店盘点调整 PC-DEMO-0001', 'PC-DEMO-0001', 4],
            [$this->warehouse, 'warehouse', 'create', '登记报废单 SC-DEMO-0001', 'SC-DEMO-0001', 2],
            [$this->purchase, 'purchase', 'create', '创建采购订单 PO-DEMO-0001', 'PO-DEMO-0001', 3],
            [$this->purchase, 'purchase', 'create', '登记收货单 RC-DEMO-0002', 'RC-DEMO-0002', 2],
            [$this->finance, 'finance', 'approve', '过账客户应收发票 INV-DEMO-0001', 'INV-DEMO-0001', 3],
            [$this->finance, 'finance', 'update', '完成银行流水匹配', 'BANK-UNMATCH-001', 1],
            [$this->auditor, 'system', 'export', '导出审计日志样本', 'AUDIT-EXPORT-001', 1],
            [$this->auditor, 'system', 'view', '抽查 POS 折扣与退货链路', 'POS-01', 2],
        ];

        $auditIds = [];
        foreach ($audits as [$user, $module, $action, $message, $documentNo, $daysAgo]) {
            $auditIds[] = DB::table('audit_logs')->insertGetId([
                'operator_id' => $user->id,
                'acted_at' => Carbon::now()->subDays($daysAgo)->setTime(11, 30),
                'module' => $module,
                'model' => null,
                'record_id' => null,
                'document_no' => $documentNo,
                'action' => $action,
                'field' => null,
                'old_value' => null,
                'new_value' => json_encode(['demo' => true], JSON_UNESCAPED_UNICODE),
                'source' => 'web',
                'ip' => '127.0.0.1',
                'user_agent' => 'Mozilla/5.0 Demo Browser',
                'session_id' => 'demo-'.$user->id,
                'context' => json_encode(['demo' => true, 'operator' => $user->username], JSON_UNESCAPED_UNICODE),
                'message' => $message,
            ]);
        }

        DB::table('audit_field_diffs')->insert([
            [
                'log_id' => $auditIds[8],
                'model' => 'App\\Models\\StockCount',
                'record_id' => null,
                'action' => 'update',
                'field' => 'counted_qty',
                'field_label' => '实盘数量',
                'old_text' => '48',
                'new_text' => '46',
                'change_type' => 'changed',
                'created_at' => now(),
            ],
            [
                'log_id' => $auditIds[2],
                'model' => 'App\\Models\\PurchaseOrder',
                'record_id' => null,
                'action' => 'approve',
                'field' => 'status',
                'field_label' => '单据状态',
                'old_text' => 'draft',
                'new_text' => 'confirmed',
                'change_type' => 'changed',
                'created_at' => now(),
            ],
        ]);
    }
}
