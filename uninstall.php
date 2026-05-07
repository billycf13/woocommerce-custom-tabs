<?php
/**
 * Uninstall handler for WooCommerce Custom Product Tabs Importer
 *
 * Bersihkan data plugin saat dihapus dari WordPress.
 */

if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

global $wpdb;

// Hapus semua post meta _custom_product_tabs
$wpdb->delete($wpdb->postmeta, array('meta_key' => '_custom_product_tabs'));

// Hapus transient cache
delete_transient('custom_tabs_max_count');
