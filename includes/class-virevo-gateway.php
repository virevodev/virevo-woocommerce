<?php
/**
 * Passerelle de paiement Virevo (virement instantané).
 *
 * @package Virevo_For_WooCommerce
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WC_Gateway_Virevo extends WC_Payment_Gateway {

	public function __construct() {
		$this->id                 = 'virevo';
		$this->method_title       = __( 'Virevo — virement instantané', 'virevo-for-woocommerce' );
		$this->method_description = __( 'Encaissez par virement instantané, sans frais de carte. Le client est redirigé vers une page de paiement ; la commande est validée à réception du virement (webhook signé).', 'virevo-for-woocommerce' );
		$this->has_fields         = false;
		$this->supports           = array( 'products' );

		$this->init_form_fields();
		$this->init_settings();

		$this->title       = $this->get_option( 'title' );
		$this->description = $this->get_option( 'description' );

		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
	}

	public function init_form_fields() {
		$this->form_fields = array(
			'enabled'        => array(
				'title'   => __( 'Activer / Désactiver', 'virevo-for-woocommerce' ),
				'type'    => 'checkbox',
				'label'   => __( 'Activer le paiement par virement instantané Virevo', 'virevo-for-woocommerce' ),
				'default' => 'no',
			),
			'title'          => array(
				'title'   => __( 'Titre', 'virevo-for-woocommerce' ),
				'type'    => 'text',
				'default' => __( 'Virement instantané', 'virevo-for-woocommerce' ),
			),
			'description'    => array(
				'title'   => __( 'Description', 'virevo-for-woocommerce' ),
				'type'    => 'textarea',
				'default' => __( 'Payez par virement instantané, sans frais de carte.', 'virevo-for-woocommerce' ),
			),
			'mode'           => array(
				'title'   => __( 'Mode', 'virevo-for-woocommerce' ),
				'type'    => 'select',
				'options' => array(
					'test' => __( 'Test (bac à sable)', 'virevo-for-woocommerce' ),
					'live' => __( 'Live (réel)', 'virevo-for-woocommerce' ),
				),
				'default' => 'test',
			),
			'test_api_key'   => array(
				'title' => __( 'Clé API test', 'virevo-for-woocommerce' ),
				'type'  => 'password',
			),
			'live_api_key'   => array(
				'title' => __( 'Clé API live', 'virevo-for-woocommerce' ),
				'type'  => 'password',
			),
			'webhook_secret' => array(
				'title'       => __( 'Secret de webhook', 'virevo-for-woocommerce' ),
				'type'        => 'password',
				/* translators: %s: URL du webhook à coller dans Virevo. */
				'description' => sprintf(
					__( 'Secret « whsec_… » généré dans Virevo → Développeurs. URL de webhook à y enregistrer : %s', 'virevo-for-woocommerce' ),
					'<code>' . esc_html( rest_url( 'virevo/v1/webhook' ) ) . '</code>'
				),
			),
		);
	}

	private function get_api_key() {
		return 'live' === $this->get_option( 'mode' )
			? $this->get_option( 'live_api_key' )
			: $this->get_option( 'test_api_key' );
	}

	/**
	 * Traite le paiement : crée une demande Virevo et redirige vers payment_url.
	 *
	 * @param int $order_id Identifiant de commande.
	 * @return array
	 */
	public function process_payment( $order_id ) {
		$order = wc_get_order( $order_id );

		if ( 'EUR' !== get_woocommerce_currency() ) {
			wc_add_notice( __( 'Virevo ne prend en charge que les paiements en euros.', 'virevo-for-woocommerce' ), 'error' );
			return array( 'result' => 'failure' );
		}

		$api          = new Virevo_API( $this->get_api_key() );
		$amount_cents = (int) round( (float) $order->get_total() * 100 );

		$resp = $api->create_payment(
			$amount_cents,
			'EUR',
			(string) $order->get_id(),
			'wc-' . $order_id // idempotence par commande.
		);

		if ( is_wp_error( $resp ) ) {
			wc_add_notice( $resp->get_error_message(), 'error' );
			return array( 'result' => 'failure' );
		}

		// Mémorise l'identifiant Virevo pour la réconciliation au webhook.
		$order->update_meta_data( '_virevo_payment_id', sanitize_text_field( $resp['id'] ) );
		$order->update_status( 'on-hold', __( 'En attente du virement instantané (Virevo).', 'virevo-for-woocommerce' ) );
		$order->save();

		WC()->cart->empty_cart();

		return array(
			'result'   => 'success',
			'redirect' => esc_url_raw( $resp['payment_url'] ),
		);
	}
}
