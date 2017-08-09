<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

wc_get_template( 'emails/email-header.php', array( 'email_heading' => $email_heading ) );
echo wpautop( wp_kses_post( get_option( 'ivole_email_body' ) ) );
wc_get_template( 'emails/email-footer.php' );
