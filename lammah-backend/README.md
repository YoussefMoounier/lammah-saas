# Lammah SaaS Backend - Phase 1-4 Backend Foundation

This folder contains Laravel-ready database and WooCommerce synchronization assets for Lammah SaaS.

## Included

### Phase 1

- Multi-tenant merchant and WooCommerce store schema
- Sanctum-compatible `personal_access_tokens` table using ULID morphs
- Spatie-style RBAC tables with merchant/team scoping
- Normalized WooCommerce customer, product, category, order, item, webhook, and sync-run tables
- IPTV delivery, subscriptions, shifts, audit, and bulk price update tables
- Finance/profit/runway tables
- AI metric cache tables for Laravel-native analytics now and FastAPI workers later
- Default role/permission seeder
- Feature migration tests for the schema contract

### Phase 2

- WooCommerce REST client using Laravel HTTP, Basic Auth, retries, timeouts, and typed API exceptions
- Connection test service for validating store credentials before sync/webhook setup
- Eloquent models with encrypted casts for store secrets and customer/order PII
- Repository-based sync writes for customers, categories, products, orders, order items, sync runs, and webhook events
- Queue jobs for full-store sync, single-resource sync, webhook registration, and webhook processing
- HMAC webhook signature verification using WooCommerce `X-WC-Webhook-Signature`
- Webhook route/controller that persists incoming events and dispatches async processing

### Phase 3

- Native PHP linear regression for next-month sales, net profit, and order-count forecasting
- Net profit calculator and order snapshot service using product base costs, payment gateway fees, refunds, shipping, and taxes
- Fraud/Test-Account Radar using deterministic anomaly scoring for order velocity, shared IPs, trial abuse, and suspicious email patterns
- RFM churn scoring with customer segmentation, subscription-expiry signals, and churn probability
- Analytics queue jobs for forecasts, profit snapshots, fraud scans, RFM scoring, and full-store analytics refreshes

### Phase 4

- Sanctum-protected `/api/v1` REST API routes for Vue and Flutter clients
- Merchant membership and role/permission enforcement for every protected controller
- API resources for forecasts, fraud signals, net profit snapshots, RFM scores, shifts, and price update batches
- Dashboard summary endpoint for gross revenue, net profit, fraud, churn, active shifts, and queued price actions
- Staff shift start/close endpoints
- Bulk price update endpoints with queued WooCommerce price application

## Install Notes

Copy `app`, `config`, `database`, `routes`, and `tests` into a Laravel 11/12 API project configured for PostgreSQL.

Required packages for the intended app shell:

```bash
composer require laravel/sanctum spatie/laravel-permission
```

Register `App\Providers\WooCommerceServiceProvider::class` if your Laravel version does not auto-discover application providers.

Run:

```bash
php artisan migrate:fresh --seed
php artisan test --filter=PhaseOneSchemaTest
php artisan test --filter=WooCommerceWebhookVerifierTest
php artisan test --filter=WooCommerceRestClientTest
php artisan test --filter=LinearRegressionTest
php artisan test --filter=NetProfitCalculatorTest
php artisan test --filter=FraudRiskScorerTest
php artisan test --filter=RfmScorerTest
php artisan test --filter=PriceAdjustmentCalculatorTest
```

Recommended queue workers:

```bash
php artisan queue:work --queue=woocommerce-webhooks,woocommerce-sync,analytics,default
```

Webhook delivery URL format:

```text
https://your-api-domain.com/api/webhooks/woocommerce/{store_ulid}
```

Dispatch examples:

```php
SyncWooCommerceStoreJob::dispatch($store->id);
RegisterWooCommerceWebhooksJob::dispatch($store->id);
RunStoreAnalyticsJob::dispatch($store->id);
CalculateOrderNetProfitJob::dispatch($order->id);
```

Protected API examples:

```text
GET    /api/v1/merchants/{merchant}/dashboard/summary
GET    /api/v1/merchants/{merchant}/stores/{store}/forecasts
POST   /api/v1/merchants/{merchant}/stores/{store}/forecasts/generate
GET    /api/v1/merchants/{merchant}/stores/{store}/fraud-signals
POST   /api/v1/merchants/{merchant}/stores/{store}/fraud-signals/scan
PATCH  /api/v1/merchants/{merchant}/fraud-signals/{signal}
GET    /api/v1/merchants/{merchant}/stores/{store}/rfm-scores
POST   /api/v1/merchants/{merchant}/stores/{store}/rfm-scores/refresh
GET    /api/v1/merchants/{merchant}/stores/{store}/profits
POST   /api/v1/merchants/{merchant}/orders/{order}/profit/recalculate
GET    /api/v1/merchants/{merchant}/shifts
POST   /api/v1/merchants/{merchant}/shifts/start
PATCH  /api/v1/merchants/{merchant}/shifts/{shift}/close
GET    /api/v1/merchants/{merchant}/stores/{store}/price-updates
POST   /api/v1/merchants/{merchant}/stores/{store}/price-updates
GET    /api/v1/merchants/{merchant}/price-updates/{batch}
```

Encrypted columns are named with an `_encrypted` suffix and are cast in the Phase 2 models.
