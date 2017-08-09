<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once('class-ivole-admin.php');
require_once('class-ivole-admin-coupon.php');
require_once('class-ivole-sender.php');
require_once('class-ivole-reviews.php');

class Ivole {
  public function __construct() {
        $ivole_admin = new Ivole_Admin();
        $ivole_admin_coupon = new Ivole_Admin_Coupon();
		$ivole_sender = new Ivole_Sender();
		$ivole_reviews = new Ivole_Reviews();
  }
}

?>
