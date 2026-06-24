<?php
/**
 * Désinstallation : supprime les réglages de la passerelle.
 *
 * @package Virevo_For_WooCommerce
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

delete_option( 'woocommerce_virevo_settings' );
