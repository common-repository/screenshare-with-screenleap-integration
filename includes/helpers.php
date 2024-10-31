<?php

namespace GPLSCore\GPLS_PLUGIN_SLI\Helpers;

use function GPLSCore\GPLS_PLUGIN_SLI\Utils\get_timezones;

defined( 'ABSPATH' ) || exit();

/**
 * Get TimeZone Dropdown Select HTML.
 *
 * @return void
 */
function get_timezone_dropdown( $select_name, $val = '' ) {

	if ( empty( $val ) ) {
		$current_offset  = get_option( 'gmt_offset' );
		$tzstring        = get_option( 'timezone_string' );
		$check_zone_info = true;

		// Remove old Etc mappings. Fallback to gmt_offset.
		if ( false !== strpos( $tzstring, 'Etc/GMT' ) ) {
			$tzstring = '';
		}

		if ( empty( $tzstring ) ) { // Create a UTC+- zone if no timezone string exists.
			$check_zone_info = false;
			if ( 0 == $current_offset ) {
				$tzstring = 'UTC+0';
			} elseif ( $current_offset < 0 ) {
				$tzstring = 'UTC' . $current_offset;
			} else {
				$tzstring = 'UTC+' . $current_offset;
			}
		}
	} else {
		$tzstring = $val;
	}
	?>
	<select id="<?php echo esc_attr( $select_name ); ?>" name="<?php echo esc_attr( $select_name ); ?>" aria-describedby="timezone-description">
		<?php foreach ( get_timezones() as $name => $title ) : ?>
			<option value="<?php echo esc_attr( $name ); ?>" <?php selected( $val, $name ); ?>><?php echo esc_html( $title ); ?></option>
		<?php endforeach; ?>
	</select>
	<?php
}
