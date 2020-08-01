<?php
/*
Plugin Name: BruteGuard – Brute Force Login Protection
Plugin URI: https://bruteguard.co
Description: BruteGuard is a cloud powered brute force login protection that shields your site against botnet attacks.
Version: 0.1.4
Author: EverPress
Author URI: https://everpress.co
Tags: bruteguard
License: GPLv2 or later
*/

define( 'BRUTEGUARD_VERSION', '0.1.4' );
define( 'BRUTEGUARD_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'BRUTEGUARD_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'BRUTEGUARD_API_ENDPOINT', 'https://api.bruteguard.co/v1/' );
require_once BRUTEGUARD_PLUGIN_DIR . 'includes/functions.php';
require_once BRUTEGUARD_PLUGIN_DIR . 'classes/bruteguard.class.php';

bruteguard();
bruteguard( 'admin' );

register_activation_hook( __FILE__, array( 'BruteGuardAdmin', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'BruteGuardAdmin', 'deactivate' ) );
