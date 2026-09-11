# Stock Inventory Management System — Polytechnic College Based

A web-based inventory management system for a polytechnic/college environment.

## Features

- Main administrator / principal management
- Department administration
- Student and teacher management
- Stock management and supply/order workflows
- Issue and complaint reporting
- Department-wise reports
- MariaDB/MySQL database support
- RFID inventory integration (ESP32 + RC522) in the upgraded version

## Requirements

- PHP 8.x
- MySQL/MariaDB
- Apache or PHP development server
- PHP `mysqli` extension

## Run with XAMPP

1. Copy the application folder into:
   `C:\xampp\htdocs\`
2. Start **Apache** and **MySQL** in XAMPP.
3. Create a database named `inventory`.
4. Import `UPDATED SQL/inventory.sql`.
5. Open the project through:
   `http://localhost/<project-folder>/`

## Run with Termux

From the project directory:

```bash
php -d opcache.enable=0 -d opcache.enable_cli=0 -d opcache.file_cache="" -S 127.0.0.1:8082 -t .
```

Then open:

`http://127.0.0.1:8082/`

For ESP32/RFID testing over the same Wi-Fi network, bind the development server to `0.0.0.0:8082` and use the phone's LAN IP in the ESP32 API URL.

## Database

Database name:

`inventory`

Import:

`UPDATED SQL/inventory.sql`

## Login / Security

**Do not publish real usernames, passwords, API keys, SMTP credentials, or database credentials in this repository.**

Login credentials should be configured in the local database/application environment. If the original project contained default credentials, change them before deployment.

The RFID API key is also a local secret and must not be committed to GitHub.

## Development Notes

- Use prepared statements for database queries.
- Store passwords using secure password hashing; never store plaintext passwords.
- Keep production secrets outside tracked source files.
- Back up the database before schema migrations.
- The PHP built-in server is intended for development/testing, not production hosting.

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
│   └── admin/
└── UPDATED SQL/
```

## License

See [LICENSE](LICENSE).
