<?php

namespace Tests\Feature;

use App\Models\AdminAuditLog;
use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductStorePrice;
use App\Models\Store;
use App\Models\Tenant;
use App\Models\TenantCustomer;
use App\Models\TenantDataImportRun;
use App\Models\TenantDefaultSetting;
use App\Models\TenantPaymentMethod;
use App\Models\TenantSupplier;
use App\Models\User;
use App\Services\DataImport\ImportObservabilityBridgeService;
use App\Services\DataImport\ImportRollbackService;
use App\Services\DataImport\ImportSupportBridgeService;
use App\Services\DataImport\TenantDataImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class Sprint37TenantDataImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_category_product_supplier_customer_price_payment_and_stock_imports_are_dry_run_then_execute_idempotent(): void
    {
        [$tenant, $store, $admin] = $this->fixture();
        $service = app(TenantDataImportService::class);

        $categoryFile = $this->csv('category', 'name,sort_order'.PHP_EOL.'Beverage,1'.PHP_EOL);
        $dryRun = $service->validateFile($tenant, 'category', $categoryFile, $admin, null, 'cat-1');
        $this->assertSame('validated', $dryRun->status);
        $this->assertSame(0, ProductCategory::count());

        $executed = $service->executeFile($tenant, 'category', $categoryFile, $admin, null, 'cat-1-exec', 'bootstrap');
        $this->assertSame('completed', $executed->status);
        $this->assertSame(1, ProductCategory::where('tenant_id', $tenant->id)->count());

        $service->executeFile($tenant, 'category', $categoryFile, $admin, null, 'cat-1-exec', 'bootstrap');
        $this->assertSame(1, ProductCategory::where('tenant_id', $tenant->id)->count());

        $productFile = $this->csv('product', 'sku,name,category,selling_price,cost_price,unit'.PHP_EOL.'SKU-1,Tea,Beverage,12000,8000,cup'.PHP_EOL);
        $service->executeFile($tenant, 'product', $productFile, $admin, null, 'prod-1', 'bootstrap');
        $this->assertSame(1, Product::where('tenant_id', $tenant->id)->where('sku', 'SKU-1')->count());

        $supplierFile = $this->csv('supplier', 'code,name,email,phone,address'.PHP_EOL.'SUP-1,Acme,a@example.test,081234,Hidden'.PHP_EOL);
        $supplierRun = $service->executeFile($tenant, 'supplier', $supplierFile, $admin, null, 'sup-1', 'bootstrap');
        $this->assertSame(1, TenantSupplier::where('tenant_id', $tenant->id)->count());
        $this->assertStringNotContainsString('a@example.test', json_encode($supplierRun->rows()->first()->toArray()));

        $customerFile = $this->csv('customer', 'code,name,email,phone,address'.PHP_EOL.'CUS-1,Jane,jane@example.test,08999,Hidden'.PHP_EOL);
        $service->executeFile($tenant, 'customer', $customerFile, $admin, null, 'cus-1', 'bootstrap');
        $this->assertSame(1, TenantCustomer::where('tenant_id', $tenant->id)->count());

        $stockFile = $this->csv('initial_stock', 'sku,store_code,qty'.PHP_EOL.'SKU-1,'.$store->code.',5'.PHP_EOL);
        $service->executeFile($tenant, 'initial_stock', $stockFile, $admin, $store->id, 'stock-1', 'bootstrap');
        $this->assertEqualsWithDelta(5.0, (float) InventoryMovement::where('tenant_id', $tenant->id)->where('product_id', Product::where('sku', 'SKU-1')->value('id'))->sum('signed_qty'), 0.001);
        $service->executeFile($tenant, 'initial_stock', $stockFile, $admin, $store->id, 'stock-1', 'bootstrap');
        $this->assertSame(1, InventoryMovement::where('tenant_id', $tenant->id)->where('movement_type', InventoryMovement::TYPE_OPENING)->count());

        $priceFile = $this->csv('price', 'sku,store_code,selling_price'.PHP_EOL.'SKU-1,'.$store->code.',13000'.PHP_EOL);
        $service->executeFile($tenant, 'price', $priceFile, $admin, $store->id, 'price-1', 'bootstrap');
        $this->assertSame(1, ProductStorePrice::where('tenant_id', $tenant->id)->count());

        $paymentFile = $this->csv('payment_method', 'code,name,method_type,is_default'.PHP_EOL.'CASH,Cash,cash,1'.PHP_EOL);
        $service->executeFile($tenant, 'payment_method', $paymentFile, $admin, null, 'pay-1', 'bootstrap');
        $this->assertSame(1, TenantPaymentMethod::where('tenant_id', $tenant->id)->count());

        $settingsFile = $this->csv('default_settings', 'setting_key,setting_value'.PHP_EOL.'receipt_footer,Thank you'.PHP_EOL);
        $service->executeFile($tenant, 'default_settings', $settingsFile, $admin, null, 'set-1', 'bootstrap');
        $this->assertSame(1, TenantDefaultSetting::where('tenant_id', $tenant->id)->count());

        $this->assertGreaterThan(0, AdminAuditLog::where('action', 'IMPORT_EXECUTED')->count());
    }

    public function test_validation_failure_is_auditable_redacted_and_observable(): void
    {
        [$tenant, , $admin] = $this->fixture();
        $run = app(TenantDataImportService::class)->executeFile(
            $tenant,
            'customer',
            $this->csv('customer-invalid', 'code,name,email'.PHP_EOL.'CUS-2,,secret@example.test'.PHP_EOL),
            $admin,
            null,
            'invalid-customer',
            'bootstrap',
        );

        $this->assertSame('failed', $run->status);
        $this->assertSame(1, $run->invalid_rows);
        $this->assertStringNotContainsString('secret@example.test', json_encode($run->rows()->first()->toArray()));
        $this->assertSame('WARN', app(ImportObservabilityBridgeService::class)->signals()[0]['status']);
        $this->assertNotEmpty(app(ImportSupportBridgeService::class)->summaryForTenant($tenant));
    }

    public function test_rollback_affects_only_import_created_records(): void
    {
        [$tenant, , $admin] = $this->fixture();
        ProductCategory::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Existing']);

        $run = app(TenantDataImportService::class)->executeFile(
            $tenant,
            'category',
            $this->csv('category-rollback', 'name'.PHP_EOL.'Rollback Me'.PHP_EOL),
            $admin,
            null,
            'rollback-category',
            'bootstrap',
        );

        $dryRun = app(ImportRollbackService::class)->rollback($run, $admin, false);
        $this->assertSame(1, $dryRun['rollbackable_rows']);
        app(ImportRollbackService::class)->rollback($run, $admin, true, 'bootstrap');

        $this->assertSame(1, ProductCategory::where('tenant_id', $tenant->id)->count());
        $this->assertSame('Existing', ProductCategory::where('tenant_id', $tenant->id)->value('name'));
        $this->assertSame('rolled_back', $run->fresh()->status);
    }

    public function test_commands_cover_validate_execute_summary_rows_and_rollback(): void
    {
        [$tenant, , $admin] = $this->fixture();
        $file = $this->csv('command-category', 'name'.PHP_EOL.'Command Cat'.PHP_EOL);

        $this->assertSame(0, Artisan::call('import:validate', ['--tenant' => $tenant->id, '--type' => 'category', '--file' => $file]));
        $this->assertSame(0, ProductCategory::count());

        $this->assertSame(0, Artisan::call('import:execute', ['--tenant' => $tenant->id, '--type' => 'category', '--file' => $file, '--idempotency-key' => 'cmd-cat', '--reason' => 'bootstrap', '--execute' => true, '--actor' => $admin->id]));
        $run = TenantDataImportRun::where('import_type', 'category')->where('mode', 'execute')->firstOrFail();

        $this->assertSame(0, Artisan::call('import:summary', ['--tenant' => $tenant->id]));
        $this->assertSame(0, Artisan::call('import:rows', ['--run' => $run->id]));
        $this->assertSame(0, Artisan::call('import:rollback', ['--run' => $run->id, '--execute' => true, '--reason' => 'bootstrap', '--actor' => $admin->id]));
    }

    private function fixture(): array
    {
        $tenant = Tenant::factory()->create();
        $store = Store::factory()->create(['tenant_id' => $tenant->id, 'code' => 'MAIN']);
        $admin = User::factory()->platformAdmin()->create();

        return [$tenant, $store, $admin];
    }

    private function csv(string $name, string $contents): string
    {
        $path = sys_get_temp_dir().'/'.$name.'-'.uniqid().'.csv';
        file_put_contents($path, $contents);

        return $path;
    }
}
