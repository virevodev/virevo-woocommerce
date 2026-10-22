<?php
/**
 * Plugin Name: Virevo mock (captures locales uniquement)
 * Répond à la place de l'API Virevo pour les appels vers http://mock-virevo :
 * la création de paiement renvoie un identifiant et redirige vers la page
 * « commande reçue », sans aucun appel réseau.
 */
add_filter(
	'pre_http_request',
	function ( $pre, $args, $url ) {
		if ( 0 !== strpos( $url, 'http://mock-virevo/' ) ) {
			return $pre;
		}
		$body = json_decode( $args['body'], true );
		return array(
			'headers'  => array(),
			'body'     => wp_json_encode(
				array(
					'id'          => 'pay_demo_' . $body['reference'],
					'payment_url' => isset( $body['return_url'] ) ? $body['return_url'] : home_url(),
				)
			),
			'response' => array( 'code' => 201, 'message' => 'Created' ),
			'cookies'  => array(),
			'filename' => null,
		);
	},
	10,
	3
);

// Pas de serveur de courrier dans le conteneur : on simule l'envoi pour ne pas
// polluer les notes de commande avec des échecs d'envoi.
add_filter( 'pre_wp_mail', '__return_true' );
