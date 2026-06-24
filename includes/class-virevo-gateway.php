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
		$this->supports           = array( 'products', 'refunds' );

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
			'api_base'       => array(
				'title'       => __( "URL de l'API (avancé)", 'virevo-for-woocommerce' ),
				'type'        => 'text',
				'description' => __( "Laisser vide en production (https://app.virevo.fr). Pour un test local : http://localhost:3000", 'virevo-for-woocommerce' ),
				'default'     => '',
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

		$api          = new Virevo_API( $this->get_api_key(), $this->get_option( 'api_base' ) ?: 'https://app.virevo.fr' );
		$amount_cents = (int) round( (float) $order->get_total() * 100 );

		$resp = $api->create_payment(
			$amount_cents,
			'EUR',
			(string) $order->get_id(),
			'wc-' . $order_id, // idempotence par commande.
			$this->get_return_url( $order ), // page « commande reçue » après paiement.
			wc_get_checkout_url() // retour au checkout en cas d'annulation.
		);

		if ( is_wp_error( $resp ) ) {
			wc_add_notice( $resp->get_error_message(), 'error' );
			return array( 'result' => 'failure' );
		}

		// Mémorise l'identifiant Virevo pour la réconciliation au webhook.
		// On LAISSE la commande en « attente de paiement » (pending) : le stock
		// n'est décrémenté qu'à la confirmation du virement, par payment_complete()
		// (cœur WooCommerce, idempotent). Les commandes abandonnées sont
		// auto-annulées par WooCommerce (réglage « Conserver le stock (minutes) »),
		// ce qui libère le stock. On ajoute seulement une note (pas de transition).
		$order->update_meta_data( '_virevo_payment_id', sanitize_text_field( $resp['id'] ) );
		$order->add_order_note( __( 'Paiement Virevo initié — en attente de confirmation du virement (le stock sera décrémenté à la confirmation).', 'virevo-for-woocommerce' ) );
		$order->save();

		WC()->cart->empty_cart();

		return array(
			'result'   => 'success',
			'redirect' => esc_url_raw( $resp['payment_url'] ),
		);
	}

	/**
	 * Remboursement (total ou partiel) déclenché depuis l'admin WooCommerce.
	 * Renvoie true en cas de succès, WP_Error sinon (WooCommerce affiche le message).
	 *
	 * @param int        $order_id Identifiant de commande.
	 * @param float|null $amount   Montant à rembourser (null = total).
	 * @param string     $reason   Motif.
	 * @return bool|WP_Error
	 */
	public function process_refund( $order_id, $amount = null, $reason = '' ) {
		$order      = wc_get_order( $order_id );
		$payment_id = $order ? $order->get_meta( '_virevo_payment_id' ) : '';
		if ( ! $payment_id ) {
			return new WP_Error( 'virevo_refund', __( 'Identifiant de paiement Virevo introuvable sur cette commande.', 'virevo-for-woocommerce' ) );
		}

		$api          = new Virevo_API( $this->get_api_key(), $this->get_option( 'api_base' ) ?: 'https://app.virevo.fr' );
		$amount_cents = ( null !== $amount ) ? (int) round( (float) $amount * 100 ) : 0;

		$resp = $api->refund( $payment_id, $amount_cents, $reason );
		if ( is_wp_error( $resp ) ) {
			return $resp; // WooCommerce affiche le message (ex. « indisponible en production »).
		}

		$order->add_order_note(
			sprintf(
				/* translators: %s: montant remboursé. */
				__( 'Remboursement Virevo effectué : %s.', 'virevo-for-woocommerce' ),
				wc_price( null !== $amount ? $amount : $order->get_total() )
			)
		);
		return true;
	}
}
