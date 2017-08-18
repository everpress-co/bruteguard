<?php
/*
Plugin Name: BruteGuard - Brute Force Protection
Plugin URI: https://bruteguard.co
Description: This plugin protects the WordPress backend from Bruteforce attacks with a global IP blacklist
Version: 0.1.1
Author: EverPress
Author URI: https://everpress.io
Tags: bruteguard
License: GPLv2 or later
*/

define( 'BRUTEGUARD_VERSION', '0.1.1' );
define( 'BRUTEGUARD_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'BRUTEGUARD_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'BRUTEGUARD_API_ENDPOINT', 'https://api.bruteguard.co/v1/' );
require_once BRUTEGUARD_PLUGIN_DIR . 'includes/functions.php';
require_once BRUTEGUARD_PLUGIN_DIR . 'classes/bruteguard.class.php';

bruteguard();
bruteguard( 'admin' );

register_activation_hook( __FILE__,  array( 'BruteGuardAdmin', 'activate' ) );
register_deactivation_hook( __FILE__,  array( 'BruteGuardAdmin', 'deactivate' ) );
