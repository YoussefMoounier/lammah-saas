<?php
/**
 * Plugin Name: Lammah Connector
 * Description: Sends WooCommerce orders, products, and customers to Lammah SaaS.
 * Version: 0.1.0
 * Author: Lammah SaaS
 * Requires PHP: 8.0
 * Requires at least: 6.0
 * WC requires at least: 7.0
 */

if (! defined('ABSPATH')) {
    exit;
}

const LAMMAH_CONNECTOR_VERSION = '0.1.0';
const LAMMAH_CONNECTOR_OPTION = 'lammah_connector_settings';

add_action('admin_menu', 'lammah_connector_register_menu');
add_action('admin_init', 'lammah_connector_handle_admin_actions');
add_action('woocommerce_new_order', 'lammah_connector_send_order_event', 10, 1);
add_action('woocommerce_update_order', 'lammah_connector_send_order_event', 10, 1);
add_action('woocommerce_order_status_changed', 'lammah_connector_send_order_event', 10, 1);

function lammah_connector_register_menu(): void
{
    $parent = class_exists('WooCommerce') ? 'woocommerce' : 'options-general.php';

    add_submenu_page(
        $parent,
        'Lammah Connector',
        'Lammah Connector',
        'manage_options',
        'lammah-connector',
        'lammah_connector_render_settings_page'
    );
}

function lammah_connector_handle_admin_actions(): void
{
    if (! is_admin() || ! current_user_can('manage_options')) {
        return;
    }

    if (empty($_POST['lammah_connector_action'])) {
        return;
    }

    check_admin_referer('lammah_connector_settings');

    $settings = lammah_connector_settings();
    $settings['api_url'] = esc_url_raw(rtrim((string) ($_POST['api_url'] ?? ''), '/'));
    $settings['store_id'] = sanitize_text_field((string) ($_POST['store_id'] ?? ''));
    $settings['token'] = sanitize_text_field((string) ($_POST['token'] ?? ''));
    $settings['batch_size'] = max(5, min(100, (int) ($_POST['batch_size'] ?? 25)));
    update_option(LAMMAH_CONNECTOR_OPTION, $settings, false);

    $action = sanitize_key((string) $_POST['lammah_connector_action']);

    if ($action === 'connect') {
        $result = lammah_connector_handshake();
    } elseif ($action === 'sync') {
        $result = lammah_connector_sync_now();
    } else {
        $result = ['ok' => true, 'message' => 'Settings saved.'];
    }

    set_transient('lammah_connector_notice', $result, 60);
    wp_safe_redirect(admin_url('admin.php?page=lammah-connector'));
    exit;
}

function lammah_connector_render_settings_page(): void
{
    $settings = lammah_connector_settings();
    $notice = get_transient('lammah_connector_notice');
    delete_transient('lammah_connector_notice');
    ?>
    <div class="wrap">
        <h1>Lammah Connector</h1>
        <p>Connect this WooCommerce store to your Lammah SaaS dashboard.</p>

        <?php if (! class_exists('WooCommerce')) : ?>
            <div class="notice notice-error"><p>WooCommerce is not active. Activate WooCommerce before syncing store data.</p></div>
        <?php endif; ?>

        <?php if (is_array($notice)) : ?>
            <div class="notice <?php echo ! empty($notice['ok']) ? 'notice-success' : 'notice-error'; ?>">
                <p><?php echo esc_html((string) ($notice['message'] ?? 'Action finished.')); ?></p>
            </div>
        <?php endif; ?>

        <form method="post">
            <?php wp_nonce_field('lammah_connector_settings'); ?>
            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row"><label for="api_url">Laravel API URL</label></th>
                    <td><input class="regular-text" id="api_url" name="api_url" type="url" value="<?php echo esc_attr($settings['api_url']); ?>" placeholder="https://lammah-saas-production.up.railway.app" required></td>
                </tr>
                <tr>
                    <th scope="row"><label for="store_id">Store ULID</label></th>
                    <td><input class="regular-text" id="store_id" name="store_id" type="text" value="<?php echo esc_attr($settings['store_id']); ?>" required></td>
                </tr>
                <tr>
                    <th scope="row"><label for="token">Connector Token</label></th>
                    <td><input class="regular-text" id="token" name="token" type="password" value="<?php echo esc_attr($settings['token']); ?>" required></td>
                </tr>
                <tr>
                    <th scope="row"><label for="batch_size">Batch size</label></th>
                    <td><input id="batch_size" name="batch_size" type="number" min="5" max="100" value="<?php echo esc_attr((string) $settings['batch_size']); ?>"></td>
                </tr>
            </table>

            <p class="submit">
                <button class="button button-primary" name="lammah_connector_action" value="connect" type="submit">Connect</button>
                <button class="button" name="lammah_connector_action" value="sync" type="submit">Sync Now</button>
                <button class="button" name="lammah_connector_action" value="save" type="submit">Save Settings</button>
            </p>
        </form>
    </div>
    <?php
}

function lammah_connector_settings(): array
{
    $settings = get_option(LAMMAH_CONNECTOR_OPTION, []);

    return wp_parse_args(is_array($settings) ? $settings : [], [
        'api_url' => '',
        'store_id' => '',
        'token' => '',
        'batch_size' => 25,
    ]);
}

function lammah_connector_configured(): bool
{
    $settings = lammah_connector_settings();

    return $settings['api_url'] !== '' && $settings['store_id'] !== '' && $settings['token'] !== '';
}

function lammah_connector_handshake(): array
{
    if (! lammah_connector_configured()) {
        return ['ok' => false, 'message' => 'Missing API URL, Store ULID, or Connector Token.'];
    }

    $response = lammah_connector_request('handshake', [
        'site_url' => home_url(),
        'plugin_version' => LAMMAH_CONNECTOR_VERSION,
        'wordpress_version' => get_bloginfo('version'),
        'woocommerce_version' => defined('WC_VERSION') ? WC_VERSION : null,
    ]);

    return $response['ok']
        ? ['ok' => true, 'message' => 'Connected to Lammah successfully.']
        : ['ok' => false, 'message' => $response['message']];
}

function lammah_connector_sync_now(): array
{
    if (! class_exists('WooCommerce')) {
        return ['ok' => false, 'message' => 'WooCommerce is not active.'];
    }

    $products = lammah_connector_sync_products();
    $customers = lammah_connector_sync_customers();
    $orders = lammah_connector_sync_orders();
    $ok = $products['ok'] && $customers['ok'] && $orders['ok'];

    return [
        'ok' => $ok,
        'message' => sprintf(
            'Sync finished. Products: %d, Customers: %d, Orders: %d%s',
            $products['synced'],
            $customers['synced'],
            $orders['synced'],
            $ok ? '' : '. Some batches failed; check settings and retry.'
        ),
    ];
}

function lammah_connector_sync_products(): array
{
    $settings = lammah_connector_settings();
    $page = 1;
    $synced = 0;
    $ok = true;

    do {
        $products = wc_get_products([
            'limit' => (int) $settings['batch_size'],
            'page' => $page,
            'paginate' => false,
            'status' => ['publish', 'draft', 'private', 'pending'],
            'return' => 'objects',
        ]);

        $items = array_map('lammah_connector_product_payload', $products);
        $result = $items === [] ? ['ok' => true] : lammah_connector_request('bulk/products', ['items' => $items]);
        $ok = $ok && $result['ok'];
        $synced += count($items);
        $page++;
    } while (count($products) === (int) $settings['batch_size'] && $page <= 20);

    return ['ok' => $ok, 'synced' => $synced];
}

function lammah_connector_sync_customers(): array
{
    if (! class_exists('WC_Customer_Query')) {
        return ['ok' => true, 'synced' => 0];
    }

    $settings = lammah_connector_settings();
    $page = 1;
    $synced = 0;
    $ok = true;

    do {
        $query = new WC_Customer_Query([
            'limit' => (int) $settings['batch_size'],
            'paged' => $page,
        ]);
        $customers = $query->get_customers();
        $items = array_map('lammah_connector_customer_payload', $customers);
        $result = $items === [] ? ['ok' => true] : lammah_connector_request('bulk/customers', ['items' => $items]);
        $ok = $ok && $result['ok'];
        $synced += count($items);
        $page++;
    } while (count($customers) === (int) $settings['batch_size'] && $page <= 20);

    return ['ok' => $ok, 'synced' => $synced];
}

function lammah_connector_sync_orders(): array
{
    $settings = lammah_connector_settings();
    $page = 1;
    $synced = 0;
    $ok = true;

    do {
        $orders = wc_get_orders([
            'limit' => (int) $settings['batch_size'],
            'page' => $page,
            'paginate' => false,
            'status' => array_keys(wc_get_order_statuses()),
            'return' => 'objects',
        ]);

        $items = array_map('lammah_connector_order_payload', $orders);
        $result = $items === [] ? ['ok' => true] : lammah_connector_request('bulk/orders', ['items' => $items]);
        $ok = $ok && $result['ok'];
        $synced += count($items);
        $page++;
    } while (count($orders) === (int) $settings['batch_size'] && $page <= 20);

    return ['ok' => $ok, 'synced' => $synced];
}

function lammah_connector_send_order_event(int $order_id): void
{
    if (! lammah_connector_configured() || ! function_exists('wc_get_order')) {
        return;
    }

    $order = wc_get_order($order_id);

    if (! $order) {
        return;
    }

    lammah_connector_request('events/order', [
        'event_id' => sprintf('%d:%s:%d', $order_id, $order->get_status(), time()),
        'topic' => 'order.updated',
        'order' => lammah_connector_order_payload($order),
    ]);
}

function lammah_connector_request(string $path, array $body): array
{
    $settings = lammah_connector_settings();
    $url = sprintf(
        '%s/api/v1/connectors/stores/%s/%s',
        rtrim((string) $settings['api_url'], '/'),
        rawurlencode((string) $settings['store_id']),
        ltrim($path, '/')
    );

    $response = wp_remote_post($url, [
        'timeout' => 20,
        'headers' => [
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
            'Authorization' => 'Bearer ' . $settings['token'],
        ],
        'body' => wp_json_encode($body),
    ]);

    if (is_wp_error($response)) {
        return ['ok' => false, 'message' => $response->get_error_message()];
    }

    $status = (int) wp_remote_retrieve_response_code($response);
    $payload = json_decode((string) wp_remote_retrieve_body($response), true);

    if ($status < 200 || $status >= 300) {
        return ['ok' => false, 'message' => is_array($payload) ? (string) ($payload['message'] ?? 'Lammah API request failed.') : 'Lammah API request failed.'];
    }

    return ['ok' => true, 'payload' => is_array($payload) ? $payload : []];
}

function lammah_connector_product_payload(WC_Product $product): array
{
    return [
        'id' => $product->get_id(),
        'sku' => $product->get_sku(),
        'name' => $product->get_name(),
        'slug' => $product->get_slug(),
        'type' => $product->get_type(),
        'status' => $product->get_status(),
        'regular_price' => $product->get_regular_price(),
        'sale_price' => $product->get_sale_price(),
        'price' => $product->get_price(),
        'stock_status' => $product->get_stock_status(),
        'stock_quantity' => $product->get_stock_quantity(),
        'manage_stock' => $product->get_manage_stock(),
        'virtual' => $product->is_virtual(),
        'downloadable' => $product->is_downloadable(),
        'categories' => array_map(fn ($id): array => ['id' => $id], $product->get_category_ids()),
        'attributes' => [],
    ];
}

function lammah_connector_customer_payload(WC_Customer $customer): array
{
    return [
        'id' => $customer->get_id(),
        'email' => $customer->get_email(),
        'first_name' => $customer->get_first_name(),
        'last_name' => $customer->get_last_name(),
        'username' => $customer->get_username(),
        'date_created' => lammah_connector_date($customer->get_date_created()),
        'billing' => [
            'email' => $customer->get_billing_email(),
            'phone' => $customer->get_billing_phone(),
            'first_name' => $customer->get_billing_first_name(),
            'last_name' => $customer->get_billing_last_name(),
        ],
    ];
}

function lammah_connector_order_payload(WC_Order $order): array
{
    $line_items = [];

    foreach ($order->get_items('line_item') as $item) {
        $product = $item->get_product();
        $line_items[] = [
            'id' => $item->get_id(),
            'product_id' => $item->get_product_id(),
            'variation_id' => $item->get_variation_id(),
            'name' => $item->get_name(),
            'sku' => $product ? $product->get_sku() : null,
            'quantity' => $item->get_quantity(),
            'subtotal' => $item->get_subtotal(),
            'total' => $item->get_total(),
            'total_tax' => $item->get_total_tax(),
            'meta_data' => [],
        ];
    }

    return [
        'id' => $order->get_id(),
        'order_key' => $order->get_order_key(),
        'status' => $order->get_status(),
        'currency' => $order->get_currency(),
        'discount_total' => $order->get_discount_total(),
        'shipping_total' => $order->get_shipping_total(),
        'total_tax' => $order->get_total_tax(),
        'total' => $order->get_total(),
        'payment_method' => $order->get_payment_method(),
        'payment_method_title' => $order->get_payment_method_title(),
        'transaction_id' => $order->get_transaction_id(),
        'customer_id' => $order->get_customer_id(),
        'customer_ip_address' => $order->get_customer_ip_address(),
        'date_created' => lammah_connector_date($order->get_date_created()),
        'date_created_gmt' => lammah_connector_date($order->get_date_created(), true),
        'date_paid' => lammah_connector_date($order->get_date_paid()),
        'date_completed' => lammah_connector_date($order->get_date_completed()),
        'billing' => [
            'email' => $order->get_billing_email(),
            'phone' => $order->get_billing_phone(),
            'first_name' => $order->get_billing_first_name(),
            'last_name' => $order->get_billing_last_name(),
            'country' => $order->get_billing_country(),
        ],
        'shipping' => [
            'first_name' => $order->get_shipping_first_name(),
            'last_name' => $order->get_shipping_last_name(),
            'country' => $order->get_shipping_country(),
        ],
        'line_items' => $line_items,
        'fee_lines' => [],
        'refunds' => [],
    ];
}

function lammah_connector_date(?WC_DateTime $date, bool $gmt = false): ?string
{
    if (! $date) {
        return null;
    }

    if ($gmt) {
        $date = clone $date;
        $date->setTimezone(new DateTimeZone('UTC'));
    }

    return $date->date('Y-m-d\TH:i:s');
}
