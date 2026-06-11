# Lammah Connector

WordPress plugin that pushes WooCommerce data to Lammah SaaS.

## Install

1. Zip the `lammah-connector` folder.
2. In WordPress, go to Plugins -> Add New -> Upload Plugin.
3. Activate `Lammah Connector`.
4. Open WooCommerce -> Lammah Connector.

## Configure

Paste:

- Laravel API URL, for example `https://lammah-saas-production.up.railway.app`
- Store ULID from Lammah dashboard
- Connector Token generated once from Lammah dashboard

Click `Connect`, then `Sync Now`.

## Data Flow

The plugin sends data only to Laravel:

- products -> `/api/v1/connectors/stores/{store}/bulk/products`
- customers -> `/api/v1/connectors/stores/{store}/bulk/customers`
- orders -> `/api/v1/connectors/stores/{store}/bulk/orders`
- order events -> `/api/v1/connectors/stores/{store}/events/order`

The Vercel dashboard should continue reading from Laravel only.
