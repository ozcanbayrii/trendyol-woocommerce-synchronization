# Trendyol - WooCommerce Stock & Price Sync

***Automated Two-Way Inventory & Pricing Integration for WordPress & Trendyol Marketplace***

**Language:** English | [Türkçe](README_TR.md)

![License](https://img.shields.io/badge/License-GPLv2-blue.svg)
![Platform](https://img.shields.io/badge/Platform-WordPress%20%7C%20WooCommerce-brightgreen.svg)
![PHP](https://img.shields.io/badge/PHP-7.4%2B-orange.svg)
![API](https://img.shields.io/badge/API-Trendyol%20Partner-f27a1a.svg)

> **Quick Match:** Ensure your WooCommerce product **SKU** matches your Trendyol **Barcode** identically for seamless synchronization.

---

## Overview

An advanced WordPress plugin that integrates your WooCommerce store with the **Trendyol Marketplace**, automatically synchronizing stock levels and prices.

It supports flexible synchronization modes:

1. **Webhook Real-Time Sync:** Instantly deducts WooCommerce stock the moment a new order is placed on Trendyol.
2. **Scheduled Cron Sync:** Runs automated background checks at customizable intervals (15 mins, 30 mins, 1 hour) in either direction or fully two-way.

## Key Features

- **Stock Synchronization:** Keep inventory counts accurately synced between WooCommerce and Trendyol.
- **Price Synchronization:** Automatically reflect price updates across both platforms.
- **Webhook Support:** Instant stock deduction in WooCommerce when Trendyol orders arrive.
- **Two-Way Sync:** Operate as Trendyol -> WooCommerce, WooCommerce -> Trendyol, or full bidirectional sync.
- **Automated Checks (Cron):** Scheduled background tasks at 15-minute, 30-minute, or 1-hour intervals.
- **Detailed Logging:** Track and audit all sync operations through comprehensive logs.

## Installation

1. Download the `.php` file from this repository.
2. Place the file inside a folder and compress it into a `.zip` archive, or upload it directly to your `wp-content/plugins/` directory.
3. Navigate to the **Plugins** menu in your WordPress Admin dashboard.
4. Activate the **Trendyol - WooCommerce Stok ve Fiyat Senkronizasyonu** plugin.

## Configuration

1. Obtain your API credentials (*Supplier ID, API Key, API Secret*) from your **Trendyol Partner** dashboard.
2. In your WordPress Admin panel, go to **Trendyol Entegrasyon > Ayarlar** (Settings).
3. Enter your API credentials and save your changes.
4. Click the **"Bağlantıyı Test Et"** (Test Connection) button to verify the integration.

## Important Notes

- For synchronization to work properly, the **SKU** field of your WooCommerce products must match the **Barcode** field on Trendyol identically.
- Requires **PHP 7.4** or higher.

## Contributing

Please use the **Issues** tab to report bugs or request new features. Pull requests are warmly welcomed!
