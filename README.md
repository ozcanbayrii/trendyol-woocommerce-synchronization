# Trendyol - WooCommerce Stok ve Fiyat Senkronizasyonu / Stock & Price Sync

<p align="center">
  <a href="#-türkçe">🇹🇷 Türkçe</a> •
  <a href="#-english">🇬🇧 English</a>
</p>

---

## 🇹🇷 Türkçe

WooCommerce mağazanızı Trendyol Pazaryeri ile entegre eden, stok ve fiyatları otomatik senkronize eden gelişmiş bir WordPress eklentisidir.

### Özellikler

- **Stok Senkronizasyonu:** WooCommerce ve Trendyol arasındaki stokları eşitleyin.
- **Fiyat Senkronizasyonu:** Fiyat değişikliklerini otomatik yansıtın.
- **Webhook Desteği:** Trendyol'da sipariş geldiğinde WooCommerce stoğunu anında düşer.
- **Çift Yönlü Senkronizasyon:** İster Trendyol -> WooCommerce, ister WooCommerce -> Trendyol, isterseniz çift yönlü çalışın.
- **Otomatik Kontrol (Cron):** Belirlenen aralıklarla (15 dk, 30 dk, 1 saat) otomatik kontrol sağlar.
- **Loglama:** Tüm işlemleri detaylı log kayıtları ile takip edin.

### Kurulum

1. Bu repodaki `.php` dosyasını indirin.
2. Dosyayı bir klasöre koyup `.zip` haline getirin veya doğrudan `wp-content/plugins/` dizinine yükleyin.
3. WordPress Admin panelinden **Eklentiler** menüsüne gidin.
4. **Trendyol - WooCommerce Stok ve Fiyat Senkronizasyonu** eklentisini etkinleştirin.

### Yapılandırma

1. **Trendyol Partner** panelinden API bilgilerini alın (*Satıcı ID, API Key, API Secret*).
2. WordPress panelinde **Trendyol Entegrasyon > Ayarlar** sayfasına gidin.
3. API bilgilerinizi girin ve kaydedin.
4. **"Bağlantıyı Test Et"** butonu ile bağlantıyı doğrulayın.

### Önemli Notlar

- Senkronizasyonun sağlıklı çalışması için WooCommerce ürünlerinizin **Stok Kodu (SKU)** alanı ile Trendyol'daki **Barkod** alanı birebir aynı olmalıdır.
- Eklenti minimum **PHP 7.4** gerektirir.

### Katkıda Bulunma

Hata bildirimleri ve özellik istekleri için **Issues** sekmesini kullanabilirsiniz. Pull request'ler memnuniyetle karşılanır.

---

## 🇬🇧 English

An advanced WordPress plugin that integrates your WooCommerce store with the Trendyol Marketplace, automatically synchronizing stock levels and prices.

### Features

- **Stock Synchronization:** Keep stock levels synced between WooCommerce and Trendyol.
- **Price Synchronization:** Automatically reflect price updates across both platforms.
- **Webhook Support:** Instantly deducts WooCommerce stock when a new order is placed on Trendyol.
- **Two-Way Sync:** Choose between Trendyol -> WooCommerce, WooCommerce -> Trendyol, or full bidirectional synchronization.
- **Automated Checks (Cron):** Runs scheduled background checks at set intervals (15 mins, 30 mins, 1 hour).
- **Detailed Logging:** Track and debug all synchronization operations via comprehensive logs.

### Installation

1. Download the `.php` file from this repository.
2. Place the file inside a folder and compress it into a `.zip` archive, or upload it directly to your `wp-content/plugins/` directory.
3. Navigate to the **Plugins** menu in your WordPress Admin dashboard.
4. Activate the **Trendyol - WooCommerce Stok ve Fiyat Senkronizasyonu** plugin.

### Configuration

1. Obtain your API credentials (*Supplier ID, API Key, API Secret*) from your **Trendyol Partner** dashboard.
2. In your WordPress Admin panel, go to **Trendyol Entegrasyon > Ayarlar** (Settings).
3. Enter your API credentials and save your changes.
4. Click the **"Bağlantıyı Test Et"** (Test Connection) button to verify the integration.

### Important Notes

- For synchronization to work properly, the **SKU** field of your WooCommerce products must match the **Barcode** field on Trendyol identically.
- Requires **PHP 7.4** or higher.

### 🤝 Contributing

Please use the **Issues** tab to report bugs or request new features. Pull requests are warmly welcomed!
