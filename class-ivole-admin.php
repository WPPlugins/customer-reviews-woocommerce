<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'Ivole_Admin' ) ) :

	require_once('class-ivole-email.php');

	global $woocommerce;

	class Ivole_Admin {
	  public function __construct() {
	    	$this->id    = 'ivole';
			$this->label = __( 'Reviews', 'ivole' );

			add_filter( 'woocommerce_settings_tabs_array', array( $this, 'add_settings_page' ), 30 );
			add_action( 'woocommerce_settings_' . $this->id, array( $this, 'output' ) );
			add_action( 'woocommerce_settings_save_' . $this->id, array( $this, 'save' ) );
			add_action( 'woocommerce_sections_' . $this->id, array( $this, 'output_sections' ) );
			add_action( 'woocommerce_admin_field_cselect', array( $this, 'show_cselect' ) );
			add_action( 'woocommerce_admin_settings_sanitize_option_ivole_enabled_categories', array( $this, 'save_cselect' ), 10, 3 );
			add_action( 'woocommerce_admin_field_htmltext', array( $this, 'show_htmltext' ) );
			add_action( 'woocommerce_admin_settings_sanitize_option_ivole_email_body', array( $this, 'save_htmltext' ), 10, 3 );
			add_action( 'woocommerce_admin_field_emailtest', array( $this, 'show_emailtest' ) );
			add_action( 'woocommerce_admin_field_textarea_emails', array( $this, 'show_textarea_emails' ) );
			add_action( 'woocommerce_admin_settings_sanitize_option_ivole_unsubscribed_emails', array( $this, 'save_textarea_emails' ), 10, 3 );
			add_action( 'admin_footer', array( $this, 'test_email_javascript' ) );
			add_action( 'wp_ajax_ivole_send_test_email', array( $this, 'send_test_email' ) );
			add_action( 'wp_loaded', array( $this, 'create_unsubscribe_page' ) );


	  }



	  	/**
		 * Add this page to settings.
		 */
		public function add_settings_page( $pages ) {
			$pages[ $this->id ] = $this->label;

			return $pages;
		}

	    /**
		 * Get settings array.
		 *
		 * @return array
		 */
		public function get_settings( $current_section = '' ) {
			if ( 'ivole_reviews' == $current_section ) {
				$settings = array(
					array(
		        		'title' => __( 'Extensions for Customer Reviews', 'ivole' ),
		        		'type' => 'title',
		        		'desc' => __( 'Settings for WooCommerce Customer Reviews plugin. Configure various extensions for standard WooCommerce reviews.', 'ivole' ),
		        		'id' => 'ivole_options'
		      		),
					array(
						'title'         => __( 'Attach Image', 'ivole' ),
						'desc'          => __( 'Enable attachment of images to reviews.', 'ivole' ),
						'id'            => 'ivole_attach_image',
						'default'       => 'no',
						'type'          => 'checkbox'
					),
					array(
						'title'         => __( 'reCAPTCHA V2 for Reviews', 'ivole' ),
						'desc'          => __( 'Enable reCAPTCHA to eliminate fake reviews. You must enter Site Key and Secret Key in the fields below if you want to use reCAPTCHA. You will receive Site Key and Secret Key after registration at reCAPTCHA website.', 'ivole' ),
						'id'            => 'ivole_enable_captcha',
						'default'       => 'no',
						'type'          => 'checkbox'
					),
					array(
		        		'title' => __( 'reCAPTCHA V2 Site Key', 'ivole' ),
						'type' => 'text',
		        		'desc' => __( 'If you want to use reCAPTCHA V2, insert here Site Key that you will receive after registration at reCAPTCHA website.', 'ivole' ),
						'default'  => '',
		        		'id' => 'ivole_captcha_site_key',
						'css'      => 'min-width:400px;',
						'desc_tip' => true
		      		),
					array(
		        		'title' => __( 'reCAPTCHA V2 Secret Key', 'ivole' ),
		        		'type' => 'text',
		        		'desc' => __( 'If you want to use reCAPTCHA V2, insert here Secret Key that you will receive after registration at reCAPTCHA website.', 'ivole' ),
						'default'  => '',
		        		'id' => 'ivole_captcha_secret_key',
						'css'      => 'min-width:400px;',
						'desc_tip' => true
		      		),
					array(
						'type' => 'sectionend',
						'id' => 'ivole_options'
					)
				);
			} else {
				$settings = array(
		      		array(
		        		'title' => __( 'Reminders for Customer Reviews', 'ivole' ),
		        		'type' => 'title',
		        		'desc' => __( 'Settings for WooCommerce Customer Reviews plugin. Configure WooCommerce to send automatic follow-up emails (reminders) that gather product reviews.', 'ivole' ),
		        		'id' => 'ivole_options'
		      		),
		      		array(
						'title'         => __( 'Enable Reminder', 'ivole' ),
						'desc'          => __( 'Enable follow-up emails with a reminder to submit a review.', 'ivole' ),
						'id'            => 'ivole_enable',
						'default'       => 'no',
						'type'          => 'checkbox'
					),
					array(
		        		'title' => __( 'Sending Delay (Days)', 'ivole' ),
		        		'type' => 'number',
		        		'desc' => __( 'Emails will be sent N days after order status is set to "Completed". N is a sending delay that needs to be defined in this field.', 'ivole' ),
						'default'  => 5,
		        		'id' => 'ivole_delay',
						'desc_tip' => true
		      		),
					array(
		        		'title' => __( 'Enable for', 'ivole' ),
		        		'type' => 'select',
		        		'desc' => __( 'Define if reminders will be send for all or only specific categories of products.', 'ivole' ),
						'default'  => 'all',
		        		'id' => 'ivole_enable_for',
						'desc_tip' => true,
						'class'    => 'wc-enhanced-select',
						'css'      => 'min-width:300px;',
						'options'  => array(
							'all'  => __( 'All Categories', 'ivole' ),
							'categories' => __( 'Specific Categories', 'ivole' )
							)
		      		),
					array(
		        		'title' => __( 'Categories', 'ivole' ),
		        		'type' => 'cselect',
		        		'desc' => __( 'If reminders are enabled only for specific categories of products, this field enables you to choose these categories.', 'ivole' ),
		        		'id' => 'ivole_enabled_categories',
						'desc_tip' => true,
						'class'    => 'wc-enhanced-select',
						'css'      => 'min-width:300px;'
		      		),
					array(
						'title'         => __( 'Send to Guests', 'ivole' ),
						'desc'          => __( 'Enable follow-up emails with a reminder for customers who do not have accounts on the website.', 'ivole' ),
						'id'            => 'ivole_enable_guests',
						'default'       => 'no',
						'type'          => 'checkbox'
					),
					array(
		        		'title' => __( 'BCC Address', 'ivole' ),
		        		'type' => 'text',
		        		'desc' => __( 'Add a BCC recipient for emails with reminders. It can be useful to verify that emails are being sent properly.', 'ivole' ),
						'default'  => '',
		        		'id' => 'ivole_email_bcc',
						'css'      => 'min-width:300px;',
						'desc_tip' => true
		      		),
					array(
						'type' => 'sectionend',
						'id' => 'ivole_options'
					),
					array(
		        		'title' => __( 'Email Template', 'ivole' ),
		        		'type' => 'title',
		        		'desc' => __( 'Adjust template of the email that will be sent to customers.', 'ivole' ),
		        		'id' => 'ivole_options_email'
		      		),
					array(
		        		'title' => __( 'Email Subject', 'ivole' ),
		        		'type' => 'text',
		        		'desc' => __( 'Subject of the email that will be sent to customers.', 'ivole' ),
						'default'  => '[{site_title}] Review Your Experience with Us',
		        		'id' => 'ivole_email_subject',
						'css'      => 'min-width:600px;',
						'desc_tip' => true
		      		),
					array(
		        		'title' => __( 'Email Heading', 'ivole' ),
		        		'type' => 'text',
		        		'desc' => __( 'Heading of the email that will be sent to customers.', 'ivole' ),
						'default'  => 'How did we do?',
		        		'id' => 'ivole_email_heading',
						'css'      => 'min-width:600px;',
						'desc_tip' => true
		      		),
					array(
		        		'title' => __( 'Email Body', 'ivole' ),
		        		'type' => 'htmltext',
		        		'desc' => __( 'Body of the email that will be sent to customers.', 'ivole' ),
		        		'id' => 'ivole_email_body',
						'desc_tip' => true
		      		),
					array(
		        		'title' => __( 'Send Test', 'ivole' ),
		        		'type' => 'emailtest',
		        		'desc' => __( 'Send a test email to this address.', 'ivole' ),
						'default'  => '',
						'placeholder' => 'Email address',
		        		'id' => 'ivole_email_test',
						'css'      => 'min-width:300px;',
						'desc_tip' => true
		      		),
					array(
		        		'title' => __( 'Unsubscribe Page', 'ivole' ),
		        		'type' => 'single_select_page',
		        		'desc' => __( 'Customers will be redirected to this page after clicking the unsubscribe link in emails. You can modify Unsubscribe page as you like but it must contain [ivole_unsubscribe] shortcode.', 'ivole' ),
		        		'id' => 'ivole_email_unsubscribe',
						'css'      => 'min-width:300px;',
						'desc_tip' => true
		      		),
					array(
		        		'title' => __( 'Unsubscribed Emails', 'ivole' ),
		        		'type' => 'textarea_emails',
		        		'desc' => __( 'Comma-separated list of emails of customers who have asked not to receive any more reminders about reviews.', 'ivole' ),
		        		'id' => 'ivole_unsubscribed_emails',
						'css'      => 'min-width:600px;',
						'desc_tip' => true
		      		),
					array(
						'type' => 'sectionend',
						'id' => 'ivole_options_email'
					)
		    );
			}
	    return $settings;
	  }

	  	/**
		 * Output the settings.
		 */
		public function output() {
			global $current_section;
			if($current_section!='ivole_coupons') {
				$settings = $this->get_settings($current_section);
				WC_Admin_Settings::output_fields($settings);
			}
		}

	  	/**
		 * Save settings.
		 */
		public function save() {
			global $current_section;
			if($current_section!='ivole_coupons') {
				$settings = $this->get_settings($current_section);
				WC_Admin_Settings::save_fields($settings);

				if ($current_section) {
					do_action('woocommerce_update_options_' . $this->id . '_' . $current_section);
				}
			}
		}



		/**
		 * Custom field type for categories
		 */
		public function show_cselect($value) {
			$tmp = WC_Admin_Settings::get_field_description($value);
			$tooltip_html = $tmp['tooltip_html'];
			$description = $tmp['description'];
			$args = array(
				'number'     => 0,
				'orderby'    => 'name',
				'order'      => 'ASC',
				'hide_empty' => false,
				'fields'		 => 'id=>name'
			);
			$categories = get_terms( 'product_cat', $args );
			$selections = (array) WC_Admin_Settings::get_option( $value['id'] );
			?><tr valign="top">
				<th scope="row" class="titledesc">
					<label for="<?php echo esc_attr( $value['id'] ); ?>"><?php echo esc_html( $value['title'] ); ?></label>
					<?php echo $tooltip_html; ?>
				</th>
				<td class="forminp">
					<select multiple="multiple" name="<?php echo esc_attr( $value['id'] ); ?>[]" style="width:350px" data-placeholder="<?php esc_attr_e( 'Choose product categories&hellip;', 'ivole' ); ?>" aria-label="<?php esc_attr_e( 'Category', 'ivole' ) ?>" class="wc-enhanced-select">
						<option value="" selected="selected"></option>
						<?php
							if ( ! empty( $categories ) ) {
								foreach ( $categories as $key => $val ) {
									echo '<option value="' . esc_attr( $key ) . '" ' . selected( in_array( $key, $selections ), true, false ) . '>' . $val . '</option>';
								}
							}
						?>
					</select> <?php echo ( $description ) ? $description : ''; ?> <br />
					<a class="select_all button" href="#"><?php _e( 'Select all', 'ivole' ); ?></a>
					<!--<a class="select_none button" href="#"><?php _e( 'Select none', 'ivole' ); ?>--></a>
				</td>
			</tr><?php
		}

		/**
		 * Custom field type for categories
		 */
		public function save_cselect( $value, $option, $raw_value ) {
			if(is_array($value)){
				$value=array_filter($value,function($v){return $v!="";});
			}
			return $value;
		}

		/**
		 * Custom field type for body email
		 */
		public function show_htmltext($value) {
			$tmp = WC_Admin_Settings::get_field_description($value);
			$tooltip_html = $tmp['tooltip_html'];
			$description = $tmp['description'];
			$default_text = "Hi {customer_first_name},\n\nThank you for shopping with us!\n\nWe would love if you could help us and other customer by reviewing your shopping experience and products that you recently purchased. It only takes a minute and it would really help others. Click the link below for each product from your order and leave your review under the \"Reviews\" tab.\n<h2>Order #{order_id}, {order_date}</h2>\n{order_items}\n\nBest wishes,\nJohn Doe\nCEO of Sky Shop\n<p style=\"text-align: center;\">Don't want future emails? {unsubscribe_link}</p>";
			$body = wp_kses_post( WC_Admin_Settings::get_option( $value['id'], $default_text ) );
			$settings = array (
				'teeny' => true,
				'editor_css' => '<style>#wp-ivole_email_body-wrap {max-width: 700px !important;}</style>',
				'textarea_rows' => 26
			);
			?><tr valign="top">
				<th scope="row" class="titledesc">
					<label for="<?php echo esc_attr( $value['id'] ); ?>"><?php echo esc_html( $value['title'] ); ?></label>
					<?php echo $tooltip_html; ?>
				</th>
				<td class="forminp forminp-<?php echo sanitize_title( $value['type'] ) ?>">
					<?php echo $description; ?>
					<?php wp_editor( $body, 'ivole_email_body', $settings );
					echo '<div">';
					echo '<p style="font-weight:bold;margin-top:1.5em;font-size=1em;">' . __( 'Variables', 'ivole' ) . '</p>';
					echo '<p>' . __( 'You can use the following variables in the email:' ) . '</p>';
					echo '<p><strong>{site_title}</strong> - ' . __( 'The title of your WordPress website.' ) . '</p>';
					echo '<p><strong>{customer_first_name}</strong> - ' . __( 'The first name of the customer who purchased from your store.' ) . '</p>';
					echo '<p><strong>{customer_name}</strong> - ' . __( 'The full name of the customer who purchased from your store.' ) . '</p>';
					echo '<p><strong>{order_id}</strong> - ' . __( 'The order number for the purchase.' ) . '</p>';
					echo '<p><strong>{order_date}</strong> - ' . __( 'The date that the order was made.' ) . '</p>';
					echo '<p><strong>{order_items}</strong> - ' . __( 'Displays a list of purchased items.' ) . '</p>';
					echo '<p><strong>{unsubscribe_link}</strong> - ' . __( 'The link to unsubscribe from reminders about reviews.' ) . '</p>';
					echo '</div>';
					?>
				</td>
			</tr>
			<?php
		}

		/**
		 * Custom field type for body email
		 */
		public function save_htmltext( $value, $option, $raw_value ) {
			//error_log( print_r( $raw_value, true ) );
			//error_log( print_r( wp_kses_post( $raw_value ), true ) );
			return wp_kses_post( $raw_value );
		}

		/**
		 * Custom field type for body email
		 */
		public function show_emailtest($value) {
			$tmp = WC_Admin_Settings::get_field_description($value);
			$tooltip_html = $tmp['tooltip_html'];
			$description = $tmp['description'];
			$coupon_class='';
			if($value['id']=='ivole_email_test_coupon') {
				$coupon_class=' coupon_mail';
			}

				?><tr valign="top">
				<th scope="row" class="titledesc">
					<label for="<?php echo esc_attr( $value['id'] ); ?>"><?php echo esc_html( $value['title'] ); ?></label>
					<?php echo $tooltip_html; ?>
				</th>
				<td class="forminp forminp-<?php echo sanitize_title( $value['type'] ) ?>">
					<input
						name="<?php echo esc_attr( $value['id'] ); ?>"
						id="<?php echo esc_attr( $value['id'] ); ?>"
						type="text"
						style="<?php echo esc_attr( $value['css'] ); ?>"
						class="<?php echo esc_attr( $value['class'] ); ?>"
						placeholder="<?php echo esc_attr( $value['placeholder'] ); ?>"
						/> <?php echo $description; ?>
					<input
						type="button"
						id="ivole_test_email_button"
						value="Send Test"
						class="button-primary <?php echo $coupon_class; ?>"
						/>
					<p id="ivole_test_email_status" style="font-style:italic;visibility:hidden;">A</p>
				</td>
			</tr>
			<?php
		}

		/**
		 * Custom field type for unsubscribed emails
		 */
		public function show_textarea_emails($value) {
			$tmp = WC_Admin_Settings::get_field_description($value);
			$tooltip_html = $tmp['tooltip_html'];
			$description = $tmp['description'];
			$emails_array = get_option( $value['id'], array() );
			$option_value = implode(', ', $emails_array);
			?><tr valign="top">
				<th scope="row" class="titledesc">
					<label for="<?php echo esc_attr( $value['id'] ); ?>"><?php echo esc_html( $value['title'] ); ?></label>
					<?php echo $tooltip_html; ?>
				</th>
				<td class="forminp forminp-<?php echo sanitize_title( $value['type'] ) ?>">
					<?php echo $description; ?>

					<textarea
						name="<?php echo esc_attr( $value['id'] ); ?>"
						id="<?php echo esc_attr( $value['id'] ); ?>"
						style="<?php echo esc_attr( $value['css'] ); ?>"
						class="<?php echo esc_attr( $value['class'] ); ?>"
						><?php echo esc_textarea( $option_value );  ?></textarea>
				</td>
			</tr>
			<?php
		}

		/**
		 * Custom field type for unsubscribed emails
		 */
		public function save_textarea_emails( $value, $option, $raw_value ) {
			// remove whitespaces
			$value = preg_replace('/\s+/', '', $value);
			// convert string to array
			$emails_array = explode( ',', $value );
			// validate input and make sure that emails have correct format
			$emails_array2 = array();
			foreach ( $emails_array as $key => $value ) {
				if ( filter_var($value, FILTER_VALIDATE_EMAIL) ) {
					$emails_array2[] = $value;
				}
			}
			return $emails_array2;
		}

		/**
		 * Function to include JS with AJAX that is necessary for testing email
		 */
		public function test_email_javascript() {
			?>
			<script type="text/javascript" >
			jQuery(document).ready(function($) {
				jQuery('#ivole_test_email_button').click(function(){
					var is_coupon='';
					if(jQuery(this).hasClass("coupon_mail")){
						is_coupon='_coupon';
					}
					if(is_coupon=="") {
						var data = {
							'action': 'ivole_send_test_email' + is_coupon,
							'email': jQuery('#ivole_email_test' + is_coupon).val()
						};
					}else{
						var data = {
							'action': 'ivole_send_test_email' + is_coupon,
							'email': jQuery('#ivole_email_test' + is_coupon).val(),
							'coupon_type' : jQuery('#ivole_coupon_type').val(),
							'existing_coupon' : jQuery('#ivole_existing_coupon').val(),
							'discount_type': jQuery('#ivole_coupon__discount_type').val(),
							'discount_amount': jQuery('#ivole_coupon__coupon_amount').val(),
						};
					}
					jQuery('#ivole_test_email_status').text('Sending...');
					jQuery('#ivole_test_email_status').css('visibility', 'visible');
					jQuery('#ivole_test_email_button').prop('disabled', true);
					jQuery.post(ajaxurl, data, function(response) {
						jQuery('#ivole_test_email_status').css('visibility', 'visible');
						jQuery('#ivole_test_email_button').prop('disabled', false);
						if(response === '0') {
							jQuery('#ivole_test_email_status').text('Success: email has been successfully sent!');
						} else if (response === '1') {
							jQuery('#ivole_test_email_status').text('Error: email could not be sent, please check if your WordPress is properly configured for sending emails.');
						} else if (response === '99') {
							jQuery('#ivole_test_email_status').text('Error: please enter a valid email address!');
						} else {
							jQuery('#ivole_test_email_status').text('Error: unknown error!');
						}
						console.log("test Email ajax "+is_coupon);
					});
		    });
			});
			</script>
			<?php
		}

		/**
		 * Function that sends testing email
		 */
		public function send_test_email() {
			$email = strval( $_POST['email'] );
			if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
				$e = new Ivole_Email();
				$result = $e->trigger( null, $email );
				if($result) {
					echo '0';
				} else {
					echo '1';
				}
			} else {
				echo '99';
			}
			wp_die();
		}

		/**
		 * Function to create unsubscribe page (if none is specified in settings)
		 */
		public function create_unsubscribe_page() {
			// if there is no "Unsubscribe" page specified, create one
			if( false === get_option( 'ivole_email_unsubscribe', false ) ) {
				$unsubscribe_page = array(
					'post_title'   => 'Unsubscribe',
					'post_content' => '[ivole_unsubscribe]',
					'post_type'    => 'page',
					'post_status'  => 'publish'
				);
				$unsubscribe_page_id = wp_insert_post( $unsubscribe_page );
				if( !is_wp_error( $unsubscribe_page_id ) || $unsubscribe_page_id > 0) {
					update_option( 'ivole_email_unsubscribe', $unsubscribe_page_id );
				}
			}
		}

		public function get_sections() {
		    $sections = array(
		        '' => __( 'Review Reminder', 'ivole' ),
		        'ivole_reviews' => __( 'Review Extensions', 'ivole' ),
				'ivole_coupons' => __( 'Review for Discount', 'ivole' )
		    );
		    return $sections;
		}

		public function output_sections() {
        global $current_section;

        $sections = $this->get_sections();

        if ( empty( $sections ) || 1 === sizeof( $sections ) ) {
            return;
        }

        echo '<ul class="subsubsub">';

        $array_keys = array_keys( $sections );

        foreach ( $sections as $id => $label ) {
            echo '<li><a href="' . admin_url( 'admin.php?page=wc-settings&tab=' . $this->id . '&section=' . sanitize_title( $id ) ) . '" class="' . ( $current_section == $id ? 'current' : '' ) . '">' . $label . '</a> ' . ( end( $array_keys ) == $id ? '' : '|' ) . ' </li>';
        }

        echo '</ul><br class="clear" />';
    }

	}

endif;

?>
