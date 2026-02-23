export const generatePluginCode = (): string => {
  return `<?php
/**
 * Plugin Name: Trendyol - WooCommerce Stok ve Fiyat Senkronizasyonu
 * Description: Trendyol ve WooCommerce  arasında stok ve fiyat senkronizasyonu.
 * Version: 3.6.0
 * Author: Özcan BAYRİ
 * Author URI: https://www.ozcan.work/
 * Text Domain: woo-trendyol-sync
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * WC requires at least: 6.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

register_activation_hook( __FILE__, array( 'Woo_Trendyol_Sync', 'on_activation' ) );

if ( ! class_exists( 'Woo_Trendyol_Sync' ) ) :

class Woo_Trendyol_Sync {

    const OPTION_GROUP = 'woo_trendyol_settings_group';
    const OPTION_NAME = 'woo_trendyol_settings';
    const LOG_TABLE = 'woo_trendyol_logs';
    const API_BASE_INVENTORY = 'https://apigw.trendyol.com/integration/inventory/sellers';
    const CRON_HOOK = 'woo_trendyol_scheduled_sync';
    const DB_VERSION = '1.1';
    const LOCK_TRANSIENT = 'woo_trendyol_sync_lock';

    public function __construct() {
        add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
        add_action( 'admin_init', array( $this, 'register_settings' ) );
        add_action( 'admin_init', array( $this, 'check_db_version' ) );
        
        // Admin Scriptleri
        add_action( 'admin_footer', array( $this, 'admin_footer_scripts' ) );
        add_action( 'wp_ajax_trendyol_check_connection', array( $this, 'ajax_check_connection' ) );
        
        // Log Temizleme Action
        add_action( 'admin_post_woo_trendyol_clear_logs', array( $this, 'process_clear_logs' ) );

        // Woo -> Trendyol Tetikleyiciler
        add_action( 'woocommerce_payment_complete', array( $this, 'trigger_stock_push' ), 10, 1 );
        add_action( 'woocommerce_reduce_order_stock', array( $this, 'trigger_stock_push' ), 10, 1 );
        add_action( 'woocommerce_update_product', array( $this, 'trigger_product_push' ), 10, 2 );

        // Webhook
        add_action( 'rest_api_init', array( $this, 'register_webhook_route' ) );
        
        // Cron
        add_filter( 'cron_schedules', array( $this, 'add_custom_cron_schedules' ) );
        add_action( self::CRON_HOOK, array( $this, 'exec_cron_job' ) );
        register_deactivation_hook( __FILE__, array( $this, 'clear_scheduled_events' ) );

        // HPOS Uyumluluğu
        add_action( 'before_woocommerce_init', function() {
            if ( class_exists( '\\Automattic\\WooCommerce\\Utilities\\FeaturesUtil' ) ) {
                \\Automattic\\WooCommerce\\Utilities\\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
            }
        } );
    }

    /* ==========================================================================
       VERİTABANI & LOGLAMA
       ========================================================================== */

    public static function on_activation() {
        global $wpdb;
        $table_name = $wpdb->prefix . self::LOG_TABLE;
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            sku varchar(100) NOT NULL,
            message text NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id)
        ) $charset_collate;";

        require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
        dbDelta( $sql );

        add_option( 'woo_trendyol_db_version', self::DB_VERSION );
    }

    public function check_db_version() {
        if ( get_option( 'woo_trendyol_db_version' ) != self::DB_VERSION ) {
            self::on_activation();
        }
    }

    private function log_to_db( $sku, $message ) {
        global $wpdb;
        $table_name = $wpdb->prefix . self::LOG_TABLE;
        
        // SQL Injection Koruması: prepare kullanımı
        $wpdb->insert(
            $table_name,
            array(
                'sku' => substr( $sku, 0, 100 ),
                'message' => $message,
                'created_at' => wp_date( 'Y-m-d H:i:s', null, new DateTimeZone('Europe/Istanbul') )
            ),
            array( '%s', '%s', '%s' )
        );

        // Otomatik Log Temizliği: Veritabanı şişmesini önlemek için son 1000 kaydı tut, gerisini sil.
        if ( rand( 1, 20 ) === 1 ) {
            // FIX: Doğrudan sorgu yerine prepare kullanımı
            $wpdb->query( $wpdb->prepare(
                "DELETE FROM {$table_name} WHERE id NOT IN (SELECT id FROM (SELECT id FROM {$table_name} ORDER BY id DESC LIMIT %d) AS foo)",
                1000
            ));
        }
    }
    
    public function process_clear_logs() {
        if ( ! current_user_can( 'manage_options' ) ) return;
        check_admin_referer( 'woo_trendyol_clear_logs' );
        
        global $wpdb;
        $table_name = $wpdb->prefix . self::LOG_TABLE;
        
        // FIX: TRUNCATE yerine DELETE kullanımı (daha güvenli ve WP standardı)
        $wpdb->query( "DELETE FROM {$table_name}" );
        
        wp_redirect( admin_url( 'admin.php?page=woo-trendyol-sync&tab=logs&msg=cleared' ) );
        exit;
    }

    /* ==========================================================================
       YÖNETİCİ ARAYÜZÜ
       ========================================================================== */

    public function add_admin_menu() {
        add_submenu_page(
            'woocommerce',
            'Trendyol Entegrasyon',
            'Trendyol Entegrasyon',
            'manage_options',
            'woo-trendyol-sync',
            array( $this, 'render_admin_page' )
        );
    }

    public function register_settings() {
        register_setting( self::OPTION_GROUP, self::OPTION_NAME, array( $this, 'sanitize_settings' ) );

        add_settings_section( 'main_section', 'API ve Entegrasyon Ayarları', null, 'woo-trendyol-sync' );
        add_settings_field( 'supplier_id', 'Satıcı ID', array( $this, 'render_input' ), 'woo-trendyol-sync', 'main_section', array( 'field' => 'supplier_id' ) );
        add_settings_field( 'api_key', 'API Anahtarı', array( $this, 'render_input' ), 'woo-trendyol-sync', 'main_section', array( 'field' => 'api_key' ) );
        add_settings_field( 'api_secret', 'API Gizli Anahtarı', array( $this, 'render_input' ), 'woo-trendyol-sync', 'main_section', array( 'field' => 'api_secret', 'type' => 'password' ) );
        
        add_settings_section( 'sync_section', 'Senkronizasyon Ayarları', null, 'woo-trendyol-sync' );
        add_settings_field( 'sync_direction', 'Senkronizasyon Yönü', array( $this, 'render_select_direction' ), 'woo-trendyol-sync', 'sync_section' );
        add_settings_field( 'auto_sync_interval', 'Otomatik Stok Kontrolü (Cron)', array( $this, 'render_select_cron' ), 'woo-trendyol-sync', 'sync_section' );
    }

    public function sanitize_settings( $input ) {
        $output = array();
        $output['supplier_id'] = sanitize_text_field( $input['supplier_id'] ?? '' );
        $output['api_key'] = sanitize_text_field( $input['api_key'] ?? '' );
        $output['api_secret'] = sanitize_text_field( $input['api_secret'] ?? '' );
        
        // FIX: Whitelist validasyonu
        $allowed_directions = ['trendyol_to_woo', 'woo_to_trendyol', 'bi_directional'];
        $direction = $input['sync_direction'] ?? 'bi_directional';
        $output['sync_direction'] = in_array( $direction, $allowed_directions, true ) ? $direction : 'bi_directional';
        
        $old_settings = $this->get_settings();
        $old_interval = $old_settings['auto_sync_interval'] ?? 'none';
        $new_interval = sanitize_text_field( $input['auto_sync_interval'] ?? 'none' );
        $output['auto_sync_interval'] = $new_interval;

        if ( $old_interval !== $new_interval ) {
            $this->update_cron_schedule( $new_interval );
        }

        return $output;
    }

    public function render_input( $args ) {
        $opts = $this->get_settings();
        $val = $opts[ $args['field'] ] ?? '';
        $type = $args['type'] ?? 'text';
        echo '<input type="' . $type . '" name="' . self::OPTION_NAME . '[' . $args['field'] . ']" value="' . esc_attr( $val ) . '" class="regular-text">';
    }

    public function render_select_direction() {
        $opts = $this->get_settings();
        $val = $opts['sync_direction'] ?? 'bi_directional';
        ?>
        <select name="<?php echo self::OPTION_NAME; ?>[sync_direction]">
            <option value="trendyol_to_woo" <?php selected( $val, 'trendyol_to_woo' ); ?>>Trendyol -> WooCommerce</option>
            <option value="woo_to_trendyol" <?php selected( $val, 'woo_to_trendyol' ); ?>>WooCommerce -> Trendyol</option>
            <option value="bi_directional" <?php selected( $val, 'bi_directional' ); ?>>Çift Yönlü</option>
        </select>
        <?php
    }

    public function render_select_cron() {
        $opts = $this->get_settings();
        $val = $opts['auto_sync_interval'] ?? 'none';
        ?>
        <select name="<?php echo self::OPTION_NAME; ?>[auto_sync_interval]">
            <option value="none" <?php selected( $val, 'none' ); ?>>Kapalı (Sadece Manuel)</option>
            <option value="every_5_minutes" <?php selected( $val, 'every_5_minutes' ); ?>>Her 5 Dakikada Bir (Hızlı)</option>
            <option value="every_15_minutes" <?php selected( $val, 'every_15_minutes' ); ?>>Her 15 Dakikada Bir</option>
            <option value="every_30_minutes" <?php selected( $val, 'every_30_minutes' ); ?>>Her 30 Dakikada Bir</option>
            <option value="hourly" <?php selected( $val, 'hourly' ); ?>>Her Saat Başı</option>
        </select>
        <p class="description">Trendyol'dan düzenli stok kontrolü için süre seçiniz.</p>
        <?php
    }

    public function render_admin_page() {
        if ( ! current_user_can( 'manage_options' ) ) return;
        
        $active_tab = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'guide'; // Default artık Guide olabilir ya da settings
        if(!isset($_GET['tab'])) $active_tab = 'settings';
        ?>
        <div class="wrap">
            <h1>Trendyol Entegrasyon Yöneticisi</h1>
            <h2 class="nav-tab-wrapper">
                <a href="?page=woo-trendyol-sync&tab=settings" class="nav-tab <?php echo $active_tab == 'settings' ? 'nav-tab-active' : ''; ?>">Ayarlar</a>
                <a href="?page=woo-trendyol-sync&tab=tools" class="nav-tab <?php echo $active_tab == 'tools' ? 'nav-tab-active' : ''; ?>">Araçlar</a>
                <a href="?page=woo-trendyol-sync&tab=logs" class="nav-tab <?php echo $active_tab == 'logs' ? 'nav-tab-active' : ''; ?>">İşlem Kayıtları (Log)</a>
                <a href="?page=woo-trendyol-sync&tab=guide" class="nav-tab <?php echo $active_tab == 'guide' ? 'nav-tab-active' : ''; ?>">📘 Kullanım Kılavuzu</a>
            </h2>
            <br>
            <?php
            if ( $active_tab == 'tools' ) {
                $this->render_tools_tab();
            } elseif ( $active_tab == 'logs' ) {
                $this->render_logs_tab();
            } elseif ( $active_tab == 'guide' ) {
                $this->render_guide_tab();
            } else {
                $this->render_settings_tab();
            }
            ?>
        </div>
        <?php
    }

    private function render_guide_tab() {
        ?>
        <div style="width: 100%;">
            <div class="card" style="padding: 20px; background: #fff; border: 1px solid #ccd0d4; margin-bottom: 20px;">
                <h2 style="margin-top:0;">👋 Hoş Geldiniz!</h2>
                <p>Bu eklenti WooCommerce mağazanızı Trendyol ile senkronize eder. Hiçbir teknik bilgiye ihtiyaç duymadan kurulumu tamamlamak için aşağıdaki adımları takip edin.</p>
            </div>

            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(350px, 1fr)); gap: 20px;">
                <!-- Adım 1 -->
                <div class="card" style="padding: 20px; background: #fff; border: 1px solid #ccd0d4; border-left: 4px solid #f97316; margin: 0;">
                    <h3>🔑 Trendyol API Bilgilerini Alma</h3>
                    <p>Eklentinin Trendyol hesabınıza erişebilmesi için <strong>API Anahtarlarına</strong> ihtiyacı vardır. Bu bilgileri almak çok kolaydır:</p>
                    <ol style="margin-left: 20px; line-height: 2;">
                        <li>
                            <a href="https://partner.trendyol.com/account/info?tab=integrationInformation" target="_blank" class="button button-secondary">
                                Trendyol Entegrasyon Bilgileri Sayfasına Git ↗
                            </a>
                        </li>
                        <li>Açılan sayfada <strong>"Satıcı ID" (Supplier ID)</strong> numaranızı bulun ve kopyalayın.</li>
                        <li>Eğer daha önce oluşturulmamışsa <strong>"API Anahtarı Oluştur"</strong> butonuna basın.</li>
                        <li>Oluşan <strong>API Key</strong> ve <strong>API Secret (Gizli Anahtar)</strong> bilgilerini kopyalayın.</li>
                    </ol>
                </div>

                <!-- Adım 2 -->
                <div class="card" style="padding: 20px; background: #fff; border: 1px solid #ccd0d4; border-left: 4px solid #2271b1; margin: 0;">
                    <h3>⚙️ Ayarları Kaydetme</h3>
                    <p>Kopyaladığınız bilgileri bu eklentinin ayarlarına girme zamanı:</p>
                    <ol style="margin-left: 20px; line-height: 2;">
                        <li>Yukarıdaki <strong>"Ayarlar"</strong> sekmesine tıklayın.</li>
                        <li><strong>Satıcı ID</strong>, <strong>API Anahtarı</strong> ve <strong>API Gizli Anahtarı</strong> kutularını doldurun.</li>
                        <li><strong>"Ayarları Kaydet"</strong> butonuna basın.</li>
                        <li>Sayfanın altındaki <strong>"Bağlantıyı Test Et"</strong> butonuna basın. Eğer <span style="color:green">Yeşil Onay</span> alırsanız kurulum başarılıdır!</li>
                    </ol>
                </div>

                <!-- Adım 3 -->
                <div class="card" style="padding: 20px; background: #fff; border: 1px solid #ccd0d4; border-left: 4px solid #7e22ce; margin: 0;">
                    <h3>⚠️ Barkod (SKU) Eşleşmesi</h3>
                    <p>Entegrasyonun çalışması için <strong>ALTIN KURAL</strong> şudur:</p>
                    <p style="background: #f0f0f1; padding: 10px; border-radius: 4px;">
                        Sitenizdeki ürünün <strong>Stok Kodu (SKU)</strong> ile Trendyol'daki ürünün <strong>Barkodu</strong> birebir AYNI olmalıdır.
                    </p>
                    <p>Eğer sitenizdeki üründe stok kodu yazmıyorsa veya farklıysa, eklenti o ürünü Trendyol'da bulamaz ve eşitleme yapamaz.</p>
                </div>

                <!-- Adım 4 -->
                <div class="card" style="padding: 20px; background: #fff; border: 1px solid #ccd0d4; border-left: 4px solid #16a34a; margin: 0;">
                    <h3>⚡ Webhook (Anlık Sipariş)</h3>
                    <p>Trendyol'da bir ürün satıldığında sitenizdeki stoğun <strong>anında düşmesi</strong> için:</p>
                    <ol style="margin-left: 20px; line-height: 2;">
                        <li>"Ayarlar" sekmesindeki <strong>Webhook Endpoint URL</strong> adresini kopyalayın (Uzun bir linktir).</li>
                        <li>Trendyol Entegrasyon sayfasında, Webhook/Bildirim ayarlarına gidin.</li>
                        <li><strong>"Sipariş Oluşturuldu (Order Created)"</strong> olayını seçin.</li>
                        <li>Kopyaladığınız linki oraya yapıştırıp kaydedin.</li>
                    </ol>
                </div>
                
                 <!-- Adım 5 -->
                <div class="card" style="padding: 20px; background: #fff; border: 1px solid #ccd0d4; border-left: 4px solid #db2777; margin: 0;">
                    <h3>🔄 Otomatik Kontrol</h3>
                    <p>Trendyol panelinden elinizle stok değiştirirseniz sitenizin bundan haberi olması için:</p>
                    <ol style="margin-left: 20px; line-height: 2;">
                        <li>Ayarlar sekmesinden <strong>Otomatik Stok Kontrolü</strong> seçeneğini bulun.</li>
                        <li><strong>"Her 15 Dakikada Bir"</strong> seçeneğini seçin.</li>
                        <li>Böylece sistem düzenli olarak Trendyol'u kontrol edip sitenizi güncelleyecektir.</li>
                    </ol>
                </div>
            </div>
        </div>
        <?php
    }

    private function render_settings_tab() {
        ?>
        <form action="options.php" method="post">
            <?php
            settings_fields( self::OPTION_GROUP );
            do_settings_sections( 'woo-trendyol-sync' );
            submit_button( 'Ayarları Kaydet' );
            ?>
        </form>
        <div class="card" style="margin-top:20px; padding:15px; border:1px solid #ccc;">
            <h3>🔌 Bağlantı Testi</h3>
            <button type="button" id="btn-check-connection" class="button button-secondary">Bağlantıyı Test Et</button>
            <div id="connection-result" style="margin-top:10px; font-weight:bold;"></div>
        </div>
        <hr>
        <div class="card" style="margin-top:20px; padding:15px; background:#f0f6fc; border:1px solid #72aee6;">
            <h3>Webhook Endpoint URL</h3>
            <code><?php echo esc_url( get_rest_url( null, 'ozcanwork/v1/webhook' ) ); ?></code>
            <p class="description">Bu adresi Trendyol Partner panelindeki "Sipariş Oluşturuldu" webhook alanına yapıştırın.</p>
        </div>
        <?php
    }

    private function render_tools_tab() {
        if ( isset( $_POST['manual_sync'] ) && check_admin_referer( 'woo_trendyol_manual_sync' ) ) {
            $scope = sanitize_text_field( $_POST['sync_scope'] );
            $direction = sanitize_text_field( $_POST['manual_direction'] );
            if ( $direction === 'pull' ) {
                $this->process_manual_pull_ui( $scope );
            } else {
                $this->process_manual_push_ui( $scope );
            }
        }
        $opts = $this->get_settings();
        $default_dir = ($opts['sync_direction'] ?? 'bi_directional') === 'trendyol_to_woo' ? 'pull' : 'push';
        ?>
        <div class="card" style="padding: 20px;">
            <h3>Manuel Senkronizasyon</h3>
            <form method="post">
                <?php wp_nonce_field( 'woo_trendyol_manual_sync' ); ?>
                <p>
                    <label><input type="radio" name="manual_direction" value="pull" <?php checked($default_dir, 'pull'); ?>> Trendyol'dan Çek (İçe Aktar)</label><br>
                    <label><input type="radio" name="manual_direction" value="push" <?php checked($default_dir, 'push'); ?>> Trendyol'a Gönder (Dışa Aktar)</label>
                </p>
                <button type="submit" name="manual_sync" value="1" onclick="document.getElementById('sync_scope_input').value='stock'; return confirm('Emin misiniz?');" class="button button-primary">Sadece Stoklar</button>
                <button type="submit" name="manual_sync" value="1" onclick="document.getElementById('sync_scope_input').value='price'; return confirm('Emin misiniz?');" class="button">Sadece Fiyatlar</button>
                <button type="submit" name="manual_sync" value="1" onclick="document.getElementById('sync_scope_input').value='all'; return confirm('Emin misiniz?');" class="button">Hepsini Eşitle</button>
                <input type="hidden" name="sync_scope" id="sync_scope_input" value="all">
            </form>
        </div>
        <?php
    }

    private function render_logs_tab() {
        global $wpdb;
        $table_name = $wpdb->prefix . self::LOG_TABLE;
        
        $logs = $wpdb->get_results( 
            $wpdb->prepare( "SELECT * FROM $table_name ORDER BY id DESC LIMIT %d", 100 ) 
        );
        
        echo '<div style="margin-top:20px;">';
        // FIX: esc_url kullanımı
        echo '<form action="' . esc_url( admin_url('admin-post.php') ) . '" method="post" style="float:right; margin-bottom:10px;">';
        echo '<input type="hidden" name="action" value="woo_trendyol_clear_logs">';
        wp_nonce_field( 'woo_trendyol_clear_logs' );
        submit_button( 'Kayıtları Temizle', 'delete', 'delete_logs', false );
        echo '</form>';
        
        echo '<table class="widefat fixed striped">';
        echo '<thead><tr><th style="width:160px;">Tarih</th><th style="width:150px;">SKU</th><th>Mesaj</th></tr></thead>';
        echo '<tbody>';
        if ( empty( $logs ) ) {
            echo '<tr><td colspan="3">Kayıt bulunamadı.</td></tr>';
        } else {
            foreach ( $logs as $log ) {
                echo '<tr>';
                echo '<td>' . esc_html( $log->created_at ) . '</td>';
                echo '<td>' . esc_html( $log->sku ) . '</td>';
                echo '<td>' . esc_html( $log->message ) . '</td>';
                echo '</tr>';
            }
        }
        echo '</tbody></table></div>';
    }

    public function admin_footer_scripts() {
        if ( ! isset( $_GET['page'] ) || $_GET['page'] !== 'woo-trendyol-sync' ) return;
        $ajax_nonce = wp_create_nonce( 'woo_trendyol_ajax' );
        ?>
        <script type="text/javascript">
        jQuery(document).ready(function($) {
            $('#btn-check-connection').on('click', function() {
                var $btn = $(this);
                var $res = $('#connection-result');
                $btn.prop('disabled', true).text('Kontrol ediliyor...');
                $res.html('');
                $.post(ajaxurl, { 
                    action: 'trendyol_check_connection',
                    security: '<?php echo $ajax_nonce; ?>'
                }, function(response) {
                    $btn.prop('disabled', false).text('Bağlantıyı Test Et');
                    if(response.success) $res.html('<span style="color:green">✅ ' + response.data + '</span>');
                    else $res.html('<span style="color:red">❌ ' + response.data + '</span>');
                });
            });
        });
        </script>
        <?php
    }

    public function ajax_check_connection() {
        check_ajax_referer( 'woo_trendyol_ajax', 'security' );

        $opts = $this->get_settings();
        $supplier_id = trim( $opts['supplier_id'] ?? '' );
        $api_key = trim( $opts['api_key'] ?? '' );
        $api_secret = trim( $opts['api_secret'] ?? '' );
        
        if(empty($supplier_id) || empty($api_key)) { wp_send_json_error('Eksik ayar.'); return; }

        $url = "https://apigw.trendyol.com/integration/product/sellers/{$supplier_id}/products?page=0&size=1";
        $auth = base64_encode( $api_key . ':' . $api_secret );
        
        $response = wp_remote_get( $url, array(
            'headers' => array( 'Authorization' => 'Basic ' . $auth, 'User-Agent' => $supplier_id . ' - SelfIntegration' ),
            'timeout' => 15
        ));

        if ( is_wp_error( $response ) ) {
            error_log( 'Trendyol Bağlantı Hatası: ' . $response->get_error_message() );
            wp_send_json_error( 'Bağlantı kurulamadı. Lütfen logları kontrol edin.' );
        } elseif ( wp_remote_retrieve_response_code( $response ) == 200 ) {
            wp_send_json_success( "Bağlantı Başarılı!" );
        } else {
            $code = wp_remote_retrieve_response_code( $response );
            wp_send_json_error( "Hata: Sunucudan $code kodu döndü." );
        }
    }

    /* ==========================================================================
       CORE LOGIC
       ========================================================================== */

    public function add_custom_cron_schedules( $schedules ) {
        $schedules['every_5_minutes'] = array('interval' => 300, 'display' => 'Her 5 Dakikada Bir');
        $schedules['every_15_minutes'] = array('interval' => 900, 'display' => 'Her 15 Dakikada Bir');
        $schedules['every_30_minutes'] = array('interval' => 1800, 'display' => 'Her 30 Dakikada Bir');
        return $schedules;
    }

    private function get_settings() { return get_option( self::OPTION_NAME, array() ); }

    private function pull_products_from_trendyol( $scope ) {
        // Race Condition Fix: Transient ile kilitleme
        if ( get_transient( self::LOCK_TRANSIENT ) ) {
             return array('success' => false, 'message' => 'Senkronizasyon zaten çalışıyor.', 'debug_log' => []);
        }
        set_transient( self::LOCK_TRANSIENT, true, 300 ); // 5 dakika kilit

        // FIX: Try-Catch-Finally bloğu
        try {
            if ( ! defined( 'WOO_TRENDYOL_SYNC_RUNNING' ) ) define( 'WOO_TRENDYOL_SYNC_RUNNING', true );
            
            $opts = $this->get_settings();
            if ( empty( $opts['supplier_id'] ) || empty( $opts['api_key'] ) ) {
                return array('success' => false, 'message' => 'API ayarları eksik.', 'debug_log' => []);
            }

            $supplier_id = trim( $opts['supplier_id'] );
            $auth = base64_encode( trim($opts['api_key']) . ':' . trim($opts['api_secret']) );
            
            $page = 0; 
            $size = 100;
            $has_more = true; 
            $count = 0;
            $debug_log = array();

            while ( $has_more ) {
                $url = "https://apigw.trendyol.com/integration/product/sellers/{$supplier_id}/products?page={$page}&size={$size}&archived=false";
                $response = wp_remote_get( $url, array(
                    'headers' => array( 'Authorization' => 'Basic ' . $auth, 'User-Agent' => $supplier_id . ' - SelfIntegration' ),
                    'timeout' => 60
                ));

                if ( is_wp_error( $response ) || wp_remote_retrieve_response_code($response) !== 200 ) {
                     $this->log_to_db( 'SYSTEM', 'API Hatası: Veri çekilemedi.' );
                     return array('success' => false, 'message' => 'API Error', 'debug_log' => $debug_log);
                }

                $body = json_decode( wp_remote_retrieve_body( $response ), true );
                if ( empty( $body['content'] ) ) { $has_more = false; break; }

                foreach ( $body['content'] as $item ) {
                    $barcode = isset($item['barcode']) ? trim((string)$item['barcode']) : '';
                    if ( empty($barcode) ) continue;
                    $product_id = wc_get_product_id_by_sku( $barcode );
                    
                    if(count($debug_log) < 10) $debug_entry = array('barcode' => $barcode, 'woo_id' => $product_id ?: 'BULUNAMADI');
                    else $debug_entry = null;

                    if ( ! $product_id ) continue;
                    $product = wc_get_product( $product_id );
                    $updated = false;

                    if ( $scope === 'stock' || $scope === 'all' ) {
                        $qty = (int) ($item['quantity'] ?? 0);
                        if ( $product->get_stock_quantity() !== $qty ) {
                            $old_qty = $product->get_stock_quantity();
                            wc_update_product_stock( $product, $qty );
                            $product->set_stock_status( ($qty > 0) ? 'instock' : 'outofstock' );
                            $updated = true;
                            
                            $this->log_to_db( $barcode, "Stok Güncellendi. Eski: $old_qty, Yeni: $qty" );
                            if($debug_entry) $debug_entry['action'] = "Stok: $old_qty -> $qty";
                        } else {
                             if($product->get_stock_status() !== (($qty > 0) ? 'instock' : 'outofstock')) {
                                 $product->set_stock_status( ($qty > 0) ? 'instock' : 'outofstock' );
                                 $product->save();
                                 $updated = true;
                                 $this->log_to_db( $barcode, "Stok Statüsü Düzeltildi." );
                             }
                        }
                    }

                    if ( $scope === 'price' || $scope === 'all' ) {
                         $t_list = (float)($item['listPrice'] ?? 0);
                         $t_sale = (float)($item['salePrice'] ?? 0);
                         if ( $t_list > 0 && abs($product->get_regular_price() - $t_list) > 0.01 ) {
                             $product->set_regular_price( $t_list );
                             if ( $t_sale > 0 && $t_sale < $t_list ) {
                                 $product->set_price( $t_sale );
                                 $product->set_sale_price( $t_sale );
                             } else {
                                 $product->set_price( $t_list );
                                 $product->set_sale_price( '' );
                             }
                             $product->save();
                             $updated = true;
                             $this->log_to_db( $barcode, "Fiyat Güncellendi: $t_list TL" );
                             if($debug_entry) $debug_entry['action'] = "Fiyat: $t_list";
                         }
                    }

                    if($updated) {
                        wc_delete_product_transients($product_id);
                        $count++;
                    }
                    if($debug_entry) $debug_log[] = $debug_entry;
                }
                
                $total_elements = $body['totalElements'] ?? 0;
                $current_count = ($page + 1) * $size;
                
                if ( $current_count >= $total_elements ) {
                    $has_more = false;
                } else {
                    $page++;
                }
            }

            if ( $count > 0 ) {
                $this->log_to_db( 'SYSTEM', "İşlem Tamamlandı. Güncellenen: $count" );
            }
            
            return array('success' => true, 'count' => $count, 'debug_log' => $debug_log);

        } catch (Exception $e) {
            $this->log_to_db('SYSTEM', 'Kritik Hata: ' . $e->getMessage());
            return array('success' => false, 'message' => 'Exception', 'debug_log' => []);
        } finally {
            // FIX: Kilit her zaman kaldırılır
            delete_transient( self::LOCK_TRANSIENT );
        }
    }
    
    private function process_manual_pull_ui( $scope ) {
        set_time_limit(0);
        echo '<div class="notice notice-info"><p>İşlem başladı...</p></div>';
        $result = $this->pull_products_from_trendyol( $scope );
        if ( $result['success'] ) {
            echo '<div class="notice notice-success"><p>Tamamlandı. Güncellenen: ' . $result['count'] . '</p></div>';
            if ( ! empty( $result['debug_log'] ) ) {
                echo '<table class="widefat fixed striped" style="margin-top:10px;"><thead><tr><th>Barkod</th><th>Woo ID</th><th>İşlem</th></tr></thead><tbody>';
                foreach ( $result['debug_log'] as $log ) {
                    echo '<tr><td>' . esc_html( $log['barcode'] ) . '</td><td>' . esc_html( $log['woo_id'] ) . '</td><td>' . esc_html( $log['action'] ?? '-' ) . '</td></tr>';
                }
                echo '</tbody></table>';
            }
        } else {
            echo '<div class="notice notice-error"><p>Hata: ' . $result['message'] . '</p></div>';
        }
    }

    private function process_manual_push_ui( $scope ) {
        echo '<div class="notice notice-warning"><p>Manuel Gönderim bu versiyonda sadece tetikleyicilerle çalışır veya limitlidir.</p></div>';
    }

    private function send_batch_to_trendyol( $items, $opts ) {
        if ( empty( $opts['supplier_id'] ) ) return;
        $url = self::API_BASE_INVENTORY . "/{$opts['supplier_id']}/products/price-and-inventory";
        $auth = base64_encode( trim($opts['api_key']) . ':' . trim($opts['api_secret']) );
        
        $response = wp_remote_post( $url, array(
            'headers' => array('Authorization' => 'Basic ' . $auth, 'Content-Type'  => 'application/json', 'User-Agent' => "{$opts['supplier_id']} - SelfIntegration"),
            'body'    => wp_json_encode( array( 'items' => $items ) ), // FIX: wp_json_encode
            'timeout' => 15, 'blocking' => true 
        ));
        
        if( is_wp_error($response) ) {
             $this->log_to_db( 'SYSTEM', 'Trendyol Push Hatası: Bağlantı sorunu.' );
        } else {
             $code = wp_remote_retrieve_response_code($response);
             if($code != 200) {
                 $this->log_to_db( 'SYSTEM', "Trendyol Push Hatası HTTP $code" );
             }
        }
    }
    
    public function update_cron_schedule($interval) {
        $timestamp = wp_next_scheduled( self::CRON_HOOK );
        if($timestamp) wp_unschedule_event($timestamp, self::CRON_HOOK);
        if($interval !== 'none') wp_schedule_event(time(), $interval, self::CRON_HOOK);
    }
    public function clear_scheduled_events() {
        $timestamp = wp_next_scheduled( self::CRON_HOOK );
        if($timestamp) wp_unschedule_event($timestamp, self::CRON_HOOK);
    }
    public function exec_cron_job() { $this->pull_products_from_trendyol('stock'); }
    
    public function trigger_stock_push($order_id) {
         if(defined('WOO_TRENDYOL_SYNC_RUNNING')) return;
         
         $lock_key = 'woo_trendyol_push_' . $order_id;
         if(get_transient($lock_key)) return;
         set_transient($lock_key, true, 60);

         $opts = $this->get_settings();
         if (($opts['sync_direction'] ?? 'bi_directional') === 'trendyol_to_woo') {
             delete_transient($lock_key);
             return;
         }
         
         $order = wc_get_order($order_id);
         if(!$order) {
             delete_transient($lock_key);
             return;
         }
         
         $batch = array();
         foreach($order->get_items() as $item) {
             $prod = $item->get_product();
             if($prod && $prod->get_sku()) {
                 $qty = (int)$prod->get_stock_quantity();
                 $batch[] = array('barcode' => $prod->get_sku(), 'quantity' => $qty);
                 $this->log_to_db( $prod->get_sku(), "Sipariş Sonrası Gönderiliyor ($order_id). Yeni Stok: $qty" );
             }
         }
         if(!empty($batch)) $this->send_batch_to_trendyol($batch, $opts);
         
         delete_transient($lock_key);
    }

    public function trigger_product_push($id, $prod) {
        if(defined('WOO_TRENDYOL_SYNC_RUNNING')) return;
        
        $lock_key = 'woo_trendyol_prod_push_' . $id;
        if(get_transient($lock_key)) return;
        set_transient($lock_key, true, 30);

        $opts = $this->get_settings();
        if (($opts['sync_direction'] ?? 'bi_directional') === 'trendyol_to_woo') {
            delete_transient($lock_key);
            return;
        }
        if($prod->get_status() !== 'publish' || !$prod->get_sku()) {
            delete_transient($lock_key);
            return;
        }
        
        $item = array(
            'barcode' => $prod->get_sku(), 
            'quantity' => (int)$prod->get_stock_quantity(), 
            'listPrice' => (float)($prod->get_regular_price()?:$prod->get_price()), 
            'salePrice' => (float)($prod->get_sale_price()?:$prod->get_price())
        );
        $this->log_to_db( $prod->get_sku(), "Ürün Güncelleme Tetiklendi." );
        $this->send_batch_to_trendyol(array($item), $opts);
        
        delete_transient($lock_key);
    }

    public function register_webhook_route() {
        register_rest_route( 'ozcanwork/v1', '/webhook', array(
            'methods' => 'POST',
            'callback' => array( $this, 'handle_webhook' ),
            // FIX: Webhook İmza Doğrulaması
            'permission_callback' => array( $this, 'verify_webhook_signature' ),
        ) );
    }

    // FIX: Webhook Doğrulama Metodu
    public function verify_webhook_signature( $request ) {
        $signature = $request->get_header( 'X-Trendyol-Signature' );
        if ( empty( $signature ) ) return false;
        
        $body = $request->get_body();
        $opts = $this->get_settings();
        $secret = $opts['api_secret'] ?? '';
        
        if ( empty( $secret ) ) return false;
        
        // HMAC SHA256 ile imza kontrolü
        $expected = hash_hmac( 'sha256', $body, $secret );
        return hash_equals( $expected, $signature );
    }

    public function handle_webhook( $request ) {
        if ( ! defined( 'WOO_TRENDYOL_SYNC_RUNNING' ) ) define( 'WOO_TRENDYOL_SYNC_RUNNING', true );
        $params = $request->get_json_params();
        if ( empty( $params ) ) return new WP_REST_Response( array('status' => 'no_data'), 200 );
        
        $status = $params['status'] ?? '';
        $is_created = ($status === 'Created');
        $is_cancelled = ($status === 'Cancelled');
        $updated_count = 0;
        
        if ( ! empty( $params['lines'] ) && is_array( $params['lines'] ) ) {
            foreach ( $params['lines'] as $line ) {
                $sku = trim($line['barcode'] ?? $line['merchantSku'] ?? '');
                $qty = (int) ($line['quantity'] ?? 0);
                if ( empty( $sku ) ) continue;

                $product_id = wc_get_product_id_by_sku( $sku );
                if ( $product_id ) {
                    $product = wc_get_product( $product_id );
                    if ( ! $product->get_manage_stock() ) $product->set_manage_stock( true );
                    
                    if ( $is_created ) {
                        $product->reduce_stock( $qty );
                        $this->log_to_db( $sku, "WEBHOOK: Sipariş Geldi. Stok $qty adet düşüldü." );
                    } elseif ( $is_cancelled ) {
                        $product->increase_stock( $qty );
                        $this->log_to_db( $sku, "WEBHOOK: İptal. Stok $qty adet iade edildi." );
                    }
                    $product->save();
                    $updated_count++;
                } else {
                    $this->log_to_db( $sku, "WEBHOOK: Ürün Woo'da bulunamadı." );
                }
            }
        }
        return new WP_REST_Response( array( 'success' => true, 'updated' => $updated_count ), 200 );
    }
}

new Woo_Trendyol_Sync();
endif;
`;
};