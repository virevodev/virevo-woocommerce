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

		switch ( $event['type'] ?? '' ) {
			case 'payment.succeeded':
				self::mark_order_paid( $event );
				break;

			// Les TROIS façons dont un paiement se termine sans argent. Jusqu'au
			// 2026-09-24, elles étaient acquittées puis jetées : la commande
			// restait « en attente de paiement » indéfiniment, son stock réservé
			// avec elle, et le marchand faisait le ménage à la main sans savoir
			// lesquelles étaient mortes.
			case 'payment.failed':
			case 'payment.canceled':
			case 'payment.expired':
				self::close_unpaid_order( $event );
				break;

			// Remboursement décidé depuis le tableau de bord Virevo : il n'était
			// pas répercuté dans la boutique, dont les totaux devenaient faux.
			case 'payment.refunded':
				self::record_refund( $event );
				break;
		}

		// 2xx : on accuse réception (sinon Virevo réessaie). Un type inconnu est
		// acquitté volontairement — un événement ajouté plus tard ne doit pas
		// faire échouer la livraison chez les marchands qui n'ont pas mis à jour.
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
	 * Retrouve la commande d'un événement, ou null.
	 *
	 * La référence Virevo est l'ID de commande WooCommerce. On revérifie
	 * l'identifiant de paiement mémorisé : une référence seule pourrait désigner
	 * une commande étrangère à ce paiement.
	 */
	private static function resolve_order( array $event ) {
		$payment    = isset( $event['data']['payment'] ) ? $event['data']['payment'] : array();
		$reference  = isset( $payment['reference'] ) ? $payment['reference'] : '';
		$payment_id = isset( $payment['id'] ) ? $payment['id'] : '';

		$order = wc_get_order( (int) $reference );
		if ( ! $order ) {
			return null;
		}

		$stored = $order->get_meta( '_virevo_payment_id' );
		if ( $stored && $payment_id && $stored !== $payment_id ) {
			return null; // incohérence : on ignore.
		}
		return $order;
	}

	/** Marque la commande payée. */
	private static function mark_order_paid( array $event ) {
		$order = self::resolve_order( $event );
		if ( ! $order ) {
			return;
		}

		if ( $order->is_paid() ) {
			return; // idempotent : déjà réglée.
		}

		$payment    = isset( $event['data']['payment'] ) ? $event['data']['payment'] : array();
		$payment_id = isset( $payment['id'] ) ? $payment['id'] : '';

		$order->payment_complete( $payment_id );
		$order->add_order_note( __( 'Virement instantané reçu (Virevo).', 'virevo-for-woocommerce' ) );
	}

	/**
	 * Clôt une commande dont le paiement ne viendra pas.
	 *
	 * Deux gardes valent plus que le reste de la méthode.
	 *
	 * 1. **Une commande payée n'est jamais touchée.** Un `payment.failed` peut
	 *    arriver après un `payment.succeeded` — notification tardive, tentative
	 *    précédente notifiée en retard. Annuler alors une commande réglée serait
	 *    bien pire que de ne rien faire.
	 * 2. **Un état terminal n'est pas réécrit.** Le marchand a pu annuler ou
	 *    rembourser lui-même entre-temps ; sa décision prime sur un événement
	 *    qui redit ce qu'on sait déjà.
	 */
	private static function close_unpaid_order( array $event ) {
		$order = self::resolve_order( $event );
		if ( ! $order ) {
			return;
		}
		if ( $order->is_paid() ) {
			return;
		}
		if ( $order->has_status( array( 'cancelled', 'failed', 'refunded' ) ) ) {
			return;
		}

		$type = $event['type'] ?? '';
		if ( 'payment.failed' === $type ) {
			// « failed » est l'état WooCommerce d'un paiement REFUSÉ : la commande
			// reste visible et le client peut réessayer de payer.
			$order->update_status(
				'failed',
				__( 'Virement refusé (Virevo) : plafond dépassé, ou refus de la banque du client.', 'virevo-for-woocommerce' )
			);
			return;
		}

		$note = 'payment.expired' === $type
			? __( 'Demande de paiement Virevo expirée : elle n\'est plus payable.', 'virevo-for-woocommerce' )
			: __( 'Paiement Virevo annulé avant règlement.', 'virevo-for-woocommerce' );
		// « cancelled » libère le stock que WooCommerce avait réservé.
		$order->update_status( 'cancelled', $note );
	}

	/**
	 * Répercute dans la boutique un remboursement décidé chez Virevo.
	 *
	 * ⚠️ Le piège est la BOUCLE. Un remboursement lancé depuis l'admin
	 * WooCommerce appelle l'API Virevo, qui émet `payment.refunded`, qui revient
	 * ici : sans garde, on créerait une SECONDE ligne de remboursement et le
	 * total de la commande deviendrait faux.
	 *
	 * La passerelle mémorise donc l'identifiant de chaque remboursement qu'elle
	 * a elle-même déclenché (`_virevo_refund_ids`), et on ignore ceux qu'on
	 * connaît déjà. Ne passent que les remboursements décidés ailleurs.
	 */
	private static function record_refund( array $event ) {
		$order = self::resolve_order( $event );
		if ( ! $order ) {
			return;
		}

		$refund    = isset( $event['data']['refund'] ) ? $event['data']['refund'] : array();
		$refund_id = isset( $refund['id'] ) ? (string) $refund['id'] : '';
		$cents     = isset( $refund['amount_cents'] ) ? (int) $refund['amount_cents'] : 0;
		if ( '' === $refund_id || $cents <= 0 ) {
			return; // Sans identifiant, impossible de garantir l'idempotence.
		}

		$known = $order->get_meta( '_virevo_refund_ids' );
		$known = is_array( $known ) ? $known : array();
		if ( in_array( $refund_id, $known, true ) ) {
			return; // Déjà reflété : c'est nous qui l'avons déclenché.
		}

		$created = wc_create_refund(
			array(
				'order_id' => $order->get_id(),
				'amount'   => $cents / 100,
				'reason'   => __( 'Remboursement effectué depuis Virevo.', 'virevo-for-woocommerce' ),
			)
		);
		if ( is_wp_error( $created ) ) {
			$order->add_order_note(
				sprintf(
					/* translators: %s: message d'erreur. */
					__( 'Remboursement Virevo reçu mais non enregistré : %s', 'virevo-for-woocommerce' ),
					$created->get_error_message()
				)
			);
			return;
		}

		$known[] = $refund_id;
		$order->update_meta_data( '_virevo_refund_ids', $known );
		$order->save();
		$order->add_order_note(
			sprintf(
				/* translators: %s: montant remboursé. */
				__( 'Remboursement enregistré depuis Virevo : %s.', 'virevo-for-woocommerce' ),
				wc_price( $cents / 100 )
			)
		);
	}
}
