<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once(ABSPATH . 'wp-admin/includes/media.php');
require_once(ABSPATH . 'wp-admin/includes/file.php');
require_once(ABSPATH . 'wp-admin/includes/image.php');

if ( ! class_exists( 'Ivole_Reviews' ) ) :

	class Ivole_Reviews {

		private $limit_file_size = 5000000;

	  public function __construct() {
			if( 'yes' == get_option( 'ivole_attach_image', 'no' ) ) {
				add_action( 'woocommerce_product_review_comment_form_args', array( $this, 'custom_fields_attachment' ) );
				add_filter( 'wp_insert_comment', array( $this, 'save_review_image' ) );
				add_filter( 'comments_array', array( $this, 'display_review_image' ) );
				add_action( 'wp_enqueue_scripts', array( $this, 'ivole_style_1' ) );
			}
			if( 'yes' == get_option( 'ivole_enable_captcha', 'no' ) ) {
				add_action( 'woocommerce_product_review_comment_form_args', array( $this, 'custom_fields_captcha' ) );
				add_filter( 'preprocess_comment', array( $this, 'validate_captcha' ) );
				add_action( 'wp_enqueue_scripts', array( $this, 'ivole_style_1' ) );
				add_action( 'wp_enqueue_scripts', array( $this, 'ivole_style_2' ) );
			}
	  }
		public function custom_fields_attachment( $comment_form ) {
			$post_id = get_the_ID();
			$comment_form['comment_field'] .= '<p><label for="comment_image_' . $post_id . '">';
			$comment_form['comment_field'] .= __( 'Upload an image for your review (GIF, PNG, JPG, JPEG):', 'ivole' );
			$comment_form['comment_field'] .= '</label><input type="file" name="review_image_' . $post_id . '" id="review_image" />';
			$comment_form['comment_field'] .= '</p>';
			return $comment_form;
		}
		public function custom_fields_captcha( $comment_form ) {
			$site_key = get_option( 'ivole_captcha_site_key', '' );
			$comment_form['comment_field'] .= '<div class="g-recaptcha ivole-recaptcha" data-sitekey="' . $site_key . '"></div>';
			return $comment_form;
		}
		public function save_review_image( $comment_id ) {
			error_log("comment_id: " . print_r($comment_id, true));
			$post_id = $_POST['comment_post_ID'];
			error_log("post_id: " . print_r($_POST['comment_post_ID'], true));
			$comment_image_id = "review_image_$post_id";
			error_log("comment_image_id: " . print_r($comment_image_id, true));
			error_log("files: " . print_r($_FILES, true));
			if( isset( $_FILES[ $comment_image_id ] ) && ! empty( $_FILES[ $comment_image_id ] ) ) {
				//check file size
				if ( $this->limit_file_size < $_FILES[ $comment_image_id ]['size'] ) {
					echo __( "Error: Uploaded file is too large. <br/> Go back to: ", 'ivole' );
					echo '<a href="' . get_permalink( $post_id ) . '">' . get_the_title( $post_id ) . '</a>';
					die;
				}
				// Get file extension
				$file_name_parts = explode( '.', $_FILES[ $comment_image_id ]['name'] );
				$file_ext = $file_name_parts[ count( $file_name_parts ) - 1 ];

				if( $this->is_valid_file_type( $file_ext ) ) {
					$comment_image_file = wp_upload_bits( $comment_id . '.' . $file_ext, null, file_get_contents( $_FILES[ $comment_image_id ]['tmp_name'] ) );
					$img_url = media_sideload_image( $comment_image_file['url'], $post_id );
					preg_match_all( "#[^<img src='](.*)[^'alt='' />]#", $img_url, $matches );
					$comment_image_file['url'] = $matches[0][0];
					if( FALSE === $comment_image_file['error'] ) {
						// Since we've already added the key for this, we'll just update it with the file.
						add_comment_meta( $comment_id, 'ivole_review_image', $comment_image_file );
					}
				}
			}
		}
		private function is_valid_file_type( $type ) {
			$type = strtolower( trim ( $type ) );
			return  $type == 'png' || $type == 'gif' || $type == 'jpg' || $type == 'jpeg';
		}
		public function display_review_image( $comments ) {
			if( count( $comments ) > 0 ) {
				foreach( $comments as $comment ) {
					if( true == get_comment_meta( $comment->comment_ID, 'ivole_review_image' ) ) {
						$comment_image = get_comment_meta( $comment->comment_ID, 'ivole_review_image', true );
						$comment->comment_content .= '<p class="iv-comment-image-text">Uploaded image:</p>';
						$comment->comment_content .= '<p class="iv-comment-image">';
						$comment->comment_content .= '<a href="' . $comment_image['url'] . '"><img src="' . $comment_image['url'] . '" alt="" /></a>';
						$comment->comment_content .= '</p>';
					}
				}
			}
			return $comments;
		}
		public function ivole_style_1() {
			if( is_product() ) {
				wp_register_style( 'ivole-frontend-css', plugins_url( '/css/frontend.css', __FILE__ ), array(), null, 'all' );
				wp_register_script( 'ivole-frontend-js', plugins_url( '/js/frontend.js', __FILE__ ), array(), null, true );
				wp_enqueue_style( 'ivole-frontend-css' );
				wp_enqueue_script( 'ivole-frontend-js' );
			}
		}
		public function ivole_style_2() {
			if( is_product() ) {
				wp_register_script( 'ivole-recaptcha', 'https://www.google.com/recaptcha/api.js', array(), null, true );
				wp_enqueue_script( 'ivole-recaptcha' );
			}
		}
		public function validate_captcha( $commentdata ) {
			if( get_post_type( $commentdata['comment_post_ID'] ) === 'product' ) {
				if( !$this->ping_captcha() ) {
					wp_die( __( 'reCAPTCHA vertification failed and your review cannot be saved.', 'ivole' ), __( 'Add Review Error', 'ivole' ), array( 'back_link' => true ) );
				}
			}
			return $commentdata;
		}
		private function ping_captcha() {
			if( isset( $_POST['g-recaptcha-response'] ) ) {
				$secret_key = get_option( 'ivole_captcha_secret_key', '' );
				$response = json_decode(wp_remote_retrieve_body( wp_remote_get( "https://www.google.com/recaptcha/api/siteverify?secret=' . $secret_key . '&response=" .$_POST['g-recaptcha-response'] ) ), true );
				if( $response["success"] )
				{
						return true;
				}
			}
			return false;
		}
	}

endif;

?>
