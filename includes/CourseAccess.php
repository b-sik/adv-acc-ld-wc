<?php //phpcs:ignore
/**
 * Course Access (Learn Dash) Customizations
 *
 * @package BSZYK_ADV_ACC
 */

namespace BSZYK_ADV_ACC;

use BSZYK_ADV_ACC\Utilities;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Manage course expiration - manually, extension product, etc.
 */
class CourseAccess {
	/**
	 * Construct
	 */
	public function __construct() {
	}

	/**
	 * Init LearnDash hooks.
	 *
	 * @return void
	 */
	public function init_ld() {
		// manually override the exp date for a user's course.
		add_action( 'admin_post_exp_override_settings__cb', array( $this, 'override_course_expiration' ), 10, 0 );

		// delete a manual override.
		add_action( 'admin_post_del_exp_override_settings__cb', array( $this, 'del_override_course_expiration' ), 10, 0 );

		// save updated exp date from course post data.
		add_action( 'save_post_sfwd-courses', array( $this, 'add_exp_after_event_meta' ), 99, 3 );

		// filter a user's course exp date.
		// manually override, check if first lesson completed, add extension.
		add_filter( 'ld_course_access_expires_on', array( $this, 'filter_user_course_expiration' ), 99, 3 );
	}

	/**
	 * Init WooCommerce hooks
	 *
	 * @return void
	 */
	public function init_wc() {
		// add ext meta to product.
		add_action( 'save_post_product', array( $this, 'add_extension_meta_save_post' ), 99, 3 );

		// add ext meta to user after payment.
		add_action( 'woocommerce_payment_complete', array( $this, 'add_extension_meta_after_purchase' ), 10, 1 );

	}

	/**
	 * Add ext post meta after product w/ extension created/updated.
	 *
	 * @param int     $post_id Post ID.
	 * @param WP_Post $post Post object.
	 * @param bool    $update An existing post being updated or not.
	 *
	 * @return void
	 */
	public function add_extension_meta_save_post( $post_id, $post, $update ) {
		if ( ! isset( $_POST ) || empty( $_POST ) ) {
			return;
		}

		if ( ! isset( $_POST[ BSZYK_ADV_ACC_NONCE_PREFIX . '-ext-meta' ] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST[ BSZYK_ADV_ACC_NONCE_PREFIX . '-ext-meta' ] ) ), BSZYK_ADV_ACC_NONCE_ACTION_PREFIX . '-ext-meta' ) ) {
			wp_die( 'Failed nonce verification: `add_extension_meta_save_post`' );
		}

		if ( ! isset( $_POST['ea_ext_meta'] ) || ! isset( $_POST['ea_ext_meta']['course_id'] ) || ! isset( $_POST['ea_ext_meta']['length__num'] ) ) {
			return;
		}

		// variables.
		$ext_course_id   = sanitize_text_field( wp_unslash( $_POST['ea_ext_meta']['course_id'] ) );
		$ext_length__num = sanitize_text_field( wp_unslash( $_POST['ea_ext_meta']['length__num'] ) );

		if ( '0' === $ext_length__num ) {
			delete_post_meta( $post_id, BSZYK_ADV_ACC_EXT_META );
			return;
		}

		update_post_meta(
			$post_id,
			BSZYK_ADV_ACC_EXT_META,
			array(
				'course_id'   => $ext_course_id,
				'length__num' => $ext_length__num,
			)
		);
	}

	/**
	 * Add updated expiration after lesson completed meta.
	 *
	 * @param int     $post_id Post ID.
	 * @param WP_Post $post Post object.
	 * @param bool    $update An existing post being updated or not.
	 *
	 * @return void
	 */
	public function add_exp_after_event_meta( $post_id, $post, $update ) {
		if ( 'publish' !== $post->post_status || wp_is_post_revision( $post_id ) ) {
			return;
		}

		// if ( wp_verify_nonce( $_POST['learndash-course-users-settings']['nonce'] ) ) {
		// echo 'woooooo!!!!!!';
		// }

		// if ( check_admin_referer( 'edit' ) ) {
		// echo 'woooooo!!!!';
		// }

		// echo Utilities::pretty_print( $_POST );

		// echo 'booooooo!!!!!';

		// turn the setting off if we need to and get out of here.
		if ( ! isset( $_POST['learndash-course-access-settings']['expire_access'] ) || 'on' !== $_POST['learndash-course-access-settings']['expire_access'] ) {
			update_post_meta(
				$post_id,
				BSZYK_ADV_ACC_EXP_AFTER_EVENT_META,
				array(
					'enabled' => false,
				)
			);
			return;
		}

		// check variables.
		if ( ! isset( $_POST['learndash-course-access-settings']['exp_after_event_length'] ) || empty( $_POST['learndash-course-access-settings']['exp_after_event_length'] ) || ! isset( $_POST['learndash-course-access-settings']['exp_after_event_length__type'] ) || ! isset( $_POST['learndash-course-access-settings']['exp_after_event_id'] ) ) {
			update_post_meta(
				$post_id,
				BSZYK_ADV_ACC_EXP_AFTER_EVENT_META,
				array(
					'enabled' => false,
				)
			);
			return;
		}

		// declare variables.
		$length_til_exp = sanitize_text_field( wp_unslash( $_POST['learndash-course-access-settings']['exp_after_event_length'] ) );
		$length_type    = sanitize_text_field( wp_unslash( $_POST['learndash-course-access-settings']['exp_after_event_length__type'] ) );
		$event_id       = sanitize_text_field( wp_unslash( $_POST['learndash-course-access-settings']['exp_after_event_id'] ) );

		if ( 0 === $event_id ) {
			update_post_meta(
				$post_id,
				BSZYK_ADV_ACC_EXP_AFTER_EVENT_META,
				array(
					'enabled' => false,
				)
			);
		}

		if ( $event_id > 0 ) {
			update_post_meta(
				$post_id,
				BSZYK_ADV_ACC_EXP_AFTER_EVENT_META,
				array(
					'length_til_exp'       => $length_til_exp,
					'length_til_exp__type' => $length_type,
					'event_id'             => $event_id,
					'enabled'              => true,
				)
			);
		}
	}

	/**
	 * Add user meta to indicate a manual course expiration override.
	 *
	 * @return void
	 */
	public function override_course_expiration() {
		$error  = null;
		$status = null;
		$msg    = null;
		$note   = '';

		if ( ! isset( $_POST[ BSZYK_ADV_ACC_NONCE_PREFIX . '_exp_override' ] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST[ BSZYK_ADV_ACC_NONCE_PREFIX . '_exp_override' ] ) ), BSZYK_ADV_ACC_NONCE_ACTION_PREFIX . '_exp_override' ) ) {
			wp_die( 'Nonce verification error.' );
		}

		if ( ! isset( $_POST['override_exp_date']['user_id'] ) || ! isset( $_POST['override_exp_date']['course_id'] ) || ! isset( $_POST['override_exp_date']['date'] ) ) {
			$error  = true;
			$status = 'error';
			$msg    = 'Error processing POST data. Variables not set.';
		}

		// variables.
		$user_id   = sanitize_text_field( wp_unslash( $_POST['override_exp_date']['user_id'] ) );
		$course_id = sanitize_text_field( wp_unslash( $_POST['override_exp_date']['course_id'] ) );
		$exp_date  = strtotime( sanitize_text_field( wp_unslash( $_POST['override_exp_date']['date'] ) ) );

		if ( '' === $user_id ) {
			$error  = true;
			$status = 'warning';
			$msg    = 'User ID not entered.';
		} elseif ( '' === $course_id ) {
			$error  = true;
			$status = 'warning';
			$msg    = 'Course ID not entered.';
		} elseif ( 'sfwd-courses' !== get_post_type( $course_id ) ) {
			$error  = true;
			$status = 'error';
			$msg    = 'Post ID ' . $course_id . ' is not a LearnDash course.';
		} elseif ( false === $exp_date ) {
			$error  = true;
			$status = 'warning';
			$msg    = 'New expiration date not entered.';
		}

		// if there isn't access to a course, check for expiration meta.
		if ( ! get_user_meta( $user_id, 'course_' . $course_id . '_access_from' ) ) {
			$expired = get_user_meta( $user_id, 'learndash_course_expired_' . $course_id, true );

			if ( $expired ) {
				$note .= 'Note: this user\'s course access had previously expired.';

				/** @todo what time should the new access be? */
				update_user_meta( $user_id, 'course_' . $course_id . '_access_from', time() );
				delete_user_meta( $user_id, 'learndash_course_expired_' . $course_id );
			}

			if ( ! $expired ) {
				$error  = false;
				$status = 'error';
				$msg    = 'This user is not enrolled in the selected course.';
			}
		}

		// if no errors have occurred.
		if ( null === $error ) {
			// add the meta.
			update_user_meta( $user_id, BSZYK_ADV_ACC_EXT_META_PREFIX . 'override_' . $course_id, $exp_date );

			$status = 'success';
			$msg    = "Successfully updated user's course expiration date!";

			// check if user has an extension.
			$has_extension = get_user_meta( $user_id, BSZYK_ADV_ACC_EXT_META_PREFIX . 'course_' . $course_id, true );

			if ( $has_extension ) {
				$note .= 'Note: this user has purchased a course extension.';
			} else {
				$note .= 'Note: this user has not purchased a course extension.';
			}
		}

		// go back to settings page with error/success data.
		$url = add_query_arg(
			array(
				'_override_course_expiration_cb_nonce' => wp_create_nonce( 'override_course_expiration__cb' ),
				'override_status'                      => $status,
				'override_msg'                         => $msg,
				'override_note'                        => $note,
			),
			BSZYK_ADV_ACC_EXT_ADMIN_URL
		);

		wp_safe_redirect( $url );
		exit;
	}

	/**
	 * Delete a user's course override.
	 *
	 * @return void
	 */
	public function del_override_course_expiration() {
		$error  = null;
		$status = null;
		$msg    = null;
		$note   = '';

		if ( ! isset( $_POST[ BSZYK_ADV_ACC_NONCE_PREFIX . '_del_override' ] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST[ BSZYK_ADV_ACC_NONCE_PREFIX . '_del_override' ] ) ), BSZYK_ADV_ACC_NONCE_ACTION_PREFIX . '_del_override' ) ) {
			wp_die( 'Nonce verification error.' );
		}

		if ( ! isset( $_POST['del_override']['data'] ) ) {
			$error  = true;
			$status = 'error';
			$msg    = 'Error processing POST data. Variables not set.';
		}

		// variables.
		$ids       = explode( '-', sanitize_text_field( wp_unslash( $_POST['del_override']['data'] ) ) );
		$course_id = $ids[0];
		$user_id   = $ids[1];

		// if no errors have occurred.
		if ( null === $error ) {
			// delete the meta.
			delete_user_meta( $user_id, BSZYK_ADV_ACC_EXT_META_PREFIX . 'override_' . $course_id );

			$status = 'success';
			$msg    = "Successfully deleted user's course override!";

			// check if user has an extension.
			$has_extension = get_user_meta( $user_id, BSZYK_ADV_ACC_EXT_META_PREFIX . 'course_' . $course_id, true );

			if ( $has_extension ) {
				$note .= 'Note: this user has purchased a course extension.';
			} else {
				$note .= 'Note: this user has not purchased a course extension.';
			}
		}

		// go back to settings page with error/success data.
		$url = add_query_arg(
			array(
				'_del_override_course_expiration_cb_nonce' => wp_create_nonce( 'del_override_course_expiration__cb' ),
				'del_override_status'                      => $status,
				'del_override_msg'                         => $msg,
				'del_override_note'                        => $note,
			),
			BSZYK_ADV_ACC_EXT_ADMIN_URL
		);

		wp_safe_redirect( $url );
		exit;
	}


	/**
	 * Add extension metadata after product purchase.
	 *
	 * @param mixed $order_id order ID.
	 * @return void
	 */
	public function add_extension_meta_after_purchase( $order_id ) {
		$order = wc_get_order( $order_id );

		if ( ! $order ) {
			return;
		}

		$user_id    = $order->get_user_id();
		$order_date = strtotime( $order->get_date_created() );

		$products = $order->get_items();
		foreach ( $products as $product ) {
			// get ext meta from the product.
			$ext_meta = get_post_meta( $product['product_id'], BSZYK_ADV_ACC_EXT_META, true );

			if ( ! $ext_meta ) {
				return;
			}

			if ( ! isset( $ext_meta['course_id'] ) || ! isset( $ext_meta['length__num'] ) ) {
				return;
			}

			$course_id       = $ext_meta['course_id'];
			$ext_length__num = $ext_meta['length__num'];

			update_user_meta(
				$user_id,
				BSZYK_ADV_ACC_EXT_META_PREFIX . 'course_' . $course_id,
				array(
					'order_date'  => $order_date,
					'length__num' => $ext_length__num,
				)
			);
		}

		$order->update_status( 'completed' );
	}

	/**
	 * Update expiration date.
	 *
	 * @param int $course_access_upto Expiration timestamp.
	 * @param int $course_id Course ID.
	 * @param int $user_id User ID.
	 *
	 * @return int Unix timestamp.
	 */
	public function filter_user_course_expiration( $course_access_upto, $course_id, $user_id ) {
		if ( 'sfwd-courses' !== get_post_type( $course_id ) ) {
			return $course_access_upto;
		}

		// If course expiration override usermeta exists, use that.
		$manual_override_date = get_user_meta( $user_id, BSZYK_ADV_ACC_EXT_META_PREFIX . 'override_' . $course_id, true );
		if ( $manual_override_date ) {
			// if the course is marked as expired, the expiration date can't be updated - so get rid of it.
			$expired = get_user_meta( $user_id, 'learndash_course_expired_' . $course_id, true );
			if ( $expired && '' !== $expired && ! empty( $expired ) ) {
				// if there is a manual override, use that date to calculate 'new' `_access_from` timestamp.
				// this prevents learndash from marking course as expired until we need it to.
				$new_course_access_from = time() - $manual_override_date;

				update_user_meta( $user_id, 'course_' . $course_id . '_access_from', $new_course_access_from );
				delete_user_meta( $user_id, 'learndash_course_expired_' . $course_id );
			}

			$course_access_upto = $manual_override_date;
			return $course_access_upto;
		}

		// Update course access if set to update once an event is triggered.
		$exp_after_event_meta = get_post_meta( $course_id, BSZYK_ADV_ACC_EXP_AFTER_EVENT_META, true );
		// is the meta set.
		if ( false !== $exp_after_event_meta && isset( $exp_after_event_meta['enabled'] ) ) {
			// is the setting on or off.
			if ( true === $exp_after_event_meta['enabled'] ) {
				// is the data set.
				if ( isset( $exp_after_event_meta['length_til_exp'] ) && isset( $exp_after_event_meta['length_til_exp__type'] ) && isset( $exp_after_event_meta['event_id'] ) ) {
					$length_til_expire = (int) $exp_after_event_meta['length_til_exp'];
					$length_type       = (int) $exp_after_event_meta['length_til_exp__type'];
					$event_id          = (int) $exp_after_event_meta['event_id'];
				}

				// an id greater than zero references a lesson.
				if ( $event_id > 0 ) {
					$timestamp_of_lesson_completed = $this->get_timestamp_of_lesson_completed( $course_id, $user_id, $event_id );

					$length_type_int = 1; // days.
					if ( 1 === $length_type ) { // weeks.
						$length_type_int = 7;
					} elseif ( 2 === $length_type ) { // months.
						$length_type_int = 30;
					} elseif ( 3 === $length_type ) { // years.
						$length_type_int = 365;
					}

					// If lesson activity is found, update timestamp.
					if ( false !== $timestamp_of_lesson_completed ) {
						$course_access_upto = $timestamp_of_lesson_completed + ( ( $length_til_expire * $length_type_int ) * DAY_IN_SECONDS );
					}
				}
			}
		}

		// lastly, filter any extensions the user has purchased.
		$ext_meta = get_user_meta( $user_id, BSZYK_ADV_ACC_EXT_META_PREFIX . 'course_' . $course_id, true );
		if ( '' !== $ext_meta || ! empty( $ext_meta ) ) {
			if ( isset( $ext_meta['length__num'] ) || ! empty( $ext_meta['length__num'] ) ) {
				$ext_length__num = intval( $ext_meta['length__num'] );

				$ext_length_unix = ( $ext_length__num * 7 ) * DAY_IN_SECONDS;

				$course_access_upto = $course_access_upto + $ext_length_unix;
			}
		}

		return $course_access_upto;
	}

	/**
	 * Database queries to get the timestamp of when/if a lesson was completed.
	 *
	 * @param int $course_id Course ID.
	 * @param int $user_id User ID.
	 * @param int $lesson_id Lesson ID.
	 *
	 * @return int|bool Unix timestamp or false.
	 */
	public function get_timestamp_of_lesson_completed( $course_id, $user_id, $lesson_id ) {
		global $wpdb;

		// Get timestamp of first lesson completion, if it exists.
		$timestamp_of_lesson_completed = $wpdb->get_var( //phpcs:ignore
			$wpdb->prepare(
				'
				SELECT activity_completed
				FROM wp_learndash_user_activity
				WHERE activity_status = 1
				AND course_id = %s
				AND user_id = %s
				AND post_id = %s
			',
				$course_id,
				$user_id,
				$lesson_id
			)
		);

		// If no lesson activity is found, return false.
		if ( ! isset( $timestamp_of_lesson_completed ) ) {
			return false;
		}

		return $timestamp_of_lesson_completed;
	}
}

