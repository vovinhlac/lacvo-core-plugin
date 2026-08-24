<?php
/**
 * Plugin Name: Lacvo Core — Portfolio Snapshot
 * Plugin URI: https://vovinhlac.com/
 * Description: Focused WooCommerce engineering snapshot demonstrating encrypted digital fulfilment and multi-currency order snapshots.
 * Version: 2.1.10
 * Requires at least: 6.8
 * Requires PHP: 8.0
 * Requires Plugins: woocommerce
 * Author: Vo Vinh Lac
 * Author URI: https://vovinhlac.com/
 * License: GPLv2 or later
 * Text Domain: lacvo-core
 *
 * @package Lacvo_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'LACVO_CORE_VERSION', '2.1.10' );
define( 'LACVO_CORE_FILE', __FILE__ );
define( 'LACVO_CORE_DIR', plugin_dir_path( __FILE__ ) );

require_once LACVO_CORE_DIR . 'includes/class-lacvo-core-crypto.php';
require_once LACVO_CORE_DIR . 'includes/class-lacvo-core-license-repository.php';
require_once LACVO_CORE_DIR . 'includes/class-lacvo-core-order-delivery.php';
require_once LACVO_CORE_DIR . 'includes/class-lacvo-core-multi-currency.php';

/** Create the encrypted inventory table used by the public snapshot. */
function lacvo_core_portfolio_activate(): void {
	global $wpdb;
	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	$table   = $wpdb->prefix . 'lacvo_license_codes';
	$charset = $wpdb->get_charset_collate();

	dbDelta(
		"CREATE TABLE {$table} (
			id bigint unsigned NOT NULL AUTO_INCREMENT,
			product_id bigint unsigned NOT NULL DEFAULT 0,
			variation_id bigint unsigned NOT NULL DEFAULT 0,
			code_encrypted longtext NOT NULL,
			code_hash char(64) NOT NULL,
			status varchar(20) NOT NULL DEFAULT 'available',
			order_id bigint unsigned NOT NULL DEFAULT 0,
			order_item_id bigint unsigned NOT NULL DEFAULT 0,
			created_at datetime NOT NULL,
			assigned_at datetime NULL,
			PRIMARY KEY (id),
			UNIQUE KEY code_hash (code_hash),
			KEY inventory_lookup (product_id, variation_id, status),
			KEY order_item (order_item_id, status)
		) {$charset};"
	);
}
register_activation_hook( __FILE__, 'lacvo_core_portfolio_activate' );

/** Declare modern WooCommerce storage/checkout compatibility. */
function lacvo_core_portfolio_declare_compatibility(): void {
	if ( class_exists( '\\Automattic\\WooCommerce\\Utilities\\FeaturesUtil' ) ) {
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', LACVO_CORE_FILE, true );
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', LACVO_CORE_FILE, true );
	}
}
add_action( 'before_woocommerce_init', 'lacvo_core_portfolio_declare_compatibility' );

/** Boot the selected services after WooCommerce is available. */
function lacvo_core_portfolio_boot(): void {
	if ( ! class_exists( 'WooCommerce' ) ) {
		return;
	}

	$repository = new Lacvo_Core_License_Repository();
	( new Lacvo_Core_Order_Delivery( $repository ) )->register();
	Lacvo_Core_Multi_Currency::instance()->register();
}
add_action( 'plugins_loaded', 'lacvo_core_portfolio_boot', 20 );
