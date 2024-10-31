<?php

namespace GPLSCore\GPLS_PLUGIN_SLI;

use GPLSCore\GPLS_PLUGIN_SLI\Settings;

defined( 'ABSPATH' ) || exit();

/**
 * Screenleap API Class.
 *
 * Handle the connection with the screeleap API.
 */
class API {

	/**
	 * Active Tab.
	 *
	 * @var string
	 */
	public $active_tab;

	/**
	 * Settings Class Object
	 *
	 * @var object
	 */
	public $settings;

	/**
	 * Screenleap API URL.
	 *
	 * @var string
	 */
	private $api_url;

	/**
	 * ScreenShare API URLS.
	 *
	 * @var array
	 */
	private $api_routes;

	/**
	 * Error Codes.
	 *
	 * @var array
	 */
	private $error_codes;

	/**
	 * Class Constructor.
	 *
	 * @param Settings $settings Settings Object.
	 */
	public function __construct( Settings $settings ) {
		$this->settings = $settings;
		$this->setup();
	}

	/**
	 * Setup API Routes - URLs - Errors.
	 *
	 * @return void
	 */
	private function setup() {

		$this->api_url = 'https://api.screenleap.com/v2';

		$this->api_routes = array(
			'screenshare_request'   => array(
				'method' => 'POST',
				'url'    => $this->api_url . '/screen-shares',
			),
			'screenshare_stop'      => array(
				'method' => 'POST',
				'url'    => $this->api_url . '/screen-shares/{screenShareCode}/stop',
			),
			'screenshare_info'      => array(
				'method' => 'GET',
				'url'    => $this->api_url . '/screen-shares/{screenShareCode}',
			),
			'recent_screenshares'   => array(
				'method' => 'GET',
				'url'    => $this->api_url . '/recent-screen-shares',
			),
			'reterive_screenshares' => array(
				'method' => 'GET',
				'url'    => $this->api_url . '/screen-shares',
			),
		);

		$this->error_codes = array(
			400 => 'invalid parameter',
			401 => 'missing account id or authtoken',
			403 => 'exceeded the free hours',
			404 => 'screenshare has already ended or not found',
			500 => 'Server error in Screenleap side',
		);
	}

	/**
	 * Send a screenshare request.
	 *
	 * @param array $params  ScreenShare Request Parameters.
	 *
	 * @return array|WP_Error
	 *  @type   [screenShareCode]
	 *          [presenterParams]
	 *          [viewerUrl]
	 *          [origin]            => API
	 *          [useV2]             => 1/0
	 *          [participantId]
	 *          [token]
	 *          [hostname]
	 *          [accountId]
	 */
	public function screenshare_request( $params ) {
		return $this->_screenleap_api_request( $this->api_routes['screenshare_request']['url'], $this->api_routes['screenshare_request']['method'], $params );
	}

	/**
	 * Send a Request to screenshare API.
	 *
	 * @param string $url    Route API.
	 * @param string $method Endpoint.
	 *
	 * @return array|WP_Error
	 */
	private function _screenleap_api_request( $url, $method, $params = array(), $requires_auth = true, $replay = false ) {
		if ( empty( $this->settings->settings_arr['account_id'] ) || ( $requires_auth && empty( $this->settings->settings_arr['auth_token'] ) ) ) {
			return new \WP_Error(
				$this->settings->plugin_info['name'] . '-missing-credentials-error',
				__( 'Account ID or Authentication Token is missing, please <a href="' . esc_url( $this->settings->settings_page_link ) . '" >add</a> them first', 'gpls-sli-wp-screenleap-integration' )
			);
		}

		$headers = array();
		$body    = array();

		foreach ( $params as $key => $value ) {
			if ( $value === true ) {
				$params[ $key ] = 'true';
			} elseif ( $value === false ) {
				$params[ $key ] = 'false';
			}
		}

		$params['presenterAppType'] = 'NATIVE';
		$params['accountid']        = $this->settings->settings_arr['account_id'];
		$url                        = add_query_arg(
			$params,
			$url
		);

		// Include Auth-Token.
		if ( $requires_auth ) {
			$headers['authtoken'] = $this->settings->settings_arr['auth_token'];
		}

		// // Add Ip Address to avoid timeout.
		// if ( isset( $_SERVER['REMOTE_ADDR'] ) ) {
		// $body['presenterIpAddress'] = filter_var( wp_unslash( $_SERVER['REMOTE_ADDR'] ), FILTER_VALIDATE_IP );
		// }

		$result = \wp_remote_request(
			$url,
			array(
				'method'  => $method,
				'headers' => $headers,
				'body'    => $body,
				'timeout' => 2000,
			)
		);

		$response_header  = wp_remote_retrieve_headers( $result );
		$response_code    = wp_remote_retrieve_response_code( $result );
		$response_body    = wp_remote_retrieve_body( $result );
		$response_message = wp_remote_retrieve_response_message( $result );

		if ( empty( $response_code ) && false === $replay ) {
			return $this->_screenleap_api_request( $url, $method, $params, $requires_auth, true );
		} else {
			if ( 200 === $response_code ) {
				return json_decode( $response_body, true );
			} else {
				$response_body = json_decode( $response_body, true );
				return new \WP_Error(
					$this->settings->plugin_info['name'] . '-api-response-error',
					sprintf(
						/* translators: %s: response error message. */
						__( 'An error occured: %s', 'gpls-sli-wp-screenleap-integration' ),
						( ! empty( $response_body['errorMessage'] ) ? $response_body['errorMessage'] : $response_message )
					)
				);
			}
		}

		return new \WP_Error(
			$this->settings->plugin_info['name'] . '-api-response-error',
			__( 'Screenleap server timeout', 'gpls-sli-wp-screenleap-integration' )
		);
	}

	/**
	 * Stop Active Screenshare.
	 *
	 * @param string $screenshare_code Screenshare Code.
	 * @return array
	 *      200 - OK            The message was sent to the screen share successfully. When the presenter app closes, the screenleap.onScreenShareEnd JavaScript will be triggered, just as it is when a user clicks the stop button.
	 *      401 - Unauthorized  The provided credential was missing or incorrect. See Authentication.
	 *      403 - Forbidden     You are attempting to access a screen share that you did not create through the Screenleap API. Check your screen share code.
	 *      404 - Not Found     The screen share that you are trying to stop does not exist or has already ended.
	 */
	public function stop_active_screen_share( $screenshare_code ) {
		$api_url = str_replace( '{screenShareCode}', $screenshare_code, $this->api_routes['screenshare_stop']['url'] );
		$result  = $this->_screenleap_api_request( $api_url, $this->api_routes['screenshare_stop']['method'], array(), true );
		return $result;
	}

	/**
	 * Get ScreenShare Info.
	 *
	 * @param string $screenshare_code  Screenshare Code.
	 * @return array
	 */
	public function get_screen_share_info( $screenshare_code ) {
		$api_url = str_replace( '{screenShareCode}', $screenshare_code, $this->api_routes['screenshare_info']['url'] );
		$result  = $this->_screenleap_api_request( $api_url, $this->api_routes['screenshare_info']['method'], array(), true );
		return $result;
	}

	/**
	 * Retrieve Screen Shares info about successfull screen shares ( Successfull screen share is one to which both the presenter and one or more viewers connect, completed screen shares are not included. )
	 *
	 * @param array $params Optional Query string to filter screen shares.
	 *      startedBefore          The start time (in milliseconds from epoch time) to be used as a filter. Only screen shares that started before this time will be returned.
	 *      startedAfter           The time (in milliseconds from epoch time) to be used as a filter. Only screen shares that started after this time will be returned.
	 *      endedBefore            The end time (in milliseconds from epoch time) to be used as a filter. Only screen shares that ended before this time will be returned.
	 *      endedAfter             The time (in milliseconds from epoch time) to be used as a filter. Only screen shares that ended after this time will be returned.
	 *      dateFormat Optional.   The format for the passed in dates, whose pattern conforms with Java's SimpleDateFormat. If not specified, the dates passed in can be in milliseconds from epoch time or one of the following default formats: "dd/MM/yy", "dd/MM/yy HH:mm:ss z".
	 *      externalId             The external ID to be used as a filter. Only screen shares that were created with this value as the externalId will be returned.
	 *
	 * @return array|\WP_Error
	 */
	public function get_screen_shares( $params ) {
		return $this->_screenleap_api_request( $this->api_routes['reterive_screenshares']['url'], $this->api_routes['reterive_screenshares']['method'], $params, true );
	}

	/**
	 * Retrieve Recent Screen Shares.
	 *
	 * @return array|\WP_Error
	 *      screenShareCode:        The 9-digit screen share code assigned to this session.
	 *      dateCreated:            The time (in milliseconds from epoch time) at which the share was created.
	 *      accountId:              The Screenleap API account id which was used to create this session.
	 *      externalId:             The external ID which was assigned to this screen share, if any.
	 *      isSecure:               A boolean signifying whether SSL was used for this screen share.
	 *      showScreenleapBranding: A boolean signifying whether Screenleap branding was used for this screen share.
	 *      isActive:               For recent shares, true for shares to which both presenter and viewer have connected, but which have not ended yet. Note that there can be a delay of several minutes after a share ends before the isActive flag gets reset to false. This HTTP request should not be relied upon for realtime information; the JavaScript callback functions and Webhook URL should be used for that purpose.
	 *      startTime:              The time (in milliseconds from epoch time) at which the share started.
	 *      endTime:                The time (in milliseconds from epoch time) at which the share ended.
	 *      totalViewers:           The number of viewers who connected to the share before it ended.
	 *      userMinutes:            For successful shares that have ended. This includes the sum of minutes each participant was in the sharing session (from first connection to last disconnection).
	 *      costInCents:            For successful shares that have ended, the total cost for this share, in cents.
	 *      title:                  The custom title that was used for this share, if any.
	 *      apiCallbackUrl:         The URL to which Screenleap will post server-to-server callbacks on share start and end, if any. The Screenleap servers will make a POST request and the content will be of type "application/json".
	 *      participants:           A JSON array of information about the participants (presenter and viewer(s)) on this share. Each participant JSON object may include the following elements:
	 *      participantId:          The 6-digit code assigned to this participant for this session.
	 *      userAgent:              The user agent for this participant's browser connection.
	 *      sharingStartTime:       The time (in milliseconds from epoch time) at which this participant first joined the active share.
	 *      sharingEndTime:         The time (in milliseconds from epoch time) at which this participant last left the active share.
	 *      durationInMinutes:      The number of minutes this participant was connected to the active share. Contributes to the screen share userMinutes
	 *      isPresenter:            A boolean signifying whether this participant was the presenter (true) or a viewer (false).
	 */
	public function get_recent_screen_shares() {
		return $this->_screenleap_api_request( $this->api_routes['recent_screenshares']['url'], $this->api_routes['recent_screenshares']['method'], array(), true );
	}
}
