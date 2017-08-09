<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'Ivole_Admin_Coupon' ) ) :

    require_once('class-ivole-email-coupon.php');

    global $woocommerce;

    class Ivole_Admin_Coupon{
        public function __construct(){
            $this->ivole_id = 'ivole';
            $this->id = 'coupons';
            $this->section_id='ivole_coupons';
            add_action( 'woocommerce_settings_' . $this->ivole_id, array( $this, 'output' ) );
            add_action( 'woocommerce_settings_save_' . $this->ivole_id, array( $this, 'save' ) );
            add_action( 'woocommerce_admin_field_titlewithid', array( $this, 'show_titlewithid' ) );
            add_action( 'woocommerce_admin_field_sectionendwithid', array( $this, 'show_sectionendwithid' ) );

            add_action( 'woocommerce_admin_field_couponselect', array( $this, 'show_couponselect' ) );
            add_action( 'woocommerce_admin_settings_sanitize_option_ivole_existing_coupon', array( $this, 'save_couponselect' ), 10, 3 );

            add_action( 'woocommerce_admin_field_productsearch', array( $this, 'show_productsearch' ) );
            add_action( 'woocommerce_admin_settings_sanitize_option_ivole_coupon__product_ids', array( $this, 'save_product_ids' ), 10, 3 );
            add_action( 'woocommerce_admin_settings_sanitize_option_ivole_coupon__exclude_product_ids', array( $this, 'save_exclude_product_ids' ), 10, 3 );
            add_action( 'woocommerce_admin_settings_sanitize_option_ivole_coupon__product_categories', array( $this, 'save_product_categories' ), 10, 3 );
            add_action( 'woocommerce_admin_settings_sanitize_option_ivole_coupon__excluded_product_categories', array( $this, 'save_excluded_product_categories' ), 10, 3 );

            add_action( 'woocommerce_admin_field_htmltext_coupon', array( $this, 'show_htmltext_coupon' ) );
            add_action( 'woocommerce_admin_settings_sanitize_option_ivole_email_body_coupon', array( $this, 'save_htmltext_coupon' ), 10, 3 );


            add_action('admin_head', array($this, 'add_admin_js'));
            add_action('wp_ajax_woocommerce_json_search_coupons', array($this,'woocommerce_json_search_coupons'));
            add_action( 'wp_ajax_ivole_send_test_email_coupon', array( $this, 'send_test_email' ) );

            add_action( 'comment_post', array( $this, 'comment_received' ), 10, 2 );
            add_action('comment_unapproved_to_approved',array( $this, 'comment_approved'));
        }

        /**
         * Hook callback when review approved
         */
        public function comment_approved($comment){
            $this->comment_received($comment->comment_ID, 1);
        }
            /**
         * Hook callback when review received
         */
        public function comment_received($comment_id,$approved){
            $coupon_enabled = get_option( 'ivole_coupon_enable', 'no' );
            $comment=get_comment($comment_id);
            $to=$comment->comment_author_email;
            $post_type=get_post_type($comment->comment_post_ID);
            $emails_array = get_option( 'ivole_unsubscribed_emails', array() );
            if( in_array( $to, $emails_array ) ) {
                //the customer opted out from email list, skip sending
                return;
            }else {
                $guest_coupon_enabled = get_option( 'ivole_coupon_guest_enabled','no');
                if ($approved == 1 && $coupon_enabled == 'yes' && $post_type == 'product'
                    && ( ($guest_coupon_enabled=='yes' && wc_customer_bought_product( $to, false,$comment->comment_post_ID  ) )
                        ||
                        ($guest_coupon_enabled=='no' && intval($comment->user_id)>0 ) )
                ) {
                    $e = new Ivole_Email_Coupon();
                    $result = $e->trigger($comment_id, $to);
                }
            }
        }


        /**
         * Add js scrips to plugin settings page.
         */
        public function add_admin_js(){
            if(isset($_GET['page']) && $_GET['page']=='wc-settings' && isset($_GET['tab']) && $_GET['tab']==$this->ivole_id
                && (!isset($_GET['section']) || !in_array($_GET['section'], array('ivole_reviews','ivole_coupons')))){
                // add warning text about coupons dynamically above the email body
                $is_coupon_enabled=WC_Admin_Settings::get_option( 'ivole_coupon_enable' );
                ?>
                <style >
                    li.select2-selection__choice[title=""] {
                        display: none;
                    }
                    #coupon_notification > td{
                        padding:0 !important;
                    }
                    #coupon_notification > td span{
                        max-width: 680px !important;
                        padding:10px;
                        margin-left:10px;
                        display:inline-block;
                        background-color: #ffff00;

                    }
                </style>
                <script type="text/javascript">
                    <?php
                    $display=($is_coupon_enabled=='yes') ? '' : 'display:none;';
                    ?>
                    var coupon_notification_html="<tr valign='top'  style='<?php echo $display; ?>' id='coupon_notification'><th></th><td><span><strong>";
                    coupon_notification_html+="<?php echo __( 'Discounts for customers who provide reviews are enabled. Don’t forget to mention it in this email to increase the number of reviews.</span>', 'ivole' ) ?>";
                    coupon_notification_html+="</strong></td></tr>";
                    jQuery(document).ready(function(){
                        jQuery('#ivole_email_heading').parent().parent().after(coupon_notification_html);
                    });
                </script>
                <?php
            }elseif(isset($_GET['page']) && $_GET['page']=='wc-settings' &&
                isset($_GET['tab']) && $_GET['tab']==$this->ivole_id &&
                isset($_GET['section']) && $_GET['section']=='ivole_coupons') {
                // show/hide the setting for static/dynamic coupon creation
                $coupon_type=WC_Admin_Settings::get_option( 'ivole_coupon_type','static' );
                ?>
                <style >
                    li.select2-selection__choice[title=""] {
                        display: none;
                    }
                </style>
                <script id='ivole-coupon-scripts' type="text/javascript">
                    jQuery(document).ready(function(){
                        var ctype=jQuery('#ivole_coupon_type').val();
                        if(ctype=='static') {
                            jQuery('.coupon-setting-fields-dynamic').hide();
                            jQuery('.coupon-setting-fields-static').show();
                        }else{
                            jQuery('.coupon-setting-fields-dynamic').show();
                            jQuery('.coupon-setting-fields-static').hide();
                        }
                        jQuery('#ivole_coupon_type').change(function(){
                            if(jQuery(this).val()=='static') {
                                jQuery('.coupon-setting-fields-dynamic').hide();
                                jQuery('.coupon-setting-fields-static').show();
                            }else{
                                jQuery('.coupon-setting-fields-dynamic').show();
                                jQuery('.coupon-setting-fields-static').hide();
                            }
                        });
                        jQuery('#mainform').submit(function(){
                            if(jQuery('#ivole_coupon_type').val()=='static' && jQuery('#ivole_coupon_enable:checked').length>0) {
                                var v = jQuery('#ivole_existing_coupon').val();
                                if (parseInt(v) + '' != v + '' || parseInt(v) == 0) {
                                    alert("<?php echo __('Please select an existing coupon!'); ?>");
                                    return false;
                                }
                                return true;
                            }
                            return true;
                        });
                    });

                    jQuery( function( $ ) {
                        function getEnhancedSelectFormatString() {
                            return {
                                'language': {
                                    errorLoading: function() {
                                        // Workaround for https://github.com/select2/select2/issues/4355 instead of i18n_ajax_error.
                                        return wc_enhanced_select_params.i18n_searching;
                                    },
                                    inputTooLong: function( args ) {
                                        var overChars = args.input.length - args.maximum;

                                        if ( 1 === overChars ) {
                                            return wc_enhanced_select_params.i18n_input_too_long_1;
                                        }

                                        return wc_enhanced_select_params.i18n_input_too_long_n.replace( '%qty%', overChars );
                                    },
                                    inputTooShort: function( args ) {
                                        var remainingChars = args.minimum - args.input.length;

                                        if ( 1 === remainingChars ) {
                                            return wc_enhanced_select_params.i18n_input_too_short_1;
                                        }

                                        return wc_enhanced_select_params.i18n_input_too_short_n.replace( '%qty%', remainingChars );
                                    },
                                    loadingMore: function() {
                                        return wc_enhanced_select_params.i18n_load_more;
                                    },
                                    maximumSelected: function( args ) {
                                        if ( args.maximum === 1 ) {
                                            return wc_enhanced_select_params.i18n_selection_too_long_1;
                                        }

                                        return wc_enhanced_select_params.i18n_selection_too_long_n.replace( '%qty%', args.maximum );
                                    },
                                    noResults: function() {
                                        return wc_enhanced_select_params.i18n_no_matches;
                                    },
                                    searching: function() {
                                        return wc_enhanced_select_params.i18n_searching;
                                    }
                                }
                            };
                        }
                        try {
                            // Ajax coupon search box
                            console.log("init runs..")
                            $(':input.wc-coupon-search').filter(':not(.enhanced)').each(function () {
                                console.log("ss")
                                var select2_args = {
                                    allowClear: $(this).data('allow_clear') ? true : false,
                                    placeholder: $(this).data('placeholder'),
                                    minimumInputLength: $(this).data('minimum_input_length') ? $(this).data('minimum_input_length') : '3',
                                    escapeMarkup: function (m) {
                                        return m;
                                    },
                                    ajax: {
                                        url: wc_enhanced_select_params.ajax_url,
                                        dataType: 'json',
                                        delay: 250,
                                        data: function (params) {
                                            return {
                                                term: params.term,
                                                action: $(this).data('action') || 'woocommerce_json_search_coupons',
                                                security: wc_enhanced_select_params.search_products_nonce,
                                                exclude: $(this).data('exclude'),
                                                include: $(this).data('include'),
                                                limit: $(this).data('limit')
                                            };
                                        },
                                        processResults: function (data) {
                                            var terms = [];
                                            if (data) {
                                                $.each(data, function (id, text) {
                                                    terms.push({id: id, text: text});
                                                });
                                            }
                                            return {
                                                results: terms
                                            };
                                        },
                                        cache: true
                                    }
                                };

                                select2_args = $.extend(select2_args, getEnhancedSelectFormatString());

                                $(this).select2(select2_args).addClass('enhanced');

                                if ($(this).data('sortable')) {
                                    var $select = $(this);
                                    var $list = $(this).next('.select2-container').find('ul.select2-selection__rendered');

                                    $list.sortable({
                                        placeholder: 'ui-state-highlight select2-selection__choice',
                                        forcePlaceholderSize: true,
                                        items: 'li:not(.select2-search__field)',
                                        tolerance: 'pointer',
                                        stop: function () {
                                            $($list.find('.select2-selection__choice').get().reverse()).each(function () {
                                                var id = $(this).data('data').id;
                                                var option = $select.find('option[value="' + id + '"]')[0];
                                                $select.prepend(option);
                                            });
                                        }
                                    });
                                }
                                //	});
                            });
                        }catch( err ) {
                            // If select2 failed (conflict?) log the error but don't stop other scripts breaking.
                            window.console.log( err );
                        }
                    });
                </script>
                <?php
            }
        }

        /**
         * Get settings array fro the coupon section
         *
         * @return array
         */
        public function get_settings( $current_section = '' ) {
            $settings = array(
                            array(
                                    'title' => __( 'Review for Discount', 'ivole' ),
                                    'type' => 'title',
                                    'desc' => __( 'Settings for WooCommerce Coupons sending for Review.', 'ivole' ),
                                    'id' => 'ivole_coupon_options_selector',
                            ),

                            array(
                                    'title'         => __( 'Enable	Review	for	Discount', 'ivole' ),
                                    'desc'          => __( 'Enable generation of discount coupons for customers who provide reviews.', 'ivole' ),
                                    'id'            => 'ivole_coupon_enable',
                                    'default'       => 'no',
                                    'type'          => 'checkbox'
                            ),
                            array(
                                    'title' => __( 'Coupon to Use', 'ivole' ),
                                    'type' => 'select',
                                    'desc' => __( 'Choose if an individual unique coupon will be generated for
  										            each customer or the same existing coupon configured in WooCommerce
  										            will be sent to all customers.', 'ivole' ),
                                    'id' => 'ivole_coupon_type',
                                    'default' =>'static',
                                    'options' => array('static' => __( 'Existing Coupon','ivole'), 'dynamic' =>__( 'Generate a Unique Coupon','ivole')),
                                    'desc_tip' => true
                            ),
                            array(
                                    'title'         => __( 'Send to Guests', 'ivole' ),
                                    'desc'          => __( 'Enable sending of discounts to customers who do not have accounts on the website but left a review for a purchased product.', 'ivole' ),
                                    'id'            => 'ivole_coupon_guest_enabled',
                                    'default'       => 'no',
                                    'type'          => 'checkbox'
                            ),
                            array(
                                    'title' => __( 'BCC Address', 'ivole' ),
                                    'type' => 'text',
                                    'desc' => __( 'Add a BCC recipient for emails with discount coupon. It can be useful to verify that emails are being sent properly.', 'ivole' ),
                                    'default'  => '',
                                    'id' => 'ivole_coupon_email_bcc',
                                    'css'      => 'min-width:300px;',
                                    'desc_tip' => true
                            ),

                            array(
                                    'type' => 'sectionend',
                                    'id' => 'ivole_coupon_options_selector'
                            ),
                            array(
                                    'title' => __( 'Existing Coupon to Use', 'ivole' ),
                                    'type' => 'titlewithid',
                                    'desc' => __( 'Choose one the existing coupons to be sent to customers who provided reviews.', 'ivole' ),
                                    'id' => 'ivole_coupon_options_static',
                                    'class'=> 'coupon-setting-fields-static',
                                    'css' => 'display:none;'
                            ),
                            array(
                                    'title' => __('Existing coupon','ivole'),
                                    'type' => 'couponselect',
                                    'desc' => __( 'This coupon code will be sent to customers who provide reviews.','ivole'),
                                    'id' => 'ivole_existing_coupon'
                            ),
                            array(
                                    'type' => 'sectionendwithid',
                                    'id' => 'ivole_coupon_options_static'
                            ),
                            array(
                                    'title' => __( 'Generation of Individual Coupons', 'ivole' ),
                                    'type' => 'titlewithid',
                                    'desc' => __( 'Settings for automatic generation of unique coupons for each customer and order. When a customer submits a review, a new, unique discount code will be generated according to these settings and sent to the customer.', 'ivole' ),
                                    'id' => 'ivole_coupon_options_dynamic',
                                    'class' => 'coupon-setting-fields-dynamic',
                                    'css'=>'display:none'
                            ),
                            array(
                                    'id'      => 'ivole_coupon__discount_type',
                                    'title'   => __( 'Discount type', 'woocommerce' ),
                                    'options' => wc_get_coupon_types(),
                                    'type' => 'select'
                            ),
                            array(
                                    'id'          => 'ivole_coupon__coupon_amount',
                                    'title'       => __( 'Coupon amount', 'woocommerce' ),
                                    'placeholder' => wc_format_localized_price( 0 ),
                                    'desc' => __( 'Value of the coupon.', 'woocommerce' ),
                                    'type'   => 'text',
                                    'desc_tip'    => true,
                            ),
                            array(
                                    'id'          => 'ivole_coupon__free_shipping',
                                    'title'       => __( 'Allow free shipping', 'woocommerce' ),
                                    'desc' => sprintf( __( 'Check this box if the coupon grants free shipping. A <a href="%s" target="_blank">free shipping method</a> must be enabled in your shipping zone and be set to require "a valid free shipping coupon" (see the "Free Shipping Requires" setting).', 'woocommerce' ), 'https://docs.woocommerce.com/document/free-shipping/' ),
                                    'default'       => 'no',
                                    'type'          => 'checkbox'
                            ) ,
                            array(
                                    'id'          => 'ivole_coupon__expires_days',
                                    'title'       => __( 'Validity', 'ivole' ),
                                    'placeholder' => 0,//wc_format_localized_price( 0 ),
                                    'desc' => __( 'Number of days during which the coupon will be valid from the moment of submission of a review or set to 0 for unlimited validity.', 'ivole' ),
                                    'class'             => 'short',
                                    'custom_attributes' => array(
                                        'step' 	=> 1,
                                        'min'	=> 0,
                                    ),
                                    'type'   => 'number',
                                    'default' => '0',
                                    'desc_tip'    => true,
                            ),
                            array(
                                    'id'          => 'ivole_coupon__minimum_amount',
                                    'title'       => __( 'Minimum spend', 'woocommerce' ),
                                    'placeholder' => __( 'No minimum', 'woocommerce' ),
                                    'desc' => __( 'This field allows you to set the minimum spend (subtotal, including taxes) allowed to use the coupon.', 'woocommerce' ),
                                    'type'   => 'text',
                                    'desc_tip'    => true,
                            ),
                            array(
                                    'id'          => 'ivole_coupon__maximum_amount',
                                    'title'       => __( 'Maximum spend', 'woocommerce' ),
                                    'placeholder' => __( 'No maximum', 'woocommerce' ),
                                    'desc' => __( 'This field allows you to set the maximum spend (subtotal, including taxes) allowed when using the coupon.', 'woocommerce' ),
                                    'type'   => 'text',
                                    'desc_tip'    => true,
                            ),
                            array(
                                    'id'          => 'ivole_coupon__individual_use',
                                    'title'       => __( 'Individual use only', 'woocommerce' ),
                                    'desc' => __( 'Check this box if the coupon cannot be used in conjunction with other coupons.', 'woocommerce' ),
                                    'default'       => 'no',
                                    'type'          => 'checkbox'
                            ),
                            array(
                                    'id'          => 'ivole_coupon__exclude_sale_items',
                                    'title'       => __( 'Exclude sale items', 'woocommerce' ),
                                    'desc' => __( 'Check this box if the coupon should not apply to items on sale. Per-item coupons will only work if the item is not on sale. Per-cart coupons will only work if there are items in the cart that are not on sale.', 'woocommerce' ),
                                    'default'       => 'no',
                                    'type'          => 'checkbox'
                            ),
                            array(
                                    'id'          => 'ivole_coupon__product_ids',
                                    'title'       => __( 'Products', 'woocommerce' ),
                                    'desc' => __( 'Products which need to be in the cart to use this coupon or, for "Product Discounts", which products are discounted.', 'woocommerce' ),
                                    'placeholder' => __( 'Search for a product&hellip;', 'woocommerce' ),
                                    'type'          => 'productsearch'
                            ),
                            array(
                                    'id'          => 'ivole_coupon__exclude_product_ids',
                                    'title'       => __( 'Exclude products', 'woocommerce' ),
                                    'desc' => __( 'Products which must not be in the cart to use this coupon or, for "Product Discounts", which products are not discounted.', 'woocommerce' ),
                                    'placeholder' => __( 'Search for a product&hellip;', 'woocommerce' ),
                                    'type'          => 'productsearch'
                            ),
                            array(
                                    'title' => __( 'Product categories', 'ivole' ),
                                    'type' => 'cselect',
                                    'desc' => __( 'A product must be in this category for the coupon to remain valid or, for "Product Discounts", products in these categories will be discounted.', 'ivole' ),
                                    'id' => 'ivole_coupon__product_categories',
                                    'desc_tip' => true,
                                    'class'    => 'wc-enhanced-select',
                                    'css'      => 'min-width:300px;'
                            ),
                            array(
                                    'title' => __( 'Exclude categories', 'ivole' ),
                                    'type' => 'cselect',
                                    'desc' => __( 'Product must not be in this category for the coupon to remain valid or, for "Product Discounts", products in these categories will not be discounted.', 'ivole' ),
                                    'id' => 'ivole_coupon__excluded_product_categories',
                                    'desc_tip' => true,
                                    'class'    => 'wc-enhanced-select',
                                    'css'      => 'min-width:300px;'
                            ),
                            array(
                                    'id'  => 'ivole_coupon__usage_limit',
                                    'title' => __( 'Usage limit', 'woocommerce' ),
                               //     'placeholder' => esc_attr__( 'Unlimited usage', 'woocommerce' ),
                                    'desc'  => __( 'How many times this coupon can be used before it is void. Set it to 0 for unlimited usage.', 'woocommerce' ),
                                    'type'              => 'number',
                                    'desc_tip'          => true,
                                    'default'           => 0,
                                    'placeholder' => 0,
                                    'class'             => 'short',
                                    'custom_attributes' => array(
                                                    'step' 	=> 1,
                                                    'min'	=> 0,
                                        )
                            ),



                            array(
                                    'type' => 'sectionendwithid',
                                    'id' => 'ivole_coupon_options_dynamic'
                            ),
                            array(
                                    'title' => __( 'Email Template', 'ivole' ),
                                    'type' => 'title',
                                    'desc' => __( 'Adjust template of the email that will be sent to customers.', 'ivole' ),
                                    'id' => 'ivole_options_email_coupon'
                            ),
                            array(
                                    'title' => __( 'Email Subject', 'ivole' ),
                                    'type' => 'text',
                                    'desc' => __( 'Subject of the email that will be sent to customers.', 'ivole' ),
                                    'default'  => '[{site_title}] Discount Coupon for You',
                                    'id' => 'ivole_email_subject_coupon',
                                    'css'      => 'min-width:600px;',
                                    'desc_tip' => true
                            ),
                            array(
                                    'title' => __( 'Email Heading', 'ivole' ),
                                    'type' => 'text',
                                    'desc' => __( 'Heading of the email that will be sent to customers.', 'ivole' ),
                                    'default'  => 'Thank You for Leaving a Review',
                                    'id' => 'ivole_email_heading_coupon',
                                    'css'      => 'min-width:600px;',
                                    'desc_tip' => true
                            ),
                            array(
                                    'title' => __( 'Email Body', 'ivole' ),
                                    'type' => 'htmltext_coupon',
                                    'desc' => __( 'Body of the email that will be sent to customers.', 'ivole' ),
                                    'id' => 'ivole_email_body_coupon',
                                    'desc_tip' => true
                            ),
                            array(
                                    'title' => __( 'Send Test', 'ivole' ),
                                    'type' => 'emailtest',
                                    'desc' => __( 'Send a test email to this address.', 'ivole' ),
                                    'default'  => '',
                                    'placeholder' => 'Email address',
                                    'id' => 'ivole_email_test_coupon',
                                    'css'      => 'min-width:300px;',
                                    'desc_tip' => true
                            ),
                            array(
                                    'type' => 'sectionend',
                                    'id' => 'ivole_options_email_coupon'
                            )
            );
            return $settings;
        }

        /**
         * Custom field type for section start with ID and/or CLASS option (adding a wrapper div)
         */
        public function show_titlewithid($value) {
            $tmp = WC_Admin_Settings::get_field_description($value);
//			$tooltip_html = $tmp['tooltip_html'];
            $description = $tmp['description'];
            $id=(isset($value['id']) && $value['id']!="") ? "id='".$value['id']."'" : ''  ;
            $class=isset($value['class']) ? "class='".$value['class']."'" : ''  ;
            $css=isset($value['css']) ? $value['css'] : ''  ;
            echo "<div $id $class style='$css'>";
            if ( ! empty( $value['title'] ) ) {
                echo '<h3>' . esc_html( $value['title'] ) . '</h3>';
            }
            if ( ! empty( $description ) ) {
                echo wpautop( wptexturize( wp_kses_post( $description ) ) );
            }
            echo '<table class="form-table">'. "\n\n";
        }
        /**
         * Custom field type for section end with ID and/or CLASS option (adding a wrapper div)
         */
        public function show_sectionendwithid($value) {
            echo '</table></div>';
        }

        /**
         * Custom field type for selecting products
         */

        public function show_productsearch($value){
            $tmp = WC_Admin_Settings::get_field_description($value);
            $tooltip_html = $tmp['tooltip_html'];
            $description = $tmp['description'];
            $selection = (array)WC_Admin_Settings::get_option( $value['id'] );
            $class="wc-product-search";
            ?>
            <tr valign="top">
            <th scope="row" class="titledesc">
                <label for="<?php echo esc_attr( $value['id'] ); ?>"><?php echo esc_html( $value['title'] ); ?></label>
                <?php echo $tooltip_html; ?>
                <?php //print_r($selection); ?>
            </th>
            <td class="forminp"><!-- id="<?php echo esc_attr( $value['id'] ); ?>" -->
                <select class="<?php echo $class; ?>"  multiple="multiple" style="width: 350px;"
                        name="<?php echo esc_attr( $value['id'] ); ?>[]" data-placeholder="<?php esc_attr_e($value['placeholder'],'woocommerce'); ?>"
                        data-action="woocommerce_json_search_products_and_variations" data-allow_clear="true" >
                    <option value="" selected="selected"></option>
                    <?php
                        foreach ( $selection as $product_id ) {
                                $product = wc_get_product( $product_id );
                                if ( is_object( $product ) ) {
                                    echo '<option value="' . esc_attr( $product_id ) . '"' . selected( true, true, false ) . '>' . wp_kses_post( $product->get_formatted_name() ) . '</option>';
                                }
                        }
                    ?>
                </select><br>
                <?php echo ( $description ) ? $description : ''; ?>
            </td>
            </tr>
            <?php
        }

        /**
         * Custom field type for selecting existing coupons
         */
        public function show_couponselect($value) {
            $tmp = WC_Admin_Settings::get_field_description($value);
            $tooltip_html = $tmp['tooltip_html'];
            $description = $tmp['description'];
            $selection = WC_Admin_Settings::get_option( $value['id'] );
            $coupons=$this->get_existing_coupons();
            ?><tr valign="top">
            <th scope="row" class="titledesc">
                <label for="<?php echo esc_attr( $value['id'] ); ?>"><?php echo esc_html( $value['title'] ); ?></label>
                <?php echo $tooltip_html; ?>
            </th>
            <td class="forminp">
                <select class="wc-coupon-search"  style="width: 350px;" id="<?php echo esc_attr( $value['id'] ); ?>"
                        name="<?php echo esc_attr( $value['id'] ); ?>" data-placeholder="Search for a coupon&hellip;"
                        data-action="woocommerce_json_search_coupons" >
                    <?php
                    foreach($coupons as $key=>$val){
                        if($selection==$key){
                            echo "<option value='". esc_attr( $key ) ."'>".$val."</option>";
                        }
                    }
                    ?>
                </select><br>

                <?php echo ( $description ) ? $description : ''; ?>
            </td>
            </tr><?php
        }
        /**
         * Custom field type for body email
         */
        public function show_htmltext_coupon($value) {
            $tmp = WC_Admin_Settings::get_field_description($value);
            $tooltip_html = $tmp['tooltip_html'];
            $description = $tmp['description'];

            $default_text = "Hi {customer_first_name},\n\nThank you for reviewing our product!\n\n";
            $default_text.= "As a token of appreciation, we’d like to offer you a discount coupon for your next purchases in our shop. ";
            $default_text.= "Please apply the following coupon code during checkout to receive {discount_amount} discount.\n\n";
            $default_text.= "{coupon_code}\n\n";
            $default_text.= "Best wishes,\n";
            $default_text.= "John Doe\n";
            $default_text.= "CEO of Sky Shop\n";
            $default_text.= '<p style="text-align: center">Don'."'".'t want future emails? {unsubscribe_link}</p>';


            $body = wp_kses_post( WC_Admin_Settings::get_option( $value['id'], $default_text ) );
            $settings = array (
                'teeny' => true,
                'editor_css' => '<style>#wp-ivole_email_body_coupon-wrap {max-width: 700px !important;}</style>',
                'textarea_rows' => 26
            );
            ?><tr valign="top">
            <th scope="row" class="titledesc">
                <label for="<?php echo esc_attr( $value['id'] ); ?>"><?php echo esc_html( $value['title'] ); ?></label>
                <?php echo $tooltip_html; ?>
            </th>
            <td class="forminp forminp-<?php echo sanitize_title( $value['type'] ) ?>">
                <?php echo $description; ?>
                <?php wp_editor( $body, 'ivole_email_body_coupon', $settings );
                echo '<div">';
                echo '<p style="font-weight:bold;margin-top:1.5em;font-size=1em;">' . __( 'Variables', 'ivole' ) . '</p>';
                echo '<p>' . __( 'You can use the following variables in the email:' ) . '</p>';
                echo '<p><strong>{site_title}</strong> - ' . __( 'The title of your WordPress website.' ) . '</p>';
                echo '<p><strong>{customer_first_name}</strong> - ' . __( 'The first name of the customer who purchased from your store.' ) . '</p>';
                echo '<p><strong>{customer_name}</strong> - ' . __( 'The full name of the customer who purchased from your store.' ) . '</p>';
                echo '<p><strong>{coupon_code}</strong> - ' . __( 'The code of coupon for discount.').'</p>';
                echo '<p><strong>{discount_amount}</strong> - ' . __( 'Amount of the coupon (e.g., $10 or 11% depending on type of the coupon).').'</p>';
                echo '<p><strong>{product_name}</strong> - ' . __( 'Name of product that was reviewed by the customer.').'</p>';
                echo '<p><strong>{unsubscribe_link}</strong> - ' . __( 'The link to unsubscribe from review	for	discount emails.' ) . '</p>';

                echo '</div>';
                ?>
            </td>
            </tr>
            <?php
        }
        /**
         * Custom field type for selecting existing coupons
         */
        public function save_couponselect( $value, $option, $raw_value ) {

            return $value;
        }

        /**
         * Custom field type for selecting products
         */
        public function save_product_ids( $value, $option, $raw_value ) {
            if(is_array($value)){
                $value=array_filter($value,function($v){return $v!="";});
            }
            return $value;
        }

        /**
         * Custom field type for selecting excluded products
         */
        public function save_exclude_product_ids( $value, $option, $raw_value ) {
            if(is_array($value)){
                $value=array_filter($value,function($v){return $v!="";});
            }
            return $value;
        }

        /**
         * Custom field type for selecting product categories of unique coupon
         */
        public function save_product_categories( $value, $option, $raw_value ) {
            if(is_array($value)){
                $value=array_filter($value,function($v){return $v!="";});
            }
            return $value;
        }

        /**
         * Custom field type for selecting excluded product categories of unique coupon
         */
        public function save_excluded_product_categories( $value, $option, $raw_value ) {
            if(is_array($value)){
                $value=array_filter($value,function($v){return $v!="";});
            }
            return $value;
        }

        /**
         * Custom field type for body of coupon email
         */
        public function save_htmltext_coupon( $value, $option, $raw_value ) {
            //error_log( print_r( $raw_value, true ) );
            //error_log( print_r( wp_kses_post( $raw_value ), true ) );
            return wp_kses_post( $raw_value );
        }


        /**
         * Save settings.
         */
        public function save() {
            global $current_section;
            if($current_section=='ivole_coupons') {
                $settings = $this->get_settings($current_section);
                WC_Admin_Settings::save_fields($settings);

                if ($current_section) {
                    do_action('woocommerce_update_options_' . $this->ivole_id . '_' . $current_section);
                }
            }
        }

        public function get_existing_coupons(){
            global $wpdb;
            $all=$wpdb->get_results( "SELECT * FROM $wpdb->posts WHERE
										post_type = 'shop_coupon' AND post_status = 'publish'
										ORDER BY post_date DESC;",ARRAY_A );

            $coupons=array();
            $today=time();
            foreach ( $all as $coupon ) {
                $expires=get_post_meta($coupon['ID'],'date_expires',true);
                $email_array=get_post_meta($coupon['ID'],'customer_email',true);
                if((intval($expires)>$today || intval($expires)==0) &&
                    (!is_array($email_array) || count($email_array)==0)
                ){
                    $coupons[$coupon['ID']] = rawurldecode(stripslashes($coupon['post_title']));
                }
            }
            return $coupons;

        }

        /**
         * Ajax action callback for enhanced select box for existing coupuns
         */

        public function woocommerce_json_search_coupons(){
            global $wpdb;
            $term=stripslashes( $_GET['term']."%" );
            if ( empty( $term ) ) {
                wp_die();
            }
            $data_store = WC_Data_Store::load( 'coupon' );
            //$ids        = $data_store->search_products( $term, '', (bool) $include_variations );
            global $wpdb;
            $all=$wpdb->get_results( $wpdb->prepare( "SELECT * FROM $wpdb->posts WHERE post_title LIKE %s AND
													post_type = 'shop_coupon' AND post_status = 'publish'
													ORDER BY post_date DESC;", $term ),ARRAY_A );

            $coupons=array();
            $today=time();
            foreach ( $all as $coupon ) {
                $expires=get_post_meta($coupon['ID'],'date_expires',true);
                $email_array=get_post_meta($coupon['ID'],'customer_email',true);
                if((intval($expires)>$today || intval($expires)==0) &&
                    (!is_array($email_array) || count($email_array)==0)
                ){
                    $coupons[$coupon['ID']] = rawurldecode(stripslashes($coupon['post_title']));
                }
            }

            wp_send_json( $coupons );
        }

        /**
         * Ajax callback  that sends testing email
         */
        public function send_test_email() {
            $email = strval( $_POST['email'] );
            $coupon_type=$_POST['coupon_type'];
            if($coupon_type=='static'){
                $coupon_id=intval($_POST['existing_coupon']);
                if(get_post_type($coupon_id)=='shop_coupon' && get_post_status($coupon_id)=='publish'){
                    $coupon_code= get_post_field('post_title',$coupon_id);
                    $discount_type=get_post_meta($coupon_id,'discount_type',true);
                    $discount_amount=intval(get_post_meta($coupon_id,'coupon_amount',true));
                    $discount_string="";
                    if($discount_type=="percent" ){
                        $discount_string=$discount_amount."%";
                    }else{
                        $discount_string=trim(strip_tags(wc_price($discount_amount,array('currency'=>get_option('woocommerce_currency')))));
                    }
                }else{
                    $coupon_code="<strong>NO_COUPON_SET</strong>";
                    $discount_string="<strong>NO_AMOUNT_SET</strong>";
                }
            }else{
                $discount_type=$_POST['discount_type'];
                $discount_amount=intval($_POST['discount_amount']);
                if($discount_type=="percent" && $discount_amount>0){
                    $discount_string=$discount_amount."%";
                }elseif($discount_amount>0){
                    $discount_string=trim(strip_tags(wc_price($discount_amount,array('currency'=>get_option('woocommerce_currency')))));
                }
                //$discount_string="";
                $coupon_code=uniqid("TEST");
            }
            if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $e = new Ivole_Email_Coupon();
                $result = $e->trigger( null, $email ,$coupon_code,$discount_string);
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
         * Output the settings.
         */
        public function output() {
            global $current_section;
            if($current_section=='ivole_coupons') {
                $settings = $this->get_settings($current_section);
                WC_Admin_Settings::output_fields($settings);
            }
        }
    }

endif;