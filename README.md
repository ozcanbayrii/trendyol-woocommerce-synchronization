# Trendyol - WooCommerce Stok ve Fiyat Senkronizasyonu

WooCommerce mağazanızı Trendyol Pazaryeri ile entegre eden, stok ve fiyatları otomatik senkronize eden gelişmiş bir WordPress eklentisidir.

## 🚀 Özellikler

- **Stok Senkronizasyonu:** WooCommerce ve Trendyol arasındaki stokları eşitleyin.
- **Fiyat Senkronizasyonu:** Fiyat değişikliklerini otomatik yansıtın.
- **Webhook Desteği:** Trendyol'da sipariş geldiğinde WooCommerce stoğunu anında düşer.
- **Çift Yönlü Senkronizasyon:** İster Trendyol -> Woo, ister Woo -> Trendyol, isterseniz çift yönlü çalışın.
- **Otomatik Kontrol (Cron):** Belirlenen aralıklarla (15dk, 30dk, 1 saat) otomatik kontrol sağlar.
- **Loglama:** Tüm işlemleri detaylı log kayıtları ile takip edin.

## 📦 Kurulum

1. Bu repodaki `.php` dosyasını indirin.
2. Dosyayı bir klasöre koyup `.zip` yapın veya doğrudan `wp-content/plugins/` dizinine atın.
3. WordPress Admin panelinden **Eklentiler** menüsüne gidin.
4. **Trendyol - WooCommerce Stok ve Fiyat Senkronizasyonu** eklentisini etkinleştirin.

## ⚙️ Yapılandırma

1. **Trendyol Partner** panelinden API bilgilerini alın (Satıcı ID, API Key, API Secret).
2. WordPress panelinde **Trendyol Entegrasyon > Ayarlar** sayfasına gidin.
3. API bilgilerinizi girin ve kaydedin.
4. "Bağlantıyı Test Et" butonu ile bağlantıyı doğrulayın.


## ⚠️ Önemli Notlar

- Senkronizasyonun çalışması için WooCommerce ürünlerinizin **Stok Kodu (SKU)** alanı ile Trendyol'daki **Barkod** alanı birebir aynı olmalıdır.
- Eklenti minimum **PHP 7.4** gerektirir.

## 🤝 Katkıda Bulunma

Hata bildirimleri ve özellik istekleri için "Issues" kısmını kullanabilirsiniz. Pull request'ler memnuniyetle karşılanır.


