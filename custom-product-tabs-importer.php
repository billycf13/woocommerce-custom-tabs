<?php
/**
 * Plugin Name: WooCommerce Custom Product Tabs Importer
 * Plugin URI: https://github.com/billycf13/woocommerce-custom-tabs
 * Description: <strong>WooCommerce Custom Product Tabs Importer</strong> memungkinkan Anda menambahkan tab kustom tak terbatas pada produk WooCommerce Anda dengan mudah. 
 * Fitur utama: <ul style="list-style-type: none; padding-left: 20px; margin: 5px 0;">
 * <li>✨ Editor visual (TinyMCE) dengan dukungan media uploader</li>
 * <li>📝 Mode Code untuk pengeditan HTML langsung</li>
 * <li>📥 Import/Export tab kustom via CSV</li>
 * <li>🔄 Pengaturan prioritas untuk mengatur urutan tab</li>
 * <li>🎨 Antarmuka admin yang user-friendly</li>
 * <li>🔌 Kompatibel dengan WooCommerce HPOS</li>
 * </ul>
 * <em>Plugin ini dibuat untuk memudahkan pengelolaan konten produk WooCommerce Anda.</em>
 * Version: 1.1.0
 * Author: Billycf
 * Author URI: https://github.com/billycf13
 * Text Domain: custom-product-tabs-importer
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.2
 * WC requires at least: 5.0
 * WC tested up to: 8.0
 */

if (!defined('ABSPATH')) {
    exit;
}

// Deklarasi kompatibilitas dengan WooCommerce HPOS
add_action('before_woocommerce_init', function () {
    if (class_exists('\Automattic\WooCommerce\Utilities\FeaturesUtil')) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
    }
});

// Inisialisasi plugin update checker
require_once plugin_dir_path(__FILE__) . 'plugin-update-checker/plugin-update-checker.php';
use YahnisElsts\PluginUpdateChecker\v5\PucFactory;

$myUpdateChecker = PucFactory::buildUpdateChecker(
    'https://github.com/billycf13/woocommerce-custom-tabs/',
    __FILE__,
    'custom-product-tabs-importer'
);

// Set branch yang akan digunakan
$myUpdateChecker->setBranch('main');

// Mengatur update checker untuk membaca info dari release
$myUpdateChecker->getVcsApi()->enableReleaseAssets();

class Custom_Product_Tabs_Importer
{
    private static $instance = null;

    public static function get_instance()
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function __construct()
    {
        // Hook untuk menambah tab di halaman edit produk
        add_filter('woocommerce_product_data_tabs', array($this, 'add_custom_product_tab'), 10, 1);
        add_action('woocommerce_product_data_panels', array($this, 'add_custom_product_tab_content'));
        add_action('woocommerce_process_product_meta', array($this, 'save_custom_product_tab_data'));
        add_action('woocommerce_rest_insert_product_object', array($this, 'save_from_rest'), 10, 3);

        // Hook untuk import/export
        add_filter('woocommerce_csv_product_import_mapping_options', array($this, 'add_import_mapping_options'));
        add_filter('woocommerce_csv_product_import_mapping_default_columns', array($this, 'add_import_mapping_default_columns'));
        add_filter('woocommerce_product_import_pre_insert_product_object', array($this, 'process_import'), 10, 2);

        // Hook untuk export
        add_filter('woocommerce_product_export_column_names', array($this, 'add_export_column'));
        add_filter('woocommerce_product_export_product_default_columns', array($this, 'add_export_column'));

        // Hook untuk setiap kolom tab kustom
        $max_export_tabs = max(10, $this->get_max_tabs_count());
        for ($i = 1; $i <= $max_export_tabs; $i++) {
            add_filter("woocommerce_product_export_product_column_custom_tab_{$i}_title", array($this, 'get_column_value'), 10, 2);
            add_filter("woocommerce_product_export_product_column_custom_tab_{$i}_content", array($this, 'get_column_value'), 10, 2);
            add_filter("woocommerce_product_export_product_column_custom_tab_{$i}_priority", array($this, 'get_column_value'), 10, 2);
        }

        // Hook untuk menampilkan tab di frontend
        add_filter('woocommerce_product_tabs', array($this, 'display_custom_product_tabs'));

        // Enqueue scripts dan styles
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_frontend_scripts'));
    }

    public function save_from_rest($product, $request, $creating)
    {
        $meta_data = $request->get_param('meta_data');
        if (empty($meta_data))
            return;

        foreach ($meta_data as $meta) {
            if ($meta['key'] === '_custom_product_tabs') {
                $value = $meta['value'];
                if (is_string($value)) {
                    $value = json_decode($value, true);
                }
                if (is_array($value)) {
                    $sanitized = array();
                    foreach ($value as $tab_id => $tab) {
                        if (!empty($tab['title'])) {
                            $sanitized[$tab_id] = array(
                                'title' => sanitize_text_field($tab['title']),
                                'content' => wp_kses_post($tab['content'] ?? '')
                            );
                        }
                    }
                    update_post_meta($product->get_id(), '_custom_product_tabs', $sanitized);
                }
                break;
            }
        }
    }

    /**
     * Mengembalikan nilai kolom untuk export produk
     * @param mixed $value Nilai default kolom
     * @param WC_Product $product Objek produk
     * @return string Nilai kolom yang akan diekspor
     */
    public function get_column_value($value, $product)
    {
        // Ambil data tab kustom dari produk
        $custom_tabs = $product->get_meta('_custom_product_tabs');

        // Jika tidak ada tab kustom, return string kosong
        if (empty($custom_tabs) || !is_array($custom_tabs)) {
            return '';
        }

        // Konversi tab kustom menjadi array berurutan
        $tabs_array = array_values($custom_tabs);

        // Cek apakah nama kolom sesuai dengan format tab kustom
        if (preg_match('/custom_tab_(\d+)_(title|content|priority)$/', current_filter(), $matches)) {
            $tab_number = (int) $matches[1];
            $field_type = $matches[2];

            // Index array dimulai dari 0, sedangkan nomor tab dimulai dari 1
            $tab_index = $tab_number - 1;

            // Cek apakah tab dengan index tersebut ada
            if (isset($tabs_array[$tab_index])) {
                $tab = $tabs_array[$tab_index];

                // Return nilai sesuai dengan jenis field yang diminta
                switch ($field_type) {
                    case 'title':
                        return isset($tab['title']) ? $tab['title'] : '';
                    case 'content':
                        return isset($tab['content']) ? $tab['content'] : '';
                    case 'priority':
                        return (string) ($tab_index + 1);
                }
            }
        }

        return '';
    }

    /**
     * Menambahkan kolom export untuk tab kustom
     * @param array $columns Kolom yang sudah ada
     * @return array Kolom yang sudah ditambahkan
     */
    public function add_export_column($columns)
    {
        // Ambil jumlah tab maksimum dari produk yang ada
        $max_tabs = $this->get_max_tabs_count();

        // Tambahkan kolom untuk setiap tab dengan format yang sama seperti import
        for ($i = 1; $i <= $max_tabs; $i++) {
            $columns["custom_tab_{$i}_title"] = "custom_tab_{$i}_title";
            $columns["custom_tab_{$i}_content"] = "custom_tab_{$i}_content";
            $columns["custom_tab_{$i}_priority"] = "custom_tab_{$i}_priority";
        }

        return $columns;
    }

    /**
     * Menambahkan script dan style untuk admin panel
     */
    public function enqueue_admin_scripts()
    {
        if (get_post_type() === 'product') {
            // Enqueue WordPress editor scripts
            wp_enqueue_editor();
            wp_enqueue_media();

            // Enqueue plugin scripts
            wp_enqueue_script(
                'custom-tabs-admin',
                plugins_url('assets/js/admin-tabs.js', __FILE__),
                array('jquery', 'jquery-ui-sortable', 'wp-editor', 'wp-util', 'quicktags', 'wplink', 'media-upload'),
                '1.1.0',
                true
            );

            // Enqueue plugin styles
            wp_enqueue_style(
                'custom-tabs-admin',
                plugins_url('assets/css/admin-style.css', __FILE__),
                array(),
                '1.1.0'
            );

            // Tambahkan data localization untuk JavaScript
            wp_localize_script('custom-tabs-admin', 'customTabsData', array(
                'labels' => array(
                    'newTab' => __('Tab Baru', 'custom-product-tabs-importer'),
                    'delete' => __('Hapus', 'custom-product-tabs-importer'),
                    'tabTitle' => __('Nama / Judul Tab', 'custom-product-tabs-importer'),
                    'tabContent' => __('Konten Lengkap Tab', 'custom-product-tabs-importer'),
                    'visual' => __('Visual', 'custom-product-tabs-importer'),
                    'code' => __('Code', 'custom-product-tabs-importer')
                ),
                'nonce' => wp_create_nonce('custom_tabs_nonce')
            ));
        }
    }

    /**
     * Menambahkan script dan style untuk frontend
     */
    public function enqueue_frontend_scripts()
    {
        if (is_product()) {
            wp_enqueue_style(
                'custom-tabs-frontend',
                plugins_url('assets/css/frontend-style.css', __FILE__),
                array(),
                '1.1.0'
            );
        }
    }

    /**
     * Menampilkan tab kustom di halaman produk frontend
     * @param array $tabs Tab yang sudah ada
     * @return array Tab yang sudah ditambahkan
     */
    public function display_custom_product_tabs($tabs)
    {
        global $product;
        if (!$product)
            return $tabs;

        $product_id = $product->get_id();
        $custom_tabs = get_post_meta($product_id, '_custom_product_tabs', true);

        if (!empty($custom_tabs) && is_array($custom_tabs)) {
            $priority = 11; // Mulai dari 11 agar muncul setelah "Description" (10)
            foreach ($custom_tabs as $tab_id => $tab) {
                if (!empty($tab['title']) && !empty(trim($tab['content']))) {
                    $tabs[$tab_id] = array(
                        'title' => $tab['title'],
                        'priority' => $priority++,
                        'callback' => array($this, 'custom_tab_content'),
                        'content' => $tab['content']
                    );
                }
            }
        }

        return $tabs;
    }

    /**
     * Menampilkan konten tab kustom
     * @param string $key Key tab
     * @param array $tab Data tab
     */
    public function custom_tab_content($key, $tab)
    {
        echo '<div class="custom-tab-container">';
        echo '<div class="custom-tab-content-text">';
        // Gunakan do_shortcode dan wpautop alih-alih the_content untuk menghindari duplikasi dari plugin lain
        $content = isset($tab['content']) ? $tab['content'] : '';
        echo do_shortcode(wpautop($content));
        echo '</div>';
        echo '</div>';
    }

    /**
     * Menambahkan tab kustom ke panel data produk
     * @param array $tabs Tab yang sudah ada
     * @return array Tab yang sudah ditambahkan
     */
    public function add_custom_product_tab($tabs)
    {
        $tabs['custom_tabs'] = array(
            'label' => __('Tab Kustom', 'custom-product-tabs-importer'),
            'target' => 'custom_product_tabs_data',
            'class' => array('show_if_simple', 'show_if_variable'),
            'priority' => 80
        );
        return $tabs;
    }

    /**
     * Menambahkan konten panel tab kustom
     */
    public function add_custom_product_tab_content()
    {
        global $post;
        $custom_tabs = get_post_meta($post->ID, '_custom_product_tabs', true);
        if (empty($custom_tabs)) {
            $custom_tabs = array();
        }
        ?>
        <div id="custom_product_tabs_data" class="panel woocommerce_options_panel">
            <?php wp_nonce_field('custom_tabs_nonce', 'custom_tabs_nonce'); ?>
            <div class="custom-tabs-header">
                <h2><?php _e('Kelola Tab Kustom', 'custom-product-tabs-importer'); ?></h2>
                <p class="description">
                    <?php _e('Tambahkan informasi tambahan produk Anda melalui tab kustom di bawah ini.', 'custom-product-tabs-importer'); ?>
                </p>
            </div>

            <div class="custom_tabs_container">
                <?php
                if (!empty($custom_tabs)) {
                    foreach ($custom_tabs as $tab_id => $tab) {
                        $this->render_tab_fields($tab_id, $tab);
                    }
                }
                ?>
            </div>

            <div class="custom-tabs-footer">
                <button type="button" class="button button-primary add_custom_tab_button">
                    <span class="dashicons dashicons-plus-alt"></span>
                    <?php _e('Tambah Tab Baru', 'custom-product-tabs-importer'); ?>
                </button>
            </div>
        </div>
        <?php
    }

    /**
     * Render fields untuk tab kustom
     * 
     * @param string $tab_id ID unik untuk tab
     * @param array $tab_data Data tab (title, content, priority)
     */
    private function render_tab_fields($tab_id, $tab_data = array())
    {
        $title = isset($tab_data['title']) ? $tab_data['title'] : '';
        $content = isset($tab_data['content']) ? $tab_data['content'] : '';
        ?>
        <div class="custom_tab_fields cardx" data-tab="<?php echo esc_attr($tab_id); ?>">
            <div class="tab-header">
                <div class="tab-reorder-actions">
                    <button type="button" class="move-tab-up button-link"
                        title="<?php esc_attr_e('Pindahkan ke atas', 'custom-product-tabs-importer'); ?>">
                        <span class="dashicons dashicons-arrow-up-alt2"></span>
                    </button>
                    <button type="button" class="move-tab-down button-link"
                        title="<?php esc_attr_e('Pindahkan ke bawah', 'custom-product-tabs-importer'); ?>">
                        <span class="dashicons dashicons-arrow-down-alt2"></span>
                    </button>
                </div>
                <div class="tab-drag-handle">
                    <span class="dashicons dashicons-menu"></span>
                </div>
                <div class="tab-title-wrapper">
                    <span
                        class="tab-title"><?php echo $title ? esc_html($title) : __('Tab Baru', 'custom-product-tabs-importer'); ?></span>
                </div>
                <div class="tab-actions">
                    <button type="button" class="toggle-tab-content button-link"
                        title="<?php esc_attr_e('Buka/Tutup', 'custom-product-tabs-importer'); ?>">
                        <span class="dashicons dashicons-arrow-down-alt2"></span>
                    </button>
                </div>
            </div>

            <div class="tab-content-wrapper">
                <div class="form-grid">
                    <div class="form-field-group full-width">
                        <label><?php _e('Nama / Judul Tab', 'custom-product-tabs-importer'); ?></label>
                        <input type="text" name="custom_product_tabs[<?php echo esc_attr($tab_id); ?>][title]"
                            value="<?php echo esc_attr($title); ?>"
                            placeholder="<?php esc_attr_e('Contoh: Spesifikasi Lengkap', 'custom-product-tabs-importer'); ?>" />
                    </div>
                </div>

                <div class="form-field-group full-width">
                    <label><?php _e('Konten Lengkap Tab', 'custom-product-tabs-importer'); ?></label>
                    <div class="editor-container">
                        <?php
                        $editor_id = 'custom_tab_' . $tab_id . '_content';
                        wp_editor(
                            $content,
                            $editor_id,
                            array(
                                'textarea_name' => "custom_product_tabs[{$tab_id}][content]",
                                'editor_class' => 'custom_tab_content',
                                'media_buttons' => true,
                                'tinymce' => true,
                                'quicktags' => true,
                                'editor_height' => 200,
                                'teeny' => false
                            )
                        );
                        ?>
                    </div>
                </div>

                <div class="tab-footer">
                    <button type="button" class="remove_tab button button-link delete">
                        <span class="dashicons dashicons-trash"></span>
                        <?php _e('Hapus Tab', 'custom-product-tabs-importer'); ?>
                    </button>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Menyimpan data tab kustom
     * @param int $post_id ID produk
     */
    public function save_custom_product_tab_data($post_id)
    {
        if (!isset($_POST['custom_tabs_nonce']) || !wp_verify_nonce($_POST['custom_tabs_nonce'], 'custom_tabs_nonce')) {
            return;
        }

        if (!current_user_can('edit_product', $post_id)) {
            return;
        }

        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        $custom_tabs = isset($_POST['custom_product_tabs']) ? $_POST['custom_product_tabs'] : array();
        $sanitized_tabs = array();

        if (!empty($custom_tabs)) {
            foreach ($custom_tabs as $tab_id => $tab) {
                if (!empty($tab['title'])) {
                    $sanitized_tabs[$tab_id] = array(
                        'title' => sanitize_text_field($tab['title']),
                        'content' => wp_kses_post($tab['content'])
                    );
                }
            }
        }

        update_post_meta($post_id, '_custom_product_tabs', $sanitized_tabs);

        // Clear cached max tabs count
        delete_transient('custom_tabs_max_count');
    }

    /**
     * Menambahkan opsi mapping untuk import
     * @param array $options Opsi mapping yang sudah ada
     * @return array Opsi mapping yang sudah ditambahkan
     */
    public function add_import_mapping_options($options)
    {
        $tab_count = $this->get_tab_count_from_csv();

        // Default minimal 1 tab
        $tab_count = max(5, $tab_count);

        // Tambahkan opsi mapping untuk setiap tab
        for ($i = 1; $i <= $tab_count; $i++) {
            $options["custom_tab_{$i}_title"] = __('Tab Kustom - Judul', 'custom-product-tabs-importer');
            $options["custom_tab_{$i}_content"] = __('Tab Kustom - Konten', 'custom-product-tabs-importer');
            $options["custom_tab_{$i}_priority"] = __('Tab Kustom - Prioritas', 'custom-product-tabs-importer');
        }

        return $options;
    }

    /**
     * Mendapatkan jumlah tab dari file CSV
     * @return int Jumlah tab
     */
    private function get_tab_count_from_csv()
    {
        if (!isset($_POST['file']) || empty($_POST['file'])) {
            return 1;
        }

        $file = sanitize_text_field(wp_unslash($_POST['file']));

        // Validasi file berada di dalam direktori uploads
        $upload_dir = wp_upload_dir();
        $upload_base = realpath($upload_dir['basedir']);
        $real_file = realpath($file);

        if (!$real_file || !$upload_base || strpos($real_file, $upload_base) !== 0) {
            return 1;
        }

        if (!file_exists($real_file)) {
            return 1;
        }

        $handle = fopen($real_file, 'r');
        if ($handle === false) {
            return 1;
        }

        // Baca header CSV
        $headers = fgetcsv($handle);
        fclose($handle);

        if (!$headers) {
            return 1;
        }

        // Hitung jumlah tab berdasarkan pola nama kolom
        $tab_count = 0;
        $i = 1;
        while (in_array("custom_tab_{$i}_title", $headers)) {
            $tab_count++;
            $i++;
        }

        return $tab_count > 0 ? $tab_count : 1;
    }

    /**
     * Menambahkan kolom default untuk import
     * @param array $columns Kolom default yang sudah ada
     * @return array Kolom default yang sudah ditambahkan
     */
    public function add_import_mapping_default_columns($columns)
    {
        $tab_count = $this->get_tab_count_from_csv();

        // Default minimal 1 tab
        $tab_count = max(1, $tab_count);

        // Tambahkan kolom default untuk setiap tab
        for ($i = 1; $i <= $tab_count; $i++) {
            $columns["custom_tab_{$i}_title"] = "custom_tab_{$i}_title";
            $columns["custom_tab_{$i}_content"] = "custom_tab_{$i}_content";
            $columns["custom_tab_{$i}_priority"] = "custom_tab_{$i}_priority";
        }

        return $columns;
    }

    /**
     * Memproses data import
     * @param WC_Product $product Objek produk
     * @param array $data Data yang diimpor
     * @return WC_Product Objek produk yang sudah diupdate
     */
    public function process_import($product, $data)
    {
        $custom_tabs = array();
        $i = 1;

        // Proses semua tab yang ada di data
        while (isset($data["custom_tab_{$i}_title"])) {
            if (!empty($data["custom_tab_{$i}_title"])) {
                $tab_id = 'tab_' . uniqid();
                $custom_tabs[$tab_id] = array(
                    'title' => $data["custom_tab_{$i}_title"],
                    'content' => isset($data["custom_tab_{$i}_content"]) ? $data["custom_tab_{$i}_content"] : '',
                    'priority' => isset($data["custom_tab_{$i}_priority"]) ? absint($data["custom_tab_{$i}_priority"]) : 10 // Default priority
                );
            }
            $i++; // Increment variabel $i untuk pindah ke tab berikutnya
        }

        if (!empty($custom_tabs)) {
            // Urutkan tab berdasarkan priority sebelum disimpan agar posisinya benar di array
            uasort($custom_tabs, function ($a, $b) {
                return $a['priority'] - $b['priority'];
            });

            // Simpan ke meta (tanpa field priority agar database tetap bersih)
            $final_tabs = array();
            foreach ($custom_tabs as $id => $tab) {
                $final_tabs[$id] = array(
                    'title' => $tab['title'],
                    'content' => $tab['content']
                );
            }
            $product->update_meta_data('_custom_product_tabs', $final_tabs);
        }

        return $product;
    }

    /**
     * Mendapatkan jumlah tab maksimum dari produk yang ada
     * @return int Jumlah tab maksimum
     */
    private function get_max_tabs_count()
    {
        $cached = get_transient('custom_tabs_max_count');
        if ($cached !== false) {
            return (int) $cached;
        }

        global $wpdb;

        // Ambil semua custom tab dari database
        $meta_values = $wpdb->get_col($wpdb->prepare(
            "SELECT meta_value FROM {$wpdb->postmeta} WHERE meta_key = %s",
            '_custom_product_tabs'
        ));

        $max_tabs = 1; // Minimal 1 tab

        foreach ($meta_values as $meta_value) {
            $tabs = maybe_unserialize($meta_value);
            if (is_array($tabs)) {
                $max_tabs = max($max_tabs, count($tabs));
            }
        }

        set_transient('custom_tabs_max_count', $max_tabs, HOUR_IN_SECONDS);
        return $max_tabs;
    }
}

// Inisialisasi plugin
add_action('plugins_loaded', function () {
    Custom_Product_Tabs_Importer::get_instance();
});

