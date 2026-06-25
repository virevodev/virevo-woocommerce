<?php
/**
 * Plugin Name: Virevo for WooCommerce
 * Plugin URI: https://virevo.fr/developpeurs.html
 * Description: Encaissez par virement instantané (Virevo) dans WooCommerce — sans frais de carte. Lien de paiement + confirmation par webhook signé.
 * Version: 0.5.1
 * Author: Virevo
 * Author URI: https://virevo.fr
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: virevo-for-woocommerce
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Requires Plugins: woocommerce
 * WC requires at least: 7.0
 *
 * @package Virevo_For_WooCommerce
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Accès direct interdit.
}

define( 'VIREVO_WC_VERSION', '0.5.1' );
define( 'VIREVO_WC_PATH', plugin_dir_path( __FILE__ ) );
define( 'VIREVO_WC_FILE', __FILE__ );

/**
 * Compatibilité High-Performance Order Storage (HPOS) + checkout par blocs.
 */
add_action(
	'before_woocommerce_init',
	function () {
		if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', __FILE__, true );
		}
	}
);

/**
 * Enregistre la passerelle de paiement + le webhook une fois WooCommerce chargé.
 */
add_action(
	'plugins_loaded',
	function () {
		if ( ! class_exists( 'WC_Payment_Gateway' ) ) {
			return; // WooCommerce absent : on ne fait rien.
		}

		require_once VIREVO_WC_PATH . 'includes/class-virevo-api.php';
		require_once VIREVO_WC_PATH . 'includes/class-virevo-gateway.php';
		require_once VIREVO_WC_PATH . 'includes/class-virevo-webhook.php';

		add_filter(
			'woocommerce_payment_gateways',
			function ( $gateways ) {
				$gateways[] = 'WC_Gateway_Virevo';
				return $gateways;
			}
		);

		Virevo_Webhook::init();
	}
);

/**
 * Intégration au checkout par BLOCS (WooCommerce Blocks).
 */
add_action(
	'woocommerce_blocks_loaded',
	function () {
		if ( ! class_exists( \Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType::class ) ) {
			return;
		}
		require_once VIREVO_WC_PATH . 'includes/class-virevo-blocks.php';
		add_action(
			'woocommerce_blocks_payment_method_type_registration',
			function ( $registry ) {
				$registry->register( new Virevo_Blocks() );
			}
		);
	}
);

/**
 * Mises à jour automatiques depuis les releases GitHub (auto-distribution, pilotes).
 * Nécessite une release publiée (et un dépôt/release public pour les pilotes).
 */
add_action(
	'admin_init',
	function () {
		require_once VIREVO_WC_PATH . 'includes/class-virevo-updater.php';
		new Virevo_Updater( VIREVO_WC_FILE, 'virevodev/virevo-woocommerce', VIREVO_WC_VERSION );
	}
);

/**
 * Lien « Réglages » sur la liste des extensions.
 */
add_filter(
	'plugin_action_links_' . plugin_basename( __FILE__ ),
	function ( $links ) {
		$url = admin_url( 'admin.php?page=wc-settings&tab=checkout&section=virevo' );
		array_unshift( $links, '<a href="' . esc_url( $url ) . '">' . esc_html__( 'Réglages', 'virevo-for-woocommerce' ) . '</a>' );
		return $links;
	}
);
