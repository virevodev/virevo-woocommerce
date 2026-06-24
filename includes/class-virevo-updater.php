<?php
/**
 * Mises à jour automatiques depuis les releases GitHub (auto-distribution).
 *
 * Pour les pilotes : pas de WordPress.org. WordPress vérifie périodiquement la
 * dernière release publiée du dépôt et propose la mise à jour dans l'admin si
 * une version plus récente existe, avec le ZIP attaché à la release comme paquet.
 *
 * @package Virevo_For_WooCommerce
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Virevo_Updater {

	const CACHE_KEY = 'virevo_wc_latest_release';

	/** @var string */
	private $basename;
	/** @var string */
	private $slug;
	/** @var string */
	private $repo;
	/** @var string */
	private $version;

	public function __construct( $plugin_file, $repo, $version ) {
		$this->basename = plugin_basename( $plugin_file ); // dossier/fichier.php
		$this->slug     = dirname( $this->basename );      // dossier du plugin
		$this->repo     = $repo;                           // owner/repo GitHub
		$this->version  = $version;

		add_filter( 'pre_set_site_transient_update_plugins', array( $this, 'inject_update' ) );
		add_filter( 'plugins_api', array( $this, 'plugin_info' ), 20, 3 );
	}

	/**
	 * Dernière release publiée (mise en cache 6 h). Renvoie [] si indisponible
	 * (dépôt privé sans release publique, réseau, etc.) → aucun effet de bord.
	 */
	private function latest_release() {
		$cached = get_transient( self::CACHE_KEY );
		if ( false !== $cached ) {
			return $cached;
		}

		$response = wp_remote_get(
			'https://api.github.com/repos/' . $this->repo . '/releases/latest',
			array(
				'timeout' => 10,
				'headers' => array(
					'Accept'     => 'application/vnd.github+json',
					'User-Agent' => 'virevo-for-woocommerce',
				),
			)
		);

		if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
			set_transient( self::CACHE_KEY, array(), 3 * HOUR_IN_SECONDS );
			return array();
		}

		$data    = json_decode( wp_remote_retrieve_body( $response ), true );
		$version = isset( $data['tag_name'] ) ? ltrim( $data['tag_name'], 'v' ) : '';

		// On préfère un ZIP attaché à la release (structure de dossier correcte).
		$package = '';
		if ( ! empty( $data['assets'] ) && is_array( $data['assets'] ) ) {
			foreach ( $data['assets'] as $asset ) {
				if ( isset( $asset['name'] ) && '.zip' === substr( $asset['name'], -4 ) ) {
					$package = $asset['browser_download_url'];
					break;
				}
			}
		}

		$info = array(
			'version' => $version,
			'package' => $package,
			'url'     => isset( $data['html_url'] ) ? $data['html_url'] : '',
			'notes'   => isset( $data['body'] ) ? $data['body'] : '',
		);
		set_transient( self::CACHE_KEY, $info, 6 * HOUR_IN_SECONDS );
		return $info;
	}

	public function inject_update( $transient ) {
		if ( empty( $transient->checked ) ) {
			return $transient;
		}
		$latest = $this->latest_release();
		if ( empty( $latest['version'] ) || empty( $latest['package'] ) ) {
			return $transient;
		}
		if ( version_compare( $latest['version'], $this->version, '>' ) ) {
			$transient->response[ $this->basename ] = (object) array(
				'slug'        => $this->slug,
				'plugin'      => $this->basename,
				'new_version' => $latest['version'],
				'package'     => $latest['package'],
				'url'         => $latest['url'],
			);
		}
		return $transient;
	}

	public function plugin_info( $result, $action, $args ) {
		if ( 'plugin_information' !== $action || empty( $args->slug ) || $args->slug !== $this->slug ) {
			return $result;
		}
		$latest = $this->latest_release();
		if ( empty( $latest['version'] ) ) {
			return $result;
		}
		return (object) array(
			'name'          => 'Virevo for WooCommerce',
			'slug'          => $this->slug,
			'version'       => $latest['version'],
			'homepage'      => $latest['url'],
			'sections'      => array( 'changelog' => wpautop( esc_html( $latest['notes'] ) ) ),
			'download_link' => $latest['package'],
		);
	}
}
