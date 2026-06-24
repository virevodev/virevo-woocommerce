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

	public function is_active() {
		return ! empty( $this->settings['enabled'] ) && 'yes' === $this->settings['enabled'];
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
		);
	}
}
