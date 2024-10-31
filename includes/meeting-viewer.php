<?php

namespace GPLSCore\GPLS_PLUGIN_SLI;

use function GPLSCore\GPLS_PLUGIN_SLI\Utils\get_timezone_title;

defined( 'ABSPATH' ) || exit();

/**
 * Viewer Side Integration Class.
 */
class Meeting_Viewer {

	/**
	 * Settings Object.
	 *
	 * @var object
	 */
	public $settings;

	/**
	 * Meeting Integration Object.
	 *
	 * @var object
	 */
	public $meeting;

	/**
	 * Class constructor.
	 *
	 * @param Settings            $settings Settings Object.
	 * @param Meeting_Integration $meeting_integration Meeting Integration Object.
	 */
	public function __construct( Settings $settings, Meeting_Integration $meeting_integration ) {
		$this->settings     = $settings;
		$this->meeting      = $meeting_integration;
		$this->hooks();
	}

	/**
	 * Actions and Filters Hooks.
	 *
	 * @return void
	 */
	public function hooks() {
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_front_assets' ) );
		add_action( 'wp_ajax_' . $this->settings->plugin_info['name'] . '-viewer_get_url', array( $this, 'ajax_get_viewer_url' ) );
		add_action( 'wp_ajax_nopriv_' . $this->settings->plugin_info['name'] . '-viewer_get_url', array( $this, 'ajax_get_viewer_url' ) );
		add_filter( 'single_template', array( $this, 'meeting_template_path' ), PHP_INT_MAX, 3 );
		add_filter( 'theme_' . $this->meeting->post_type_name . '_templates', array( $this, 'meeting_single_post_template' ), 100, 4 );
		add_action( 'save_post_' . $this->meeting->post_type_name, array( $this, 'set_single_meeting_template_by_default' ), 100, 3 );
	}

	/**
	 * Front Single Meeting Assets.
	 *
	 * @return void
	 */
	public function enqueue_front_assets() {
		if ( is_singular( $this->meeting->post_type_name ) ) {
			wp_enqueue_style( $this->settings->plugin_info['name'] . '-viewer-css', $this->settings->plugin_info['url'] . 'assets/dist/css/viewer/viewer-styles.min.css', array( $this->settings->plugin_info['name'] . '-viewer-bootstrap-css' ), $this->settings->plugin_info['version'], 'all' );
			wp_enqueue_style( $this->settings->plugin_info['name'] . '-countdown-css', $this->settings->plugin_info['url'] . 'core/assets/libs/flipdown.min.css', array(), $this->settings->plugin_info['version'], 'all' );
			if ( ! wp_script_is( 'jquery' ) ) {
				wp_enqueue_script( 'jquery' );
			}
			wp_enqueue_script( $this->settings->plugin_info['name'] . '-countdown-js', $this->settings->plugin_info['url'] . 'core/assets/libs/flipdown.min.js', array( 'jquery' ), $this->settings->plugin_info['version'], true );
			wp_enqueue_script( $this->settings->plugin_info['name'] . '-viewer-js', $this->settings->plugin_info['url'] . 'assets/dist/js/screenleap/viewer/viewer-screenleap.min.js', array( $this->settings->plugin_info['name'] . '-countdown-js', 'jquery', 'wp-i18n' ), $this->settings->plugin_info['version'], true );
			wp_localize_script(
				$this->settings->plugin_info['name'] . '-viewer-js',
				str_replace( '-', '_', $this->settings->plugin_info['name'] . '-screenleap-localize-obj' ),
				array(
					'ajax_url'                => admin_url( 'admin-ajax.php' ),
					'screenleap_viewer_nonce' => wp_create_nonce( $this->settings->plugin_info['name'] . '_screenleap_viewer_nonce' ),
					'prefix'                  => $this->settings->plugin_info['name'],
					'eventsource_path'        => $this->settings->plugin_info['url'] . 'includes/meeting-ws.php',
				)
			);

			if ( ( $this->settings->plugin_info['path'] . 'templates/single-meeting.php' ) === get_single_template() ) {
				wp_enqueue_style( $this->settings->plugin_info['name'] . '-viewer-bootstrap-css', $this->settings->plugin_info['url'] . 'core/assets/libs/bootstrap.min.css', array(), $this->settings->plugin_info['version'], 'all' );
				wp_enqueue_script( $this->settings->plugin_info['name'] . '-viewer-bootstrap-js', $this->settings->plugin_info['url'] . 'core/assets/libs/bootstrap.bundle.min.js', array( 'jquery' ), $this->settings->plugin_info['version'], true );
			}
		}
	}

	/**
	 * Add single-meeting.php to Template option.
	 *
	 * @param array  $post_templates
	 * @param object $wp_theme_obj
	 * @param object $post
	 * @param string $post_type
	 * @return array
	 */
	public function meeting_single_post_template( $post_templates, $wp_theme_obj, $post, $post_type ) {
		$post_templates[ $this->meeting->post_type_name . '_general_template' ] = 'Default Template [ Plugin Template ]';
		return $post_templates;
	}

	/**
	 * Single Meeting Post Template.
	 *
	 * @return void
	 */
	public function meeting_template_path( $template, $type, $templates ) {
		global $post;
		if ( $post && ( $this->meeting->post_type_name === $post->post_type ) ) {
			$post_template = get_page_template_slug( $post );
			if ( empty( $template ) || ( $this->meeting->post_type_name . '_general_template' === $post_template ) ) {
				$template = $this->settings->plugin_info['path'] . 'templates/single-meeting.php';
			}
			set_query_var( 'settings', $this->settings );
			set_query_var( 'meeting_viewer', $this );
		}
		return $template;
	}

	/**
	 * Set Single Meeting Template By Default on insert post.
	 *
	 * @param int     $post_id Meeting Post ID.
	 * @param object  $post Post Object.
	 * @param boolean $update Is Update.
	 * @return void
	 */
	public function set_single_meeting_template_by_default( $post_id, $post, $update ) {
		if ( $update ) {
			return;
		}
		update_post_meta( $post_id, '_wp_page_template', $this->meeting->post_type_name . '_general_template' );
	}

	/**
	 * Get Meeting Viewser URL.
	 *
	 * @return void
	 */
	public function ajax_get_viewer_url() {

		if ( ! empty( $_POST['nonce'] ) && check_admin_referer( $this->settings->plugin_info['name'] . '_screenleap_viewer_nonce', 'nonce' ) ) {
			if ( ! empty( $_POST['meeting_id'] ) && is_numeric( $_POST['meeting_id'] ) ) {
				$meeting_id = absint( sanitize_text_field( wp_unslash( $_POST['meeting_id'] ) ) );
				$viewer_url = $this->get_meeting_viewer_url( $meeting_id );
				if ( ! $viewer_url ) {
					wp_send_json_error(
						__( 'Failed to open the meeting, please refresh the page' )
					);
				}
				wp_send_json_success(
					array(
						'result' => $viewer_url,
					)
				);
			}
		}
	}

	/**
	 * Check if login required for access the meeting.
	 *
	 * @param int $meeting_id Meeting Post ID.
	 * @return boolean
	 */
	public function is_login_required( $meeting_id ) {
		$meeting_settings = $this->meeting->get_meeting_settings( $meeting_id );
		return $meeting_settings['requires_login'];
	}

	/**
	 * Show Remaining Countdown section.
	 *
	 * @param int $meeting_id Meeting Post ID.
	 * @return boolean
	 */
	public function show_remaining_countdown( $meeting_id ) {
		$meeting_settings = $this->meeting->get_meeting_settings( $meeting_id );
		return $meeting_settings['show_remaining_countdown'];
	}

	/**
	 * Get Meeting Viewer URL.
	 *
	 * @param int $meeting_id Meeting Post ID.
	 * @return string|false
	 */
	public function get_meeting_viewer_url( $meeting_id ) {
		return $this->meeting->get_meeting_detail( $meeting_id, 'viewerUrl' );
	}

	/**
	 * Get The Meeting Settings Array as Title => Value for Viewing.
	 *
	 * @param int $meeting_id Meeting Post ID.
	 * @return string
	 */
	public function get_meeting_settings_for_view( $meeting_id ) {
		$result           = array();
		$meeting_settings = get_post_meta( $meeting_id, $this->meeting->meeting_meta_settings, true );
		$mappings         = array(
			'presenter_name' => 'Presenter Name',
			'date'           => 'Date',
			'time'           => 'Time',
			'timezone'       => 'Time Zone',
			'duration'       => 'Duration',
		);

		if ( ! empty( $meeting_settings ) && is_array( $meeting_settings ) ) {
			$key_title_mapped = array_flip( array_intersect_key( $mappings, $meeting_settings ) );
			foreach ( $key_title_mapped as $title => $key ) {
				// Time Adjust.
				if ( 'time' === $key ) {
					$time_separated = explode( ':', $meeting_settings[ $key ] );
					$midday         = 'AM';
					if ( is_array( $time_separated ) && ( 2 == count( $time_separated ) ) ) {
						if ( 12 <= intval( $time_separated[0] ) ) {
							$time_separated[0] -= 12;
							$time_separated[0]  = ( ( strlen( strval( $time_separated[0] ) ) === 1 ) ? ( '0' . $time_separated[0] ) : $time_separated[0] );
							$midday             = 'PM';
						}
						if ( 0 === intval( $time_separated[0] ) ) {
							$time_separated[0] = 12;
						}
						$meeting_settings[ $key ] = $time_separated[0] . ':' . $time_separated[1] . ' ' . $midday;
					}
				} elseif ( 'timezone' === $key ) {
					// Time Zone Adjust.
					$timezone_title           = get_timezone_title( $meeting_settings[ $key ] );
					$meeting_settings[ $key ] = $timezone_title ? $timezone_title : $meeting_settings[ $key ];
				} elseif ( 'duration' === $key ) {
					// Duration Adjust.
					$duration = intval( $meeting_settings[ $key ] );
					$hours    = floor( $duration / 60 );
					$minutes  = $duration % 60;

					if ( $hours ) {
						$meeting_settings[ $key ] = $hours . ' ' . ( $hours > 1 ? __( 'hours', 'screenshare-with-screenleap-integration' ) : __( 'hour', 'screenshare-with-screenleap-integration' ) ) . ( $minutes ? ( ' - ' . $minutes ) : '' );
					}

					if ( $minutes ) {
						$meeting_settings[ $key ] .= ' ' . ( $minutes > 1 ? __( 'minutes', 'screenshare-with-screenleap-integration' ) : __( 'minute', 'screenshare-with-screenleap-integration' ) );
					}
				}
				$result[ $title ] = $meeting_settings[ $key ];
			}
		}

		$meeting_status = $this->get_meeting_status( $meeting_id );
		if ( 'active' === $meeting_status ) {
			$result['Status'] = 'Started';
		} elseif ( 'end' === $meeting_status ) {
			$result['Status'] = 'Ended';
		}

		return $result;
	}

	/**
	 * Get the Meeting Agenda.
	 *
	 * @param int $meeting_id Meeting Post ID.
	 * @return string
	 */
	public function get_meeting_agenda( $meeting_id ) {
		$meeting_settings = get_post_meta( $meeting_id, $this->meeting->meeting_meta_settings, true );
		if ( ! empty( $meeting_settings ) && ! empty( $meeting_settings['agenda'] ) ) {
			return $meeting_settings['agenda'];
		} else {
			return '';
		}
	}

	/**
	 * Build the Viewer URL based on the meeting viewer configuration.
	 *
	 * @param int $meeting_id Meeting Post ID.
	 * @return string|false
	 */
	public function build_meeting_viewer_url( $meeting_id ) {
		$meeting_viewer_configuration = $this->meeting->get_meeting_viewer_conf( $meeting_id );
		$viewer_url                   = $this->meeting->get_meeting_detail( $meeting_id, 'viewerUrl' );
		$user_id                      = get_current_user_id();
		$params                       = array(
			'externalId'  => ( ! $user_id ? wp_generate_uuid4() : $user_id ),
			'fitToWindow' => true,
		);
		$params                       = array_merge( $params, $meeting_viewer_configuration );

		// remove view_method key.
		unset( $params['view_method'] );

		// Filer the params before building the URL.
		if ( 'iframe' === $meeting_viewer_configuration['view_method'] ) {
			unset( $params['redirectOnError'] );
			unset( $params['redirectOnEnd'] );
		}

		if ( empty( $params['redirectOnError'] ) ) {
			unset( $params['redirectOnError'] );
		}

		if ( empty( $params['redirectOnEnd'] ) ) {
			unset( $params['redirectOnEnd'] );
		}

		foreach ( $params as $key => $value ) {
			if ( $value === true ) {
				$params[ $key ] = 'true';
			} elseif ( $value === false ) {
				$params[ $key ] = 'false';
			}
		}

		if ( $viewer_url ) {
			$viewer_url .= '&' . http_build_query( $params );
			return $viewer_url;
		} else {
			return false;
		}
	}

	/**
	 * Get meeting URL.
	 *
	 * @param int $meeting_id Meeting Post ID.
	 * @return string
	 */
	public function get_meeting_url( $meeting_id ) {
		$url          = '';
		$meeting_type = $this->meeting->get_meeting_type( $meeting_id );
		if ( 'meet_now' === $meeting_type['type'] ) {
			$url = 'https://screenleap.com/' . $this->settings->get_handle();
		} elseif ( 'scheduled_meet' === $meeting_type['type'] ) {
			$url = $meeting_type['scheduled_meeting_link'];
		} elseif ( 'api' === $meeting_type['type'] ) {
			$url = $this->build_meeting_viewer_url( $meeting_id );
		}
		return $url;
	}

	/**
	 * Get Meeting Type.
	 *
	 * @param int $meeting_id Meeting Post ID.
	 * @return string
	 */
	public function get_meeting_type_only( $meeting_id ) {
		$meeting_type = $this->meeting->get_meeting_type( $meeting_id );
		return $meeting_type['type'];
	}

	/**
	 * Get Meeting Viewer Configuration.
	 *
	 * @param int $meeting_id Meeting Post ID.
	 * @return array
	 */
	public function get_viewer_configuration( $meeting_id ) {
		return $this->meeting->get_meeting_viewer_conf( $meeting_id );
	}

	/**
	 * Get Meeting status.
	 *
	 * @param int $meeting_id Meeting Post ID.
	 * @return string
	 */
	public function get_meeting_status( $meeting_id ) {
		$status = $this->meeting->get_meeting_status( $meeting_id );
		if ( 'pause' === $status ) {
			$status = 'active';
		}

		return $status;
	}

	/**
	 * Get Meeting Details.
	 *
	 * @param int $meeting_id Meeting Post ID.
	 * @return array
	 */
	public function get_meeting_details( $meeting_id ) {
		return $this->meeting->get_meeting_details( $meeting_id );
	}

	/**
	 * Get Meeting Remaining Time to Start.
	 *
	 * @param int $meeting_id Meeting Post ID.
	 * @return int
	 */
	public function get_meeting_remaining( $meeting_id ) {
		$meeting_settings = $this->meeting->get_meeting_settings( $meeting_id );
		$meeting_date     = $meeting_settings['date'];
		$meeting_time     = $meeting_settings['time'];
		$meeting_timezone = $meeting_settings['timezone'];

		if ( empty( $meeting_date ) ) {
			return false;
		}

		if ( empty( $meeting_timezone ) ) {
			$meeting_timezone = null;
		} else {
			$meeting_timezone = new \DateTimeZone( $meeting_timezone );
		}

		$meeting_datetime = \DateTime::createFromFormat( 'Y-m-d H:i', $meeting_date . ' ' . $meeting_time, $meeting_timezone );
		$now_datetime     = new \DateTime( 'now', $meeting_timezone );
		$datetime_diff    = $meeting_datetime->getTimestamp() - $now_datetime->getTimestamp();
		if ( $datetime_diff > 0 ) {
			return $meeting_datetime->getTimestamp();
		} else {
			return false;
		}
	}

	/**
	 * Reminder Section Form for meeting.
	 *
	 * @param int $meeting_id   Meeting Post ID.
	 * @return void
	 */
	public function reminder_section( $meeting_id ) {
		ob_start();
		?>
		<form class="form-inline <?php echo esc_attr( $this->settings->plugin_info['name'] . '-reminder-email-form' ); ?>">
			<input type="hidden" class="<?php echo esc_attr( $this->settings->plugin_info['name'] . '-reminder-meeting' ); ?>" name="<?php echo esc_attr( $this->settings->plugin_info['name'] . '-reminder-meeting' ); ?>" value="<?php echo esc_attr( $meeting_id ); ?>">
			<input type="email" required class="<?php echo esc_attr( $this->settings->plugin_info['name'] . '-reminder-email' ); ?> form-control" name="<?php echo esc_attr( $this->settings->plugin_info['name'] . '-reminder-email' ); ?>" >
			<button type="submit" class="btn btn-submit btn-secondary"><?php _e( 'Submit', 'screenshare-with-screenleap-integration' ); ?></button>
		</form>
		<?php
		$output = ob_get_clean();
		echo $output;
	}

	/**
	 * Show Reminder Box.
	 *
	 * @param int $meeting_id Meeting Post ID.
	 * @return boolean
	 */
	public function show_reminder_section( $meeting_id ) {
		$meeting_settings = $this->meeting->get_meeting_settings( $meeting_id );
		return $meeting_settings['reminder_box'];
	}

}
