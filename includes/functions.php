<?php

function bruteguard( $subclass = null ) {
	$class = 'BruteGuard';
	if ( ! is_null( $subclass ) ) {
		$class .= ucwords( $subclass );
		require_once BRUTEGUARD_PLUGIN_DIR . 'classes/' . strtolower( $subclass ) . '.class.php';
	}
	if ( ! isset( $GLOBALS[ $class ] ) ) {
		$GLOBALS[ $class ] = new $class();
		if ( method_exists( $GLOBALS[ $class ], 'activate' ) ) {
			register_activation_hook( __FILE__, array( $GLOBALS[ $class ], 'activate' ) );
		}
		if ( method_exists( $GLOBALS[ $class ], 'deactivate' ) ) {
			register_activation_hook( __FILE__, array( $GLOBALS[ $class ], 'deactivate' ) );
		}
	}
	return $GLOBALS[ $class ];
}
