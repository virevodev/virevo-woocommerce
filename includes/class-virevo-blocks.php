<?php
/**
 * Intégration de la passerelle au checkout par BLOCS (WooCommerce Blocks).
 *
 * @package Virevo_For_WooCommerce
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;

final class Virevo_Blocks extends AbstractPaymentMethodType {

	protected $name = 'virevo';

	public function initialize() {
		$this->settings = get_option( 'woocommerce_virevo_settings', array() );
	}

	/**
	 * Délègue à la passerelle classique.
	 *
	 * Se contenter de `enabled` laissait le checkout par BLOCS proposer Virevo
	 * alors que la passerelle classique, elle, le masquait faute de clé ou hors
	 * zone euro. Or les blocs sont le checkout par défaut des boutiques
	 * récentes : c'est le chemin le PLUS emprunté qui était le moins gardé.
	 *
	 * `WC_Gateway_Virevo::is_available()` reste donc la seule règle, et les deux
	 * tunnels ne peuvent plus diverger.
	 */
	public function is_active() {
		if ( ! function_exists( 'WC' ) || ! WC()->payment_gateways() ) {
			return false;
		}
		$gateways = WC()->payment_gateways()->payment_gateways();
		if ( empty( $gateways['virevo'] ) ) {
			return false;
		}
		return $gateways['virevo']->is_available();
	}

	public function get_payment_method_script_handles() {
		wp_register_script(
			'virevo-blocks',
			plugins_url( 'assets/blocks.js', dirname( __FILE__ ) ),
			array( 'wc-blocks-registry', 'wc-settings', 'wp-element', 'wp-html-entities' ),
			VIREVO_WC_VERSION,
			true
		);
		return array( 'virevo-blocks' );
	}

	public function get_payment_method_data() {
		return array(
			'title'       => isset( $this->settings['title'] ) ? $this->settings['title'] : 'Virement instantané',
			'description' => isset( $this->settings['description'] ) ? $this->settings['description'] : '',
			'supports'    => array( 'products' ),
			// Le checkout par blocs recalcule le panier sans recharger la page :
			// le minimum doit donc aussi être vérifié côté navigateur.
			'minAmountCents' => WC_Gateway_Virevo::MIN_AMOUNT_CENTS,
		);
	}
}
