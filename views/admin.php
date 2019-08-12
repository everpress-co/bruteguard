<?php
$stats = bruteguard()->get_stats();

$apikey    = get_site_option( 'bruteguard_apikey' );
$user      = get_site_option( 'bruteguard_user' );
$whitelist = get_site_option( 'bruteguard_whitelist' );

$status = get_site_option( 'bruteguard_apikey_status', 'inactive' );

?>


<div class="wrap" id="bruteguard-page">
<h1>BruteGuard</h1>
	<form action="options.php" method="post">
	<?php settings_fields( 'bruteguard' ); ?>
	<?php do_settings_sections( 'bruteguard' ); ?>
	<?php settings_errors(); ?>

	<div class="metabox-holder">
		<div id="mailster-mb-quick-links" class="postbox">
		<h2 class="hndle"><span><?php esc_html_e( 'Activation', 'bruteguard' ); ?></span></h2>
			<div class="inside">
			<div class="bruteguard-apikey-field">
			<?php if ( $apikey && 'verified' == $status ) : ?>
				<h3 class="active">BruteGuard is active</h3>
				<input type="hidden" value="<?php echo esc_attr( $user ); ?>" name="bruteguard_user">
			<?php else : ?>
				<?php if ( $apikey ) : ?>
					<h3 class="inactive">Pending Verification</h3>
					<div class="bruteguard-email-field">
						<input type="email" class="bruteguard-email" value="<?php echo esc_attr( $user ); ?>" placeholder="Enter Email" name="bruteguard_user" required>
						<input type="submit" class="bruteguard-email-submit" value="<?php esc_attr_e( 'Send Activation Link', 'bruteguard' ); ?>" />
						<a href="<?php echo add_query_arg( 'key', $apikey ); ?>">Check API key</a>
					</div>
				<?php else : ?>
					<h3 class="inactive">BruteGuard is inactive</h3>
					<div class="bruteguard-email-field">
						<input type="email" class="bruteguard-email" value="<?php echo esc_attr( $user ); ?>" placeholder="Enter Email" name="bruteguard_user" required>
						<input type="submit" class="bruteguard-email-submit" value="<?php esc_attr_e( 'Get Protected!', 'bruteguard' ); ?>" />
					</div>
				<?php endif; ?>
			<?php endif; ?>
			</div>
			<input type="hidden" value="<?php echo esc_attr( $apikey ); ?>" name="bruteguard_apikey">

			</div>
		</div>

	</div>

<?php if ( 'verified' == $status ) : ?>

	<div class="metabox-holder">
		<div id="bruteguard-mb-stats" class="postbox">
		<h2 class="hndle"><span>BruteGuard Stats</span></h2>
			<div class="inside">

				<?php if ( is_wp_error( $stats ) ) : ?>
					<?php

					switch ( $stats->get_error_code() ) {
						case '405':
							echo 'Please register!';
							break;

						default:
							break;
					}
					?>
				<?php else : ?>
				<ul class="bruteguard-stats">
					<li>
					<div class="stats-label" data-count="<?php echo $stats['stats']['sites']; ?>" data-rate="<?php echo $stats['rate']['sites']; ?>"><?php echo number_format_i18n( $stats['stats']['sites'] ); ?></div>
					<div class="stats-label-sub">Sites Protected</div>
					</li>
					<li>
					<div class="stats-label" data-count="<?php echo $stats['stats']['attacks']; ?>" data-rate="<?php echo $stats['rate']['attacks']; ?>"><?php echo number_format_i18n( $stats['stats']['attacks'] ); ?></div>
					<div class="stats-label-sub">Attacks Prevented</div>
					</li>
				</ul>
				<ul class="bruteguard-stats">
					<li>
					<div class="stats-label" data-count="<?php echo $stats['stats']['bots']; ?>" data-rate="<?php echo $stats['rate']['bots']; ?>"><?php echo number_format_i18n( $stats['stats']['bots'] ); ?></div>
					<div class="stats-label-sub">Bots Blocked</div>
					</li>
					<li>
					<div class="stats-label" data-count="<?php echo $stats['stats']['attacks_today']; ?>" data-rate="<?php echo $stats['rate']['attacks_today']; ?>"><?php echo number_format_i18n( $stats['stats']['attacks_today'] ); ?></div>
					<div class="stats-label-sub">Attacks Today</div>
					</li>
				</ul>
				<?php endif; ?>

				<br class="clear">

			</div>
		</div>

	</div>


		<div class="metabox-holder">
			<div id="mailster-mb-quick-links" class="postbox">
			<h2 class="hndle"><span><?php esc_html_e( 'White listed IPs', 'bruteguard' ); ?></span></h2>
				<div class="inside">
				<p class="description">
				<?php esc_html_e( 'Enter your IP\'s you like to white list. One per line.', 'bruteguard' ); ?>
				</p>
				<p>
					<textarea name="bruteguard_whitelist" class="large-text" rows="10"><?php echo esc_textarea( $whitelist ); ?></textarea>
				</p>
				<p>
				<input name="Submit" class="button button-primary" type="submit" value="<?php esc_attr_e( 'Save Changes', 'bruteguard' ); ?>" />

				</p>

				</div>
			</div>

		</div>
	<?php endif; ?>
</form>

<div id="ajax-response"></div>
<br class="clear">
</div>
