# Stock Inventory Management System — Polytechnic College Based

A web-based inventory management system for a polytechnic/college environment.

## Core stock model

The upgraded inventory flow treats:

- **BOH (Back of House)** = Principal / Central stock
- **FOH (Front of House)** = Department / Other operational stocks

The key stock rule is:

```text
BOH → FOH

BOH quantity = BOH quantity - moved quantity
FOH quantity = FOH quantity + moved quantity
Movement time and audit information are saved.
```

RFID is used as the physical-item identification layer for this workflow.

## Features

- Main administrator / principal management
- Department administration
- Student and teacher management
- Stock management and supply/order workflows
- BOH (Principal) to FOH (department/other) stock movement
- Movement history with timestamp, quantity, source and destination
- Issue and complaint reporting
- Department-wise reports
- MariaDB/MySQL database support
- RFID inventory integration (ESP32 + RC522)
- RFID tag registration and status control
- RFID scan history and unknown/blocked tag handling
- RFID BOH → FOH movement API and dashboard

## RFID + BOH/FOH flow

```text
RFID Tag
   ↓
RC522 Reader
   ↓ SPI
ESP32
   ↓ Wi-Fi / HTTP JSON
PHP RFID API
   ↓
MariaDB
   ↓
Identify Item
   ↓
Check BOH Stock
   ↓
BOH → FOH Movement
   ↓
BOH decreases + FOH receives quantity
   ↓
Movement timestamp + audit log
   ↓
Dashboard / Reports
```

## Requirements

- PHP 8.x
- MySQL/MariaDB
- Apache or PHP development server
- PHP `mysqli` extension
- ESP32 + RC522 for RFID hardware

## Run with XAMPP

1. Copy the application folder into `C:\xampp\htdocs\`.
2. Start Apache and MySQL in XAMPP.
3. Create a database named `inventory`.
4. Import `UPDATED SQL/inventory.sql`.
5. Apply `UPDATED SQL/rfid_boh_foh_movement.sql`.
6. Open the project through `http://localhost/<project-folder>/`.

## Run with Termux

From the project directory:

```bash
php -d opcache.enable=0 -d opcache.enable_cli=0 -d opcache.file_cache="" -S 127.0.0.1:8082 -t .
```

Then open:

`http://127.0.0.1:8082/`

For ESP32/RFID testing over the same Wi-Fi network, bind the development server to `0.0.0.0:8082` and use the phone's LAN IP in the ESP32 API URL.

## RFID BOH → FOH endpoints

RFID API:

`RFID/api/rfid_api.php`

BOH → FOH movement API:

`RFID/api/boh_foh_move.php`

Movement dashboard:

`RFID/admin/boh_foh.php`

The BOH → FOH API validates the RFID tag, verifies that it is active and currently in BOH, rejects movements larger than the BOH quantity, records the movement timestamp, and writes an audit/scan record.

## Database

Database name:

`inventory`

Import the main schema:

`UPDATED SQL/inventory.sql`

Then apply the RFID BOH/FOH migration:

`UPDATED SQL/rfid_boh_foh_movement.sql`

The migration adds RFID stock-zone information and the `rfid_boh_foh_movements` audit table.

## Login / Security

**Do not publish real usernames, passwords, API keys, SMTP credentials, or database credentials in this repository.**

Login credentials should be configured in the local database/application environment. The RFID API key is also a local secret and must not be committed to GitHub.

RFID UID should be treated as an item identifier, not as a replacement for normal login/role authorization.

## Development Notes

- Use prepared statements for database queries.
- Use database transactions for stock movements.
- Never allow BOH stock to become negative.
- Record source, destination, quantity, user/device and movement time.
- Back up the database before schema migrations.
- Keep production secrets outside tracked source files.
- The PHP built-in server is intended for development/testing, not production.

## Project Structure

```text
Stock Inventory Management System/
├── index.php
├── config.php
├── main_admin.php
├── PRINCIPAL/
├── Head of Department/
├── TEACHERS/
├── STUDENTS/
├── CSS/
├── informations/
├── RFID/
│   ├── api/
│   │   ├── config.php
│   │   ├── rfid_api.php
│   │   └── boh_foh_move.php
│   └── admin/
│       └── boh_foh.php
└── UPDATED SQL/
    └── rfid_boh_foh_movement.sql
```

## License

See [LICENSE](LICENSE).
