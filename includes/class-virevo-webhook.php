<?php
/**
 * Réception des webhooks Virevo (payment.succeeded) — signature vérifiée.
 *
 * @package Virevo_For_WooCommerce
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Virevo_Webhook {

	const TOLERANCE_SECONDS = 300;

	public static function init() {
		add_action(
			'rest_api_init',
			function () {
				register_rest_route(
					'virevo/v1',
					'/webhook',
					array(
						'methods'             => 'POST',
						'callback'            => array( __CLASS__, 'handle' ),
						'permission_callback' => '__return_true', // sécurité = signature HMAC.
					)
				);
			}
		);
	}

	/**
	 * Récupère le secret de webhook configuré sur la passerelle.
	 */
	private static function get_secret() {
		$settings = get_option( 'woocommerce_virevo_settings', array() );
		return isset( $settings['webhook_secret'] ) ? $settings['webhook_secret'] : '';
	}

	public static function handle( WP_REST_Request $request ) {
		$payload = $request->get_body();
		// WordPress normalise l'en-tête « Virevo-Signature » en « virevo_signature ».
		$signature = $request->get_header( 'virevo_signature' );

		if ( ! self::verify_signature( self::get_secret(), $signature, $payload ) ) {
			return new WP_REST_Response( array( 'error' => 'invalid signature' ), 400 );
		}

		$event = json_decode( $payload, true );
		if ( ! is_array( $event ) ) {
			return new WP_REST_Response( array( 'error' => 'invalid payload' ), 400 );
		}

		if ( 'payment.succeeded' === ( $event['type'] ?? '' ) ) {
			self::mark_order_paid( $event );
		}

		// 2xx : on accuse réception (sinon Virevo réessaie).
		return new WP_REST_Response( array( 'received' => true ), 200 );
	}

	/**
	 * Valide la signature « t=<unix>,v1=<hmac>[,v1=<hmac>] » (HMAC-SHA256 de
	 * "<t>.<corps>"). L'en-tête peut porter PLUSIEURS v1 pendant une rotation de
	 * secret (ancien + nouveau) : on accepte si l'un d'eux correspond.
	 */
	private static function verify_signature( $secret, $header, $body ) {
		if ( empty( $secret ) || empty( $header ) ) {
			return false;
		}
		$t    = null;
		$sigs = array();
		foreach ( explode( ',', $header ) as $part ) {
			$kv = explode( '=', $part, 2 );
			if ( count( $kv ) !== 2 ) {
				continue;
			}
			$k = trim( $kv[0] );
			$v = trim( $kv[1] );
			if ( 't' === $k ) {
				$t = (int) $v;
			} elseif ( 'v1' === $k && '' !== $v ) {
				$sigs[] = $v;
			}
		}
		if ( null === $t || empty( $sigs ) ) {
			return false;
		}
		if ( abs( time() - $t ) > self::TOLERANCE_SECONDS ) {
			return false; // anti-rejeu.
		}
		$expected = hash_hmac( 'sha256', $t . '.' . $body, $secret );
		foreach ( $sigs as $v1 ) {
			if ( hash_equals( $expected, $v1 ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Marque la commande payée. La référence Virevo = ID de commande WooCommerce.
	 * On vérifie en plus l'identifiant de paiement mémorisé (défense).
	 */
	private static function mark_order_paid( array $event ) {
		$payment = isset( $event['data']['payment'] ) ? $event['data']['payment'] : array();
		$reference  = isset( $payment['reference'] ) ? $payment['reference'] : '';
		$payment_id = isset( $payment['id'] ) ? $payment['id'] : '';

		$order = wc_get_order( (int) $reference );
		if ( ! $order ) {
			return;
		}

		$stored = $order->get_meta( '_virevo_payment_id' );
		if ( $stored && $payment_id && $stored !== $payment_id ) {
			return; // incohérence : on ignore.
		}

		if ( $order->is_paid() ) {
			return; // idempotent : déjà réglée.
		}

		$order->payment_complete( $payment_id );
		$order->add_order_note( __( 'Virement instantané reçu (Virevo).', 'virevo-for-woocommerce' ) );
	}
}
