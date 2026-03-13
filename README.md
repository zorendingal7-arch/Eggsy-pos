# Eggsy POS System

A web-based Point of Sale and Inventory System built for Eggsy Store.

## Tech Stack

- PHP 8.3
- MySQL 8
- Tailwind CSS 3
- Plus Jakarta Sans
- Material Symbols Icons
- WAMP Server

## Features

- Admin and Cashier roles
- POS order processing with cash payment
- Automatic inventory deduction on order
- Receipt generation with Facebook QR code
- Bundle product support
- Inventory management with low stock alerts
- Product management with image upload
- Staff management
- Sales reports and End-of-Day summary
- Cash drawer tracking with start shift flow
- Forgot password with security question
- Dark mode with persistent preference
- Fully offline, no internet required
- Mobile responsive POS page
- PDF export for reports
- Database backup

## Installation

1. Install WAMP Server
2. Clone or copy the project into `C:\wamp64\www\pos-system\`
3. Import `eggsy_pos.sql` into phpMyAdmin
4. Open your browser and go to `http://localhost/pos-system/`

## Local Network Access

To access the system from other devices on the same WiFi:

1. Open Apache `httpd.conf` and change `Listen 80` to `Listen 0.0.0.0:80`
2. Allow `httpd.exe` through Windows Firewall
3. Disable AP Isolation on your router
4. Access via `http://192.168.x.x/pos-system/` from any device on the same network

## Rebuilding CSS

If you add new Tailwind classes, rebuild the CSS by running:
```
npx tailwindcss -i ./assets/css/input.css -o ./assets/css/app.css --minify
```

## License

This project is for personal and commercial use by Eggsy Store.
```
