<?php

class BruteGuardAdmin {

	function __construct() {

		add_action( 'admin_menu', array( $this, 'add_menu_page' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_init', array( $this, 'maybe_redirect' ) );

	}

	public static function deactivate() {
		$settings = get_site_option( 'bruteguard_privacy_opt_in' );
		update_site_option( 'bruteguard_saved_settings', $settings );
		update_site_option( 'bruteguard_deactivated', '1' );
		bruteguard()->deactivate();
	}

	public static function activate() {
		update_site_option( 'bruteguard_user', '' );
		update_site_option( 'bruteguard_version', BRUTEGUARD_VERSION );
		add_site_option( 'bruteguard_do_activation_redirect', true );
		bruteguard()->activate();
	}


	public function maybe_redirect() {

		if ( get_site_option( 'bruteguard_do_activation_redirect' ) ) {
			delete_site_option( 'bruteguard_do_activation_redirect' );
			wp_redirect( admin_url( 'admin.php?page=bruteguard' ) );
			exit;
		}

	}


	public function add_menu_page() {

		// $page = add_submenu_page( 'tools.php', 'BruteGuard', 'BruteGuard', 'manage_options', 'bruteguard',  array( $this, 'admin_page' ) );
		$page = add_menu_page( 'BruteGuard', 'BruteGuard', 'manage_options', 'bruteguard',  array( $this, 'admin_page' ), BRUTEGUARD_PLUGIN_URL . 'assets/menu-logo.svg' );

		add_action( "admin_print_styles-{$page}", array( $this, 'wp_enqueue_scripts' ) );
		add_action( "admin_print_styles-{$page}", array( $this, 'check_api_key' ) );
	}

	public function admin_page() {
		include BRUTEGUARD_PLUGIN_DIR . 'views/admin.php';
	}

	public function wp_enqueue_scripts() {
		wp_enqueue_style( 'bruteguard-admin', BRUTEGUARD_PLUGIN_URL . 'assets/css/admin.css', array(), BRUTEGUARD_VERSION );
		wp_enqueue_script( 'bruteguard-admin', BRUTEGUARD_PLUGIN_URL . 'assets/js/admin.js', array(), BRUTEGUARD_VERSION, true );
	}

	public function register_settings() {
		register_setting( 'bruteguard', 'bruteguard_apikey', array( $this, 'validate_apikey' ) );
		register_setting( 'bruteguard', 'bruteguard_whitelist', array( $this, 'validate_whitelist' ) );
		register_setting( 'bruteguard', 'bruteguard_user', array( $this, 'validate_user' ) );
	}

	public function validate_user( $email ) {

		if ( ! $email ) {
			return $email;
		}

		if ( ! is_email( $email ) ) {
			add_settings_error( 'bruteguard_user', 'no_email', 'This is not a valid email address', 'error' );
			return '';
		}

		$url = bruteguard()->get_api_link( $email );

		$response = wp_remote_get( $url );

		if ( is_wp_error( $response ) ) {
			add_settings_error( 'bruteguard_user', 'http_err', $response->get_error_message(), 'error' );
			return $email;
		}

		$code = wp_remote_retrieve_response_code( $response );
		$body = wp_remote_retrieve_body( $response );

		if ( 200 == $code ) {
			$data = json_decode( $body );
			update_site_option( 'bruteguard_apikey', $data->key );
			add_settings_error( 'bruteguard_user', 'http_err', 'We have sent you a mail to ' . $email . '. Please click on the activation link!', 'updated' );
			return $email;
		}

		add_settings_error( 'bruteguard_user', 'http_err', 'There was an error processing your request!', 'error' );

		return $email;
	}

	public function validate_apikey( $apikey ) {

		$apikey = trim( $apikey );

		$response = bruteguard()->verify_apikey( $apikey );
		if ( isset( $response['error'] ) ) {
			// add_settings_error( 'bruteguard_apikey', $response['error'], $response['error'], 'error' );
		}

		return $apikey;

	}
	public function validate_whitelist( $whitelist ) {

		$whitelist = trim( $whitelist );

		return $whitelist;

	}
	public function check_api_key() {
		if ( isset( $_GET['key'] ) ) {
			$apikey = trim( $_GET['key'] );
			$response = bruteguard()->verify_apikey( $apikey );
			update_site_option( 'bruteguard_apikey', $apikey );

			if ( isset( $response['error'] ) ) {

				switch ( $response['code'] ) {
					case 401:
						$message = 'Please activate your account by clicking the verification in the mail';
						break;
					default:
						$message = $response['error'];
						break;
				}
				add_settings_error( 'bruteguard_apikey', $response['code'], $message, 'error' );
				update_site_option( 'bruteguard_apikey_status', 'pending' );
			} elseif ( isset( $response['status'] ) && 'ok' == $response['status'] ) {
				update_site_option( 'bruteguard_apikey_status', 'verified' );
				wp_redirect( admin_url( 'admin.php?page=bruteguard' ), 302 );

			}
		}

	}


}
