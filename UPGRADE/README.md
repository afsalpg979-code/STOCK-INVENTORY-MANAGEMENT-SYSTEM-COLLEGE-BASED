# College Inventory Control Upgrade

This module adds a college-style inventory transaction workflow around the existing `principal_stock` and `hod_stock` tables.

## Workflow

`STOCK IN → Principal BOH → Movement → HOD FOH`

`HOD low/empty → Replenishment Request → Find Product in Principal BOH → Movement → HOD FOH`

`Principal/HOD → Outward → Other College/External Destination`

## Product identification

Each product can use:
- Product Code
- Barcode
- Serial Number
- QR value
- RFID UID

The identifiers point to the same product record; stock quantities remain in the existing stock tables.

## Installation

From the project root, import:

```bash
mariadb -h 127.0.0.1 -u root inventory < 'UPDATED SQL/../UPGRADE/college_inventory_upgrade.sql'
```

Or, if the shell path is easier:

```bash
mariadb -h 127.0.0.1 -u root inventory < UPGRADE/college_inventory_upgrade.sql
```

Run the module:

```bash
cd UPGRADE
php -d opcache.enable=0 -d opcache.enable_cli=0 -d opcache.file_cache="" -S 127.0.0.1:8083 -t .
```

Open `http://127.0.0.1:8083`.

For LAN/ESP32 testing:

```bash
php -d opcache.enable=0 -d opcache.enable_cli=0 -d opcache.file_cache="" -S 0.0.0.0:8083 -t .
```

## Safety

Back up the database before importing the migration. The upgrade uses transactions for stock-changing operations and rejects movement/outward quantities greater than available stock.

For production deployment, connect this module to the existing application's authentication/authorization layer rather than exposing the upgrade page publicly.
