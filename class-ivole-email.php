<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'Ivole_Email' ) ) :

if ( is_file( plugin_dir_path( __DIR__ ) . '/woocommerce/includes/libraries/class-emogrifier.php' ) ) {
	include_once( plugin_dir_path( __DIR__ ) . '/woocommerce/includes/libraries/class-emogrifier.php' );
} else {
	
}

/**
 * Reminder email for product reviews
 */
class Ivole_Email {

	public $id;
	public $to;
	public $heading;
	public $subject;
	public $template_html;
	public $template_items_html;
	public $bcc;
	public $find = array();
	public $replace = array();
	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->id               = 'ivole_reminder';
		$this->heading          = strval( get_option( 'ivole_email_heading', __( 'How did we do?', 'ivole' ) ) );
		$this->subject          = strval( get_option( 'ivole_email_subject', '[{site_title}] ' . __( 'Review Your Experience with Us', 'ivole' ) ) );
		$this->template_html    = Ivole_Email::plugin_path() . '/templates/email.php';
		$this->template_items_html    = Ivole_Email::plugin_path() . '/templates/email_items.php';

		$this->find['site-title'] = '{site_title}';
		$this->replace['site-title'] = $this->get_blogname();
	}

	/**
	 * Trigger.
	 */
	public function trigger( $order_id, $to = null ) {
		$this->find['customer-first-name']  = '{customer_first_name}';
		$this->find['customer-name'] = '{customer_name}';
		$this->find['order-id'] = '{order_id}';
		$this->find['order-date'] = '{order_date}';
		$this->find['order-items'] = '{order_items}';
		$this->find['unsubscribe-link'] = '{unsubscribe_link}';
		if ( $order_id ) {
			$order = new WC_Order( $order_id );
			$this->to = $order->get_billing_email();
			$this->replace['customer-first-name'] = $order->get_billing_first_name();
			$this->replace['customer-name'] = $order->get_billing_first_name() . ' ' . $order->get_billing_last_name();
			$this->replace['order-id'] = $order_id;
			$this->replace['order-date']   = date_i18n( wc_date_format(), strtotime( $order->get_date_created() ) );
			$this->replace['order-items'] = $this->get_order_items( false, $order );
			// check if BCC address needs to be added to email
			$bcc_address = get_option( 'ivole_email_bcc', '' );
			if( filter_var( $bcc_address, FILTER_VALIDATE_EMAIL ) ) {
				$this->bcc = $bcc_address;
			}
		} else {
			// no order number means this is a test and we should provide some dummy information
			$this->to = $to;
			$this->replace['customer-first-name'] = __( 'Jane', 'ivole' );
			$this->replace['customer-name'] = __( 'Jane Doe', 'ivole' );
			$this->replace['order-id'] = 12345;
			$this->replace['order-date'] = date_i18n( wc_date_format(), time() );
			$this->replace['order-items'] = $this->get_order_items( true );
		}
		$this->replace['unsubscribe-link'] = '<a href="' . get_permalink( get_option( 'ivole_email_unsubscribe', 0 ) ) . '?ivole_email_unsubscribe=' . $this->to . '">' . __( 'Unsubscribe', 'ivole' ) . '</a>';
		return $this->send();
	}

	/**
	 * Get content
	 *
	 * @access public
	 * @return string
	 */
	public function get_content() {
		ob_start();
		$email_heading = $this->heading;
		include( $this->template_html );
		return ob_get_clean();
	}

	public static function plugin_path() {
    return untrailingslashit( plugin_dir_path( __FILE__ ) );
  }

	public function send() {

		add_filter( 'wp_mail_from', array( $this, 'get_from_address' ) );
		add_filter( 'wp_mail_from_name', array( $this, 'get_from_name' ) );
		add_filter( 'wp_mail_content_type', array( $this, 'get_content_type' ) );

		$subject = $this->replace_variables( $this->subject );
		$message = $this->get_content();
		$message = $this->replace_variables( $message );
		$message = $this->style_inline( $message );
		$headers = array();
		if($this->bcc) {
			$headers[] = 'Bcc: ' . $this->bcc;
		}
		$return  = wp_mail( $this->to, $subject, $message, $headers, array() );

		remove_filter( 'wp_mail_from', array( $this, 'get_from_address' ) );
		remove_filter( 'wp_mail_from_name', array( $this, 'get_from_name' ) );
		remove_filter( 'wp_mail_content_type', array( $this, 'get_content_type' ) );

		return $return;
	}

	public function get_from_address() {
		$from_address = apply_filters( 'woocommerce_email_from_address', get_option( 'woocommerce_email_from_address' ), $this );
		return sanitize_email( $from_address );
	}

	public function get_from_name() {
		$from_name = apply_filters( 'woocommerce_email_from_name', get_option( 'woocommerce_email_from_name' ), $this );
		return wp_specialchars_decode( esc_html( $from_name ), ENT_QUOTES );
	}

	public function get_content_type() {
		return 'text/html';
	}

	public function style_inline( $content ) {
		// make sure we only inline CSS for html emails
		if ( in_array( $this->get_content_type(), array( 'text/html', 'multipart/alternative' ) ) && class_exists( 'DOMDocument' ) ) {
			ob_start();
			wc_get_template( 'emails/email-styles.php' );
			$css = apply_filters( 'woocommerce_email_styles', ob_get_clean() );

			// apply CSS styles inline for picky email clients
			try {
				$emogrifier = new Emogrifier( $content, $css );
				$content    = $emogrifier->emogrify();
			} catch ( Exception $e ) {
				$logger = new WC_Logger();
				$logger->add( 'emogrifier', $e->getMessage() );
			}
		}
		return $content;
	}

	public function replace_variables( $input ) {
		return str_replace( $this->find, $this->replace, __( $input ) );
	}

	public function get_blogname() {
		return wp_specialchars_decode( get_option( 'blogname' ), ENT_QUOTES );
	}

	public function get_order_items( $ivole_test, $order = null ) {
		$items = null;
		$enabled_for = get_option( 'ivole_enable_for', 'all' );
		$enabled_categories = get_option( 'ivole_enabled_categories', array() );
		ob_start();
		if ( $order ) {
			$items = $order->get_items();
		}
		include( $this->template_items_html );
		return ob_get_clean();
	}

}

endif;
