<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'Ivole_Sender' ) ) :

	require_once('class-ivole-email.php');

	class Ivole_Sender {
	  public function __construct() {
			// Triggers for this completed orders
			add_action( 'woocommerce_order_status_completed_notification', array( $this, 'sender_trigger' ) );
			add_action( 'ivole_send_reminder', array( $this, 'sender_action' ), 10, 1 );
			//$this->sender_trigger( 34 );
	  }

		public function sender_trigger( $order_id ) {
			// check if reminders are enabled
			$reminders_enabled = get_option( 'ivole_enable', 'no' );
			if( $reminders_enabled === 'no' ) {
				error_log('not enabled');
				return;
			}
			if( $order_id ) {
				$order = new WC_Order( $order_id );
				// check if sending of reminders is enabled for guests
				$guest_enabled = get_option( 'ivole_enable_guests', 'no' );
				if( $guest_enabled === 'no' ) {
					if( !isset( $order->user_id ) ) {
						//the customer is a guest, skip sending
						//error_log('guest');
						return;
					}
				}
				// check if the customer is in the unsubscribed list
				$emails_array = get_option( 'ivole_unsubscribed_emails', array() );
				if( in_array( $order->get_billing_email(), $emails_array ) ) {
					//the customer opted out from email list, skip sending
					//error_log('opted out');
					return;
				}
				// check if the order contains at least one product for which reminders are enabled (if there is filtering by categories)
				$enabled_for = get_option( 'ivole_enable_for', 'all' );
				if( $enabled_for === 'categories' ) {
					$enabled_categories = get_option( 'ivole_enabled_categories', array() );
					$items = $order->get_items();
					$skip = true;
					foreach ( $items as $item_id => $item ) {
						if ( apply_filters( 'woocommerce_order_item_visible', true, $item ) ) {
							$categories = get_the_terms( $item['product_id'], 'product_cat' );
							foreach ( $categories as $category_id => $category ) {
								if( in_array( $category->term_id, $enabled_categories ) ) {
									$skip = false;
									break;
								}
							}
						}
					}
					if( $skip ) {
						// there is no products from enabled categories in the order, skip sending
						//error_log('categories');
						return;
					}
				}

				$delay = get_option( 'ivole_delay', 5 );
				$timestamp = time() + $delay * (24 * 60 * 60);
				wp_schedule_single_event( $timestamp, 'ivole_send_reminder', array( $order_id ) );
			}
		}

		public function sender_action( $order_id ) {
			$e = new Ivole_Email();
			$e->trigger( $order_id );
		}
	}

endif;

?>
