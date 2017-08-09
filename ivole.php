<?php
/*
Plugin Name: Customer Reviews for WooCommerce
Description: Customer Reviews for WooCommerce plugin helps you get more customer reviews for your shop by sending automated reminders and coupons.
Plugin URI: https://wordpress.org/plugins/customer-reviews-woocommerce/
Version: 2.1
Author: ivole
Author URI: https://profiles.wordpress.org/ivole
License: GPLv3

WooCommerce Reviews is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 3 of the License, or
any later version.

WooCommerce Reviews is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with WooCommerce Reviews. If not, see https://www.gnu.org/licenses/gpl.html.
*/

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}


//@TODO: Delete stored settings on activation, uncomment it for development
/*
function my_ivole_activate() {
	// Activation code here...
	global $wpdb;
	$wpdb->query("DELETE FROM ".$wpdb->options." WHERE option_name LIKE 'ivole_%' ");
}
register_activation_hook( __FILE__, 'my_ivole_activate' );
*/

require_once( 'class-ivole.php' );

/**
 * Check if WooCommerce is active
**/
if ( in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {
  add_action('init', 'ivole_init');
  function ivole_init() {
    $ivole = new Ivole();
  }
}

add_shortcode( 'ivole_unsubscribe', 'ivole_email_unsubscribe_shortcode' );
function ivole_email_unsubscribe_shortcode() {
	$email = '';
	if( isset( $_GET['ivole_email_unsubscribe'] ) ) {
		$email = strval( $_GET['ivole_email_unsubscribe'] );
	};
	if( isset( $_POST['ivole_submit'] ) && isset( $_POST['ivole_email'] ) ) {
		if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
			$opt_out_emails = get_option( 'ivole_unsubscribed_emails', array() );
			if( !in_array( $email, $opt_out_emails ) ) {
				$opt_out_emails[] = $email;
				update_option( 'ivole_unsubscribed_emails', $opt_out_emails );
				echo '<p>' . __('Success: you have unsubscribed from emails related to reviews!', 'ivole') . '</p>';
			} else {
				echo '<p>' . __('Success: you have unsubscribed from emails related to reviews!', 'ivole') . '</p>';
			}
		} else {
			echo '<p>' . __('Error: please provide a valid email address!', 'ivole') . '</p>';
		}
		echo '<a href="' . get_home_url() . '">' . __( 'Go to home page', 'ivole' ) . '</a>';
		return;
	}
	?>
	<div class="ivole-unsubscribe-form">
	    <form action="" method="post">
	        <input type="hidden" name="ivole_action" value="ivole_unsubscribe" />
	        <p>
	            <label for="ivole_email"><?php _e('Email Address:', 'ivole'); ?></label>
	            <input type="text" id="ivole_email" name="ivole_email" value="<?php echo esc_attr($email); ?>" size="25" />
	        </p>
	        <p>
	            <input type="submit" name="ivole_submit" value="<?php _e('Unsubscribe', 'ivole'); ?>" />
	        </p>
	    </form>
	</div>
	<?php
}

?>
