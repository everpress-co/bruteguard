<?php

class BruteGuard {

	private $user_ip;
	private $use_https;
	private $api_key;
	private $local_host;
	private $api_endpoint;
	private $admin;
	public $last_request;
	public $last_response_raw;
	public $last_response;


	public function __construct() {

		add_filter( 'authenticate', array( &$this, 'check_preauth' ), 10, 3 );
		add_action( 'wp_login_failed', array( &$this, 'log_failed_attempt' ) );
		add_action( 'admin_init', array( $this, 'maybe_update_headers' ) );
	}


	public function deactivate() {
		$this->call( 'deactivate' );
	}

	public function activate() {
		$this->call( 'activate' );
	}

	public function check_preauth( $user ) {
		$this->check_loginability( true );

		return $user;
	}

	public function maybe_update_headers() {

		$updated_recently = $this->get_transient( 'bruteguard_headers_updated_recently' );

		if ( ! $updated_recently && current_user_can( 'update_plugins' ) ) {

			$this->set_transient( 'bruteguard_headers_updated_recently', 1, DAY_IN_SECONDS );

			$headers        = $this->get_headers();
			$trusted_header = 'REMOTE_ADDR';

			if ( count( $headers ) == 1 ) {
				$trusted_header = key( $headers );
			} elseif ( count( $headers ) > 1 ) {
				foreach ( $headers as $header => $ip ) {

					$ips = explode( ', ', $ip );

					$ip_list_has_nonprivate_ip = false;
					foreach ( $ips as $ip ) {
						$ip = $this->clean_ip( $ip );

						if ( $ip == '127.0.0.1' || $ip == '::1' || $this->is_local( $ip ) ) {
							continue;
						} else {
							$ip_list_has_nonprivate_ip = true;
							break;
						}
					}

					if ( ! $ip_list_has_nonprivate_ip ) {
						continue;
					}

					$trusted_header = $header;
					break;
				}
			}
			update_site_option( 'trusted_ip_header', $trusted_header );
		}
	}

	private function clean_ip( $ip ) {
		$ip = trim( $ip );

		if ( preg_match( '/^::ffff:(\d+\.\d+\.\d+\.\d+)$/', $ip, $matches ) ) {
			$ip = $matches[1];
		}

		return $ip;
	}
	private function set_transient( $transient, $value, $expiration ) {
		if ( is_multisite() && ! is_main_site() ) {
			switch_to_blog( $this->get_main_blog_id() );
			$return = set_transient( $transient, $value, $expiration );
			restore_current_blog();
			return $return;
		}
		return set_transient( $transient, $value, $expiration );
	}

	private function delete_transient( $transient ) {
		if ( is_multisite() && ! is_main_site() ) {
			switch_to_blog( $this->get_main_blog_id() );
			$return = delete_transient( $transient );
			restore_current_blog();
			return $return;
		}
		return delete_transient( $transient );
	}

	private function get_transient( $transient ) {
		if ( is_multisite() && ! is_main_site() ) {
			switch_to_blog( $this->get_main_blog_id() );
			$return = get_transient( $transient );
			restore_current_blog();
			return $return;
		}
		return get_transient( $transient );
	}

	public function get_main_blog_id() {
		if ( ! is_multisite() ) {
			return false;
		}

		return get_current_blog_id();
	}

	public function get_api_link( $email ) {

		$url = $this->endpoint();

		$url = add_query_arg(
			array(
				'action' => 'register',
				'domain' => $this->get_local_host(),
				'email'  => $email,
			),
			$url
		);

		return $url;

	}

	public function log_failed_attempt() {

		if ( $this->is_whitelisted() ) {
			return true;
		}

		do_action( 'bruteguard_log_failed_attempt', $this->get_ip() );
		$result = $this->call( 'failed_attempt' );

		if ( isset( $result['status'] ) && 'ko' == $result['status'] ) {
			$this->kill( $result );
		}
	}


	public function get_local_host() {

		if ( isset( $this->local_host ) ) {
			return $this->local_host;
		}

		$uri = 'http://' . strtolower( $_SERVER['HTTP_HOST'] );

		if ( is_multisite() ) {
			$uri = network_home_url();
		}

		$uridata = parse_url( $uri );

		$domain = $uridata['host'];

		if ( ! $domain ) {
			$uri     = get_site_url( 1 );
			$uridata = parse_url( $uri );
			$domain  = $uridata['host'];
		}

		$this->local_host = $domain;

		return $this->local_host;
	}

	private function call( $action = 'check_ip', $body = array(), $sign = false ) {
		global $wp_version, $wpdb, $current_user;

		if ( $this->api_key ) {
			$api_key = $this->api_key;
		} else {
			$api_key = get_site_option( 'bruteguard_apikey' );
		}

		$brute_ua = "WordPress/{$wp_version} | BruteGuard/" . BRUTEGUARD_VERSION;

		$body    = array(
			'action'             => $action,
			'ip'                 => $this->get_ip(),
			'bruteguard_version' => BRUTEGUARD_VERSION,
			'wordpress_version'  => strval( $wp_version ),
			'multisite'          => 0,
		);
		$headers = array(
			'x-domain' => $this->get_local_host(),
			'x-apikey' => $api_key,
		);

		if ( is_object( $current_user ) && isset( $current_user->ID ) ) {
			$body['wp_user_id'] = $current_user->ID;
		}

		if ( is_multisite() ) {
			$body['multisite'] = get_blog_count();
			if ( ! $body['multisite'] ) {
				$body['multisite'] = $wpdb->get_var( "SELECT COUNT(blog_id) FROM $wpdb->blogs WHERE spam = '0' AND deleted = '0' and archived = '0'" );
			}
		}

		$this->last_request = $body;

		$args = array(
			'body'        => $body,
			'headers'     => $headers,
			'user-agent'  => $brute_ua,
			'httpversion' => '1.0',
			'timeout'     => 20,
		);

		$response_json = wp_remote_post( $this->endpoint(), $args );

		$response_body = wp_remote_retrieve_body( $response_json );

		$this->last_response_raw = $response_json;

		$headers        = $this->get_headers();
		$header_hash    = md5( json_encode( $headers ) );
		$transient_name = 'bruteguard_login_' . $header_hash;

		$this->delete_transient( $transient_name );

		if ( is_array( $response_json ) ) {
			$response = json_decode( $response_json['body'], true );
		}

		if ( isset( $response['status'] ) && ! isset( $response['error'] ) ) :

			if ( isset( $response['seconds_remaining'] ) ) :
				$response['expire'] = time() + $response['seconds_remaining'];
				$this->set_transient( $transient_name, $response, $response['seconds_remaining'] );
			endif;

			else :
				$this->set_transient( 'bruteguarde_use_captcha', 1, 600 );
				$response['status']  = 'ok';
				$response['catpcha'] = true;
		endif;

			if ( isset( $response['error'] ) ) :
				 update_site_option( 'bruteguard_error', $response['error'] );
		else :
				delete_site_option( 'bruteguard_error' );
		endif;

		$this->last_response = $response;
		return $response;
	}

	public function endpoint() {

		if ( isset( $this->api_endpoint ) ) {
			return $this->api_endpoint;
		}

		$https   = $this->get_transient( 'bruteguard_https' );
		$api_url = trailingslashit( BRUTEGUARD_API_ENDPOINT );
		if ( $https == 'yes' ) {
			$this->api_endpoint = set_url_scheme( $api_url, 'https' );
		} else {
			$this->api_endpoint = set_url_scheme( $api_url, 'http' );
		}

		if ( ! $https ) {
			 $test = wp_remote_get( set_url_scheme( $api_url, 'https' ) . 'check.php' );

			$https = 'no';
			if ( ! is_wp_error( $test ) && $test['body'] == 'ok' ) {
				$https              = 'yes';
				$this->api_endpoint = set_url_scheme( $api_url, 'https' );
			}
			$this->set_transient( 'bruteguard_https', $https, 86 );
		}

		return $this->api_endpoint;
	}

	public function get_stats( $force = false ) {

		$stats  = $this->get_transient( 'bruteguard_stats' );
		$status = get_site_option( 'bruteguard_apikey_status', 'inactive' );
		$apikey = get_site_option( 'bruteguard_apikey' );

		if ( $status != 'verified' && $apikey ) {
			$r = $this->verify_apikey( $apikey );
			if ( isset( $r['code'] ) && 401 == $r['code'] ) {
				update_site_option( 'bruteguard_apikey_status', 'pending' );
			} elseif ( ! isset( $r['code'] ) && isset( $r['status'] ) && 'ok' == $r['status'] ) {
				update_site_option( 'bruteguard_apikey_status', 'verified' );
			}
		}

		if ( ! $stats || $force ) {

			$recheck = 1800;

			$stats    = array();
			$response = $this->call( 'stats' );

			if ( ! isset( $response['error'] ) ) {
				$stats['stats'] = $response['stats'];
				$stats['rate']  = $response['rate'];
				if ( isset( $response['refresh'] ) ) {
					$recheck = intval( $response['refresh'] );
				}
			} else {
				$recheck = 900;
				if ( $response['code'] == 403 ) {
					// update_site_option( 'bruteguard_apikey', '' );
					// update_site_option( 'bruteguard_apikey_status', 'inactive' );
				}
				$stats = new WP_Error( $response['code'], $response['error'] );
			}

			$this->set_transient( 'bruteguard_stats', $stats, $recheck );
		}

		return $stats;

	}

	private function get_headers() {

		if ( isset( $this->headers ) ) {
			return $this->headers;
		}

		$this->headers      = array();
		$ip_related_headers = array(
			'GD_PHP_HANDLER',
			'HTTP_AKAMAI_ORIGIN_HOP',
			'HTTP_CF_CONNECTING_IP',
			'HTTP_CLIENT_IP',
			'HTTP_FASTLY_CLIENT_IP',
			'HTTP_FORWARDED',
			'HTTP_FORWARDED_FOR',
			'HTTP_INCAP_CLIENT_IP',
			'HTTP_TRUE_CLIENT_IP',
			'HTTP_X_CLIENTIP',
			'HTTP_X_CLUSTER_CLIENT_IP',
			'HTTP_X_FORWARDED',
			'HTTP_X_FORWARDED_FOR',
			'HTTP_X_IP_TRAIL',
			'HTTP_X_REAL_IP',
			'HTTP_X_VARNISH',
			'REMOTE_ADDR',
		);

		foreach ( $ip_related_headers as $header ) :
			if ( isset( $_SERVER[ $header ] ) ) {
				$this->headers[ $header ] = $_SERVER[ $header ];
			}
			  endforeach;

		return $this->headers;
	}


	private function is_whitelisted( $ip = null ) {

		if ( is_null( $ip ) ) {
			$ip = $this->get_ip();
		}
		if ( $this->is_local( $ip ) ) {
			return true;
		}

		$whitelist = get_site_option( 'bruteguard_whitelist', '' );
		$wl_items  = explode( PHP_EOL, $whitelist );
		$iplong    = ip2long( $ip );

		if ( is_array( $wl_items ) ) :
			foreach ( $wl_items as $item ) :

				$item = trim( $item );

				if ( $ip == $item ) {
					return true;
				}

				if ( strpos( $item, '*' ) === false ) {
					continue;
				}

				$ip_low  = ip2long( str_replace( '*', '0', $item ) );
				$ip_high = ip2long( str_replace( '*', '255', $item ) );

				if ( $iplong >= $ip_low && $iplong <= $ip_high ) {
					return true;
				}

			endforeach;
		endif;

		return false;

	}
	private function check_loginability( $preauth = false ) {

		if ( $this->is_whitelisted() ) {
			return true;
		}

		$ip = $this->get_ip();

		$headers     = $this->get_headers();
		$header_hash = md5( json_encode( $headers ) );

		$transient_name = 'bruteguard_login_' . $header_hash;
		if ( $transient_value = $this->get_transient( $transient_name ) ) {
			if ( 'ok' == $transient_value['status'] ) {
				return true;
			}

			if ( 'ko' == $transient_value['status'] ) {
				$this->kill( $transient_value );
			}
		}

		$response = $this->call( 'check_ip' );

		if ( 'ko' == $response['status'] ) {
			$this->kill( $response );
		}

		return true;
	}

	private function kill( $data ) {

		$ip = $this->get_ip();
		do_action( 'bruteguard_kill', $ip );
		wp_die(
			__( 'Your IP "' . $ip . '" has been flagged for potential security violations. Please try again in a little while...', 'bruteguard' ),
			__( 'Blocked by BruteGuard', 'bruteguard' ),
			array( 'response' => 403 )
		);
	}

	public function verify_apikey( $apikey ) {
		$this->api_key  = $apikey;
		$response       = $this->call( 'verify' );
		$this->appi_key = null;
		return $response;
	}

	private function get_ip() {

		$ip      = '';
		$headers = $this->get_headers();
		foreach ( $headers as $header => $value ) {
			foreach ( explode( ',', $value ) as $ip ) {
				$ip = trim( $ip );
				if ( $this->validate_ip( $ip ) ) {
					return $ip;
				}
			}
		}
		return $ip;
	}

	private function is_local( $ip = null ) {
		if ( is_null( $ip ) ) {
			$ip = $this->get_ip();
		}

		return ! filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE );

	}

	private function validate_ip( $ip = null ) {
		if ( is_null( $ip ) ) {
			$ip = $this->get_ip();
		}

		return filter_var( $ip, FILTER_VALIDATE_IP );

	}

}
