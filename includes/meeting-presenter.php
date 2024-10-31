<?php

namespace GPLSCore\GPLS_PLUGIN_SLI;

defined( 'ABSPATH' ) || exit();

/**
 * Presenter Side Integration Class.
 */
class Meeting_Presenter {

	/**
	 * Settings Object.
	 *
	 * @var object
	 */
	public $settings;

	/**
	 * Meeting Integration Object
	 *
	 * @var object
	 */
	private $meeting;

	/**
	 * Error Messages.
	 *
	 * @param Settings $settings
	 */
	private $error_msgs = array();

	/**
	 * Class constructor.
	 *
	 * @param Settings            $settings Settings Object.
	 * @param Meeting_Integration $meeting_integration Meeting Integration Object.
	 */
	public function __construct( Settings $settings, Meeting_Integration $meeting_integration ) {
		$this->settings = $settings;
		$this->meeting  = $meeting_integration;
		$this->hooks();
	}

	/**
	 * Actions and Filters Hooks.
	 *
	 * @return void
	 */
	public function hooks() {
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ), 100 );
		add_action( 'wp_ajax_' . $this->settings->plugin_info['name'] . '-send_screenshare_request', array( $this, 'ajax_send_screenshare_request' ) );
		add_action( 'wp_ajax_' . $this->settings->plugin_info['name'] . '-get_screenshare_info', array( $this, 'ajax_get_screenshare_info' ) );
		add_action( 'wp_ajax_' . $this->settings->plugin_info['name'] . '-get_screenshare', array( $this, 'ajax_get_last_screenshare' ) );
		add_action( 'wp_ajax_' . $this->settings->plugin_info['name'] . '-get_recent_screenshares', array( $this, 'ajax_get_recent_screenshares' ) );
		add_action( 'wp_ajax_' . $this->settings->plugin_info['name'] . '-update_meeting_status', array( $this, 'ajax_update_meeting_status' ) );
	}

	/**
	 * Integration Assets Register and Enqueue.
	 *
	 * @return void
	 */
	public function enqueue_assets() {
		global $pagenow, $post;

		$screen = get_current_screen();
		if ( ( 'post-new.php' === $pagenow || 'post.php' === $pagenow ) && ( ( $screen instanceof \WP_Screen ) && $this->meeting->post_type_name === $screen->post_type ) ) {
			wp_enqueue_style( $this->settings->plugin_info['name'] . '-screenleap-presenter-bootstrap-css', $this->settings->plugin_info['url'] . 'core/assets/libs/bootstrap.min.css', array(), $this->settings->plugin_info['version'], 'all' );
			wp_enqueue_style( $this->settings->plugin_info['name'] . '-screenleap-presenter-styles', $this->settings->plugin_info['url'] . 'assets/dist/css/presenter/presenter-styles.min.css', array(), $this->settings->plugin_info['version'], 'all' );
			wp_enqueue_style( 'wp-jquery-ui-dialog' );
			if ( wp_script_is( 'jquery' ) ) {
				wp_enqueue_script( 'jquery' );
			}

			wp_enqueue_script( 'jquery-ui-dialog' );
			wp_enqueue_script( $this->settings->plugin_info['name'] . '-screenleap-presenter-bootstrap-js', $this->settings->plugin_info['url'] . 'core/assets/libs/bootstrap.bundle.min.js', array( 'jquery' ), $this->settings->plugin_info['version'], true );
			wp_enqueue_script( $this->settings->plugin_info['name'] . '-screenleap', 'https://api.screenleap.com/js/screenleap.js', array(), $this->settings->plugin_info['version'], true );
			wp_enqueue_script( $this->settings->plugin_info['name'] . '-sweetalert', $this->settings->plugin_info['url'] . 'core/assets/libs/sweetalert2.min.js', array(), $this->settings->plugin_info['version'], true );
			wp_enqueue_script( $this->settings->plugin_info['name'] . '-screenleap-js', $this->settings->plugin_info['url'] . 'assets/dist/js/screenleap/presenter/presenter-screenleap.min.js', array( 'jquery', $this->settings->plugin_info['name'] . '-screenleap', 'wp-i18n' ), $this->settings->plugin_info['version'], true );
			wp_localize_script(
				$this->settings->plugin_info['name'] . '-screenleap-js',
				str_replace( '-', '_', $this->settings->plugin_info['name'] . '-screenleap-localize-obj' ),
				array(
					'post_id'                    => ( ( $post && ! is_wp_error( $post ) ) ? $post->ID : 0 ),
					'ajax_url'                   => admin_url( 'admin-ajax.php' ),
					'screenleap_presenter_nonce' => wp_create_nonce( $this->settings->plugin_info['name'] . '_screenleap_presenter_nonce' ),
					'prefix'                     => $this->settings->plugin_info['name'],
					'meeting_status_mapping'     => $this->meeting->meeting_status_mapping,
					'imgUrl'                     => $this->settings->plugin_info['url'] . 'assets/dist/images/',
					'search_product_nonce'       => wp_create_nonce( 'search-products' ),
				)
			);
		}
	}

	/**
	 * Ajax to send Screenshare Request.
	 *
	 * @return void
	 */
	public function ajax_send_screenshare_request() {
		check_ajax_referer( $this->settings->plugin_info['name'] . '_screenleap_presenter_nonce', 'nonce' );
		if ( ! empty( $_POST['meeting_id'] ) && is_numeric( $_POST['meeting_id'] ) ) {
			$meeting_id = absint( sanitize_text_field( wp_unslash( $_POST['meeting_id'] ) ) );
			// 1) Check if the ID is right.
			if ( $this->meeting->check_is_meeting( $meeting_id ) ) {
				// Initiate a new screen share request.
				$result = $this->start_screen_share( $meeting_id );
				if ( ! is_wp_error( $result ) ) {
					$meeting_post = $this->meeting->update_meeting_details( $result, $meeting_id );
					if ( $meeting_post ) {
						wp_send_json_success(
							array(
								'result' => $result,
							),
							200
						);
					} else {
						wp_send_json_error(
							__( 'Failed to start the meeting, Try to refresh the page!', 'gpls-sli-wp-screenleap-integration' ),
							400
						);
					}
				} else {
					$error = $result->get_error_message( $this->settings->plugin_info['name'] . '-api-response-error' );
					if ( empty( $error ) ) {
						$error = __( 'Failed to start the meeting, Try to refresh the page!', 'gpls-sli-wp-screenleap-integration' );
					}
					wp_send_json_error( $error, 400 );
				}
			} else {
				wp_send_json_error(
					__( 'Failed to start the meeting, Try to refresh the page!', 'gpls-sli-wp-screenleap-integration' ),
					400
				);
			}
		} else {
			wp_send_json_error( null, 400 );
		}
	}

	/**
	 * Ajax to Get screenshare Info.
	 *
	 * @return void
	 */
	public function ajax_get_screenshare_info() {
		check_ajax_referer( $this->settings->plugin_info['name'] . '_screenleap_presenter_nonce', 'nonce' );
		if ( ! empty( $_POST['screenshare_code'] ) && is_numeric( $_POST['screenshare_code'] ) ) {
			$screenshare_code = sanitize_text_field( wp_unslash( $_POST['screenshare_code'] ) );
			$screenshare_info = $this->meeting->get_meeting_info( $screenshare_code );
			if ( ! is_wp_error( $screenshare_info ) ) {
				wp_send_json_success(
					array(
						'result' => $screenshare_info,
					),
					200
				);
			} else {
				wp_send_json_error(
					__( 'Failed to start the meeting, Try to refresh the page!', 'gpls-sli-wp-screenleap-integration' ),
					400
				);
			}
		} else {
			wp_send_json_error( null, 400 );
		}
	}

	/**
	 * Ajax Get Last Screenshare By ExternalId Field.
	 *
	 * @return void
	 */
	public function ajax_get_last_screenshare() {
		check_ajax_referer( $this->settings->plugin_info['name'] . '_screenleap_presenter_nonce', 'nonce' );
		$last_screenshare = array();
		if ( ! empty( $_POST['meeting_id'] ) ) {
			$meeting_id       = absint( sanitize_text_field( wp_unslash( $_POST['meeting_id'] ) ) );
			$last_screenshare = $this->get_screenshare( $meeting_id );

			wp_send_json_success(
				array(
					'result' => $last_screenshare,
				),
				200
			);
		} else {
			wp_send_json_error( 400 );
		}
	}

	/**
	 * Ajax Update Meeting Status.
	 *
	 * @return void
	 */
	public function ajax_update_meeting_status() {
		check_ajax_referer( $this->settings->plugin_info['name'] . '_screenleap_presenter_nonce', 'nonce' );
		if ( ! empty( $_POST['status'] ) && ! empty( $_POST['meeting_id'] ) ) {
			$meeting_id = absint( sanitize_text_field( wp_unslash( $_POST['meeting_id'] ) ) );
			$status     = sanitize_text_field( wp_unslash( $_POST['status'] ) );
			$this->meeting->update_meeting_status( $meeting_id, $status );
			wp_send_json_success(
				array(
					'result' => 'success',
				),
				200
			);
		} else {
			wp_send_json_error( null, 400 );
		}
	}

	/**
	 * Get ScreenShare By ExternalId.
	 *
	 * @param int $meeting_id Meeting CPT ID.
	 * @return false|array
	 */
	public function get_screenshare( $meeting_id ) {
		$external_id = $this->meeting->get_meeting_detail( $meeting_id, 'externalId' );
		if ( $external_id ) {
			return $this->meeting->api->get_screen_shares(
				array(
					'externalId' => $external_id,
				)
			);
		} else {
			return false;
		}
	}

	/**
	 * Send Start Screen Share Request.
	 *
	 * @param int $meeting_id   Meeting CPT ID.
	 * @return array|\WP_Error
	 */
	public function start_screen_share( $meeting_id ) {
		$external_id          = wp_generate_uuid4();
		$params               = $this->meeting->get_meeting_conf( $meeting_id );
		$params['externalId'] = $external_id;
		if ( empty( $params['title'] ) ) {
			unset( $params['title'] );
		}
		$result = $this->meeting->api->screenshare_request( $params );
		if ( ! is_wp_error( $result ) && is_array( $result ) ) {
			$result['externalId'] = $external_id;
		}
		return $result;
	}

}
