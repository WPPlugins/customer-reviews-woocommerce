<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'Ivole_Email_Coupon' ) ) :

if ( is_file( plugin_dir_path( __DIR__ ) . '/woocommerce/includes/libraries/class-emogrifier.php' ) ) {
	include_once( plugin_dir_path( __DIR__ ) . '/woocommerce/includes/libraries/class-emogrifier.php' );
} else {
	
}

/**
 * Reminder email for product reviews
 */
class Ivole_Email_Coupon {

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
		$this->id               = 'ivole_review_coupon';
		$this->heading          = strval( get_option( 'ivole_email_heading_coupon', __( 'Thank	You	for	Leaving	a Review', 'ivole' ) ) );
		$this->subject          = strval( get_option( 'ivole_email_subject_coupon', '[{site_title}] ' . __( 'Discount	Coupon	for	You', 'ivole' ) ) );
		$this->template_html    = Ivole_Email::plugin_path() . '/templates/email_coupon.php';

		$this->find['site-title'] = '{site_title}';
		$this->replace['site-title'] = $this->get_blogname();
	}


	/**
	 * Trigger.
	 */
	public function trigger( $review_id, $to = null, $coupon_code="",$discount_amount="" ) {
		$this->find['customer-first-name']  = '{customer_first_name}';
		$this->find['customer-name'] = '{customer_name}';
		$this->find['coupon-code'] = '{coupon_code}';
		$this->find['product-name'] = '{product_name}';
		$this->find['discount-amount'] = '{discount_amount}';
		$this->find['unsubscribe-link'] = '{unsubscribe_link}';


		if ( $review_id ) {
			$comment=get_comment($review_id);
			$product_id=$comment->comment_post_ID;
			$product_name=get_post_field('post_title',$product_id);//$this->replace['product-name'] =
			$coupon_type=get_option('ivole_coupon_type','static');
			if($coupon_type=='static'){
				$coupon_id=get_option('ivole_existing_coupon',0);
			}else{
				$coupon_id=$this->generate_coupon($to,$review_id);
			}
			if($coupon_id>0 && get_post_type($coupon_id)=='shop_coupon' && get_post_status($coupon_id)=='publish' ) {

				$this->to = $to;
				//$this->replace['coupon-code'] = nl2br(print_r($comment, true));
				$coupon_code= get_post_field('post_title',$coupon_id); //=$this->replace['coupon-code']
				if($comment->user_id>0){
					$user_data=get_userdata($comment->user_id);
					if($user_data->first_name=="" && $user_data->last_name==""  ){
						$this->replace['customer-first-name']=$user_data->display_name; //
						$this->replace['customer-name']=$user_data->display_name;
					}else{
						$this->replace['customer-first-name']=($user_data->first_name=="") ? $user_data->last_name : $user_data->first_name;
						$this->replace['customer-name']=$user_data->first_name." ".$user_data->last_name ;
					}
				}else{
					//find out the customer name
					$this->replace['customer-first-name'] = ($comment->comment_author!="") ? $comment->comment_author : __('Customer');
					$this->replace['customer-name'] = ($comment->comment_author!="") ? $comment->comment_author : __('Customer');
				}
				$discount_type=get_post_meta($coupon_id,'discount_type',true);
				$discount_amount=get_post_meta($coupon_id,'coupon_amount',true);
				$discount_string="";
				if($discount_type=="percent" && $discount_amount>0){
					$discount_string=$discount_amount."%";
				}elseif($discount_amount>0){
					$discount_string=trim(strip_tags(wc_price($discount_amount,array('currency'=>get_option('woocommerce_currency')))));
				}

				$this->replace['coupon-code']=$coupon_code;
				$this->replace['product-name']=$product_name;
				$this->replace['discount-amount'] = $discount_string;
				$this->replace['unsubscribe-link'] = '<a href="' . get_permalink( get_option( 'ivole_email_unsubscribe', 0 ) ) . '?ivole_email_unsubscribe=' . $this->to . '">' . __( 'Unsubscribe', 'ivole' ) . '</a>';

				$bcc_address = get_option( 'ivole_coupon_email_bcc', '' );
				if( filter_var( $bcc_address, FILTER_VALIDATE_EMAIL ) ) {
					$this->bcc = $bcc_address;
				}
				return $this->send();
			}
		} else {
			// no review_id means this is a test and we should provide some dummy information
			$this->to = $to;//$coupon_code="",$discount_amount=""
			$this->replace['customer-first-name'] = __( 'Jane', 'ivole' );
			$this->replace['customer-name'] = __( 'Jane Doe', 'ivole' );
			$this->replace['coupon-code'] =$coupon_code;
			$this->replace['product-name'] = 'Test product for email';
			$this->replace['discount-amount'] = ($discount_amount=="") ? '10%' :$discount_amount;
			$this->replace['unsubscribe-link'] = '<a href="' . get_permalink( get_option( 'ivole_email_unsubscribe', 0 ) ) . '?ivole_email_unsubscribe=' . $this->to . '">' . __( 'Unsubscribe', 'ivole' ) . '</a>';

			return $this->send();
		}
		//$this->replace['unsubscribe-link'] = '<a href="' . get_permalink( get_option( 'ivole_email_unsubscribe', 0 ) ) . '?ivole_email_unsubscribe=' . $this->to . '">' . __( 'Unsubscribe', 'ivole' ) . '</a>';
	}

	/**
	 * Generate a coupon for the given email
	 *
	 * @access public
	 * @return id of generated coupon | false
	 */
	public function generate_coupon($to,$review_id=0){
		$unique_code = (!empty( $to)) ? strtoupper( uniqid( substr( preg_replace('/[^a-z0-9]/i', '', sanitize_title( $to ) ), 0, 5 ) ) ) : strtoupper( uniqid() );
		$coupon_args = array(
			'post_title' 	=> $unique_code,
			'post_content' 	=> '',
			'post_status' 	=> 'publish',
			'post_author' 	=> 1,
			'post_type'     => 'shop_coupon'
		);
		$coupon_id = wp_insert_post( $coupon_args );
		if($coupon_id>0){
			$type=get_option('ivole_coupon__discount_type','percent');
			update_post_meta( $coupon_id, 'discount_type', $type );
			$amount=floatval(get_option('ivole_coupon__coupon_amount',0));
			update_post_meta( $coupon_id, 'coupon_amount', $amount  );
			$individual_use=get_option('ivole_coupon__individual_use','no');
			update_post_meta( $coupon_id, 'individual_use', $individual_use );
			$product_ids=get_option('ivole_coupon__product_ids',array());
			$product_ids=implode(",",$product_ids);
			update_post_meta( $coupon_id, 'product_ids', $product_ids );
			$exclude_product_ids=get_option('ivole_coupon__exclude_product_ids',array());
			$exclude_product_ids=implode(",",$exclude_product_ids);
			update_post_meta( $coupon_id, 'exclude_product_ids', $exclude_product_ids );
			$usage_limit=get_option('ivole_coupon__usage_limit',0);
			update_post_meta( $coupon_id, 'usage_limit', $usage_limit );
			update_post_meta( $coupon_id, 'usage_limit_per_user', $usage_limit );
			$days=intval(get_option('ivole_coupon__expires_days',0));
			if($days>0) {
				$today = time();
				$expiry_date = date('Y-m-d', $today + 24 * 60 * 60 * $days);
				update_post_meta($coupon_id, 'expiry_date', $expiry_date);
				$date_expires = strtotime($expiry_date);
				update_post_meta($coupon_id, 'date_expires', $date_expires);
			}else{
				update_post_meta($coupon_id, 'expiry_date', NULL);
				update_post_meta($coupon_id, 'date_expires', '');
			}
			update_post_meta( $coupon_id, 'customer_email', array( $to ) );
			$free_shipping=get_option('ivole_coupon__free_shipping','no');
			update_post_meta( $coupon_id, 'free_shipping', $free_shipping );

			$exclude_sale_items=get_option('ivole_coupon__exclude_sale_items','no');
			update_post_meta( $coupon_id, 'exclude_sale_items', $exclude_sale_items );

			$product_categories=get_option('ivole_coupon__product_categories',array());
			update_post_meta( $coupon_id, 'product_categories', $product_categories  );

			$exclude_product_categories=get_option('ivole_coupon__excluded_product_categories',array());
			update_post_meta( $coupon_id, 'exclude_product_categories', $exclude_product_categories );

			$minimum_amount=floatval(get_option('ivole_coupon__minimum_amount',0));
			if($minimum_amount>0){
				update_post_meta( $coupon_id, 'minimum_amount', $minimum_amount );
			}else{
				update_post_meta( $coupon_id, 'minimum_amount', '' );
			}

			$maximum_amount=floatval(get_option('ivole_coupon__maximum_amount',0));
			if($maximum_amount>0){
				update_post_meta( $coupon_id, 'maximum_amount', $maximum_amount );
			}else{
				update_post_meta( $coupon_id, 'maximum_amount', '' );
			}

			update_post_meta( $coupon_id, 'generated_from_review_id', $review_id );
		}
		return $coupon_id;
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

}

endif;
