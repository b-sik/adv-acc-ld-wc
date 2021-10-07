<?php //phpcs:ignore
/**
 * Plugin settings
 *
 * @package BSZYK_ADV_ACC
 */

namespace BSZYK_ADV_ACC;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Settings
 */
class Settings {
	/**
	 * Init
	 *
	 * @return void
	 */
	public function init() {
		// add LD admin submenu.
		add_action( 'admin_menu', array( $this, 'register_admin_menu' ) );

		// register settings.
		add_action( 'admin_init', array( $this, 'init_settings' ) );

		// add settings to LD course post edit.
		add_filter( 'learndash_settings_fields', array( $this, 'filter_ld_course_settings' ), 999, 2 );

		// course extension product metabox.
		add_action( 'add_meta_boxes', array( $this, 'add_extension_metabox' ), 10, 0 );
	}


	/**
	 * Add LD admin submenu.
	 *
	 * @return void
	 */
	public function register_admin_menu() {
		add_submenu_page( 'learndash-lms', BSZYK_ADV_ACC_NAME, BSZYK_ADV_ACC_NAME_SHORT, 'manage_options', BSZYK_ADV_ACC_SETTINGS_PAGE_SLUG, array( $this, 'settings_page_render' ) );
	}

	/**
	 * Init settings.
	 *
	 * @return void
	 */
	public function init_settings() {
		$this->expiration_override_settings();
		$this->del_expiration_override_settings();
	}

	/**
	 * Filter LearnDash settings.
	 *
	 * @param mixed  $option_fields Option fields.
	 * @param string $metabox_key Metabox key.
	 *
	 * @return mixed
	 */
	public function filter_ld_course_settings( $option_fields, $metabox_key ) {
		if ( 'learndash-course-access-settings' !== $metabox_key ) {
			return $option_fields;
		}

		global $post;

		if ( ! isset( $post ) ) {
			return $option_fields;
		}

		// nonce.
		// $option_fields[ BSZYK_ADV_ACC_NONCE_PREFIX . '_course_access_settings' ] = array(
		// 	'value' => wp_create_nonce( BSZYK_ADV_ACC_NONCE_ACTION_PREFIX . '_course_access_settings' ),
		// );

		// get the meta.
		$exp_after_event_meta = get_post_meta( $post->ID, BSZYK_ADV_ACC_EXP_AFTER_EVENT_META, true );

		// update default label.
		$option_fields['expire_access_days']['label'] = __( 'Access Period After Enrollment', 'bszyk-adv-acc' );

		// option field value.
		$exp_after_event_length_value = '';
		if ( isset( $exp_after_event_meta['length_til_exp'] ) ) {
			$exp_after_event_length_value = $exp_after_event_meta['length_til_exp'];
		}

		$option_fields['exp_after_event_length'] = array(
			'name'           => 'exp_after_event_length',
			'label'          => 'Access Period After Event',
			'type'           => 'number',
			'value'          => $exp_after_event_length_value,
			'default'        => 1,
			'class'          => 'small-text',
			'input_label'    => '',
			'input_error'    => 'Value should be 0 or greater.',
			'parent_setting' => 'expire_access',
			'attrs'          => array(
				'step'        => 'any',
				'min'         => 0,
				'can_decimal' => 0,
				'can_empty'   => 0,
			),
			'help_text'      => 'Set the number of days, weeks, or months a user will have access to the course after a selected event occurs.',
			'rest'           => array(
				'show_in_rest' => 1,
				'rest_args'    => array(
					'schema' => array(
						'field_key' => 'exp_after_event_length',
						'type'      => 'number',
						'default'   => 0,
					),
				),
			),
		);

		$option_fields['exp_after_event_length__type'] = array(
			'name'           => 'exp_after_event_length__type',
			'label'          => '',
			'type'           => 'select',
			'value'          => 'days',
			'options'        => array(
				array(
					'label' => 'days',
				),
				array(
					'label' => 'weeks',
				),
				array(
					'label' => 'months',
				),
				array(
					'label' => 'years',
				),
			),
			'default'        => 'days',
			'class'          => 'small-text',
			'input_label'    => '',
			'parent_setting' => 'expire_access',
			'attrs'          => array(),
			'rest'           => array(
				'show_in_rest' => 1,
				'rest_args'    => array(
					'schema' => array(
						'field_key' => 'exp_after_event_length__type',
						'type'      => 'number',
						'default'   => 0,
					),
				),
			),
		);

		// prepare dropdown/search options.
		$lesson_ids              = $this->get_lessons_related_to_course( $post->ID );
		$exp_after_event_options = array();

		// default options.
		$exp_after_event_options[0] = array(
			'label' => 'Do not update - turn feature off',
		);

		// lessons.
		if ( ! empty( $lesson_ids ) ) {
			foreach ( $lesson_ids as $id ) {
				$exp_after_event_options[ $id ] = array(
					'label'       => 'After completion of: ' . sanitize_text_field( get_the_title( $id ) ),
					'description' => sanitize_text_field( 'Post ID ' . $id ),
				);
			}
		}

		// option field value.
		$exp_after_event_value = '';
		if ( isset( $exp_after_event_meta['length_til_exp'] ) ) {
			$exp_after_event_value = $exp_after_event_meta['event_id'];
		}

		$option_fields['exp_after_event_id'] = array(
			'name'           => 'exp_after_event_id',
			'label'          => 'Event To Trigger Access Update',
			'type'           => 'select',
			'value'          => $exp_after_event_value,
			'options'        => $exp_after_event_options,
			'placeholder'    => array(
				'-1' => 'Search or select a lesson…',
			),
			'default'        => '',
			'class'          => 'small-text',
			'input_label'    => '',
			'parent_setting' => 'expire_access',
			'help_text'      => 'After this lesson is completed by the user, their course access will be updated.',
			'input_error'    => 'Value should be greater than zero.',
			'attrs'          => array(),
			'rest'           => array(
				'show_in_rest' => 1,
				'rest_args'    => array(
					'schema' => array(
						'field_key' => 'exp_after_event_id',
						'type'      => 'number',
						'default'   => 0,
					),
				),
			),
		);

		return $option_fields;
	}

	/**
	 * Expiration override settings
	 *
	 * @return void
	 */
	public function expiration_override_settings() {
		register_setting( BSZYK_ADV_ACC_SETTINGS_PREFIX . 'exp_override_settings', 'override_exp_date' );

		add_settings_section(
			BSZYK_ADV_ACC_SETTINGS_PREFIX . 'exp_override_section',
			__( 'Update a User\'s Course Expiration', 'bszyk-adv-acc' ),
			function() {
				echo esc_html__( 'Directly update the expiration date:', 'bszyk-adv-acc' );
			},
			BSZYK_ADV_ACC_SETTINGS_PREFIX . 'exp_override_settings'
		);

		add_settings_field(
			'user_id',
			__( 'User to update:', 'bszyk-adv-acc' ),
			function() {
				$args  = array(
					'fields' => array( 'display_name', 'ID' ),
				);
				$users = get_users( $args );

				?>
				<select name='override_exp_date[user_id]'>
				<option value="" selected disabled>Select user</option>
				<?php
				foreach ( $users as $user ) {
					?>
					<option value="<?php echo esc_attr( $user->ID ); ?>"><?php echo esc_html( $user->display_name ); ?></option>
					<?php
				}
				?>
				</select>
				<?php
			},
			BSZYK_ADV_ACC_SETTINGS_PREFIX . 'exp_override_settings',
			BSZYK_ADV_ACC_SETTINGS_PREFIX . 'exp_override_section'
		);

		add_settings_field(
			'course_id',
			__( 'Course to update:', 'bszyk-adv-acc' ),
			function() {
				$args    = array(
					'numberposts' => -1,
					'post_type'   => 'sfwd-courses',
					'fields'      => 'ids',
				);
				$courses = get_posts( $args );

				?>
				<select name='override_exp_date[course_id]'>
					<option value="" selected disabled>Select course</option>
				<?php
				foreach ( $courses as $id ) {
					?>
					<option value="<?php echo esc_attr( $id ); ?>"><?php echo esc_html( get_the_title( $id ) ); ?></option>
					<?php
				}
				?>
				</select>
				<?php
			},
			BSZYK_ADV_ACC_SETTINGS_PREFIX . 'exp_override_settings',
			BSZYK_ADV_ACC_SETTINGS_PREFIX . 'exp_override_section'
		);

		add_settings_field(
			'date',
			__( 'New expiration date:', 'bszyk-adv-acc' ),
			function() {
				?>
				<input type='date' name='override_exp_date[date]' value=''>
				<?php
			},
			BSZYK_ADV_ACC_SETTINGS_PREFIX . 'exp_override_settings',
			BSZYK_ADV_ACC_SETTINGS_PREFIX . 'exp_override_section'
		);
	}

	/**
	 * Delete expiration override settings section.
	 *
	 * @return void
	 */
	public function del_expiration_override_settings() {
		register_setting( BSZYK_ADV_ACC_SETTINGS_PREFIX . 'del_exp_override_settings', 'del_override' );

		add_settings_section(
			BSZYK_ADV_ACC_SETTINGS_PREFIX . 'del_exp_override_section',
			__( 'Delete a User\'s Course Expiration Override', 'bszyk-adv-acc' ),
			function() {
				echo esc_html__( 'Delete a user\'s existing override:', 'bszyk-adv-acc' );
			},
			BSZYK_ADV_ACC_SETTINGS_PREFIX . 'del_exp_override_settings'
		);

		add_settings_field(
			'data',
			__( 'Course override to delete:', 'bszyk-adv-acc' ),
			function() {
				// get the users with course extensions.
				$post_args = array(
					'fields'         => 'ids',
					'posts_per_page' => -1,
					'post_type'      => 'sfwd-courses',
				);

				$course_ids = get_posts( $post_args );

				$existing_overrides = array();
				foreach ( $course_ids as $id ) {
					$user_args = array(
						'fields'   => array( 'display_name', 'ID' ),
						'meta_key' => BSZYK_ADV_ACC_EXT_META_PREFIX . 'override_' . $id, // phpcs:ignore
					);

					$users = get_users( $user_args );

					$existing_overrides[ $id ] = array(
						'users'     => $users,
						'course_id' => $id,
					);
				}

				?>

				<select name='del_override[data]'>
				<option value="" selected disabled>Select user and course</option>
				<?php
				foreach ( $existing_overrides as $override ) {
					$course_id = $override['course_id'];
					foreach ( $override['users'] as $user ) {
						?>
						<option value="<?php echo esc_attr( $course_id . '-' . $user->ID ); ?>"><?php echo esc_html( $user->display_name . ': ' . get_the_title( $course_id ) ); ?></option>
						<?php
					}
				}
				?>
				</select>
				<?php
			},
			BSZYK_ADV_ACC_SETTINGS_PREFIX . 'del_exp_override_settings',
			BSZYK_ADV_ACC_SETTINGS_PREFIX . 'del_exp_override_section'
		);
	}


	/**
	 * Add extension metabox on product post.
	 */
	public function add_extension_metabox() {
		add_meta_box(
			'ea_ext_meta',
			'Add Course Extenstion',
			function() {
				global $post;

				$this->ext_metabox_render( $post );
			},
			'product',
			'normal',
			'default',
			null
		);
	}

	/**
	 * Render metabox for choosing course extension.
	 *
	 * @param WP_POST $post Post object.
	 * @return string HTML.
	 */
	public function ext_metabox_render( $post ) {
		$args    = array(
			'numberposts' => -1,
			'post_type'   => 'sfwd-courses',
			'fields'      => 'ids',
		);
		$courses = get_posts( $args );

		$ext_meta = get_post_meta( $post->ID, BSZYK_ADV_ACC_EXT_META, true );

		$course_id   = null;
		$length__num = null;
		if ( isset( $ext_meta['course_id'] ) && ! empty( $ext_meta['course_id'] ) ) {
			$course_id = $ext_meta['course_id'];
		}
		if ( isset( $ext_meta['length__num'] ) && ! empty( $ext_meta['length__num'] ) ) {
			$length__num = $ext_meta['length__num'];
		}

		ob_start();
		?>
		<div>
			<?php wp_nonce_field( BSZYK_ADV_ACC_NONCE_ACTION_PREFIX . '-ext-meta', BSZYK_ADV_ACC_NONCE_PREFIX . '-ext-meta' ); ?>
			<div style='display:flex;margin:5px 0px;width:100%;align-items:center;'>
				<label style='width:50%'>Select course to add extension to:</label>
				<select style='width:50%' name='ea_ext_meta[course_id]'>
					<option value="" <?php echo $course_id ? '' : 'selected'; ?> disabled>Select course</option>
				<?php
				foreach ( $courses as $id ) {
					if ( intval( $id ) === intval( $course_id ) ) {
						?>
						<option selected value="<?php echo esc_attr( $id ); ?>"><?php echo esc_html( get_the_title( $id ) ); ?></option>
						<?php
					} else {
						?>
						<option value="<?php echo esc_attr( $id ); ?>"><?php echo esc_html( get_the_title( $id ) ); ?></option>
						<?php
					}
				}
				?>
				</select>
			</div>

			<div style='display:flex;width:100%;align-items:center;'>
				<label style='width:50%;'>Length of extension (weeks)</label>
				<input style='width:50%;' type='number' min='0' name='ea_ext_meta[length__num]' value='<?php echo $length__num ? intval( $length__num ) : ''; ?>'>
			</div>
		</div>
		<?php
		return ob_flush();
	}


	/**
	 * Get all lesson posts related to a course.
	 *
	 * @param int  $course_id Course ID.
	 * @param bool $ids By default return an array of ids rather than the post objects.
	 *
	 * @return mixed
	 */
	public static function get_lessons_related_to_course( $course_id, $ids = true ) {
		global $wpdb;

		$lessons_related_to_course = $wpdb->get_results( // phpcs:ignore
			$wpdb->prepare(
				'
				SELECT post_id 
				FROM wp_postmeta
				WHERE meta_value = %s
			',
				$course_id,
			)
		);

		// return array of lesson ids.
		if ( true === $ids ) {
			$lessons_related_to_course = array_column( $lessons_related_to_course, 'post_id' );
			return $lessons_related_to_course;
		}

		return $lessons_related_to_course;
	}

	/**
	 * Render form on settings page.
	 *
	 * @return void
	 */
	public function settings_page_render() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// custom error notices for exp date override since it doesn't use the options API.
		if ( isset( $_GET['override_status'] ) && isset( $_GET['override_msg'] ) && isset( $_GET['_override_course_expiration_cb_nonce'] ) && check_admin_referer( 'override_course_expiration__cb', '_override_course_expiration_cb_nonce' ) ) {
			$type = sanitize_text_field( wp_unslash( $_GET['override_status'] ) );
			$msg  = sanitize_text_field( wp_unslash( $_GET['override_msg'] ) );

			if ( isset( $_GET['override_note'] ) && null !== $_GET['override_note'] ) {
				$note = sanitize_text_field( wp_unslash( $_GET['override_note'] ) );
				$this->custom_banner_notice( $type, $msg, $note );
			} else {
				$this->custom_banner_notice( $type, $msg );
			}
		}
		?>

		<form action='admin-post.php' method='post'>
			<?php
			settings_fields( BSZYK_ADV_ACC_SETTINGS_PREFIX . 'exp_override_settings' );
			do_settings_sections( BSZYK_ADV_ACC_SETTINGS_PREFIX . 'exp_override_settings' );
			?>
			<?php wp_nonce_field( BSZYK_ADV_ACC_NONCE_ACTION_PREFIX . '_exp_override', BSZYK_ADV_ACC_NONCE_PREFIX . '_exp_override' ); ?>
			<input type="hidden" name="action" value="exp_override_settings__cb">
			<?php
			submit_button( 'Update Expiration' );
			?>
		</form>

		<?php
		// custom error notices for exp date override since it doesn't use the options API.
		if ( isset( $_GET['del_override_status'] ) && isset( $_GET['del_override_msg'] ) && isset( $_GET['_del_override_course_expiration_cb_nonce'] ) && check_admin_referer( 'del_override_course_expiration__cb', '_del_override_course_expiration_cb_nonce' ) ) {
			$type = sanitize_text_field( wp_unslash( $_GET['del_override_status'] ) );
			$msg  = sanitize_text_field( wp_unslash( $_GET['del_override_msg'] ) );

			if ( isset( $_GET['del_override_note'] ) && null !== $_GET['del_override_note'] ) {
				$note = sanitize_text_field( wp_unslash( $_GET['del_override_note'] ) );
				$this->custom_banner_notice( $type, $msg, $note );
			} else {
				$this->custom_banner_notice( $type, $msg );
			}
		}
		?>

		<form action='admin-post.php' method='post'>
			<?php
			settings_fields( BSZYK_ADV_ACC_SETTINGS_PREFIX . 'del_exp_override_settings' );
			do_settings_sections( BSZYK_ADV_ACC_SETTINGS_PREFIX . 'del_exp_override_settings' );
			?>
			<?php wp_nonce_field( BSZYK_ADV_ACC_NONCE_ACTION_PREFIX . '_del_override', BSZYK_ADV_ACC_NONCE_PREFIX . '_del_override' ); ?>
			<input type="hidden" name="action" value="del_exp_override_settings__cb">
			<?php
			submit_button( 'Delete Override' );
			?>
		</form>
		<?php
	}

	/**
	 * Custom error notices.
	 *
	 * @param string $type Type of error.
	 * @param string $msg Message.
	 * @param string $note More info.
	 * @param string $id ID slug. Optional.
	 *
	 * @return string HTML output.
	 */
	public function custom_banner_notice( $type, $msg, $note = '', $id = 'ea-custom' ) {
		ob_start();
		?>
		<div id="setting-error- <?php echo esc_attr( $id ); ?>" class="notice notice-<?php echo esc_attr( $type ); ?> settings-error is-dismissible"> 
			<p>
				<strong><?php echo esc_html( $msg ); ?> </strong>
				<?php
				if ( '' !== $note ) {
					$notes = explode( '.', $note );
					foreach ( $notes as $note ) {
						if ( '' !== $note ) {
							echo '<br>';
							echo esc_html( $note ) . '.';
						}
					}
				}
				?>
			</p>
		</div>
		<?php
		return ob_flush();
	}
}
