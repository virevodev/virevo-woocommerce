<?php
/**
 * Plugin Name: Virevo for WooCommerce
 * Plugin URI: https://virevo.fr/developpeurs
 * Description: Encaissez par virement instantané (Virevo) dans WooCommerce — sans frais de carte. Lien de paiement + confirmation par webhook signé.
 * Version: 0.6.0
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

define( 'VIREVO_WC_VERSION', '0.6.0' );
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
 * Bandeau d'administration : dire l'état, en permanence.
 *
 * Le danger n'est pas le mode test, c'est un mode test qu'on a OUBLIÉ. Une
 * boutique en production réglée sur Test affiche « Virement instantané » au
 * checkout, les clients commandent, et rien n'arrive jamais, sans le moindre
 * signal. Même chose pour une passerelle activée sans clé.
 *
 * Le bandeau n'est donc PAS masquable : il disparaît quand la situation est
 * corrigée, pas quand on clique dessus.
 */
add_action(
	'admin_notices',
	function () {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		$settings = get_option( 'woocommerce_virevo_settings', array() );
		if ( ! is_array( $settings ) || 'yes' !== ( $settings['enabled'] ?? 'no' ) ) {
			return; // Passerelle désactivée : rien à signaler.
		}

		$mode   = 'live' === ( $settings['mode'] ?? 'test' ) ? 'live' : 'test';
		$key    = trim( (string) ( 'live' === $mode ? ( $settings['live_api_key'] ?? '' ) : ( $settings['test_api_key'] ?? '' ) ) );
		$secret = trim( (string) ( $settings['webhook_secret'] ?? '' ) );
		$url    = admin_url( 'admin.php?page=wc-settings&tab=checkout&section=virevo' );

		if ( '' === $key ) {
			printf(
				'<div class="notice notice-error"><p><strong>%s</strong> %s <a href="%s">%s</a></p></div>',
				esc_html__( 'Virevo est activé mais aucune clé d\'API n\'est renseignée.', 'virevo-for-woocommerce' ),
				esc_html__( 'Le moyen de paiement reste masqué au checkout tant que la clé manque.', 'virevo-for-woocommerce' ),
				esc_url( $url ),
				esc_html__( 'Renseigner la clé', 'virevo-for-woocommerce' )
			);
			return;
		}

		if ( '' === $secret ) {
			printf(
				'<div class="notice notice-warning"><p><strong>%s</strong> %s <a href="%s">%s</a></p></div>',
				esc_html__( 'Virevo : aucun secret de webhook.', 'virevo-for-woocommerce' ),
				esc_html__( 'Vos clients pourront payer, mais les commandes resteront « en attente de paiement » : rien ne viendra confirmer l\'encaissement.', 'virevo-for-woocommerce' ),
				esc_url( $url ),
				esc_html__( 'Configurer le webhook', 'virevo-for-woocommerce' )
			);
			return;
		}

		if ( 'test' === $mode ) {
			printf(
				'<div class="notice notice-warning"><p><strong>%s</strong> %s <a href="%s">%s</a></p></div>',
				esc_html__( 'Virevo est en mode test.', 'virevo-for-woocommerce' ),
				esc_html__( 'Les paiements sont fictifs : aucun argent n\'est réellement encaissé.', 'virevo-for-woocommerce' ),
				esc_url( $url ),
				esc_html__( 'Passer en mode live', 'virevo-for-woocommerce' )
			);
		}
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
 *
 * ⚠️ INTERDIT sur WordPress.org. La directive 8 du dépôt officiel proscrit
 * « servir des mises à jour, ou installer des extensions, des thèmes ou des
 * modules depuis des serveurs autres que ceux de WordPress.org ». Une extension
 * publiée là-bas se met à jour par WordPress.org, point.
 *
 * Le fichier est donc RETIRÉ du paquet destiné au dépôt officiel
 * (`bash bin/build-zip.sh --wporg`), et l'auto-distribution le garde. D'où le
 * test d'existence : le code ne s'active que si le fichier est présent, sans
 * erreur fatale dans le cas contraire.
 */
add_action(
	'admin_init',
	function () {
		$updater = VIREVO_WC_PATH . 'includes/class-virevo-updater.php';
		if ( ! file_exists( $updater ) ) {
			return; // Paquet WordPress.org : les mises à jour viennent du dépôt officiel.
		}
		require_once $updater;
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
