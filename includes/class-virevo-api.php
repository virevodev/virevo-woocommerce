<?php
/**
 * Client HTTP de l'API publique Virevo (/v1).
 *
 * @package Virevo_For_WooCommerce
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Virevo_API {

	/** @var string */
	private $api_key;
	/** @var string */
	private $base_url;

	public function __construct( $api_key, $base_url = 'https://app.virevo.fr' ) {
		$this->api_key  = $api_key;
		$this->base_url = rtrim( $base_url, '/' );
	}

	/**
	 * Crée un paiement. Renvoie le tableau décodé, ou WP_Error.
	 *
	 * @param int    $amount_cents    Montant en centimes.
	 * @param string $currency        Devise (EUR).
	 * @param string $reference       Référence marchand (ID de commande).
	 * @param string $idempotency_key Clé d'idempotence (évite les doublons).
	 * @param string $return_url      Redirection après paiement réussi.
	 * @param string $cancel_url      Redirection si annulation.
	 * @return array|WP_Error
	 */
	public function create_payment( $amount_cents, $currency, $reference, $idempotency_key, $return_url = '', $cancel_url = '' ) {
		$body = array(
			'amount_cents' => (int) $amount_cents,
			'currency'     => $currency,
			'reference'    => $reference,
		);
		if ( $return_url ) {
			$body['return_url'] = $return_url;
		}
		if ( $cancel_url ) {
			$body['cancel_url'] = $cancel_url;
		}

		$response = wp_remote_post(
			$this->base_url . '/v1/payments',
			array(
				'timeout' => 20,
				'headers' => array(
					'Authorization'   => 'Bearer ' . $this->api_key,
					'Content-Type'    => 'application/json',
					'Idempotency-Key' => $idempotency_key,
				),
				'body'    => wp_json_encode( $body ),
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( $code < 200 || $code >= 300 ) {
			return new WP_Error( 'virevo_api_error', self::error_message( $code, $body ) );
		}

		return is_array( $body ) ? $body : new WP_Error( 'virevo_api_error', 'Réponse Virevo illisible.' );
	}

	/**
	 * Rembourse un paiement (total si $amount_cents <= 0, sinon partiel).
	 *
	 * @param string $payment_id   Identifiant du paiement Virevo.
	 * @param int    $amount_cents Montant en centimes (0 = total restant).
	 * @param string $reason       Motif (optionnel).
	 * @return array|WP_Error
	 */
	public function refund( $payment_id, $amount_cents = 0, $reason = '' ) {
		$body = array();
		if ( (int) $amount_cents > 0 ) {
			$body['amount_cents'] = (int) $amount_cents;
		}
		if ( '' !== $reason ) {
			$body['reason'] = $reason;
		}

		$response = wp_remote_post(
			$this->base_url . '/v1/payments/' . rawurlencode( $payment_id ) . '/refund',
			array(
				'timeout' => 20,
				'headers' => array(
					'Authorization' => 'Bearer ' . $this->api_key,
					'Content-Type'  => 'application/json',
				),
				'body'    => wp_json_encode( (object) $body ),
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		$decoded = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( $code < 200 || $code >= 300 ) {
			return new WP_Error( 'virevo_refund_error', self::error_message( $code, $decoded ) );
		}
		return is_array( $decoded ) ? $decoded : new WP_Error( 'virevo_refund_error', 'Réponse Virevo illisible.' );
	}

	/** Extrait le message d'erreur de l'API ({"error":{"message":…}}). */
	private static function error_message( $code, $body ) {
		if ( is_array( $body ) && isset( $body['error']['message'] ) ) {
			return $body['error']['message'];
		}
		if ( is_array( $body ) && ! empty( $body['message'] ) ) {
			return $body['message'];
		}
		return sprintf( 'Erreur API Virevo (HTTP %d).', $code );
	}
}
