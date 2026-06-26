# 심지 결제·검사권(voucher) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** 개인(B2C) 카드결제로 검사권(크레딧)을 사고 응시 시 FIFO로 차감하는 커머스 레이어를, 자동화 테스트가 가능한 `FakeGateway` 위에서 end-to-end로 구현한다.

**Architecture:** 6개 신규 테이블(products/orders/order_items/payments/vouchers) + test_attempts.voucher_id. 도메인 로직은 서비스로 격리(CheckoutService·VoucherService·PaymentGateway 인터페이스). PG는 인터페이스 뒤로 숨기고 자동화 테스트는 `FakeGateway` 사용. 실 KG이니시스 어댑터(`InicisGateway`)는 단디 라이브러리+테스트MID가 필요해 이 플랜의 마지막 분리 태스크(수동 검증).

**Tech Stack:** PHP8/Laravel, Blade, Tailwind, Pest(Feature+Unit), SQLite(in-memory 테스트).

## Global Constraints

- 구매 모델 = 검사권(크레딧). 응시 시 1장 FIFO 차감(무료·추천권이 유료보다 먼저).
- 가격은 `products` 테이블에만. `tests`엔 가격 컬럼 없음. 무료 검사 = active product 없는 검사(게스트 응시 유지).
- product.price는 원(KRW) 양수(>0). 무료 검사는 product를 두지 않음.
- 검사권 유효기간 = 발급일 + product.valid_days(기본 365).
- 장바구니 없음(검사 1개씩 단건 결제). 포인트·추천정산·기관B2B·강의코칭결제는 비범위.
- 멱등성: PG return 중복 호출에도 검사권 이중 발급 0(`pg_tid` unique + order.status 가드 + issueForOrder 멱등).
- 금액 위변조: 서버 order.total_amount ↔ PG 승인 amount 일치 검증.
- 자동화 테스트는 `FakeGateway`로만(실 PG 호출 금지). KG이니시스 1순위, KCP는 인터페이스 자리만.
- `<?=` 단축태그 금지(Blade `{{ }}`). 기존 무료 응시(KMSIA-SAMPLE) 회귀 없음.
- 테스트 실행: `php artisan test --filter=<Name>`. 전체: `php artisan test`.

---

## File Structure

- `database/migrations/*` — products, orders, order_items, payments, vouchers 생성 + test_attempts.voucher_id (Task 1)
- `app/Models/{Product,Order,OrderItem,Payment,Voucher}.php` 생성; `Test.php`,`TestAttempt.php`,`User.php` 수정 (Task 1)
- `app/Services/VoucherService.php` (Task 2)
- `app/Payments/{PaymentGateway.php,PaymentResult.php,FakeGateway.php}` + `config/services.php`,`AppServiceProvider` (Task 3)
- `app/Services/CheckoutService.php`, `app/Http/Controllers/CheckoutController.php`, `resources/views/checkout/show.blade.php`, `routes/web.php` (Task 4)
- `app/Http/Controllers/PaymentController.php`, `resources/views/payment/{complete,fail}.blade.php`, `routes/web.php` (Task 5)
- `resources/views/catalog/show.blade.php`, `components/test-card.blade.php`, `CatalogController.php` (Task 6)
- `app/Http/Controllers/AssessmentController.php`, `resources/views/assessment/consent.blade.php` (Task 7)
- `app/Http/Controllers/MyTestController.php`, `resources/views/my/index.blade.php` (Task 8)
- `database/seeders/PaidSampleSeeder.php`, `DatabaseSeeder.php` (Task 9)
- 향후 분리: `app/Payments/InicisGateway.php` (이 플랜 밖)

---

## Task 1: 스키마 + 모델

**Files:**
- Create: `database/migrations/2026_06_26_100001_create_products_table.php`, `..._100002_create_orders_table.php`, `..._100003_create_order_items_table.php`, `..._100004_create_payments_table.php`, `..._100005_create_vouchers_table.php`, `..._100006_add_voucher_id_to_test_attempts.php`
- Create: `app/Models/Product.php`, `Order.php`, `OrderItem.php`, `Payment.php`, `Voucher.php`
- Modify: `app/Models/Test.php`, `app/Models/TestAttempt.php`, `app/Models/User.php`
- Test: `tests/Feature/CommerceSchemaTest.php`

**Interfaces:**
- Produces: Eloquent 모델 `Product,Order,OrderItem,Payment,Voucher`. `Test::activeProduct():?Product`, `Test::isPaid():bool`, `Test::products()`. `Voucher` 컬럼: user_id,test_id,order_item_id,source,status,issued_at,expires_at,used_at,used_attempt_id. `OrderItem` 컬럼에 `valid_days` 포함(발급 시 만료 계산용 스냅샷). 모두 `$guarded=[]`.

- [ ] **Step 1: 실패 테스트 작성**

`tests/Feature/CommerceSchemaTest.php`:

```php
<?php
use App\Models\{Test, Product, Order, OrderItem, Payment, Voucher, User};

test('test isPaid reflects active product', function () {
    $t = Test::create(['code'=>'PT','room'=>'univ','title_easy'=>'a','title_pro'=>'A','target'=>'x','duration_min'=>5,'item_count'=>1,'areas'=>[],'result_type'=>'signal','description'=>'d','status'=>'active']);
    expect($t->isPaid())->toBeFalse();
    expect($t->activeProduct())->toBeNull();
    Product::create(['test_id'=>$t->id,'name'=>'PT 검사권','price'=>9900,'credit_qty'=>1,'valid_days'=>365,'status'=>'active']);
    expect($t->fresh()->isPaid())->toBeTrue();
    expect($t->fresh()->activeProduct()->price)->toBe(9900);
});

test('order has items payments and user', function () {
    $u = User::factory()->create();
    $t = Test::create(['code'=>'PT2','room'=>'univ','title_easy'=>'a','title_pro'=>'A','target'=>'x','duration_min'=>5,'item_count'=>1,'areas'=>[],'result_type'=>'signal','description'=>'d','status'=>'active']);
    $o = Order::create(['order_no'=>'S-1','user_id'=>$u->id,'status'=>'pending','total_amount'=>9900]);
    $o->items()->create(['product_id'=>null,'test_id'=>$t->id,'product_name'=>'PT 검사권','unit_price'=>9900,'quantity'=>1,'credit_qty'=>1,'valid_days'=>365]);
    $o->payments()->create(['provider'=>'fake','amount'=>9900,'status'=>'ready']);
    expect($o->items)->toHaveCount(1);
    expect($o->payments)->toHaveCount(1);
    expect($o->user->id)->toBe($u->id);
});

test('voucher belongs to user and test with casts', function () {
    $u = User::factory()->create();
    $t = Test::create(['code'=>'PT3','room'=>'univ','title_easy'=>'a','title_pro'=>'A','target'=>'x','duration_min'=>5,'item_count'=>1,'areas'=>[],'result_type'=>'signal','description'=>'d','status'=>'active']);
    $v = Voucher::create(['user_id'=>$u->id,'test_id'=>$t->id,'source'=>'purchase','status'=>'active','issued_at'=>now(),'expires_at'=>now()->addYear()]);
    expect($v->status)->toBe('active');
    expect($v->user->id)->toBe($u->id);
    expect($v->test->id)->toBe($t->id);
});
```

- [ ] **Step 2: 실패 확인**

Run: `php artisan test --filter=CommerceSchemaTest`
Expected: FAIL — 테이블/모델 없음.

- [ ] **Step 3: 마이그레이션 6개 작성**

`..._100001_create_products_table.php`:

```php
<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::create('products', function (Blueprint $t) {
            $t->id();
            $t->foreignId('test_id')->constrained()->cascadeOnDelete();
            $t->string('name');
            $t->unsignedInteger('price');
            $t->unsignedSmallInteger('credit_qty')->default(1);
            $t->unsignedSmallInteger('valid_days')->default(365);
            $t->string('status')->default('active');
            $t->timestamps();
            $t->index(['test_id','status']);
        });
    }
    public function down(): void { Schema::dropIfExists('products'); }
};
```

`..._100002_create_orders_table.php`:

```php
<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::create('orders', function (Blueprint $t) {
            $t->id();
            $t->string('order_no')->unique();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->string('status')->default('pending');
            $t->unsignedInteger('total_amount');
            $t->timestamp('paid_at')->nullable();
            $t->timestamp('canceled_at')->nullable();
            $t->timestamps();
            $t->index(['user_id','status']);
        });
    }
    public function down(): void { Schema::dropIfExists('orders'); }
};
```

`..._100003_create_order_items_table.php`:

```php
<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::create('order_items', function (Blueprint $t) {
            $t->id();
            $t->foreignId('order_id')->constrained()->cascadeOnDelete();
            $t->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $t->unsignedBigInteger('test_id');
            $t->string('product_name');
            $t->unsignedInteger('unit_price');
            $t->unsignedSmallInteger('quantity')->default(1);
            $t->unsignedSmallInteger('credit_qty')->default(1);
            $t->unsignedSmallInteger('valid_days')->default(365);
            $t->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('order_items'); }
};
```

`..._100004_create_payments_table.php`:

```php
<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::create('payments', function (Blueprint $t) {
            $t->id();
            $t->foreignId('order_id')->constrained()->cascadeOnDelete();
            $t->string('provider');
            $t->string('method')->nullable();
            $t->string('pg_tid')->nullable()->unique();
            $t->unsignedInteger('amount');
            $t->string('status')->default('ready');
            $t->timestamp('paid_at')->nullable();
            $t->json('raw_response')->nullable();
            $t->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('payments'); }
};
```

`..._100005_create_vouchers_table.php`:

```php
<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::create('vouchers', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->foreignId('test_id')->constrained()->cascadeOnDelete();
            $t->foreignId('order_item_id')->nullable()->constrained()->nullOnDelete();
            $t->string('source')->default('purchase');
            $t->string('status')->default('active');
            $t->timestamp('issued_at');
            $t->timestamp('expires_at')->nullable();
            $t->timestamp('used_at')->nullable();
            $t->unsignedBigInteger('used_attempt_id')->nullable();
            $t->timestamps();
            $t->index(['user_id','test_id','status','issued_at']);
        });
    }
    public function down(): void { Schema::dropIfExists('vouchers'); }
};
```

`..._100006_add_voucher_id_to_test_attempts.php`:

```php
<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::table('test_attempts', function (Blueprint $t) {
            $t->foreignId('voucher_id')->nullable()->after('test_id')->constrained()->nullOnDelete();
        });
    }
    public function down(): void {
        Schema::table('test_attempts', function (Blueprint $t) {
            $t->dropConstrainedForeignId('voucher_id');
        });
    }
};
```

- [ ] **Step 4: 모델 5개 생성**

`app/Models/Product.php`:

```php
<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Product extends Model
{
    protected $guarded = [];
    protected $casts = ['price'=>'integer','credit_qty'=>'integer','valid_days'=>'integer'];
    public function test() { return $this->belongsTo(Test::class); }
}
```

`app/Models/Order.php`:

```php
<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Order extends Model
{
    protected $guarded = [];
    protected $casts = ['total_amount'=>'integer','paid_at'=>'datetime','canceled_at'=>'datetime'];
    public function user() { return $this->belongsTo(User::class); }
    public function items() { return $this->hasMany(OrderItem::class); }
    public function payments() { return $this->hasMany(Payment::class); }
}
```

`app/Models/OrderItem.php`:

```php
<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class OrderItem extends Model
{
    protected $guarded = [];
    protected $casts = ['unit_price'=>'integer','quantity'=>'integer','credit_qty'=>'integer','valid_days'=>'integer'];
    public function order() { return $this->belongsTo(Order::class); }
    public function product() { return $this->belongsTo(Product::class); }
    public function vouchers() { return $this->hasMany(Voucher::class); }
}
```

`app/Models/Payment.php`:

```php
<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Payment extends Model
{
    protected $guarded = [];
    protected $casts = ['amount'=>'integer','paid_at'=>'datetime','raw_response'=>'array'];
    public function order() { return $this->belongsTo(Order::class); }
}
```

`app/Models/Voucher.php`:

```php
<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Voucher extends Model
{
    protected $guarded = [];
    protected $casts = ['issued_at'=>'datetime','expires_at'=>'datetime','used_at'=>'datetime'];
    public function user() { return $this->belongsTo(User::class); }
    public function test() { return $this->belongsTo(Test::class); }
    public function orderItem() { return $this->belongsTo(OrderItem::class); }
    public function attempt() { return $this->belongsTo(TestAttempt::class, 'used_attempt_id'); }
}
```

- [ ] **Step 5: Test/TestAttempt/User 모델 보강**

`app/Models/Test.php` 에 추가(기존 메서드 옆):

```php
    public function products() { return $this->hasMany(Product::class); }
    public function activeProduct(): ?Product {
        return $this->products()->where('status','active')->orderBy('price')->first();
    }
    public function isPaid(): bool { return $this->activeProduct() !== null; }
```

`app/Models/TestAttempt.php` 에 추가:

```php
    public function voucher() { return $this->belongsTo(Voucher::class); }
```

`app/Models/User.php` 에 추가:

```php
    public function orders() { return $this->hasMany(\App\Models\Order::class); }
    public function vouchers() { return $this->hasMany(\App\Models\Voucher::class); }
```

- [ ] **Step 6: 마이그레이션 실행 + 테스트 통과**

Run: `php artisan migrate --force && php artisan test --filter=CommerceSchemaTest`
Expected: PASS (3 tests).

- [ ] **Step 7: Commit**

```bash
git add database/migrations app/Models tests/Feature/CommerceSchemaTest.php
git commit -m "feat: 결제·검사권 스키마+모델(products/orders/order_items/payments/vouchers)"
```

---

## Task 2: VoucherService (발급 멱등 + FIFO 차감)

**Files:**
- Create: `app/Services/VoucherService.php`
- Test: `tests/Feature/VoucherServiceTest.php`

**Interfaces:**
- Consumes: Task 1 모델.
- Produces: `VoucherService::issueForOrder(Order):void`(멱등), `consume(User,Test,TestAttempt):Voucher`(FIFO+lock, 없으면 `RuntimeException`), `firstActive(User,Test):?Voucher`, `availableCount(User,Test):int`.

- [ ] **Step 1: 실패 테스트 작성**

`tests/Feature/VoucherServiceTest.php`:

```php
<?php
use App\Models\{Test, Product, Order, User, TestAttempt, Voucher};
use App\Services\VoucherService;

function paidTest(int $price=9900, int $qty=1, int $credit=1): Test {
    $t = Test::create(['code'=>'VS'.$qty.$credit.$price,'room'=>'univ','title_easy'=>'a','title_pro'=>'A','target'=>'x','duration_min'=>5,'item_count'=>1,'areas'=>[],'result_type'=>'signal','description'=>'d','status'=>'active']);
    Product::create(['test_id'=>$t->id,'name'=>'권','price'=>$price,'credit_qty'=>$credit,'valid_days'=>365,'status'=>'active']);
    return $t;
}
function paidOrder(User $u, Test $t, int $qty=1, int $credit=1): Order {
    $o = Order::create(['order_no'=>'S-'.uniqid(),'user_id'=>$u->id,'status'=>'paid','total_amount'=>9900]);
    $o->items()->create(['test_id'=>$t->id,'product_name'=>'권','unit_price'=>9900,'quantity'=>$qty,'credit_qty'=>$credit,'valid_days'=>365]);
    return $o;
}

test('issueForOrder issues credit_qty*quantity vouchers and is idempotent', function () {
    $u = User::factory()->create(); $t = paidTest();
    $o = paidOrder($u, $t, qty:2, credit:3); // 2*3 = 6장
    $svc = app(VoucherService::class);
    $svc->issueForOrder($o);
    expect(Voucher::where('user_id',$u->id)->count())->toBe(6);
    $svc->issueForOrder($o); // 재호출 — 이중발급 없어야
    expect(Voucher::where('user_id',$u->id)->count())->toBe(6);
});

test('issueForOrder skips when order not paid', function () {
    $u = User::factory()->create(); $t = paidTest();
    $o = paidOrder($u, $t); $o->update(['status'=>'pending']);
    app(VoucherService::class)->issueForOrder($o);
    expect(Voucher::count())->toBe(0);
});

test('consume picks oldest active first (FIFO) and links attempt', function () {
    $u = User::factory()->create(); $t = paidTest();
    $old = Voucher::create(['user_id'=>$u->id,'test_id'=>$t->id,'source'=>'free','status'=>'active','issued_at'=>now()->subDays(2),'expires_at'=>now()->addYear()]);
    $new = Voucher::create(['user_id'=>$u->id,'test_id'=>$t->id,'source'=>'purchase','status'=>'active','issued_at'=>now(),'expires_at'=>now()->addYear()]);
    $a = TestAttempt::create(['user_id'=>$u->id,'test_id'=>$t->id,'status'=>'in_progress','started_at'=>now()]);
    $used = app(VoucherService::class)->consume($u, $t, $a);
    expect($used->id)->toBe($old->id);
    expect($used->fresh()->status)->toBe('used');
    expect($used->fresh()->used_attempt_id)->toBe($a->id);
    expect($a->fresh()->voucher_id)->toBe($old->id);
});

test('consume ignores expired vouchers', function () {
    $u = User::factory()->create(); $t = paidTest();
    Voucher::create(['user_id'=>$u->id,'test_id'=>$t->id,'source'=>'purchase','status'=>'active','issued_at'=>now()->subDays(400),'expires_at'=>now()->subDay()]);
    $a = TestAttempt::create(['user_id'=>$u->id,'test_id'=>$t->id,'status'=>'in_progress','started_at'=>now()]);
    expect(fn() => app(VoucherService::class)->consume($u, $t, $a))->toThrow(RuntimeException::class);
});

test('availableCount counts only active non-expired', function () {
    $u = User::factory()->create(); $t = paidTest();
    Voucher::create(['user_id'=>$u->id,'test_id'=>$t->id,'status'=>'active','source'=>'purchase','issued_at'=>now(),'expires_at'=>now()->addYear()]);
    Voucher::create(['user_id'=>$u->id,'test_id'=>$t->id,'status'=>'used','source'=>'purchase','issued_at'=>now(),'expires_at'=>now()->addYear()]);
    expect(app(VoucherService::class)->availableCount($u, $t))->toBe(1);
});
```

- [ ] **Step 2: 실패 확인**

Run: `php artisan test --filter=VoucherServiceTest`
Expected: FAIL — VoucherService 없음.

- [ ] **Step 3: VoucherService 구현**

`app/Services/VoucherService.php`:

```php
<?php
namespace App\Services;

use App\Models\{Order, Test, TestAttempt, User, Voucher};
use Illuminate\Support\Facades\DB;

class VoucherService
{
    public function issueForOrder(Order $order): void
    {
        if ($order->status !== 'paid') return;
        DB::transaction(function () use ($order) {
            foreach ($order->items()->get() as $item) {
                // 멱등: 이미 이 항목으로 발급된 검사권이 있으면 skip
                if (Voucher::where('order_item_id', $item->id)->exists()) continue;
                $count = max(1, $item->credit_qty) * max(1, $item->quantity);
                for ($i = 0; $i < $count; $i++) {
                    Voucher::create([
                        'user_id' => $order->user_id,
                        'test_id' => $item->test_id,
                        'order_item_id' => $item->id,
                        'source' => 'purchase',
                        'status' => 'active',
                        'issued_at' => now(),
                        'expires_at' => now()->addDays(max(1, $item->valid_days)),
                    ]);
                }
            }
        });
    }

    public function firstActive(User $user, Test $test): ?Voucher
    {
        return $this->activeQuery($user, $test)->orderBy('issued_at')->orderBy('id')->first();
    }

    public function availableCount(User $user, Test $test): int
    {
        return $this->activeQuery($user, $test)->count();
    }

    public function consume(User $user, Test $test, TestAttempt $attempt): Voucher
    {
        return DB::transaction(function () use ($user, $test, $attempt) {
            $voucher = $this->activeQuery($user, $test)
                ->orderBy('issued_at')->orderBy('id')
                ->lockForUpdate()->first();
            if (!$voucher) {
                throw new \RuntimeException('사용 가능한 검사권이 없습니다.');
            }
            $voucher->update(['status' => 'used', 'used_at' => now(), 'used_attempt_id' => $attempt->id]);
            $attempt->update(['voucher_id' => $voucher->id]);
            return $voucher;
        });
    }

    private function activeQuery(User $user, Test $test)
    {
        return Voucher::where('user_id', $user->id)
            ->where('test_id', $test->id)
            ->where('status', 'active')
            ->where(function ($q) {
                $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
            });
    }
}
```

- [ ] **Step 4: 테스트 통과 확인**

Run: `php artisan test --filter=VoucherServiceTest`
Expected: PASS (5 tests).

- [ ] **Step 5: Commit**

```bash
git add app/Services/VoucherService.php tests/Feature/VoucherServiceTest.php
git commit -m "feat: VoucherService(발급 멱등 + FIFO 차감 + 만료제외)"
```

---

## Task 3: PaymentGateway 인터페이스 + FakeGateway

**Files:**
- Create: `app/Payments/PaymentGateway.php`, `app/Payments/PaymentResult.php`, `app/Payments/FakeGateway.php`
- Modify: `config/services.php`, `app/Providers/AppServiceProvider.php`
- Test: `tests/Feature/FakeGatewayTest.php`

**Interfaces:**
- Produces: `App\Payments\PaymentGateway` 인터페이스 — `begin(Order $order): array`, `approve(array $return): PaymentResult`. `PaymentResult`(public readonly: `bool $success`, `string $orderNo`, `int $amount`, `?string $tid`, `?string $method`, `array $raw`). `FakeGateway`가 컨테이너에 `PaymentGateway`로 바인딩됨(`config('services.pg.driver')`).

- [ ] **Step 1: 실패 테스트 작성**

`tests/Feature/FakeGatewayTest.php`:

```php
<?php
use App\Models\{Order, User};
use App\Payments\{PaymentGateway, PaymentResult, FakeGateway};

test('container resolves PaymentGateway to FakeGateway in tests', function () {
    expect(app(PaymentGateway::class))->toBeInstanceOf(FakeGateway::class);
});

test('begin returns order_no and amount and return_url', function () {
    $u = User::factory()->create();
    $o = Order::create(['order_no'=>'S-XYZ','user_id'=>$u->id,'status'=>'pending','total_amount'=>9900]);
    $params = app(PaymentGateway::class)->begin($o);
    expect($params['order_no'])->toBe('S-XYZ');
    expect($params['amount'])->toBe(9900);
    expect($params)->toHaveKey('return_url');
});

test('approve success returns PaymentResult with tid', function () {
    $r = app(PaymentGateway::class)->approve(['order_no'=>'S-XYZ','amount'=>9900,'result'=>'success']);
    expect($r)->toBeInstanceOf(PaymentResult::class);
    expect($r->success)->toBeTrue();
    expect($r->orderNo)->toBe('S-XYZ');
    expect($r->amount)->toBe(9900);
    expect($r->tid)->toBe('FAKE-S-XYZ');
});

test('approve failure returns unsuccessful result', function () {
    $r = app(PaymentGateway::class)->approve(['order_no'=>'S-XYZ','amount'=>9900,'result'=>'fail']);
    expect($r->success)->toBeFalse();
});
```

- [ ] **Step 2: 실패 확인**

Run: `php artisan test --filter=FakeGatewayTest`
Expected: FAIL — 클래스 없음.

- [ ] **Step 3: PaymentResult + 인터페이스 + FakeGateway**

`app/Payments/PaymentResult.php`:

```php
<?php
namespace App\Payments;

class PaymentResult
{
    public function __construct(
        public readonly bool $success,
        public readonly string $orderNo,
        public readonly int $amount,
        public readonly ?string $tid = null,
        public readonly ?string $method = null,
        public readonly array $raw = [],
    ) {}
}
```

`app/Payments/PaymentGateway.php`:

```php
<?php
namespace App\Payments;
use App\Models\Order;

interface PaymentGateway
{
    /** 결제창 호출에 필요한 파라미터(주문번호·금액·return_url 등) */
    public function begin(Order $order): array;

    /** PG 인증결과 배열을 받아 승인 처리하고 결과 반환 */
    public function approve(array $return): PaymentResult;
}
```

`app/Payments/FakeGateway.php`:

```php
<?php
namespace App\Payments;
use App\Models\Order;

class FakeGateway implements PaymentGateway
{
    public function begin(Order $order): array
    {
        return [
            'order_no' => $order->order_no,
            'amount' => $order->total_amount,
            'return_url' => route('payment.return'),
            'provider' => 'fake',
        ];
    }

    public function approve(array $return): PaymentResult
    {
        $ok = ($return['result'] ?? 'success') === 'success';
        $orderNo = (string) ($return['order_no'] ?? '');
        $amount = (int) ($return['amount'] ?? 0);
        return new PaymentResult(
            success: $ok,
            orderNo: $orderNo,
            amount: $amount,
            tid: $ok ? 'FAKE-'.$orderNo : null,
            method: 'card',
            raw: $return,
        );
    }
}
```

- [ ] **Step 4: config + 바인딩**

`config/services.php` 의 반환 배열에 추가:

```php
    'pg' => [
        'driver' => env('PG_DRIVER', 'fake'), // fake | inicis
        'inicis' => [
            'mid' => env('INICIS_MID', 'INIpayTest'),
            'sign_key' => env('INICIS_SIGN_KEY', ''),
        ],
    ],
```

`app/Providers/AppServiceProvider.php` 의 `register()` 에 추가(없으면 메서드 생성):

```php
    public function register(): void
    {
        $this->app->bind(\App\Payments\PaymentGateway::class, function () {
            return match (config('services.pg.driver')) {
                // 'inicis' => new \App\Payments\InicisGateway(...), // 향후 분리 태스크
                default => new \App\Payments\FakeGateway(),
            };
        });
    }
```

> phpunit.xml 에 `PG_DRIVER` env가 없으면 기본 'fake'라 테스트는 FakeGateway로 바인딩된다.

- [ ] **Step 5: 테스트 통과 확인**

Run: `php artisan test --filter=FakeGatewayTest`
Expected: PASS (4 tests). `payment.return` 라우트는 Task 5에서 생기지만 `begin`의 `route()`는 Task 5 이후 통과 — **그러므로 이 태스크는 Task 5 라우트가 있어야 begin 테스트가 통과.** 순서 보장을 위해 begin 테스트의 `return_url` 검증은 라우트 존재에 의존하지 않도록 키 존재만 확인(위 테스트는 `toHaveKey('return_url')`이라 OK이나 `route()` 호출 자체가 실패하면 예외). **해결: 이 태스크에서 `routes/web.php`에 `Route::match(['get','post'],'/payment/return', fn()=>abort(404))->name('payment.return');` 임시 플레이스홀더 라우트를 추가하고 Task 5에서 실제 컨트롤러로 교체.**

`routes/web.php` 에 임시 라우트 추가(Task 5에서 교체):

```php
Route::match(['get','post'], '/payment/return', fn () => abort(404))->name('payment.return');
```

- [ ] **Step 6: Commit**

```bash
git add app/Payments config/services.php app/Providers/AppServiceProvider.php routes/web.php tests/Feature/FakeGatewayTest.php
git commit -m "feat: PaymentGateway 인터페이스 + FakeGateway + 컨테이너 바인딩"
```

---

## Task 4: CheckoutService + 결제확인 화면 + 주문 생성

**Files:**
- Create: `app/Services/CheckoutService.php`, `app/Http/Controllers/CheckoutController.php`, `resources/views/checkout/show.blade.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/CheckoutTest.php`

**Interfaces:**
- Consumes: Task1 모델, Task3 `PaymentGateway`.
- Produces: `CheckoutService::createOrder(User,Product,int $qty=1): Order`(order_no `S{Ymd}-{RAND6}`, pending). Routes: `checkout.show`(GET `/checkout/{product}`), `checkout.start`(POST `/checkout/{product}`). start는 order 생성 후 `payment.return`으로 가는 흐름을 위해 FakeGateway에선 결제확인을 거쳐 `/payment/return`을 직접 호출하는 폼을 렌더(실 PG는 결제창). 본 태스크 start는 **order 생성 + gateway.begin 파라미터를 담은 "모의 결제" 페이지**(view `checkout.show`에 hidden form)로 리다이렉트하지 않고, order 생성 후 `payment.return` 호출용 데이터를 가진 중간 페이지를 보여준다.

> 설계 단순화: FakeGateway 환경에선 `checkout.start`가 order(pending) 생성 후, `payment.return`으로 POST하는 "결제 진행(모의)" 버튼이 있는 페이지를 렌더한다. 실 InicisGateway에선 이 자리에서 INIStdPay 결제창 스크립트를 출력한다(향후 태스크). 이렇게 두면 자동화 테스트가 결제창 없이 흐름을 검증한다.

- [ ] **Step 1: 실패 테스트 작성**

`tests/Feature/CheckoutTest.php`:

```php
<?php
use App\Models\{Test, Product, Order, User};

function checkoutPaidTest(): array {
    $t = Test::create(['code'=>'CK','room'=>'univ','title_easy'=>'대학생 마음검사','title_pro'=>'CK','target'=>'대학생','duration_min'=>5,'item_count'=>1,'areas'=>[],'result_type'=>'signal','description'=>'d','status'=>'active']);
    $p = Product::create(['test_id'=>$t->id,'name'=>'CK 검사권','price'=>9900,'credit_qty'=>1,'valid_days'=>365,'status'=>'active']);
    return [$t,$p];
}

test('checkout show requires login', function () {
    [$t,$p] = checkoutPaidTest();
    $this->get("/checkout/{$p->id}")->assertRedirect(route('login'));
});

test('checkout show renders product and price for logged-in user', function () {
    [$t,$p] = checkoutPaidTest();
    $this->actingAs(User::factory()->create())
        ->get("/checkout/{$p->id}")->assertOk()
        ->assertSee('CK 검사권')->assertSee('9,900');
});

test('checkout start creates pending order and shows pay step', function () {
    [$t,$p] = checkoutPaidTest();
    $u = User::factory()->create();
    $this->actingAs($u)->post("/checkout/{$p->id}")->assertOk()->assertSee('결제');
    $o = Order::where('user_id',$u->id)->first();
    expect($o)->not->toBeNull();
    expect($o->status)->toBe('pending');
    expect($o->total_amount)->toBe(9900);
    expect($o->items()->count())->toBe(1);
});
```

- [ ] **Step 2: 실패 확인**

Run: `php artisan test --filter=CheckoutTest`
Expected: FAIL — 라우트/컨트롤러 없음.

- [ ] **Step 3: CheckoutService**

`app/Services/CheckoutService.php`:

```php
<?php
namespace App\Services;

use App\Models\{Order, Product, User};
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CheckoutService
{
    public function createOrder(User $user, Product $product, int $qty = 1): Order
    {
        $qty = max(1, $qty);
        return DB::transaction(function () use ($user, $product, $qty) {
            $order = Order::create([
                'order_no' => 'S'.now()->format('Ymd').'-'.Str::upper(Str::random(6)),
                'user_id' => $user->id,
                'status' => 'pending',
                'total_amount' => $product->price * $qty,
            ]);
            $order->items()->create([
                'product_id' => $product->id,
                'test_id' => $product->test_id,
                'product_name' => $product->name,
                'unit_price' => $product->price,
                'quantity' => $qty,
                'credit_qty' => $product->credit_qty,
                'valid_days' => $product->valid_days,
            ]);
            return $order;
        });
    }
}
```

- [ ] **Step 4: CheckoutController + 라우트**

`app/Http/Controllers/CheckoutController.php`:

```php
<?php
namespace App\Http\Controllers;

use App\Models\Product;
use App\Payments\PaymentGateway;
use App\Services\CheckoutService;
use Illuminate\Http\Request;

class CheckoutController extends Controller
{
    public function show(Product $product)
    {
        abort_unless($product->status === 'active', 404);
        return view('checkout.show', ['product' => $product, 'order' => null, 'pay' => null]);
    }

    public function start(Request $request, Product $product, CheckoutService $checkout, PaymentGateway $gateway)
    {
        abort_unless($product->status === 'active', 404);
        $order = $checkout->createOrder($request->user(), $product);
        $pay = $gateway->begin($order);
        return view('checkout.show', ['product' => $product, 'order' => $order, 'pay' => $pay]);
    }
}
```

`routes/web.php` 에 추가(use 문 + 라우트, `auth` 미들웨어):

```php
use App\Http\Controllers\CheckoutController;
// ...
Route::middleware('auth')->group(function () {
    Route::get('/checkout/{product}', [CheckoutController::class, 'show'])->name('checkout.show');
    Route::post('/checkout/{product}', [CheckoutController::class, 'start'])->name('checkout.start');
});
```

- [ ] **Step 5: 결제확인 뷰**

`resources/views/checkout/show.blade.php`:

```blade
<x-layouts.app :title="'결제 · '.$product->name">
  <div class="bg-cream min-h-[70vh]">
    <div class="max-w-2xl mx-auto px-4 py-12">
      <h1 class="text-2xl font-extrabold text-deepgreen">검사권 구매</h1>
      <div class="mt-6 rounded-3xl bg-white p-6 shadow-sm ring-1 ring-black/5">
        <div class="flex items-center justify-between">
          <div>
            <p class="font-bold text-deepgreen">{{ $product->name }}</p>
            <p class="text-sm text-navy/50 mt-1">검사권 {{ $product->credit_qty }}장 · 유효기간 {{ $product->valid_days }}일</p>
          </div>
          <p class="text-xl font-extrabold text-deepgreen">{{ number_format($product->price) }}원</p>
        </div>
      </div>

      @if(!$order)
        <form method="POST" action="{{ route('checkout.start', $product->id) }}" class="mt-6">
          @csrf
          <button class="w-full rounded-xl bg-deepgreen text-cream py-3.5 font-bold shadow-lg hover:brightness-110 transition">결제하기</button>
        </form>
      @else
        {{-- 모의 결제 단계: 실 InicisGateway에선 이 자리에 결제창 스크립트가 들어간다 --}}
        <form method="POST" action="{{ route('payment.return') }}" class="mt-6 space-y-3">
          @csrf
          <input type="hidden" name="order_no" value="{{ $order->order_no }}">
          <input type="hidden" name="amount" value="{{ $order->total_amount }}">
          <p class="text-sm text-navy/60">아래 버튼으로 결제를 진행합니다. (테스트 결제)</p>
          <div class="flex gap-3">
            <button name="result" value="success" class="flex-1 rounded-xl bg-deepgreen text-cream py-3.5 font-bold">결제 완료</button>
            <button name="result" value="fail" class="rounded-xl border border-navy/20 text-navy/60 px-5 py-3.5">취소</button>
          </div>
        </form>
      @endif
    </div>
  </div>
</x-layouts.app>
```

- [ ] **Step 6: 테스트 통과 확인**

Run: `php artisan test --filter=CheckoutTest`
Expected: PASS (3 tests).

- [ ] **Step 7: Commit**

```bash
git add app/Services/CheckoutService.php app/Http/Controllers/CheckoutController.php resources/views/checkout routes/web.php tests/Feature/CheckoutTest.php
git commit -m "feat: 결제확인 화면 + 주문(pending) 생성"
```

---

## Task 5: PaymentController (승인→검사권 발급, 멱등) + 완료/실패

**Files:**
- Create: `app/Http/Controllers/PaymentController.php`, `resources/views/payment/complete.blade.php`, `resources/views/payment/fail.blade.php`
- Modify: `routes/web.php` (Task3 임시 `payment.return` 라우트 교체 + complete/fail 추가)
- Test: `tests/Feature/PaymentReturnTest.php`

**Interfaces:**
- Consumes: Task2 `VoucherService`, Task3 `PaymentGateway`, Task1 모델.
- Produces: Routes `payment.return`(match get/post `/payment/return`), `payment.complete`(GET `/payment/complete/{order}`), `payment.fail`(GET `/payment/fail`). return 성공 시 order=paid + payment(paid, pg_tid) + 검사권 발급, 실패 시 order/payment=failed.

- [ ] **Step 1: 실패 테스트 작성**

`tests/Feature/PaymentReturnTest.php`:

```php
<?php
use App\Models\{Test, Product, Order, User, Voucher, Payment};
use App\Services\CheckoutService;

function paidOrderViaCheckout(User $u): array {
    $t = Test::create(['code'=>'PR','room'=>'univ','title_easy'=>'검사','title_pro'=>'PR','target'=>'대학생','duration_min'=>5,'item_count'=>1,'areas'=>[],'result_type'=>'signal','description'=>'d','status'=>'active']);
    $p = Product::create(['test_id'=>$t->id,'name'=>'PR 검사권','price'=>9900,'credit_qty'=>1,'valid_days'=>365,'status'=>'active']);
    $o = app(CheckoutService::class)->createOrder($u, $p);
    return [$t,$p,$o];
}

test('successful return marks order paid and issues voucher', function () {
    $u = User::factory()->create();
    [$t,$p,$o] = paidOrderViaCheckout($u);
    $this->actingAs($u)->post('/payment/return', ['order_no'=>$o->order_no,'amount'=>$o->total_amount,'result'=>'success'])
        ->assertRedirect(route('payment.complete', $o->id));
    expect($o->fresh()->status)->toBe('paid');
    expect(Payment::where('order_id',$o->id)->where('status','paid')->count())->toBe(1);
    expect(Voucher::where('user_id',$u->id)->where('test_id',$t->id)->where('status','active')->count())->toBe(1);
});

test('duplicate return does not double-issue vouchers', function () {
    $u = User::factory()->create();
    [$t,$p,$o] = paidOrderViaCheckout($u);
    $payload = ['order_no'=>$o->order_no,'amount'=>$o->total_amount,'result'=>'success'];
    $this->actingAs($u)->post('/payment/return', $payload);
    $this->actingAs($u)->post('/payment/return', $payload)->assertRedirect(route('payment.complete', $o->id));
    expect(Voucher::where('user_id',$u->id)->count())->toBe(1);
});

test('amount mismatch fails the order', function () {
    $u = User::factory()->create();
    [$t,$p,$o] = paidOrderViaCheckout($u);
    $this->actingAs($u)->post('/payment/return', ['order_no'=>$o->order_no,'amount'=>100,'result'=>'success'])
        ->assertRedirect(route('payment.fail'));
    expect($o->fresh()->status)->toBe('failed');
    expect(Voucher::count())->toBe(0);
});

test('pg failure fails the order', function () {
    $u = User::factory()->create();
    [$t,$p,$o] = paidOrderViaCheckout($u);
    $this->actingAs($u)->post('/payment/return', ['order_no'=>$o->order_no,'amount'=>$o->total_amount,'result'=>'fail'])
        ->assertRedirect(route('payment.fail'));
    expect($o->fresh()->status)->toBe('failed');
});
```

- [ ] **Step 2: 실패 확인**

Run: `php artisan test --filter=PaymentReturnTest`
Expected: FAIL — payment.return이 abort(404) 임시 라우트.

- [ ] **Step 3: PaymentController**

`app/Http/Controllers/PaymentController.php`:

```php
<?php
namespace App\Http\Controllers;

use App\Models\Order;
use App\Payments\PaymentGateway;
use App\Services\VoucherService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PaymentController extends Controller
{
    public function return(Request $request, PaymentGateway $gateway, VoucherService $vouchers)
    {
        $result = $gateway->approve($request->all());
        $order = Order::where('order_no', $result->orderNo)->first();
        if (!$order) abort(404);

        // 멱등: 이미 결제 완료된 주문이면 그대로 완료 페이지
        if ($order->status === 'paid') {
            return redirect()->route('payment.complete', $order->id);
        }

        // PG 실패 또는 금액 위변조 → 실패 처리
        if (!$result->success || $result->amount !== $order->total_amount) {
            DB::transaction(function () use ($order, $result) {
                $order->update(['status' => 'failed']);
                $order->payments()->create([
                    'provider' => $result->raw['provider'] ?? config('services.pg.driver'),
                    'method' => $result->method,
                    'amount' => $result->amount,
                    'status' => 'failed',
                    'raw_response' => $result->raw,
                ]);
            });
            return redirect()->route('payment.fail');
        }

        DB::transaction(function () use ($order, $result, $vouchers) {
            $order->payments()->create([
                'provider' => $result->raw['provider'] ?? config('services.pg.driver'),
                'method' => $result->method,
                'pg_tid' => $result->tid,
                'amount' => $result->amount,
                'status' => 'paid',
                'paid_at' => now(),
                'raw_response' => $result->raw,
            ]);
            $order->update(['status' => 'paid', 'paid_at' => now()]);
            $vouchers->issueForOrder($order);
        });

        return redirect()->route('payment.complete', $order->id);
    }

    public function complete(Order $order)
    {
        abort_unless($order->user_id === auth()->id(), 403);
        $order->load('items');
        return view('payment.complete', ['order' => $order]);
    }

    public function fail()
    {
        return view('payment.fail');
    }
}
```

- [ ] **Step 4: 라우트 교체**

`routes/web.php`: Task3의 임시 `payment.return` 라우트를 제거하고, `use App\Http\Controllers\PaymentController;` 추가 후 `auth` 그룹에 추가:

```php
Route::match(['get','post'], '/payment/return', [PaymentController::class, 'return'])->name('payment.return');
Route::middleware('auth')->group(function () {
    Route::get('/payment/complete/{order}', [PaymentController::class, 'complete'])->name('payment.complete');
    Route::get('/payment/fail', [PaymentController::class, 'fail'])->name('payment.fail');
});
```

> `payment.return`은 PG가 호출하므로 auth 그룹 밖(세션 없을 수 있음). complete/fail은 auth.

- [ ] **Step 5: 완료/실패 뷰**

`resources/views/payment/complete.blade.php`:

```blade
<x-layouts.app :title="'결제 완료'">
  <div class="bg-cream min-h-[70vh]">
    <div class="max-w-2xl mx-auto px-4 py-16 text-center">
      <div class="inline-flex h-16 w-16 items-center justify-center rounded-full bg-mint/50 text-deepgreen text-3xl">✓</div>
      <h1 class="text-2xl font-extrabold text-deepgreen mt-5">결제가 완료되었습니다</h1>
      <p class="text-navy/60 mt-2">주문번호 {{ $order->order_no }}</p>
      @php $item = $order->items->first(); @endphp
      @if($item)
        <p class="text-navy/70 mt-1">{{ $item->product_name }} · 검사권 {{ $item->credit_qty * $item->quantity }}장 발급</p>
        <div class="mt-8 flex flex-wrap justify-center gap-3">
          <a href="{{ route('catalog.show', \App\Models\Test::find($item->test_id)->code) }}" class="rounded-xl bg-deepgreen text-cream px-6 py-3 font-semibold hover:brightness-110 transition">검사 시작하기</a>
          <a href="{{ route('my.index') }}" class="rounded-xl border border-teal text-teal px-6 py-3 font-semibold hover:bg-mint/30 transition">내 검사함</a>
        </div>
      @endif
    </div>
  </div>
</x-layouts.app>
```

`resources/views/payment/fail.blade.php`:

```blade
<x-layouts.app :title="'결제 실패'">
  <div class="bg-cream min-h-[70vh]">
    <div class="max-w-2xl mx-auto px-4 py-16 text-center">
      <div class="inline-flex h-16 w-16 items-center justify-center rounded-full bg-signal-red/20 text-signal-red text-3xl">!</div>
      <h1 class="text-2xl font-extrabold text-deepgreen mt-5">결제가 완료되지 않았습니다</h1>
      <p class="text-navy/60 mt-2">결제가 취소되었거나 처리 중 문제가 발생했습니다. 다시 시도해 주세요.</p>
      <a href="{{ route('catalog.index') }}" class="inline-block mt-8 rounded-xl bg-deepgreen text-cream px-6 py-3 font-semibold hover:brightness-110 transition">검사 둘러보기</a>
    </div>
  </div>
</x-layouts.app>
```

- [ ] **Step 6: 테스트 통과 확인 + FakeGateway 회귀**

Run: `php artisan test --filter=PaymentReturnTest && php artisan test --filter=FakeGatewayTest`
Expected: 모두 PASS.

- [ ] **Step 7: Commit**

```bash
git add app/Http/Controllers/PaymentController.php resources/views/payment routes/web.php tests/Feature/PaymentReturnTest.php
git commit -m "feat: 결제 승인→검사권 발급(멱등·금액검증) + 완료/실패 화면"
```

---

## Task 6: 검사 상세/카드 — 가격 표시 + 버튼 분기

**Files:**
- Modify: `app/Http/Controllers/CatalogController.php`, `resources/views/catalog/show.blade.php`, `resources/views/components/test-card.blade.php`
- Test: `tests/Feature/CatalogButtonTest.php`

**Interfaces:**
- Consumes: Task1 `Test::isPaid()/activeProduct()`, Task2 `VoucherService::availableCount`.
- Produces: catalog.show 뷰에 `$product`(?Product), `$hasVoucher`(bool) 전달. 버튼 분기(비로그인/보유/미보유유료/무료).

- [ ] **Step 1: 실패 테스트 작성**

`tests/Feature/CatalogButtonTest.php`:

```php
<?php
use App\Models\{Test, Product, User, Voucher};

function showTest(bool $paid): Test {
    $t = Test::create(['code'=>'CB'.($paid?'P':'F'),'room'=>'univ','title_easy'=>'마음검사','title_pro'=>'CB','target'=>'대학생','duration_min'=>5,'item_count'=>1,'areas'=>['스트레스'],'result_type'=>'signal','description'=>'d','status'=>'active']);
    if ($paid) Product::create(['test_id'=>$t->id,'name'=>'CB 검사권','price'=>9900,'credit_qty'=>1,'valid_days'=>365,'status'=>'active']);
    return $t;
}

test('free test shows 검사 시작 to guest', function () {
    $t = showTest(false);
    $this->get("/tests/{$t->code}")->assertOk()->assertSee('검사 시작');
});

test('paid test shows price and 구매 to guest', function () {
    $t = showTest(true);
    $this->get("/tests/{$t->code}")->assertOk()->assertSee('9,900')->assertSee('구매');
});

test('paid test shows 검사 시작 when user owns a voucher', function () {
    $t = showTest(true);
    $u = User::factory()->create();
    Voucher::create(['user_id'=>$u->id,'test_id'=>$t->id,'status'=>'active','source'=>'purchase','issued_at'=>now(),'expires_at'=>now()->addYear()]);
    $this->actingAs($u)->get("/tests/{$t->code}")->assertOk()->assertSee('검사 시작');
});
```

- [ ] **Step 2: 실패 확인**

Run: `php artisan test --filter=CatalogButtonTest`
Expected: FAIL — show 뷰가 가격/구매 미노출.

- [ ] **Step 3: CatalogController::show 데이터 보강**

`app/Http/Controllers/CatalogController.php` 의 `show()` 교체:

```php
    public function show(string $code, \App\Services\VoucherService $vouchers)
    {
        $test = Test::where('code', $code)->firstOrFail();
        $product = $test->activeProduct();
        $hasVoucher = auth()->check() ? $vouchers->availableCount(auth()->user(), $test) > 0 : false;
        return view('catalog.show', compact('test', 'product', 'hasVoucher'));
    }
```

- [ ] **Step 4: show.blade.php 버튼 분기**

`resources/views/catalog/show.blade.php` 35행의 **검사 시작 `<a>` 한 줄만** 아래 `@if` 블록으로 교체한다. 36행의 "다른 검사 보기" `<a>`와 같은 flex 버튼 행 안에 머물도록 **감싸는 div를 추가하지 말 것**. 유료+미보유면 가격을 버튼 위가 아니라 버튼 텍스트로 노출(행 구조 유지).

교체 전(35행):

```blade
        <a href="{{ route('assessment.consent', $test->code) }}" class="rounded-xl bg-deepgreen text-cream px-8 py-3.5 font-bold shadow-lg hover:brightness-110 transition">검사 시작</a>
```

교체 후:

```blade
        @if($product && !$hasVoucher)
          @auth
            <a href="{{ route('checkout.show', $product->id) }}" class="rounded-xl bg-deepgreen text-cream px-8 py-3.5 font-bold shadow-lg hover:brightness-110 transition">{{ number_format($product->price) }}원 구매하고 응시</a>
          @else
            <a href="{{ route('login') }}" class="rounded-xl bg-deepgreen text-cream px-8 py-3.5 font-bold shadow-lg hover:brightness-110 transition">로그인하고 구매</a>
          @endauth
        @else
          <a href="{{ route('assessment.consent', $test->code) }}" class="rounded-xl bg-deepgreen text-cream px-8 py-3.5 font-bold shadow-lg hover:brightness-110 transition">검사 시작</a>
        @endif
```

> 테스트가 기대하는 문자열: 유료 미보유 시 `9,900`(number_format) + `구매`, 무료/보유 시 `검사 시작`. 가격 `number_format($product->price)`가 버튼 텍스트에 포함되므로 `assertSee('9,900')`·`assertSee('구매')` 통과.

- [ ] **Step 5: 테스트 통과 확인 + 카탈로그 회귀**

Run: `php artisan test --filter=CatalogButtonTest && php artisan test --filter=CatalogTest`
Expected: 모두 PASS.

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/CatalogController.php resources/views/catalog/show.blade.php resources/views/components/test-card.blade.php tests/Feature/CatalogButtonTest.php
git commit -m "feat: 검사 상세 가격표시+구매/응시 버튼 분기"
```

---

## Task 7: 응시 차감(start 재작성) + 보호자 동의 서버 강제

**Files:**
- Modify: `app/Http/Controllers/AssessmentController.php`
- Test: `tests/Feature/PaidAttemptTest.php`

**Interfaces:**
- Consumes: Task2 `VoucherService`, Task1 `Test::isPaid()`.
- Produces: `start()`가 유료검사는 검사권 FIFO 차감(없으면 checkout 리다이렉트, 비로그인은 login), 무료검사는 기존 그대로. `agree()`가 세션 `consent_ok:{code}=true` 세팅. 유료/보호자검사 응시 게이팅.

- [ ] **Step 1: 실패 테스트 작성**

`tests/Feature/PaidAttemptTest.php`:

```php
<?php
use App\Models\{Test, Product, User, Voucher, TestAttempt};

function paidAttemptTest(): Test {
    $t = Test::create(['code'=>'PA','room'=>'univ','title_easy'=>'검사','title_pro'=>'PA','target'=>'대학생','duration_min'=>5,'item_count'=>1,'areas'=>[],'result_type'=>'signal','description'=>'d','status'=>'active']);
    Product::create(['test_id'=>$t->id,'name'=>'PA 검사권','price'=>9900,'credit_qty'=>1,'valid_days'=>365,'status'=>'active']);
    return $t;
}

test('start on paid test without voucher redirects to checkout', function () {
    $t = paidAttemptTest();
    $u = User::factory()->create();
    $p = $t->activeProduct();
    $this->actingAs($u)->post("/assessment/{$t->code}/start")
        ->assertRedirect(route('checkout.show', $p->id));
    expect(TestAttempt::count())->toBe(0);
});

test('start on paid test as guest redirects to login', function () {
    $t = paidAttemptTest();
    $this->post("/assessment/{$t->code}/start")->assertRedirect(route('login'));
});

test('start on paid test with voucher consumes it and creates attempt', function () {
    $t = paidAttemptTest();
    $u = User::factory()->create();
    $v = Voucher::create(['user_id'=>$u->id,'test_id'=>$t->id,'status'=>'active','source'=>'purchase','issued_at'=>now(),'expires_at'=>now()->addYear()]);
    $this->actingAs($u)->post("/assessment/{$t->code}/start")->assertRedirect();
    $a = TestAttempt::where('user_id',$u->id)->first();
    expect($a)->not->toBeNull();
    expect($a->voucher_id)->toBe($v->id);
    expect($v->fresh()->status)->toBe('used');
});

test('free sample test still works for guest (regression)', function () {
    $this->seed(\Database\Seeders\SampleTestSeeder::class);
    $this->withSession(['guest_token'=>'g-x'])->post('/assessment/KMSIA-SAMPLE/start')->assertRedirect();
    expect(TestAttempt::where('guest_token','g-x')->count())->toBe(1);
});
```

- [ ] **Step 2: 실패 확인**

Run: `php artisan test --filter=PaidAttemptTest`
Expected: FAIL — start가 유료검사 게이팅 안 함.

- [ ] **Step 3: AssessmentController 재작성**

`app/Http/Controllers/AssessmentController.php` 의 `agree()`와 `start()` 교체(나머지 메서드 유지):

```php
    public function agree(Request $request, string $code)
    {
        $test = Test::where('code', $code)->firstOrFail();
        $rules = ['agree' => 'accepted'];
        if ($test->requires_guardian_consent) $rules['guardian_agree'] = 'accepted';
        $request->validate($rules);
        $request->session()->put('consent_ok:'.$code, true);
        return redirect()->route('assessment.intro', $code);
    }

    public function start(Request $request, string $code, \App\Services\VoucherService $vouchers)
    {
        $test = Test::where('code', $code)->firstOrFail();

        // 보호자 동의 검사: 동의 통과 세션 플래그 없으면 동의로
        if ($test->requires_guardian_consent && !$request->session()->get('consent_ok:'.$code)) {
            return redirect()->route('assessment.consent', $code);
        }

        $consume = null;
        if ($test->isPaid()) {
            if (!auth()->check()) return redirect()->route('login');
            $consume = $vouchers->firstActive(auth()->user(), $test);
            if (!$consume) {
                return redirect()->route('checkout.show', $test->activeProduct()->id);
            }
        }

        $attempt = TestAttempt::create(array_merge(
            $this->actorColumns($request),
            ['test_id' => $test->id, 'status' => 'in_progress', 'started_at' => now()]
        ));

        if ($consume) {
            $vouchers->consume(auth()->user(), $test, $attempt);
        }

        return redirect()->route('assessment.take', [$code, $attempt->id]);
    }
```

- [ ] **Step 4: 테스트 통과 + 회귀(기존 동의/응시)**

Run: `php artisan test --filter=PaidAttemptTest && php artisan test --filter=GuardianConsentTest && php artisan test --filter=AssessmentStartTest && php artisan test --filter=AssessmentTakeTest`
Expected: 모두 PASS.

- [ ] **Step 5: Commit**

```bash
git add app/Http/Controllers/AssessmentController.php tests/Feature/PaidAttemptTest.php
git commit -m "feat: 유료검사 검사권 FIFO 차감 + 보호자 동의 서버 강제"
```

---

## Task 8: 내 검사함 — 보유 검사권 목록

**Files:**
- Modify: `app/Http/Controllers/MyTestController.php`, `resources/views/my/index.blade.php`
- Test: `tests/Feature/MyVoucherTest.php`

**Interfaces:**
- Consumes: Task1 `Voucher`, `User::vouchers()`.
- Produces: `/my`에 보유 검사권(active) 목록 표시.

- [ ] **Step 1: 실패 테스트 작성**

`tests/Feature/MyVoucherTest.php`:

```php
<?php
use App\Models\{Test, User, Voucher};

test('my page lists active vouchers with test name', function () {
    $u = User::factory()->create();
    $t = Test::create(['code'=>'MV','room'=>'univ','title_easy'=>'내 마음검사','title_pro'=>'MV','target'=>'대학생','duration_min'=>5,'item_count'=>1,'areas'=>[],'result_type'=>'signal','description'=>'d','status'=>'active']);
    Voucher::create(['user_id'=>$u->id,'test_id'=>$t->id,'status'=>'active','source'=>'purchase','issued_at'=>now(),'expires_at'=>now()->addYear()]);
    $this->actingAs($u)->get('/my')->assertOk()->assertSee('내 마음검사')->assertSee('검사권');
});
```

- [ ] **Step 2: 실패 확인**

Run: `php artisan test --filter=MyVoucherTest`
Expected: FAIL — /my에 검사권 미표시.

- [ ] **Step 3: MyTestController 보강**

`app/Http/Controllers/MyTestController.php` 의 `index()` 에 검사권 조회 추가(기존 데이터 유지하며 병합). 현재 구현을 확인하고, 뷰에 넘기는 배열에 다음을 추가:

```php
        $vouchers = auth()->check()
            ? \App\Models\Voucher::with('test')
                ->where('user_id', auth()->id())
                ->where('status', 'active')
                ->where(function ($q) { $q->whereNull('expires_at')->orWhere('expires_at','>',now()); })
                ->orderBy('issued_at')
                ->get()
            : collect();
```

그리고 view 호출에 `'vouchers' => $vouchers` 추가.

- [ ] **Step 4: my/index.blade.php에 검사권 섹션**

`resources/views/my/index.blade.php` 상단(기존 이력 섹션 위)에 추가:

```blade
  <section class="bg-white">
    <div class="max-w-4xl mx-auto px-4 py-8">
      <h2 class="font-bold text-deepgreen mb-4">보유 검사권</h2>
      @if($vouchers->isEmpty())
        <p class="text-sm text-navy/50">보유한 검사권이 없습니다. <a href="{{ route('catalog.index') }}" class="text-teal font-semibold">검사 둘러보기</a></p>
      @else
        <ul class="divide-y divide-black/5">
          @foreach($vouchers as $v)
            <li class="flex items-center justify-between py-3">
              <span class="text-navy/80">{{ $v->test->title_easy }} <span class="text-xs text-navy/40 ml-1">검사권</span></span>
              <span class="text-xs text-navy/50">~{{ optional($v->expires_at)->format('Y.m.d') ?? '무제한' }}</span>
            </li>
          @endforeach
        </ul>
      @endif
    </div>
  </section>
```

- [ ] **Step 5: 테스트 통과 + 회귀**

Run: `php artisan test --filter=MyVoucherTest && php artisan test --filter=MyTestTest`
Expected: 모두 PASS.

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/MyTestController.php resources/views/my/index.blade.php tests/Feature/MyVoucherTest.php
git commit -m "feat: 내 검사함에 보유 검사권 목록"
```

---

## Task 9: 시더 — 유료 상품 예시 + 전체 회귀

**Files:**
- Create: `database/seeders/PaidSampleSeeder.php`
- Modify: `database/seeders/DatabaseSeeder.php`
- Test: `tests/Feature/PaidSampleSeederTest.php`

**Interfaces:**
- Consumes: Task1 모델.
- Produces: `PaidSampleSeeder`가 유료 검사 1종(code `KPAID-SAMPLE`, room `univ`) + product(price 9900) 생성. 기존 무료 `KMSIA-SAMPLE`은 건드리지 않음(회귀 방지).

- [ ] **Step 1: 실패 테스트 작성**

`tests/Feature/PaidSampleSeederTest.php`:

```php
<?php
use App\Models\Test;

test('paid sample seeder creates a paid test with product', function () {
    $this->seed(\Database\Seeders\PaidSampleSeeder::class);
    $t = Test::where('code','KPAID-SAMPLE')->first();
    expect($t)->not->toBeNull();
    expect($t->isPaid())->toBeTrue();
    expect($t->activeProduct()->price)->toBe(9900);
});
```

- [ ] **Step 2: 실패 확인**

Run: `php artisan test --filter=PaidSampleSeederTest`
Expected: FAIL — 시더 없음.

- [ ] **Step 3: PaidSampleSeeder**

`database/seeders/PaidSampleSeeder.php`:

```php
<?php
namespace Database\Seeders;
use Illuminate\Database\Seeder;
use App\Models\{Test, Product};

class PaidSampleSeeder extends Seeder
{
    public function run(): void
    {
        if (Test::where('code','KPAID-SAMPLE')->exists()) return;
        $t = Test::create([
            'code'=>'KPAID-SAMPLE','room'=>'univ',
            'title_easy'=>'대학생 마음상태 검사(유료 샘플)','title_pro'=>'KPAID Sample',
            'target'=>'대학생','duration_min'=>5,'item_count'=>10,
            'areas'=>['스트레스','우울','불안','회복탄력성'],'result_type'=>'signal',
            'description'=>'결제 흐름 시연용 유료 샘플 검사입니다.','status'=>'active',
        ]);
        // 문항은 무료 샘플과 동일 패턴(시연용)
        $items = [
            ['스트레스','요즘 사소한 일에도 쉽게 짜증이 난다',false],
            ['스트레스','해야 할 일이 너무 많아 압도되는 느낌이다',false],
            ['우울','최근 일상에서 즐거움을 느끼기 어렵다',false],
            ['우울','이유 없이 기운이 없고 무기력하다',false],
            ['우울','나 자신이 쓸모없다고 느껴질 때가 있다',false],
            ['불안','특별한 이유 없이 긴장되거나 초조하다',false],
            ['불안','걱정이 꼬리를 물어 잠들기 어렵다',false],
            ['회복탄력성','힘든 일이 있어도 곧 회복하는 편이다',true],
            ['회복탄력성','어려움을 성장의 기회로 받아들인다',true],
            ['회복탄력성','주변에 기댈 사람이 있다고 느낀다',true],
        ];
        foreach ($items as $i => [$area,$text,$rev]) {
            $t->items()->create(['no'=>$i+1,'text'=>$text,'type'=>'likert5','reverse'=>$rev,'area'=>$area]);
        }
        $t->scoringRule()->create(['rules'=>[
            'areas'=>['스트레스'=>['yellow'=>6,'red'=>8],'우울'=>['yellow'=>9,'red'=>12],'불안'=>['yellow'=>6,'red'=>8],'회복탄력성'=>['yellow'=>10,'red'=>13]],
            'interpretation'=>['green'=>'안정적입니다.','yellow'=>'관심이 필요합니다.','red'=>'전문가 상담을 권장합니다.'],
            'recommendations'=>['green'=>['유지 루틴'],'yellow'=>['스트레스 관리 4주'],'red'=>['전문가 상담']],
        ]]);
        Product::create(['test_id'=>$t->id,'name'=>'대학생 마음상태 검사권','price'=>9900,'credit_qty'=>1,'valid_days'=>365,'status'=>'active']);
    }
}
```

- [ ] **Step 4: DatabaseSeeder 등록**

`database/seeders/DatabaseSeeder.php` 의 `run()` 에서 기존 `SampleTestSeeder` 호출 옆에 추가:

```php
        $this->call([
            SampleTestSeeder::class,
            PaidSampleSeeder::class,
        ]);
```

(기존 호출 형태에 맞춰 `$this->call(PaidSampleSeeder::class);` 한 줄 추가도 가능. 기존 코드 형태를 따른다.)

- [ ] **Step 5: 테스트 통과 + 전체 회귀**

Run: `php artisan test`
Expected: 전체 PASS(기존 + 신규). 1 skip(기존 Kakao)만 허용.

- [ ] **Step 6: Commit**

```bash
git add database/seeders/PaidSampleSeeder.php database/seeders/DatabaseSeeder.php tests/Feature/PaidSampleSeederTest.php
git commit -m "feat: 유료 샘플 검사 시더(결제 흐름 시연용)"
```

---

## 최종 검증

- [ ] `php artisan test` 전체 PASS(1 skip 허용).
- [ ] `php artisan migrate:fresh --seed` 후 수동 흐름: 로그인 → `/tests/KPAID-SAMPLE` → "구매하고 응시" → 결제확인 → (테스트)결제완료 → 검사권 발급 → 검사 시작 → 응시 → 결과. `/my`에 검사권 소멸 확인.
- [ ] 무료 `KMSIA-SAMPLE`은 비로그인 게스트로 그대로 응시 가능(회귀).
- [ ] `npm run build`(뷰 신규 클래스 반영).

## 이 플랜 밖 — 향후 분리 태스크

- **InicisGateway 실구현**(KG이니시스 INIStdPay 어댑터): 단디 INICIS PHP 라이브러리 + 테스트 MID 필요. `PaymentGateway` 인터페이스에 그대로 끼움. 자동화 테스트 불가(수동 검증). `config('services.pg.driver')='inicis'`로 전환.
- 기관 B2B, 장바구니, 포인트·추천정산, 강의코칭 결제, 만료 cron, 관리자 환불·매출 UI.

## Self-Review 결과

- 스펙 커버리지: 6테이블+voucher_id(Task1), VoucherService FIFO·멱등(Task2), PG 인터페이스+Fake(Task3), 결제확인·주문(Task4), 승인·발급·멱등·금액검증(Task5), 버튼분기·가격(Task6), 차감·보호자동의(Task7), 내검사함(Task8), 시더(Task9). 환불은 스키마 지원(payments.status='refunded')·UI는 비범위로 명시. 만료 cron 비범위(조회필터). 전부 매핑됨.
- placeholder: 없음(신고의무 문구는 Task7 범위 밖, 기존 유지). InicisGateway는 의도적 분리(플랜 밖 명시).
- 타입 일관성: `issueForOrder/consume/firstActive/availableCount`, `PaymentResult(success,orderNo,amount,tid,method,raw)`, `createOrder`, order_items에 `valid_days` 포함 — 태스크 간 일치 확인.
