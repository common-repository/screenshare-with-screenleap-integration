<?php

namespace GPLSCore\GPLS_PLUGIN_SLI;

use GPLSCore\GPLS_PLUGIN_SLI\API;
use function GPLSCore\GPLS_PLUGIN_SLI\Helpers\get_timezone_dropdown;
use function GPLSCore\GPLS_PLUGIN_SLI\Utils\get_countries;

defined( 'ABSPATH' ) || exit();

/**
 * Meetings Integration Class.
 */
class Meeting_Integration {

	/**
	 * Api Object.
	 *
	 * @var object
	 */
	public $api;

	/**
	 * Settings Object.
	 *
	 * @var object
	 */
	public $settings;

	/**
	 * Post Type Name.
	 *
	 * @var string
	 */
	public $post_type_name = 'gpls_sli_screenleap';

	/**
	 * Meeting CPT meta Details.
	 *
	 * @var string
	 */
	public $meeting_meta_details;

	/**
	 * Meeting type meta field.
	 *
	 * @var string
	 */
	public $meeting_meta_type;

	/**
	 * Meeting CPT Configuration meta.
	 *
	 * @var string
	 */
	public $meeting_meta_conf;

	/**
	 * Meeting CPT Settings meta.
	 *
	 * @var string
	 */
	public $meeting_meta_settings;

	/**
	 * Meeting CPT Viewer Configuration meta.
	 *
	 * @var string
	 */
	public $meeting_meta_viewer;

	/**
	 * Meeting CPT Reminder Emails meta.
	 *
	 * @var string
	 */
	public $meeting_meta_reminder_emails;

	/**
	 * Countries and Their Codes Array.
	 *
	 * @var array
	 */
	public $countries;

	/**
	 * Default Meeting Details Array.
	 *
	 * @var array
	 */
	public $default_meeting_details = array(
		'status'          => 'start',
		'screenShareCode' => '',
		'presenterParams' => '',
		'viewerUrl'       => '',
		'origin'          => '',
		'useV2'           => '',
		'participantId'   => '',
		'token'           => '',
		'hostname'        => '',
		'accountId'       => '',
	);

	/**
	 * Default Meeting Type. [ meet_now, scheduled_meet, api ]
	 *
	 * @var array
	 */
	public $default_meeting_type = array(
		'type'                   => 'meet_now',
		'status'                 => 'start',
		'scheduled_meeting_link' => '',
	);

	/**
	 * Default Meeting Details Array.
	 *
	 * @var array
	 */
	public $default_meeting_configuration = array(
		'presenterAppType'       => 'NATIVE',
		'isSecure'               => true,
		'showScreenleapBranding' => true,
		'title'                  => '',
		'presenterCountryCode'   => '',
		'startPaused'            => false,
		'hideStopButton'         => false,
		'hidePauseButton'        => false,
		'optimization'           => 'DEFAULT',
		'externalId'             => '',
	);

	/**
	 * Default Meeting Viewer Configuration Array.
	 *
	 * @var array
	 */
	public $default_meeting_viewer_configuration = array(
		'view_method'     => 'redirect',
		'showStop'        => false,
		'showResize'      => false,
	);

	/**
	 * Default Meeting Settings.
	 *
	 * @var array
	 */
	public $default_meeting_settings = array(
		'show_remaining_countdown' => true,
		'requires_login'           => false,
		'presenter_name'           => '',
		'date'                     => '',
		'time'                     => '00:00',
		'timezone'                 => '',
		'duration'                 => '',
		'agenda'                   => '',
	);

	/**
	 * Meeting Status Mapping Array.
	 *
	 * @var array
	 */
	public $meeting_status_mapping;

	/**
	 * Class constructor.
	 *
	 * @param API $screenleap_api Screenleap API Object.
	 */
	public function __construct( API $screenleap_api ) {
		// $this->includes();
		$this->api = $screenleap_api;
		$this->setup();
		$this->hooks();
	}

	/**
	 * Setup Function.
	 *
	 * @return void
	 */
	public function setup() {
		$this->settings                     = $this->api->settings;
		$this->img_urls                     = $this->settings->plugin_info['url'] . 'assets/dist/images/';
		$this->meeting_meta_details         = $this->settings->plugin_info['name'] . '-meeting-details';
		$this->meeting_meta_type            = $this->settings->plugin_info['name'] . '-meeting-type';
		$this->meeting_meta_conf            = $this->settings->plugin_info['name'] . '-meeting-configuration';
		$this->meeting_meta_settings        = $this->settings->plugin_info['name'] . '-meeting-settings';
		$this->meeting_meta_viewer          = $this->settings->plugin_info['name'] . '-meeting-viewer-configuration';
		$this->meeting_meta_reminder_emails = $this->settings->plugin_info['name'] . '-meeting-reminder-emails';
		$this->countries                    = get_countries();
		$this->meeting_status_mapping       = array(
			'start'  => array(
				'title' => __( 'Not Started', 'screenshare-with-screenleap-integration' ),
				'icon'  => 'led-black',
			),
			'active' => array(
				'title' => __( 'Active', 'screenshare-with-screenleap-integration' ),
				'icon'  => 'led-green',
			),
			'pause'  => array(
				'title' => __( 'Paused', 'screenshare-with-screenleap-integration' ),
				'icon'  => 'led-yellow',
			),
			'end'    => array(
				'title' => __( 'Ended', 'screenshare-with-screenleap-integration' ),
				'icon'  => 'led-red',
			),
		);
	}

	/**
	 * Actions and Filters Hooks.
	 *
	 * @return void
	 */
	public function hooks() {
		add_action( 'init', array( $this, 'meetings_cpt' ) );
		add_action( 'add_meta_boxes', array( $this, 'meeting_meta_boxs' ) );
		add_action( 'save_post_' . $this->post_type_name, array( $this, 'save_meeting_meta_fields' ), 100, 3 );
		add_filter( 'woocommerce_add_to_cart_redirect', array( $this, 'force_redirect_to_checkout' ), PHP_INT_MAX, 2 );
		add_filter( 'hidden_meta_boxes', array( $this, 'bypass_screenshare_metabox_hide' ), 100, 2 );
	}

	/**
	 * Register Screenleap Meetings Custom Post Type.
	 *
	 * @return void
	 */
	public function meetings_cpt() {
		register_post_type(
			$this->post_type_name,
			array(
				'labels'       => array(
					'name'           => _x( 'Screenleap Meetings', 'screenshare-with-screenleap-integration' ),
					'singular_name'  => _x( 'Meeting', 'screenshare-with-screenleap-integration' ),
					'name_admin_bar' => _x( 'Screenleap Meeting', 'screenshare-with-screenleap-integration' ),
				),
				'position'     => 10,
				'public'       => true,
				'show_ui'      => true,
				'show_in_menu' => true,
				'show_in_rest' => false,
				'menu_icon'    => 'dashicons-video-alt',
				'rewrite'      => array(
					'slug'       => 'screenleap-meetings',
					'with_front' => true,
				),
				'has_archive'  => true,
				'taxonomies'   => array(
					'category',
					'post_tag',
				),
				'supports'     => array( 'title', 'comments', 'author', 'thumbnail', 'custom-fields' ),
			)
		);
	}

	/**
	 * Bypass the auto hide of the screensharea hide.
	 *
	 * @param array $hidden 	hidden metaboxes array.
	 * @param object $screen 	screen Object.
	 * @return array
	 */
	public function bypass_screenshare_metabox_hide( $hidden, $screen ) {
		if ( $screen->id === $this->post_type_name ) {
			$hidden = array_diff( $hidden, array( 'd-' . $this->settings->plugin_info['name'] . '-screen-share-conf-metabox' ) );
			$hidden = array_diff( $hidden, array( 'e-' . $this->settings->plugin_info['name'] . '-screen-share-viewer-metabox' ) );
			$hidden = array_diff( $hidden, array( 'f-' . $this->settings->plugin_info['name'] . '-screen-share-details-metabox' ) );
			$hidden = array_diff( $hidden, array( 'g-' . $this->settings->plugin_info['name'] . '-screen-share-actions-metabox' ) );
			$hidden = array_diff( $hidden, array( 'h-' . $this->settings->plugin_info['name'] . '-screen-share-viewers-metabox' ) );
		}
		return $hidden;
	}

	/**
	 * Screen share Actions Meta BOx.
	 *
	 * @return void
	 */
	public function meeting_meta_boxs() {
		add_meta_box( 'a-' . $this->settings->plugin_info['name'] . '-screen-share-access-metabox', __( 'Meeting Access', 'screenshare-with-screenleap-integration' ), array( $this, 'screen_share_access_callback' ), $this->post_type_name, 'advanced', 'high' );
		add_meta_box( 'b-' . $this->settings->plugin_info['name'] . '-screen-share-settings-metabox', __( 'Meeting Details', 'screenshare-with-screenleap-integration' ), array( $this, 'screen_share_settings_callback' ), $this->post_type_name, 'advanced', 'high' );
		add_meta_box( 'c-' . $this->settings->plugin_info['name'] . '-screen-share-type-metabox', __( 'Screen Share Type', 'screenshare-with-screenleap-integration' ), array( $this, 'screen_share_type_callback' ), $this->post_type_name, 'advanced', 'high' );
		add_meta_box( 'd-' . $this->settings->plugin_info['name'] . '-screen-share-conf-metabox', __( 'Screen Share Configuration', 'screenshare-with-screenleap-integration' ), array( $this, 'screen_share_conf_callback' ), $this->post_type_name, 'advanced', 'high' );
		add_meta_box( 'e-' . $this->settings->plugin_info['name'] . '-screen-share-viewer-metabox', __( 'Viewer Configuration', 'screenshare-with-screenleap-integration' ), array( $this, 'screen_share_viewer_conf_callback' ), $this->post_type_name, 'advanced', 'high' );
		add_meta_box( 'f-' . $this->settings->plugin_info['name'] . '-screen-share-details-metabox', __( 'Screen Share Details', 'screenshare-with-screenleap-integration' ), array( $this, 'screen_share_details_callback' ), $this->post_type_name, 'advanced', 'high' );
		add_meta_box( 'g-' . $this->settings->plugin_info['name'] . '-screen-share-actions-metabox', __( 'Screen Share Actions', 'screenshare-with-screenleap-integration' ), array( $this, 'screen_share_actions_callback' ), $this->post_type_name, 'advanced', 'high' );
		add_meta_box( 'h-' . $this->settings->plugin_info['name'] . '-screen-share-viewers-metabox', __( 'Logged In Viewers ( Pro )', 'screenshare-with-screenleap-integration' ), '__return_false', $this->post_type_name, 'advanced', 'high' );
		add_meta_box( 'i-' . $this->settings->plugin_info['name'] . '-screen-share-reminder-emails-metabox', __( 'Reminder Subscription Emails ( Pro )', 'screenshare-with-screenleap-integration' ), '__return_false', $this->post_type_name, 'side', 'low' );
	}

	/**
	 * Meeting Access Metabox Callback.
	 *
	 * @param object $post Post Object.
	 * @return void
	 */
	public function screen_share_access_callback( $post ) {
		$meeting_settings = $this->get_meeting_settings( $post->ID );
		?>
		<table class="form-table" role="presentation">
			<tbody>
				<tr>
					<!-- Show Remaining CountDown? -->
					<th scope="row">
						<?php _e( 'Show Remaining Countdown?', 'screenshare-with-screenleap-integration' ); ?>
					</th>
					<td>
						<fieldset>
							<label for="<?php echo esc_attr( $this->meeting_meta_settings . '-show-remaining-countdown' ); ?>">
								<input class="meeting-conf-input" type="checkbox" name="<?php echo esc_attr( $this->meeting_meta_settings . '-show-remaining-countdown' ); ?>" id="<?php echo esc_attr( $this->meeting_meta_settings . '-show-remaining-countdown' ); ?>" value="" <?php checked( sanitize_text_field( $meeting_settings['show_remaining_countdown'] ) ); ?> data-key="show_remaining_countdown" >
								<?php _e( 'Display the remaining countdown section on the meeting page.', 'screenshare-with-screenleap-integration' ); ?>
							</label>
						</fieldset>
					</td>
				</tr>
				<tr>
					<!-- Requires Login? -->
					<th scope="row">
						<?php _e( 'Require Login?', 'screenshare-with-screenleap-integration' ); ?>
					</th>
					<td>
						<fieldset>
							<label for="<?php echo esc_attr( $this->meeting_meta_settings . '-requires-login' ); ?>">
								<input class="meeting-conf-input" type="checkbox" name="<?php echo esc_attr( $this->meeting_meta_settings . '-requires-login' ); ?>" id="<?php echo esc_attr( $this->meeting_meta_settings . '-requires-login' ); ?>" value="" <?php checked( sanitize_text_field( $meeting_settings['requires_login'] ) ); ?> data-key="requires_login" >
								<?php _e( 'Only logged in users can join the meeting, The -Join Meeting- button will appear only to logged in users.', 'screenshare-with-screenleap-integration' ); ?>
							</label>
						</fieldset>
					</td>
				</tr>
				<tr class="bg-light text-muted">
					<!-- Reminder Subscription Box -->
					<th class="text-muted" scope="row">
						<?php _e( 'Show Reminder Subsciption Form? (Pro)', 'screenshare-with-screenleap-integration' ); ?>
					</th>
					<td>
						<fieldset>
							<label>
								<input disabled class="disabled meeting-conf-input" type="checkbox">
								<?php _e( 'Show a subscription email form for users to register so u can send emails to users before starting the meeting.', 'screenshare-with-screenleap-integration' ); ?>
							</label>
						</fieldset>
					</td>
				</tr>
				<!-- Is Purchasable? -->
				<tr class="bg-light text-muted">
					<th class="text-muted" scope="row">
						<?php _e( 'Is Purchasable? (Pro)', 'screenshare-with-screenleap-integration' ); ?>
					</th>
					<td>
						<fieldset>
							<label>
								<input disabled class="meeting-conf-input" type="checkbox" >
								<?php _e( 'Is the meeting purchasable?', 'screenshare-with-screenleap-integration' ); ?>
							</label>
						</fieldset>
					</td>
				</tr>
				<!-- Product Linking -->
				<tr class="meeting-product bg-light text-muted">
					<th class="text-muted" scope="row">
						<?php _e( 'Meeting Product (Pro)', 'screenshare-with-screenleap-integration' ); ?>
					</th>
					<td>
						<fieldset>
							<label >
								<select disabled class="disabled">
								</select>
								<?php _e( 'Select the Meeting WooCommerce Product.', 'screenshare-with-screenleap-integration' ); ?>
							</label>
						</fieldset>
					</td>
				</tr>
			</tbody>
		</table>

		<?php
	}

	/**
	 * Meeting Setting Metabox Callback.
	 *
	 * @param \WP_Post $post Post object.
	 * @return void
	 */
	public function screen_share_settings_callback( $post ) {
		global $is_IE;
		$meeting_settings = $this->get_meeting_settings( $post->ID );
		?>
		<div class="row">
			<?php wp_nonce_field( $this->settings->plugin_info['name'] . '-meeting-edit-nonce', $this->settings->plugin_info['name'] . '-meeting-edit-nonce', false ); ?>
			<!-- Meeting Agenda -->
			<div class="col-12 my-4">
				<h6><?php _e( 'Meeting Agenda', 'screenshare-with-screenleap-integration' ); ?></h6>
				<?php
				wp_editor(
					$meeting_settings['agenda'],
					$this->meeting_meta_settings . '-agenda',
					array(
						'_content_editor_dfw' => false,
						'drag_drop_upload'    => true,
						'tabfocus_elements'   => 'content-html,save-post',
						'editor_height'       => 300,
						'tinymce'             => array(
							'resize'                  => false,
							'wp_autoresize_on'        => true,
							'add_unload_trigger'      => false,
							'wp_keep_scroll_position' => ! $is_IE,
						),
					)
				);
				?>
			</div>
			<!-- Presenter Name -->
			<div class="col-12 my-4">
				<h6> <?php _e( 'Presenter Name', 'screenshare-with-screenleap-integration' ); ?></h6>
				<input type="text" class="regular-text" name="<?php echo esc_attr( $this->meeting_meta_settings . '-presenter-name' ); ?>" value="<?php echo esc_attr( $meeting_settings['presenter_name'] ); ?>">
			</div>
			<!-- Meeting Date -->
			<div class="col-12 my-4">
				<h6><?php _e( 'Meeting Date', 'screenshare-with-screenleap-integration' ); ?></h6>
				<input type="date" class="regular-text" min="<?php echo esc_attr( date( 'Y-m-d' ) ); ?>" name="<?php echo esc_attr( $this->meeting_meta_settings . '-date' ); ?>" value="<?php echo esc_attr( $meeting_settings['date'] ); ?>">
			</div>
			<!-- Meeting Time -->
			<div class="col-12 my-4">
				<h6><?php _e( 'Meeting time', 'screenshare-with-screenleap-integration' ); ?></h6>
				<input type="time" class="regular-text" name="<?php echo esc_attr( $this->meeting_meta_settings . '-time' ); ?>" value="<?php echo esc_attr( $meeting_settings['time'] ); ?>">
			</div>
			<!-- Meeting timezone -->
			<div class="col-12 my-4">
				<h6><?php _e( 'Meeting Timezone', 'screenshare-with-screenleap-integration' ); ?></h6>
				<?php get_timezone_dropdown( $this->meeting_meta_settings . '-timezone', $meeting_settings['timezone'] ); ?>
			</div>
			<!-- Meeting Duration -->
			<div class="col-12 my-4">
				<h6><?php _e( 'Meeting Duration ( in minutes )', 'screenshare-with-screenleap-integration' ); ?></h6>
				<input type="number" min="1" class="regular-text" name="<?php echo esc_attr( $this->meeting_meta_settings . '-duration' ); ?>" value="<?php echo esc_attr( $meeting_settings['duration'] ); ?>">
			</div>
		</div>
		<?php
	}

	/**
	 * Screen Share Type Callback.
	 *
	 * @param \WP_Post $post Post Object.
	 * @return void
	 */
	public function screen_share_type_callback( $post ) {
		$meeting_type = $this->get_meeting_type( $post->ID );
		?>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row">
					<?php _e( 'Meeting Type', 'screenshare-with-screenleap-integration' ); ?>
				</th>
				<td>
					<fieldset>
						<label for="<?php echo esc_attr( $this->meeting_meta_type ); ?>">
							<select class="<?php echo esc_attr( $this->meeting_meta_type ); ?>" name="<?php echo esc_attr( $this->meeting_meta_type . '[type]' ); ?>" id="<?php echo esc_attr( $this->meeting_meta_type ); ?>">
								<option value="meet_now" <?php selected( $meeting_type['type'], 'meet_now' ); ?> ><?php _e( 'Meet Now', 'screenshare-with-screenleap-integration' ); ?></option>
								<option value="scheduled_meet" <?php selected( $meeting_type['type'], 'scheduled_meet' ); ?> ><?php _e( 'Schedule Meeting', 'screenshare-with-screenleap-integration' ); ?></option>
								<option value="api" <?php selected( $meeting_type['type'], 'api' ); ?> ><?php _e( 'API', 'screenshare-with-screenleap-integration' ); ?></option>
							</select>
						</label>
						<?php if ( ! $this->settings->is_developer_cred_ready() ) : ?>
						<div class="api-alert alert alert-danger <?php echo ( 'api' === $meeting_type['type'] ? '' : 'd-none' ); ?>" role="alert">
							<?php _e( 'API integration info are missing in ', 'screenshare-with-screenleap-integration' ); ?> <a href="<?php echo esc_url( $this->settings->settings_page_link ); ?>" ><?php _e( 'settings' ); ?></a> <?php _e( 'page' ); ?>
						</div>
						<?php endif; ?>
						<?php if ( ! $this->settings->is_handle_ready() ) : ?>
						<div class="handle-alert alert alert-danger <?php echo ( 'meet_now' === $meeting_type['type'] ? '' : 'd-none' ); ?>" role="alert">
							<?php _e( 'The handle name is missing in ', 'screenshare-with-screenleap-integration' ); ?> <a href="<?php echo esc_url( $this->settings->settings_page_link ); ?>" ><?php _e( 'settings' ); ?></a> <?php _e( 'page' ); ?>
						</div>
						<?php endif; ?>
					</fieldset>
				</td>
			</tr>
			<tr class="scheduled-meeting">
				<th scope="row">
					<?php _e( 'Scheduled Meet Link', 'screenshare-with-screenleap-integration' ); ?>
				</th>
				<td>
					<fieldset>
						<input class="regular-text" type="url" name="<?php echo esc_attr( $this->meeting_meta_type . '[scheduled_meeting_link]' ); ?>" value="<?php echo esc_url( $meeting_type['scheduled_meeting_link'] ); ?>" >
					</fieldset>
				</td>
			</tr>
			<tr class="meeting-type-status">
				<th scope="row">
					<?php _e( 'Meeting Status', 'screenshare-with-screenleap-integration' ); ?>
				</th>
				<td>
					<fieldset>
						<label for="<?php echo esc_attr( $this->meeting_meta_type . '-status' ); ?>">
							<select class="meeting-conf-input" name="<?php echo esc_attr( $this->meeting_meta_type . '[status]' ); ?>" id="<?php echo esc_attr( $this->meeting_meta_type . '-status' ); ?>">
								<option <?php selected( esc_attr( $meeting_type['status'] ), 'start' ); ?> value="start"><?php _e( 'Not Started', 'screenshare-with-screenleap-integration' ); ?></option>
								<option <?php selected( esc_attr( $meeting_type['status'] ), 'active' ); ?> value="active"><?php _e( 'Started', 'screenshare-with-screenleap-integration' ); ?></option>
								<option <?php selected( esc_attr( $meeting_type['status'] ), 'end' ); ?> value="end"><?php _e( 'Ended', 'screenshare-with-screenleap-integration' ); ?></option>
							</select>
							<span class="d-block"><?php _e( 'Update the meeting status when it\'s not started, started or ended', 'screenshare-with-screenleap-integration' ); ?></span>
						</label>
					</fieldset>
				</td>
			</tr>
		</table>
		<?php
	}

	/**
	 * Screen Share Actions Metabox Callback.
	 *
	 * @param \WP_Post $post Post Object.
	 *
	 * @return void
	 */
	public function screen_share_conf_callback( $post ) {
		$meeting_configuration = $this->get_meeting_conf( $post->ID );
		?>
		<table class="form-table" role="presentation">
			<tbody>
				<!-- isSecure -->
				<tr>
					<th scope="row">
						<?php _e( 'Using SSL', 'screenshare-with-screenleap-integration' ); ?>
					</th>
					<td>
						<fieldset>
							<label for="<?php echo esc_attr( $this->meeting_meta_conf . '-is-ssl' ); ?>">
								<input class="meeting-conf-input is-using-ssl-input" type="checkbox" name="<?php echo esc_attr( $this->meeting_meta_conf . '[isSecure]' ); ?>" id="<?php echo esc_attr( $this->meeting_meta_conf . '-is-ssl' ); ?>" value="" <?php checked( sanitize_text_field( $meeting_configuration['isSecure'] ) ); ?> data-key="isSecure" >
								<?php _e( 'Encrypts the screen share data using SSL. This is recommended if your users will be sharing sensitive data.', 'screenshare-with-screenleap-integration' ); ?>
							</label>
						</fieldset>
					</td>
				</tr>
				<!-- showScreenleapBranding -->
				<tr>
					<th scope="row">
						<?php _e( 'Show Screenleap Branding', 'screenshare-with-screenleap-integration' ); ?>
					</th>
					<td>
						<fieldset>
							<label for="<?php echo esc_attr( $this->meeting_meta_conf . '-show-screenleap-branding' ); ?>">
								<input  class="meeting-conf-input" type="checkbox" name="<?php echo esc_attr( $this->meeting_meta_conf . '[showScreenleapBranding]' ); ?>" id="<?php echo esc_attr( $this->meeting_meta_conf . '-show-screenleap-branding' ); ?>" value="" <?php checked( sanitize_text_field( $meeting_configuration['showScreenleapBranding'] ) ); ?> data-key="showScreenleapBranding" >
								<?php _e( 'Includes a top bar with the Screenleap logo for all viewers. The Screenleap name will also be shown in the presenter console. Enabling this option gives you a discount on non-SSL screen shares.', 'screenshare-with-screenleap-integration' ); ?>
							</label>
						</fieldset>
					</td>
				</tr>
				<!-- title -->
				<tr>
					<th scope="row">
						<?php _e( 'Title', 'screenshare-with-screenleap-integration' ); ?>
					</th>
					<td>
						<input class="meeting-conf-input regular-text" type="text" name="<?php echo esc_attr( $this->meeting_meta_conf . '[title]' ); ?>" id="<?php echo esc_attr( $this->meeting_meta_conf . '-title' ); ?>" value="<?php echo esc_attr( $meeting_configuration['title'] ); ?>" data-key="title">
						<p class="description" ><?php _e( 'A title to display in the presenter console and viewer top bar (if the top bar is visible). This option is only relevant if "Show Screenleap Branding" option is false; defaults to the company name if not set.', 'screenshare-with-screenleap-integration' ); ?></p>
					</td>
				</tr>
				<!-- presenterCountryCode -->
				<tr>
					<th scope="row">
						<?php _e( 'Presenter Country', 'screenshare-with-screenleap-integration' ); ?>
					</th>
					<td>
						<?php echo $this->countries_dropdown( $meeting_configuration['presenterCountryCode'] ); ?>
						<p class="description" ><?php _e( 'To select the server closest to that country to host the share.', 'screenshare-with-screenleap-integration' ); ?></p>
					</td>
				</tr>
				<!-- hideStopButton -->
				<tr>
					<th scope="row">
						<?php _e( 'Hide Stop Button', 'screenshare-with-screenleap-integration' ); ?>
					</th>
					<td>
						<fieldset>
							<label for="<?php echo esc_attr( $this->meeting_meta_conf . '-hide-stop-button' ); ?>">
								<input class="meeting-conf-input" type="checkbox" name="<?php echo esc_attr( $this->meeting_meta_conf . '[hideStopButton]' ); ?>" id="<?php echo esc_attr( $this->meeting_meta_conf . '-hide-stop-button' ); ?>" value="" <?php checked( sanitize_text_field( $meeting_configuration['hideStopButton'] ) ); ?>  data-key="hideStopButton" >
								<?php _e( 'Hide the console stop button.', 'screenshare-with-screenleap-integration' ); ?>
							</label>
						</fieldset>
					</td>
				</tr>
				<!-- hidePauseButton -->
				<tr>
					<th scope="row">
						<?php _e( 'Hide Pause Button', 'screenshare-with-screenleap-integration' ); ?>
					</th>
					<td>
						<fieldset>
							<label for="<?php echo esc_attr( $this->meeting_meta_conf . '-hide-pause-button' ); ?>">
								<input class="meeting-conf-input" type="checkbox" name="<?php echo esc_attr( $this->meeting_meta_conf . '[hidePauseButton]' ); ?>" id="<?php echo esc_attr( $this->meeting_meta_conf . '-hide-pause-button' ); ?>" value="" <?php checked( sanitize_text_field( $meeting_configuration['hidePauseButton'] ) ); ?> data-key="hidePauseButton" >
								<?php _e( 'Hide the console Pause button.', 'screenshare-with-screenleap-integration' ); ?>
							</label>
						</fieldset>
					</td>
				</tr>
				<!-- startPaused -->
				<tr>
					<th scope="row">
						<?php _e( 'Start as paused', 'screenshare-with-screenleap-integration' ); ?>
					</th>
					<td>
						<fieldset>
							<label for="<?php echo esc_attr( $this->meeting_meta_conf . '-start-paused-' ); ?>">
								<input class="meeting-conf-input start-paused-input" type="checkbox" name="<?php echo esc_attr( $this->meeting_meta_conf . '[startPaused]' ); ?>" id="<?php echo esc_attr( $this->meeting_meta_conf . '-start-paused' ); ?>" value="" <?php checked( sanitize_text_field( $meeting_configuration['startPaused'] ) ); ?> data-key="startPaused" >
								<?php _e( '	Starts the screen share in paused mode. This is useful when the presenter wants to prepare their session before making it available to viewers..', 'screenshare-with-screenleap-integration' ); ?>
							</label>
						</fieldset>
					</td>
				</tr>
				<!-- openWholeScreen -->
				<tr class="bg-light text-muted">
					<th class="text-muted" scope="row">
						<?php _e( 'Full screen mode (Pro)', 'screenshare-with-screenleap-integration' ); ?>
					</th>
					<td>
						<fieldset>
							<label>
								<input disabled type="checkbox">
								<?php _e( 'Opens the share in whole-screen mode (i.e. no green rectangle). Be aware that it can result in poor performance for users on large screens. We recommend using the rectangle mode when possible to only share the portion of the screen that needs to be shared.', 'screenshare-with-screenleap-integration' ); ?>
							</label>
						</fieldset>
					</td>
				</tr>
				<!-- openInRectangleMode -->
				<tr class="bg-light text-muted">
					<th class="text-muted" scope="row">
						<?php _e( 'Open in rectangle mode (Pro)', 'screenshare-with-screenleap-integration' ); ?>
					</th>
					<td>
						<fieldset>
							<label >
								<input disabled type="checkbox">
								<?php _e( 'Whether to start the screen share in rectangle mode.', 'screenshare-with-screenleap-integration' ); ?>
							</label>
						</fieldset>
					</td>
				</tr>
				<!-- enableBroadcastAudio -->
				<tr class="bg-light text-muted">
					<th class="text-muted" scope="row">
						<?php _e( 'Enable broadcast audio (Pro)', 'screenshare-with-screenleap-integration' ); ?>
						<small><?php _e( 'Beta', 'screenshare-with-screenleap-integration' ); ?></small>
					</th>
					<td>
						<fieldset>
							<label>
								<input disabled type="checkbox">
								<?php _e( 'Enable one-way broadcast audio from presenter to viewers.', 'screenshare-with-screenleap-integration' ); ?>
							</label>
						</fieldset>
					</td>
				</tr>`
				<!-- allowViewerMouse -->
				<tr class="bg-light text-muted">
					<th class="text-muted" scope="row">
						<?php _e( 'Allow viewer mouse (Pro)', 'screenshare-with-screenleap-integration' ); ?>
					</th>
					<td>
						<fieldset>
							<label>
								<input disabled type="checkbox">
								<?php _e( 'Allow the presenter to select a viewer that the presenter will see the mouse cursor for on his/her screen. This can be used to allow the viewer to point things out on the presenter\'s screen to the presenter.', 'screenshare-with-screenleap-integration' ); ?>
							</label>
						</fieldset>
					</td>
				</tr>
				<!-- allowRemoteControl -->
				<tr class="bg-light text-muted">
					<th class="text-muted" scope="row">
						<?php _e( 'Allow control Sharing (Pro)', 'screenshare-with-screenleap-integration' ); ?>
					</th>
					<td>
						<fieldset>
							<label>
								<input disabled type="checkbox">
								<?php _e( 'Allow the presenter to select a viewer that will be able to control the presenter\'s computer. This can be used for collaboration or for the presenter to get assistance from a viewer.', 'screenshare-with-screenleap-integration' ); ?>
							</label>
						</fieldset>
					</td>
				</tr>
				<!-- recordVideo -->
				<tr class="bg-light text-muted">
					<th class="text-muted" scope="row">
						<?php _e( 'Enable Recording (Pro)', 'screenshare-with-screenleap-integration' ); ?>
					</th>
					<td>
						<fieldset>
							<label>
								<input disabled type="checkbox">
								<?php _e( 'Enable Recoding the screen share', 'screenshare-with-screenleap-integration' ); ?>
							</label>
						</fieldset>
					</td>
				</tr>
				<!-- autoRecord -->
				<tr class="bg-light text-muted">
					<th class="text-muted" scope="row">
						<?php _e( 'Auto Record on Start (Pro)', 'screenshare-with-screenleap-integration' ); ?>
					</th>
					<td>
						<fieldset>
							<label>
								<input disabled type="checkbox">
								<?php _e( 'Whether to start recording when the screen share is started. If false, you will need to make a call to start the recording separately.', 'screenshare-with-screenleap-integration' ); ?>
							</label>
						</fieldset>
					</td>
				</tr>
				<!-- optimization -->
				<tr class="bg-light text-muted">
					<th class="text-muted"  scope="row">
						<?php _e( 'Optimization (Pro)', 'screenshare-with-screenleap-integration' ); ?>
					</th>
					<td>
						<fieldset>
							<label>
								<select disabled >
									<option value="DEFAULT"><?php _e( 'Higher frame rate when screen changing and higher quality when screen not changing (default)', 'screenshare-with-screenleap-integration' ); ?></option>
								</select>
								<span class="d-block"><?php _e( 'Customize the screen share behavior to optimize it for your intended usage.', 'screenshare-with-screenleap-integration' ); ?></span>
							</label>
						</fieldset>
					</td>
				</tr>
			</tbody>
		</table>
		<?php
	}

	/**
	 * Screen Share Viewer Configuration Callback.
	 *
	 * @param \WP_Post $post Post Object.
	 * @return void
	 */
	public function screen_share_viewer_conf_callback( $post ) {
		$meeting_viewer_configuration = $this->get_meeting_viewer_conf( $post->ID );
		?>
		<table class="form-table" role="presentation">
			<tbody>
				<!-- view_method -->
				<tr>
					<th scope="row">
						<?php _e( 'View Type', 'screenshare-with-screenleap-integration' ); ?>
					</th>
					<td>
						<fieldset>
							<label for="<?php echo esc_attr( $this->meeting_meta_viewer . '-view-method' ); ?>">
								<select class="meeting-viewer-conf-input view-method-input" name="<?php echo esc_attr( $this->meeting_meta_viewer . '[view_method]' ); ?>" id="<?php echo esc_attr( $this->meeting_meta_viewer . '-view-method' ); ?>" data-key="view_method">
									<option <?php selected( esc_attr( $meeting_viewer_configuration['view_method'] ), 'redirect' ); ?> value="redirect"><?php _e( 'Screenleap', 'screenshare-with-screenleap-integration' ); ?></option>
									<option class="view-method-iframe" <?php selected( esc_attr( $meeting_viewer_configuration['view_method'] ), 'iframe' ); ?> value="iframe"><?php _e( 'Iframe', 'screenshare-with-screenleap-integration' ); ?></option>
								</select>
								<span><?php _e( 'redirect to the view page on screenleap site or display the screen share in an iframe on the meeting page', 'screenshare-with-screenleap-integration' ); ?></span>
								<?php if ( is_ssl() ) : ?>
								<p class="text-danger iframe-ssl-required-notice d-none"><?php _e( 'The iframe won\'t work if', 'screenshare-with-screenleap-integration' ); ?> <strong><?php _e( 'Using SSL', 'screenshare-with-screenleap-integration' ); ?></strong> <?php _e( 'option above is not checked', 'screenshare-with-screenleap-integration' ); ?></p>
								<?php endif; ?>
							</label>
						</fieldset>
					</td>
				</tr>
				<!-- showStop -->
				<tr>
					<th scope="row">
						<?php _e( 'Show stop button', 'screenshare-with-screenleap-integration' ); ?>
					</th>
					<td>
						<fieldset>
							<label for="<?php echo esc_attr( $this->meeting_meta_viewer . '-show-stop' ); ?>">
								<input class="meeting-viewer-conf-input" type="checkbox" name="<?php echo esc_attr( $this->meeting_meta_viewer . '[showStop]' ); ?>" id="<?php echo esc_attr( $this->meeting_meta_viewer . '-show-stop' ); ?>" value="" <?php checked( esc_attr( $meeting_viewer_configuration['showStop'] ) ); ?> data-key="showStop" >
								<?php _e( '	If this parameter is set to true, a bar will be displayed at the top of the viewer screen with a stop button.', 'screenshare-with-screenleap-integration' ); ?>
							</label>
						</fieldset>
					</td>
				</tr>
				<!-- showResize -->
				<tr class="bg-light text-muted">
					<th class="text-muted" scope="row">
						<?php _e( 'Show resize menu (Pro)', 'screenshare-with-screenleap-integration' ); ?>
					</th>
					<td>
						<fieldset>
							<label>
								<input disabled class="meeting-viewer-conf-input" type="checkbox">
								<?php _e( 'If this parameter is set to true, a bar will be displayed at the top of the viewer with a drop-down menu. The user can use this menu to toggle the view between "Fit to window" and "Actual screen size"', 'screenshare-with-screenleap-integration' ); ?>
							</label>
						</fieldset>
					</td>
				</tr>
				<!-- redirectOnError -->
				<tr class="bg-light text-muted">
					<th class="text-muted" scope="row" >
						<?php _e( 'Redirect on error (Pro)', 'screenshare-with-screenleap-integration' ); ?>
					</th>
					<td>
						<input disabled class="meeting-viewer-conf-input regular-text" type="url">
						<p class="description" ><?php _e( '	A URL to which the user should be redirected if there is an error. If omitted, an error message is displayed.', 'screenshare-with-screenleap-integration' ); ?></p>
					</td>
				</tr>
				<!-- redirectOnEnd -->
				<tr class="bg-light text-muted">
					<th class="text-muted" scope="row" >
						<?php _e( 'Redirect on end (Pro)', 'screenshare-with-screenleap-integration' ); ?>
					</th>
					<td>
					<input disabled class="meeting-viewer-conf-input regular-text" type="url">
						<p class="description" ><?php _e( 'A URL to which the user should be redirected when the screen share ends. If omitted, a message is displayed to inform the user that the screen share has ended.', 'screenshare-with-screenleap-integration' ); ?></p>
					</td>
				</tr>
			</tbody>
		</table>
		<?php
	}

	/**
	 * Screen share Actions Callback.
	 *
	 * @param \WP_Post $post Post Object.
	 * @return void
	 */
	public function screen_share_actions_callback( $post ) {
		if ( 'publish' === $post->post_status && $this->settings->is_developer_cred_ready() ) :
			?>
		<div class="conf-changes-detected d-none">
			<strong><?php _e( 'changes were made in the meeting configuration, please update the post to save the configuration before starting the screenshare!' ); ?></strong>
		</div>
		<div class="actions my-2 overflow-hidden">
			<div class="main-controls my-2 float-left">
				<button id="<?php echo esc_attr( $this->settings->plugin_info['name'] . '-start-screenshare' ); ?>" class="btn btn-primary <?php echo esc_attr( $this->settings->plugin_info['name'] . '-screenshare-request' ); ?>"><?php _e( 'Start Screenshare', 'screenshare-with-screenleap-integration' ); ?></button>
				<button class="btn btn-success <?php echo esc_attr( $this->settings->plugin_info['name'] . '-resume-screenshare' ); ?>"><?php _e( 'resume Screenshare', 'screenshare-with-screenleap-integration' ); ?></button>
				<button class="btn btn-warning <?php echo esc_attr( $this->settings->plugin_info['name'] . '-pause-screenshare' ); ?>"><?php _e( 'Pause Screenshare', 'screenshare-with-screenleap-integration' ); ?></button>
				<button class="btn btn-danger <?php echo esc_attr( $this->settings->plugin_info['name'] . '-stop-screenshare' ); ?>"><?php _e( 'Stop Screenshare', 'screenshare-with-screenleap-integration' ); ?></button>
			</div>
		</div>

		<div class="<?php echo esc_attr( $this->settings->plugin_info['name'] . '-screenshare-preloader-container' ); ?> position-fixed" >
			<div class="preload-wrapper">
				<div class="preload-msg">
					<h2 class="msgs-holder"></h2>
				</div>
				<img class="preload-icon" src="<?php echo esc_url( admin_url( 'images/spinner-2x.gif' ) ); ?>" alt="wp-preloader">
			</div>
			<div class="overlay"></div>
		</div>
			<?php
		else :
			?>
		<p><?php _e( 'Meeting not published yet!', 'screenshare-with-screenleap-integration' ); ?></p>
			<?php
		endif;

		if ( ! $this->settings->is_developer_cred_ready() ) :
			?>
			<div class="alert alert-danger" role="alert">
				<?php _e( 'API integration info are missing in ', 'screenshare-with-screenleap-integration' ); ?> <a href="<?php echo esc_url( $this->settings->settings_page_link ); ?>" ><?php _e( 'settings' ); ?></a> <?php _e( 'page' ); ?>
			</div>
			<?php
		endif;
	}

	/**
	 * Screen Share Details After Starting MetaBox.
	 *
	 * @param \WP_Post $post Post Object.
	 * @return void
	 */
	public function screen_share_details_callback( $post ) {
		$meeting_details = $this->get_meeting_details( $post->ID );
		$meeting_status  = $this->get_meeting_status( $post->ID );
		$meeting_status  = 'start';
		?>
		<div class="<?php echo esc_attr( $this->settings->plugin_info['name'] . '-screenshare-details-container' ); ?> <?php echo ( empty( $meeting_details ) ? 'empty-details' : '' ); ?>">
			<dl class="row pt-3">
				<dt class="col-sm-3 font-weight-bold my-1"><p><?php _e( 'Status', 'screenshare-with-screenleap-integration' ); ?></p></dt>
				<dd class="col-sm-9 font-weight-bold my-1">
					<span class="screenshare-status">
						<span class="status-icon mr-1 led <?php echo esc_attr( $this->meeting_status_mapping[ $meeting_status ]['icon'] ); ?>"></span>
						<span class="status-title"><?php echo esc_attr( $this->meeting_status_mapping[ $meeting_status ]['title'] ); ?></span>
					<?php if ( 'end' === $meeting_status ) : ?>
						<span><button type="button" class="btn-primary ml-3 reset-status btn" data-toggle="tooltip" data-placement="top" title="<?php _e( 'Reset the meeting status', 'screenshare-with-screenleap-integration' ); ?>"><?php _e( 'Reset', 'screenshare-with-screenleap-integration' ); ?></button></span>
					<?php endif; ?>
					</span>
				</dd>

				<dt class="col-sm-3 font-weight-bold my-1"><p><?php _e( 'Screenshare Code', 'screenshare-with-screenleap-integration' ); ?></p></dt>
				<dd class="col-sm-9 font-weight-bold my-1"><span class="screenshare-code"></span></dd>

				<dt class="col-sm-3 font-weight-bold my-1"><p><?php _e( 'General View URL', 'screenshare-with-screenleap-integration' ); ?></p></dt>
				<dd class="col-sm-9 font-weight-bold my-1"><span class="viewer-url"></span></dd>

				<dt class="col-sm-3 font-weight-bold my-1"><p class="viewers-counter"></p></dt>
				<dd class="col-sm-9 font-weight-bold my-1">
					<span class="viewers-count"></span>
				</dd>
			</dl>
		</div>

		<div class="<?php echo esc_attr( $this->settings->plugin_info['name'] . '-screen-share-instructions' ); ?>">
			<!-- Native installation Instrunctions -->
			<div id="nativeInstallationInstructions" class="modal screenleap-dialog modal fade" tabindex="-1" aria-hidden="true">
				<div class="modal-dialog modal-dialog-centered">
					<div class="modal-content">
						<div class="modal-body">
							<div class="install-native-instructions">
								<h4 style="margin-bottom:12px"><?php _e( 'Downloading app...', 'screenshare-with-screenleap-integration' ); ?></h4>
								<p><?php _e( 'Your download should start in seconds. If it doesn\'t', 'screenshare-with-screenleap-integration' ); ?>, <a class="<?php echo esc_attr( $this->settings->plugin_info['name'] . '-restart-app-download' ); ?> underline" href="#" ><?php _e( 'restart the download', 'screenshare-with-screenleap-integration' ); ?></a></p>
								<hr>
								<span class="mac" style="display:none">
									<ol id="chromeMacInstructions">
										<li>
											<?php _e( 'Click on the', 'screenshare-with-screenleap-integration' ); ?><b>ScreenShare.dmg</b> <?php _e( 'file at the bottom of your browser window', 'screenshare-with-screenleap-integration' ); ?>.<br>
											<img src="<?php echo esc_url( $this->img_urls . 'mac_api_native_chrome_download_bar.png' ); ?>" width="403" height="46" style="margin-top:12px;margin-bottom:12px" alt=""/>
										</li>
										<li>
											<?php _e( 'Double-click the', 'screenshare-with-screenleap-integration' ); ?> <b>ScreenShare.app</b> <?php _e( 'icon' ); ?>.<br>
											<img src="<?php echo esc_url( $this->img_urls . 'mac_api_native_finder_window.png' ); ?>" width="403" height="141" style="margin-top:12px;margin-bottom:12px" alt=""/><br>
										</li>
										<li>
											<?php _e( 'Click' ); ?> <b><?php _e( 'Open' ); ?></b> <?php _e( 'when the dialog opens', 'screenshare-with-screenleap-integration' ); ?>.<br>
											<img src="<?php echo esc_url( $this->img_urls . 'mac_api_native_chrome_open_dialog.png' ); ?>" width="403" height="177" style="margin-top:12px" alt=""/>    </li>
											<li class="custom-protocol">
											<?php _e( 'After the app is installed', 'screenshare-with-screenleap-integration' ); ?>, <a class="<?php echo esc_attr( $this->settings->plugin_info['name'] . '-start-app' ); ?> bold underline" href="#" class=""><?php _e( 'click here', 'screenshare-with-screenleap-integration' ); ?></a> <?php _e( 'to open the dialog below', 'screenshare-with-screenleap-integration' ); ?>.<br>
											<img src="<?php echo esc_url( $this->img_urls . 'mac_native_chrome_custom_protocol_handler.png' ); ?>" style="margin-top:12px;margin-bottom:12px" alt=""/><br>
											<?php _e( 'Click' ); ?> <b><?php _e( 'Remember my choice', 'screenshare-with-screenleap-integration' ); ?></b> <?php_e( 'and click', 'screenshare-with-screenleap-integration' ); ?> <b><?php _e( 'Launch Application', 'screenshare-with-screenleap-integration' ); ?></b>.<br>
										</li>
									</ol>
								</span>
								<span class="win" style="display:none">
									<ol id="chromeWinInstructions" class="overflow-hidden">
										<li>
											<?php _e( 'Click on the screenleap.exe file that you just downloaded to run it.', 'screenshare-with-screenleap-integration' ); ?><br/>
											<img src="<?php echo esc_url( $this->img_urls . 'win_native_chrome_download_bar.png' ); ?>" width="549" height="42" style="margin-top:12px;margin-bottom:12px" alt=""/>
										</li>
										<li>
											<?php _e( 'Click <b>Run</b> when the security dialog opens.', 'screenshare-with-screenleap-integration' ); ?><br>
											<img src="<?php echo esc_url( $this->img_urls . 'win_native_security_dialog_screenshare.png' ); ?>" style="margin-top:12px" alt=""/>
										</li>
										<li class="custom-protocol">
											<?php _e( 'After the app is installed', 'screenshare-with-screenleap-integration' ); ?>, <a class="<?php echo esc_attr( $this->settings->plugin_info['name'] . '-start-app' ); ?>"  href="#" onclick="" class="bold underline"><?php _e( 'click here', 'screenshare-with-screenleap-integration' ); ?></a> <?php _e( 'to open the dialog below.', 'screenshare-with-screenleap-integration' ); ?><br>
											<img src="<?php echo esc_url( $this->img_urls . 'win_native_chrome_custom_protocol_handler_screenshare.png' ); ?>" style="margin-top:12px;margin-bottom:12px" alt=""/><br>
											<?php _e( 'Check' ); ?> <b><?php _e( 'Remember my choice', 'screenshare-with-screenleap-integration' ); ?></b> <?php _e( 'and click', 'screenshare-with-screenleap-integration' ); ?> <b><?php _e( 'Launch Application', 'screenshare-with-screenleap-integration' ); ?></b>.<br>
										</li>
									</ol>
								</span>
							</div>
						</div>
					</div>
				</div>
			</div>
			<!-- retry custom Protocol Handler Message -->
			<div id="retryCustomProtocolHandlerMessage" class="troubleshooting-message sheet screenleap-dialog modal fade" tabindex="-1" aria-hidden="true" >
				<div class="modal-dialog modal-dialog-centered">
					<div class="modal-content">
						<div class="modal-header">
							<h4 class="text-danger"><?php _e( 'We were not able to start your screen share', 'screenshare-with-screenleap-integration' ); ?>.</h4>
						</div>
						<div class="modal-body">
							<ol>
								<li><?php _e( 'If you haven\'t yet done so, please', 'screenshare-with-screenleap-integration' ); ?> <a href="#" class="<?php echo esc_attr( $this->settings->plugin_info['name'] . '-restart-app-download' ); ?> bold underline"><?php _e( 'download the app', 'screenshare-with-screenleap-integration' ); ?></a> <?php _e( 'now', 'screenshare-with-screenleap-integration' ); ?>.</li>
								<li class="msie" style="display:none">
									<?php _e( 'Click', 'screenshare-with-screenleap-integration' ); ?> <b><?php _e( 'Run', 'screenshare-with-screenleap-integration' ); ?></b> <?php _e( 'when you see the prompt below', 'screenshare-with-screenleap-integration' ); ?>.<br>
									<span class="not-msie8"><img src="<?php echo esc_url( $this->img_urls . 'win_native_msie_custom_protocol_handler_screenshare_run.png' ); ?>" width="608" height="50" style="margin-top:12px;margin-bottom:12px" alt=""/><br></span>
									<span class="msie8"><img src="<?php echo esc_url( $this->img_urls . 'win_native_msie8_custom_protocol_handler_screenshare_run.png' ); ?>" width="404" height="267" style="margin-top:12px;margin-bottom:12px" alt=""/><br></span>
								</li>
								<li class="msie8" style="display:none">
									<?php _e( 'Click', 'screenshare-with-screenleap-integration' ); ?> <b><?php _e( 'Run', 'screenshare-with-screenleap-integration' ); ?></b> <?php _e( 'again when you see the prompt below', 'screenshare-with-screenleap-integration' ); ?>.<br>
									<img src="<?php echo esc_url( $this->img_urls . 'win_native_msie8_security_warning_screenshare.png' ); ?>" width="464" height="211" style="margin-top:12px;margin-bottom:12px" alt=""/>
								</li>
								<li class="not-msie" style="display:none"><?php _e( 'Please be sure to click on the downloaded app to install it', 'screenshare-with-screenleap-integration' ); ?>.</li>
								<li class="safari" style="display:none"><?php _e( 'After the app is installed', 'screenshare-with-screenleap-integration' ); ?>, <a href="#" class="<?php echo esc_attr( $this->settings->plugin_info['name'] . '-start-app' ); ?> bold underline"><?php _e( 'click here', 'screenshare-with-screenleap-integration' ); ?></a> <?php _e( 'to start the screen share', 'screenshare-with-screenleap-integration' ); ?>.</li>
								<li class="not-safari" style="display:none"><?php _e( 'After the app is installed', 'screenshare-with-screenleap-integration' ); ?>, <a href="#" class="<?php echo esc_attr( $this->settings->plugin_info['name'] . '-start-app' ); ?> bold underline"><?php _e( 'click here', 'screenshare-with-screenleap-integration' ); ?></a> <?php _e( 'to open the dialog below', 'screenshare-with-screenleap-integration' ); ?>.</li>
								<li class="mac" style="list-style-type:none;display:none"><img class="mac-custom-protocol-handler-image" style="margin-top:12px;margin-bottom:12px" alt=""/></li>
								<li class="win" style="list-style-type:none;display:none"><img class="win-custom-protocol-handler-image" style="margin-top:12px;margin-bottom:12px" alt=""/></li>
								<li class="firefox" style="display:none"><?php _e( 'Select the', 'screenshare-with-screenleap-integration' ); ?> <b><?php _e( 'Screenleap Start Application', 'screenshare-with-screenleap-integration' ); ?></b>, <?php _e( 'check', 'screenshare-with-screenleap-integration' ); ?> <b><?php _e( 'Remember my choice', 'screenshare-with-screenleap-integration' ); ?></b>, <?php _e( 'and click', 'screenshare-with-screenleap-integration' ); ?> <b><?php _e( 'OK', 'screenshare-with-screenleap-integration' ); ?></b>.</li>
								<li class="msie" style="list-style-type:none;display:none"><?php _e( 'Uncheck', 'screenshare-with-screenleap-integration' ); ?> <b><?php _e( 'Always ask before opening', 'screenshare-with-screenleap-integration' ); ?></b> <?php _e( 'and click', 'screenshare-with-screenleap-integration' ); ?> <b><?php _e( 'Allow', 'screenshare-with-screenleap-integration' ); ?></b>.</li>
								<li class="chrome" style="list-style-type:none;display:none"><?php _e( 'Check', 'screenshare-with-screenleap-integration' ); ?> <b><?php _e( 'Remember my choice', 'screenshare-with-screenleap-integration' ); ?></b> <?php _e( 'and click', 'screenshare-with-screenleap-integration' ); ?> <b><span class="chrome" style="display:none"><?php _e( 'Launch Application', 'screenshare-with-screenleap-integration' ); ?></span><span class="safari" style="display:none"><?php _e( 'OK', 'screenshare-with-screenleap-integration' ); ?></span></b>.</li>
							</ol>
							<br>
						</div>
					</div>
				</div>
			</div>
			<!-- Native Starting -->
			<div id="nativeStarting" class="sheet screenleap-dialog modal fade" tabindex="-1" aria-hidden="true" >
				<div class="modal-dialog modal-dialog-centered">
					<div class="modal-content">
						<div class="modal-body">
							<h2 class="first"><?php _e( 'Starting...', 'screenshare-with-screenleap-integration' ); ?><img src="<?php echo esc_url( $this->img_urls . 'indicator.gif' ); ?>" class="indicator" alt=""/></h2>
							<hr>
							<p><?php _e( 'This can sometimes take up to 10 seconds. Thank you for your patience.', 'screenshare-with-screenleap-integration' ); ?></p>
						</div>
					</div>
				</div>
			</div>
			<!-- App connection Failure -->
			<div id="appConnectionFailure" class="sheet screenleap-dialog modal fade" tabindex="-1" aria-hidden="true">
				<div class="modal-dialog modal-dialog-centered">
					<div class="modal-content">
						<div class="modal-body">
							<h4 class="text-danger"><?php _e( 'Presenter app connection failure', 'screenshare-with-screenleap-integration' ); ?></h4>
						</div>
					</div>
				</div>
			</div>
			<!-- Install Extnesion Instructions -->
			<div id="installExtensionInstructions" class="sheet screenleap-dialog modal fade" tabindex="-1" aria-hidden="true">
				<div class="modal-dialog modal-dialog-centered">
					<div class="modal-content">
						<div class="modal-body text-center">
							<h2 class="font-weight-bolder mb-3"><?php _e( 'You need to install the Extension', 'screenshare-with-screenleap-integration' ); ?></h2>
							<a class="btn btn-primary" target="_blank" href="https://chrome.google.com/webstore/detail/screenshare/ilikegbphpdmfjbmipgclmbhloghljag"><?php _e( 'Install', 'screenshare-with-screenleap-integration' ); ?></a>
						</div>
					</div>
				</div>
			</div>
			<!-- Enable Extension Instructions -->
			<div id="enableExtensionInstructions" class="sheet screenleap-dialog modal fade" tabindex="-1" aria-hidden="true">
				<div class="modal-dialog modal-dialog-centered">
					<div class="modal-content">
						<div class="modal-body">
							<h4 class="text-danger"><?php _e( 'The ScreenShare extension is installed but has been disabled.', 'screenshare-with-screenleap-integration' ); ?></h4>
							<hr>
							<p style="margin-bottom:0"><?php _e( 'To re-enable the extension:', 'screenshare-with-screenleap-integration' ); ?></p>
							<ol>
								<li><?php _e( 'Click the Options icon (icon with three lines) on the right side of the browser toolbar.', 'screenshare-with-screenleap-integration' ); ?></li>
								<li><?php _e( 'Select', 'screenshare-with-screenleap-integration' ); ?> <b><?php _e( 'Settings', 'screenshare-with-screenleap-integration' ); ?></b>.</li>
								<li><?php _e( 'Click on the <b>Extensions', 'screenshare-with-screenleap-integration' ); ?></b> <?php _e( 'option on the left side of the page.', 'screenshare-with-screenleap-integration' ); ?></li>
								<li><?php _e( 'Check the', 'screenshare-with-screenleap-integration' ); ?> <b><?php _e( 'Enabled', 'screenshare-with-screenleap-integration' ); ?></b> <?php _e( 'checkbox for the', 'screenshare-with-screenleap-integration' ); ?> <b><?php _e( 'ScreenShare', 'screenshare-with-screenleap-integration' ); ?></b> <?php _e( 'extension', 'screenshare-with-screenleap-integration' ); ?>.</li>
							</ol>
						</div>
					</div>
				</div>

			</div>

			<!-- Toasts -->
			<div class="position-fixed fixed-top p-3 d-flex justify-content-center mt-4" style="z-index: 1000;">
				<div class="toast screenshare-toast hide" role="alert" aria-live="assertive" aria-atomic="true" data-delay="1500">
					<div class="toast-header d-flex align-content-center overflow-hidden justify-content-between">
						<strong class="toast-text"></strong>
						<button type="button" class="ml-2 mb-1 close" data-dismiss="toast" aria-label="Close">
							<span aria-hidden="true">&times;</span>
						</button>
					</div>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Screen Share Logged in Viewers Metabox.
	 *
	 * @param \WP_Post $post Post Object.
	 * @return void
	 */
	public function screen_share_viewers_callback( $post ) {
	}

	/**
	 * Get Meeting Settings.
	 *
	 * @param int $meeting_id Meeting Post ID.
	 * @return array|false
	 */
	public function get_meeting_settings( $meeting_id ) {
		$settings = get_post_meta( $meeting_id, $this->meeting_meta_settings, true );
		return array_merge( $this->default_meeting_settings, (array) $settings );
	}

	/**
	 * Update Meeting Settings Metabox.
	 *
	 * @param int   $meeting_id Meeting Post ID.
	 * @param array $settings Meeting Settings Fields.
	 * @return void
	 */
	public function update_meeting_settings( $meeting_id, $settings ) {
		$meeting_post = $this->check_is_meeting( $meeting_id );
		if ( $meeting_post ) {
			update_post_meta( $meeting_id, $this->meeting_meta_settings, $settings );
		}
	}

	/**
	 * Check if the meeting screen share still active from the server.
	 *
	 * @param array $screen_share_data ScreenShareData.
	 * @return boolean
	 */
	public function get_meeting_status_from_server( $screen_share_data ) {
		$meeting_info = $this->get_meeting_info( $screen_share_data['screenShareCode'] );
		if ( is_array( $meeting_info ) && ! empty( $meeting_info['isActive'] ) && true === $meeting_info['isActive'] ) {
			return true;
		} else {
			return false;
		}
	}

	/**
	 * Get meeting status.
	 *
	 * @param int $meeting_id Meeting Post ID.
	 * @return string [ start - active - end ]
	 */
	public function get_meeting_status( $meeting_id ) {
		$meeting_type_details = $this->get_meeting_type( $meeting_id );
		return $meeting_type_details['status'];
	}

	/**
	 * Update meeting status.
	 *
	 * @param int    $meeting_id Meeting Post ID.
	 * @param string $new_status The new meeting status.
	 * @return void
	 */
	public function update_meeting_status( $meeting_id, $new_status ) {
		$meeting_type           = $this->get_meeting_type( $meeting_id );
		$meeting_type['status'] = $new_status;
		$this->update_meeting_type( $meeting_id, $meeting_type );
	}

	/**
	 * Get Meeting  Viewer Configuration.
	 *
	 * @param int $meeting_id Meeting Post ID.
	 * @return array
	 */
	public function get_meeting_viewer_conf( $meeting_id ) {
		$viewer_conf = get_post_meta( $meeting_id, $this->meeting_meta_viewer, true );
		return array_merge( $this->default_meeting_viewer_configuration, (array) $viewer_conf );
	}

	/**
	 * Update Meeting Viewer Configuration.
	 *
	 * @param int   $meeting_id  Meeting Post ID.
	 * @param array $viewer_conf  Viewer Configuration Array.
	 * @return void
	 */
	public function update_meeting_viewer_conf( $meeting_id, $viewer_conf ) {
		$meeting_post = $this->check_is_meeting( $meeting_id );
		if ( $meeting_post ) {
			update_post_meta( $meeting_id, $this->meeting_meta_viewer, $viewer_conf );
		}
	}

	/**
	 * Get Meeting Type.
	 *
	 * @param int $meeting_id Meeting Post ID.
	 * @return array
	 */
	public function get_meeting_type( $meeting_id ) {
		$meeting_type = get_post_meta( $meeting_id, $this->meeting_meta_type, true );
		return array_merge( $this->default_meeting_type, (array) $meeting_type );
	}

	/**
	 * Update meeting Type.
	 *
	 * @param int $meeting_id Meeting Post ID.
	 * @param array $meeting_type_arr Meeting Type Array.
	 * @return void
	 */
	public function update_meeting_type( $meeting_id, $meeting_type_arr ) {
		$meeting_post = $this->check_is_meeting( $meeting_id );
		if ( $meeting_post ) {
			update_post_meta( $meeting_id, $this->meeting_meta_type, $meeting_type_arr );
		}
	}

	/**
	 * Get Meeting Configuration.
	 *
	 * @param id $meeting_id Meeting Post ID.
	 * @return array|false
	 */
	public function get_meeting_conf( $meeting_id ) {
		$conf = get_post_meta( $meeting_id, $this->meeting_meta_conf, true );
		return array_merge( $this->default_meeting_configuration, (array) $conf );
	}

	/**
	 * Update Meeting Configuration.
	 *
	 * @param int   $meeting_id Meeting Post ID.
	 * @param array $conf
	 *
	 * @return void
	 */
	public function update_meeting_configuration( $meeting_id, $conf ) {
		$meeting_post = $this->check_is_meeting( $meeting_id );
		if ( $meeting_post ) {
			update_post_meta( $meeting_id, $this->meeting_meta_conf, $conf );
		}
	}

	/**
	 * Get Meeting details.
	 *
	 * @param int $meeting_id Meeting Post ID.
	 * @return array|false
	 */
	public function get_meeting_details( $meeting_id ) {
		$details = get_post_meta( $meeting_id, $this->meeting_meta_details, true );
		return array_merge( $this->default_meeting_details, (array) $details );
	}

	/**
	 * Get the Meeting Viewer URL.
	 *
	 * @return string|false
	 */
	public function get_meeting_detail( $meeting_id, $data ) {
		$details = $this->get_meeting_details( $meeting_id );
		if ( $details && is_array( $details ) && ! empty( $details[ $data ] ) ) {
			return $details[ $data ];
		} else {
			return false;
		}
	}

	/**
	 * Get Meeting Screenshare Info.
	 *
	 * @param string $meeting_code Screen Share Code.
	 * @return array
	 */
	public function get_meeting_info( $meeting_code ) {
		return $this->api->get_screen_share_info( $meeting_code );
	}

	/**
	 * Set Meeting Details Meta fields.
	 *
	 * @param array $details Screen share Details.
	 * @param int   $meeting_id Meeting Post ID.
	 *
	 * @return \WP_Post|false
	 */
	public function update_meeting_details( $details, $meeting_id ) {
		$meeting_post = $this->check_is_meeting( $meeting_id );
		if ( $meeting_post ) {
			update_post_meta( $meeting_id, $this->meeting_meta_details, $details );
		} else {
			return false;
		}

		return $meeting_post;
	}

	/**
	 * Check the Meeting ID if valid.
	 *
	 * @param int $meeting_id Meeting Post ID.
	 * @return bool
	 */
	public function check_is_meeting( $meeting_id ) {
		$meeting_post_object = get_post( $meeting_id );
		if ( ! is_null( $meeting_post_object ) && is_object( $meeting_post_object ) && ( $this->post_type_name === $meeting_post_object->post_type ) ) {
			return $meeting_post_object;
		} else {
			return false;
		}
	}

	/**
	 * Get Reminder Emails for a meeting.
	 *
	 * @param int $meeting_id Meeting Post ID.
	 * @return array
	 */
	public function get_reminder_emails( $meeting_id ) {
		$emails = get_post_meta( $meeting_id, $this->meeting_meta_reminder_emails, true );
		if ( ! $emails ) {
			return array();
		}
		return $emails;
	}

	/**
	 * Save Meeting meta fields.
	 *
	 * @param int    $post_id   Meeting ID.
	 * @param object $post      Meeting Post Object.
	 * @param bool   $update    New Meeting or Update.
	 * @return void
	 */
	public function save_meeting_meta_fields( $post_id, $post, $update ) {
		if ( ! empty( $_POST['post_ID'] ) && ! empty( $_POST[ $this->settings->plugin_info['name'] . '-meeting-edit-nonce' ] ) && check_admin_referer( $this->settings->plugin_info['name'] . '-meeting-edit-nonce', $this->settings->plugin_info['name'] . '-meeting-edit-nonce' ) ) {
			$meeting_id       = absint( sanitize_text_field( wp_unslash( $_POST['post_ID'] ) ) );
			$meeting_settings = $this->default_meeting_settings;
			$meeting_conf     = $this->default_meeting_configuration;
			$meeting_type     = $this->default_meeting_type;

										// === Meeting Settings === //.
			if ( ! isset( $_POST[ $this->meeting_meta_settings . '-show-remaining-countdown' ] ) ) {
				$meeting_settings['show_remaining_countdown'] = false;
			}

			if ( isset( $_POST[ $this->meeting_meta_settings . '-requires-login' ] ) ) {
				$meeting_settings['requires_login'] = true;
			}

			// Agenda.
			if ( ! empty( $_POST[ $this->meeting_meta_settings . '-agenda' ] ) ) {
				$meeting_settings['agenda'] = wp_unslash( sanitize_post_field( 'post_content', $_POST[ $this->meeting_meta_settings . '-agenda' ], 0, 'db' ) );
			}

			// Presenter Name.
			if ( ! empty( $_POST[ $this->meeting_meta_settings . '-presenter-name' ] ) ) {
				$meeting_settings['presenter_name'] = sanitize_text_field( wp_unslash( $_POST[ $this->meeting_meta_settings . '-presenter-name' ] ) );
			}

			// Meeting Date.
			if ( ! empty( $_POST[ $this->meeting_meta_settings . '-date' ] ) ) {
				$meeting_settings['date'] = sanitize_text_field( wp_unslash( $_POST[ $this->meeting_meta_settings . '-date' ] ) );
			}

			// Meeting Time.
			if ( ! empty( $_POST[ $this->meeting_meta_settings . '-time' ] ) ) {
				$meeting_settings['time'] = sanitize_text_field( wp_unslash( $_POST[ $this->meeting_meta_settings . '-time' ] ) );
			}

			// Meeting Timezone.
			if ( ! empty( $_POST[ $this->meeting_meta_settings . '-timezone' ] ) ) {
				$meeting_settings['timezone'] = sanitize_text_field( wp_unslash( $_POST[ $this->meeting_meta_settings . '-timezone' ] ) );
			}

			// Meeting Duration.
			if ( ! empty( $_POST[ $this->meeting_meta_settings . '-duration' ] ) ) {
				$meeting_settings['duration'] = sanitize_text_field( wp_unslash( $_POST[ $this->meeting_meta_settings . '-duration' ] ) );
			}

			$this->update_meeting_settings( $post_id, $meeting_settings );

										// === Meeting Type === //.
			if ( ! empty( $_POST[ $this->meeting_meta_type ] ) && is_array( $_POST[ $this->meeting_meta_type ] ) ) {
				if ( ! empty( $_POST[ $this->meeting_meta_type ]['type'] ) ) {
					$meeting_type['type'] = sanitize_text_field( wp_unslash( $_POST[ $this->meeting_meta_type ]['type'] ) );
				}

				if ( 'api' === $meeting_type['type'] ) {
					$meeting_type['status'] = 'start';
				} elseif ( ! empty( $_POST[ $this->meeting_meta_type ]['status'] ) ) {
					$meeting_type['status'] = sanitize_text_field( wp_unslash( $_POST[ $this->meeting_meta_type ]['status'] ) );
				}

				if ( ! empty( $_POST[ $this->meeting_meta_type ]['scheduled_meeting_link'] ) ) {
					$meeting_type['scheduled_meeting_link'] = sanitize_text_field( wp_unslash( $_POST[ $this->meeting_meta_type ]['scheduled_meeting_link'] ) );
					$meeting_type['scheduled_meeting_link'] = remove_query_arg( 'preview', $meeting_type['scheduled_meeting_link'] );
				}
			}
			$this->update_meeting_type( $post_id, $meeting_type );

										// === Meeting Configuration === //.
			if ( ! empty( $_POST[ $this->meeting_meta_conf ] ) ) {
				$meeting_conf = array_map( 'sanitize_text_field', wp_unslash( $_POST[ $this->meeting_meta_conf ] ) );
				$meeting_conf = $this->prepare_meeting_conf_before_saving( $meeting_conf, 'presenter' );
				$this->update_meeting_configuration( $meeting_id, $meeting_conf );
			}

										// === Meeting Viewer Configuration === //.
			if ( ! empty( $_POST[ $this->meeting_meta_viewer ] ) ) {
				$meeting_viewer_conf = array_map( 'sanitize_text_field', wp_unslash( $_POST[ $this->meeting_meta_viewer ] ) );
				$meeting_viewer_conf = $this->prepare_meeting_conf_before_saving( $meeting_viewer_conf, 'viewer' );
				$this->update_meeting_viewer_conf( $meeting_id, $meeting_viewer_conf );
			}
		}
	}

	/**
	 * Prepare Meeting Configuration before save the post.
	 *
	 * @param array  $conf Meeting Configuration.
	 * @param string $type Settings Type.
	 * @return array
	 */
	public function prepare_meeting_conf_before_saving( $conf, $type = 'presenter' ) {
		$default_conf = $this->default_meeting_configuration;
		if ( 'viewer' === $type ) {
			$default_conf = $this->default_meeting_viewer_configuration;
		}
		// Ajax Save Call.
		if ( wp_doing_ajax() ) {
			foreach ( $default_conf as $key => $value ) {
				if ( ! empty( $conf[ $key ] ) ) {
					if ( 'true' === $conf[ $key ] ) {
						$conf[ $key ] = true;
					} elseif ( 'false' === $conf[ $key ] ) {
						$conf[ $key ] = false;
					}
				} else {
					$conf[ $key ] = $default_conf[ $key ];
				}
			}
			// Save Post Call.
		} else {
			foreach ( $default_conf as $key => $value ) {
				if ( in_array( $default_conf[ $key ], array( true, false ), true ) ) {
					if ( isset( $conf[ $key ] ) ) {
						$conf[ $key ] = true;
					} else {
						$conf[ $key ] = false;
					}
				}
			}
		}

		return $conf;
	}

	/**
	 * Get Meeting linked WooCommerce Product.
	 *
	 * @param int $meeting_id Meeting Post ID.
	 * @return int
	 */
	public function get_meeting_product( $meeting_id ) {
		$meeting_settings = $this->get_meeting_settings( $meeting_id );
		return $meeting_settings['product_linking'];
	}

	/**
	 * Redirect to Cart
	 *
	 * @param string      $url Add to Cart URL.
	 * @param \WC_Product $product_object The Product Object.
	 * @return string
	 */
	public function force_redirect_to_checkout( $url, $product_object ) {
		if ( ! empty( $_GET[ $this->settings->plugin_info['name'] . '-product-type' ] ) && ( 'meeting_product' === $_GET[ $this->settings->plugin_info['name'] . '-product-type' ] ) ) {
			$url = wc_get_checkout_url();
		}
		return $url;
	}

	/**
	 * Get Countries Dropdown.
	 *
	 * @param string $selected_value  Selected Country Code.
	 * @return void
	 */
	private function countries_dropdown( $selected_value ) {
		$selected_value = sanitize_text_field( $selected_value );
		?>
		<select class="meeting-conf-input" name="<?php echo esc_attr( $this->meeting_meta_conf . '[presenterCountryCode]' ); ?>" id="<?php echo esc_attr( $this->meeting_meta_conf . '-country-code' ); ?>" data-key="presenterCountryCode">
		<option value="" <?php selected( $selected_value, 'screenshare-with-screenleap-integration' ); ?>><?php _e( '&mdash; Select &mdash;' ); ?></option>
		<?php
		foreach ( $this->countries as $country_code => $country_name ) :
			?>
			<option value="<?php echo esc_attr( $country_code ); ?>" <?php selected( $selected_value, $country_code ); ?>><?php echo esc_html( $country_name ); ?></option>
			<?php
		endforeach;
		?>
		</select>
		<?php
	}
}
