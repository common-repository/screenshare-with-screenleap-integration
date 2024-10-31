<?php

namespace GPLSCore\GPLS_PLUGIN_SLI;

defined( 'ABSPATH' ) || exit();

/**
 * Settings Class.
 */
class Settings {

	/**
	 * Core Object.
	 *
	 * @var object
	 */
	public $core;

	/**
	 * Plugin Info Object.
	 *
	 * @var object
	 */
	public $plugin_info;

	/**
	 * Meeting Post Type Name.
	 *
	 * @var string
	 */
	public $post_type_name = 'gpls_sli_screenleap';

	/**
	 * Active Tab.
	 *
	 * @var string
	 */
	public $active_tab;

	/**
	 * Settings name to be saved in options table.
	 *
	 * @var array
	 */
	private $settings_save_name;

	/**
	 * Default Values for Settings Array.
	 *
	 * @var array
	 */
	private $default_settings = array(
		'account_id' => '',
		'auth_token' => '',
		'handle'     => '',
	);

	/**
	 * Settings Array Hub.
	 *
	 * @var array
	 */
	public $settings_arr = array();

	/**
	 * Settings Page Link
	 *
	 * @var string
	 */
	public $settings_page_link;

	/**
	 * Constructor
	 *
	 * @param object $core Core Object.
	 * @param object $plugin_info Plugin Info Array.
	 */
	public function __construct( $core, $plugin_info ) {
		$this->core               = $core;
		$this->plugin_info        = $plugin_info;
		$this->active_tab         = isset( $_GET['tab'] ) ? sanitize_text_field( wp_unslash( $_GET['tab'] ) ) : 'general';
		$this->settings_save_name = $this->plugin_info['name'] . '-main-settings';
		$this->settings_page_link = admin_url( 'edit.php?post_type=' . $this->post_type_name . '&page=' . $this->plugin_info['name'] );
		$this->settings_arr       = $this->get_settings();

		$this->hooks();
	}

	/**
	 * Actions and Filters Hooks.
	 *
	 * @return void
	 */
	public function hooks() {
		add_action( 'admin_enqueue_scripts', array( $this, 'settings_assets' ) );
		add_action( 'admin_menu', array( $this, 'settings_page' ) );
		add_action( $this->plugin_info['name'] . '-general-settings-submit', array( $this, 'settings_submit' ) );
		add_filter( 'plugin_action_links_' . $this->plugin_info['basename'], array( $this, 'settings_link' ), 5, 1 );
	}

	/**
	 * Settings Page Link.
	 *
	 * @param array $links Plugin Links.
	 * @return array
	 */
	public function settings_link( $links ) {
		$links[] = '<a href="' . $this->settings_page_link . '" >' . __( 'Settings' ) . '</a>';
		return $links;
	}

	/**
	 * Settings Assets.
	 *
	 * @return void
	 */
	public function settings_assets() {
		if ( ! empty( $_GET['post_type'] ) && ( sanitize_text_field( wp_unslash( $_GET['post_type'] ) ) === $this->post_type_name ) && ! empty( $_GET['tab'] ) && ( 'pro' === sanitize_text_field( wp_unslash( $_GET['tab'] ) ) ) ) {
			wp_enqueue_style( $this->plugin_info['name'] . '-settings-bootstrap', $this->core->core_assets_lib( 'bootstrap', 'css' ), array(), 'all' );
			if ( ! wp_script_is( 'jquery' ) ) {
				wp_enqueue_script( 'jquery' );
			}
			wp_enqueue_script( $this->plugin_info['name'] . '-core-admin-bootstrap-js', $this->core->core_assets_lib( 'bootstrap.bundle', 'js' ), array( 'jquery' ), $this->plugin_info['version'], true );
		}
	}

	/**
	 * Get settings array from the DB.
	 *
	 * @return array
	 */
	public function get_settings() {
		$settings = get_option( $this->settings_save_name, $this->default_settings );
		return array_merge( $this->default_settings, $settings );
	}

	/**
	 * Save Settings Array in DB.
	 *
	 * @return void
	 */
	public function _save_settings() {
		update_option( $this->settings_save_name, $this->settings_arr );
	}

	/**
	 * Register Export admin Page
	 *
	 * @return void
	 */
	public function settings_page() {
		add_submenu_page( 'edit.php?post_type=' . $this->post_type_name, 'ScreenLeap Integration Settings', 'Settings', 'manage_options', $this->plugin_info['name'], array( $this, 'settings_page_content' ) );
	}

	/**
	 * Settings Page Content.
	 *
	 * @return void
	 */
	public function settings_page_content() {
		do_action( $this->plugin_info['name'] . '-general-settings-submit' );

		set_query_var( 'core_obj', $this->core );
		set_query_var( 'plugin_info', $this->plugin_info );
		set_query_var( 'settings', $this->settings_arr );
		set_query_var( 'settings_obj', $this );

		load_template( $this->plugin_info['path'] . '/views/settings-page.php' );
	}

	/**
	 * Settings Submit.
	 *
	 * @return void
	 */
	public function settings_submit() {
		if ( ! empty( $_POST[ $this->plugin_info['name'] . '-export-submit' ] ) && ! empty( $_POST[ $this->plugin_info['name'] . '-settings-nonce' ] ) && check_admin_referer( $this->plugin_info['name'] . '-settings-nonce', $this->plugin_info['name'] . '-settings-nonce' ) ) {
			$settings_array = $this->default_settings;
			if ( ! empty( $_POST[ $this->plugin_info['name'] . '-account-id' ] ) ) {
				$settings_array['account_id'] = sanitize_text_field( wp_unslash( $_POST[ $this->plugin_info['name'] . '-account-id' ] ) );
			}
			if ( ! empty( $_POST[ $this->plugin_info['name'] . '-auth-token' ] ) ) {
				$settings_array['auth_token'] = sanitize_text_field( wp_unslash( $_POST[ $this->plugin_info['name'] . '-auth-token' ] ) );
			}
			if ( ! empty( $_POST[ $this->plugin_info['name'] . '-handle' ] ) ) {
				$settings_array['handle'] = sanitize_text_field( wp_unslash( $_POST[ $this->plugin_info['name'] . '-handle' ] ) );
			}

			$this->settings_arr = array_merge( $this->default_settings, $settings_array );
			$this->_save_settings();
		}
	}

	/**
	 * Get Account Handle.
	 *
	 * @return string
	 */
	public function get_handle() {
		$settings = $this->get_settings();
		return $settings['handle'];
	}

	/**
	 * Check if developer Details exist.
	 *
	 * @return boolean
	 */
	public function is_developer_cred_ready() {
		$settings = $this->get_settings();
		return ( ! empty( $settings['account_id'] ) && ! empty( $settings['auth_token'] ) );
	}

	/**
	 * Check if account handle exists.
	 *
	 * @return boolean
	 */
	public function is_handle_ready() {
		$settings = $this->get_settings();
		return ( ! empty( $settings['handle'] ) );
	}

}
