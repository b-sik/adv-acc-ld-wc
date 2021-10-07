<?php //phpcs:ignore
/**
 * Customize, set, sell, and limit course access for Learndash.
 *
 * @package BSZYK_ADV_ACC
 *
 * Plugin Name: Advanced Access for LearnDash LMS
 * Plugin URI: https://bszyk.dev
 * Description: Customize, set, and limit course access.
 * Author: Brian Siklinski
 * Version: 0.3.0
 * Author URI: https://bszyk.dev
 * Textdomain: bszyk-adv-acc
 */

namespace BSZYK_ADV_ACC;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/vendor/autoload.php';

use BSZYK_ADV_ACC\CourseAccess;
use BSZYK_ADV_ACC\Settings;

define( 'BSZYK_ADV_ACC_VERSION', '0.3.0' );

define( 'BSZYK_ADV_ACC_NAME', 'Advanced Access for LearnDash LMS' );
define( 'BSZYK_ADV_ACC_NAME_SHORT', 'Advanced Access' );

// settings.
define( 'BSZYK_ADV_ACC_SETTINGS_PAGE_SLUG', 'ea_adv_acc_ld_settings' );
define( 'BSZYK_ADV_ACC_SETTINGS_PREFIX', 'ea_adv_acc_ld_settings__' );

// nonce.
define( 'BSZYK_ADV_ACC_NONCE_ACTION_PREFIX', 'ea_adv_acc_ld_nonce' );
define( 'BSZYK_ADV_ACC_NONCE_PREFIX', '_ea_adv_acc_ld_nonce' );

// exp after event trigger.
define( 'BSZYK_ADV_ACC_EXP_AFTER_EVENT_META', '_BSZYK_ADV_ACC_exp_after_event' );

// extension.
define( 'BSZYK_ADV_ACC_EXT_META', '_BSZYK_ADV_ACC_ext' );
define( 'BSZYK_ADV_ACC_EXT_META_PREFIX', '_BSZYK_ADV_ACC_ext__' );
define( 'BSZYK_ADV_ACC_EXT_ADMIN_URL', admin_url( 'admin.php?page=' . BSZYK_ADV_ACC_SETTINGS_PAGE_SLUG ) );

/**
 * Plugin.
 */
class BSZYK_ADV_ACC {

	/**
	 * Init.
	 */
	public function init() {
		$settings      = new Settings();
		$course_access = new CourseAccess();

		if ( in_array( 'sfwd-lms/sfwd_lms.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ), true ) ) {
			add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_stylesheet' ) );

			$settings->init();
			$course_access->init_ld();
		}

		if ( in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ), true ) ) {
			$course_access->init_wc();
		}
	}

	/**
	 * Enqueue stylesheet.
	 */
	public function enqueue_stylesheet() {
		wp_enqueue_style(
			'bszyk-adv-acc-stylesheet',
			plugins_url( 'style.css', __FILE__ ),
			array(),
			BSZYK_ADV_ACC_VERSION,
		);
	}
}

$adv_access_plugin = new BSZYK_ADV_ACC();
$adv_access_plugin->init();
