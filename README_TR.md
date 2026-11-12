# Trendyol - WooCommerce Stok ve Fiyat Senkronizasyonu

***WordPress ve Trendyol Pazaryeri için Otomatik Çift Yönlü Stok ve Fiyat Entegrasyonu***

**Dil:** [English](README.md) | Türkçe

![License](https://img.shields.io/badge/License-GPLv2-blue.svg)
![Platform](https://img.shields.io/badge/Platform-WordPress%20%7C%20WooCommerce-brightgreen.svg)
![PHP](https://img.shields.io/badge/PHP-7.4%2B-orange.svg)
![API](https://img.shields.io/badge/API-Trendyol%20Partner-f27a1a.svg)

> **Hızlı Eşleştirme:** Senkronizasyonun sorunsuz çalışması için WooCommerce ürünlerinizin **Stok Kodu (SKU)** ile Trendyol'daki **Barkod** alanının birebir aynı olduğundan emin olun.

---

## Genel Bakış

WooCommerce mağazanızı **Trendyol Pazaryeri** ile entegre eden, stok ve fiyatları otomatik senkronize eden gelişmiş bir WordPress eklentisidir.

İki farklı çalışma modunu destekler:

1. **Webhook Anlık Senkronizasyon:** Trendyol'da yeni bir sipariş oluşturulduğu anda WooCommerce stoğunu anında düşer.
2. **Zamanlanmış Cron Senkronizasyonu:** Belirlenen aralıklarla (15 dk, 30 dk, 1 saat) tek yönlü veya çift yönlü otomatik arka plan kontrolleri yapar.

## Öne Çıkan Özellikler

- **Stok Senkronizasyonu:** WooCommerce ve Trendyol arasındaki stokları eşitleyin.
- **Fiyat Senkronizasyonu:** Fiyat değişikliklerini otomatik yansıtın.
- **Webhook Desteği:** Trendyol'da sipariş geldiğinde WooCommerce stoğunu anında düşer.
- **Çift Yönlü Senkronizasyon:** İster Trendyol -> WooCommerce, ister WooCommerce -> Trendyol, isterseniz çift yönlü çalışın.
- **Otomatik Kontrol (Cron):** Belirlenen aralıklarla (15 dk, 30 dk, 1 saat) otomatik kontrol sağlar.
- **Loglama:** Tüm işlemleri detaylı log kayıtları ile takip edin.

## Kurulum

1. Bu repodaki `.php` dosyasını indirin.
2. Dosyayı bir klasöre koyup `.zip` haline getirin veya doğrudan `wp-content/plugins/` dizinine yükleyin.
3. WordPress Admin panelinden **Eklentiler** menüsüne gidin.
4. **Trendyol - WooCommerce Stok ve Fiyat Senkronizasyonu** eklentisini etkinleştirin.

## Yapılandırma

1. **Trendyol Partner** panelinden API bilgilerini alın (*Satıcı ID, API Key, API Secret*).
2. WordPress panelinde **Trendyol Entegrasyon > Ayarlar** sayfasına gidin.
3. API bilgilerinizi girin ve kaydedin.
4. **"Bağlantıyı Test Et"** butonu ile bağlantıyı doğrulayın.

## Önemli Notlar

- Senkronizasyonun sağlıklı çalışması için WooCommerce ürünlerinizin **Stok Kodu (SKU)** alanı ile Trendyol'daki **Barkod** alanı birebir aynı olmalıdır.
- Eklenti minimum **PHP 7.4** gerektirir.

## Katkıda Bulunma

Hata bildirimleri ve özellik istekleri için **Issues** sekmesini kullanabilirsiniz. Pull request'ler memnuniyetle karşılanır.
