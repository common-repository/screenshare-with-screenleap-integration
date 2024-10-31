<?php defined( 'ABSPATH' ) || exit(); ?>

<form method="post" class="<?php echo esc_attr( $plugin_info['name'] ); ?> position-relative">
	<h4> <?php _e( 'You can get the account ID and auth token from the', 'screenshare-with-screenleap-integration' ); ?> <a href="https://www.screenleap.com/developer" target="_blank" ><?php _e( 'developer', 'screenshare-with-screenleap-integration' ); ?> </a> <?php _e( 'tab and the handle from', 'screenshare-with-screenleap-integration' ); ?> <a target="_blank" href="https://www.screenleap.com/account/settings" ><?php _e( 'Settings', 'screenshare-with-screenleap-integration' ); ?></a> <?php _e( 'tab', 'screenshare-with-screenleap-integration' ); ?></h4>
	<table class="form-table" role="presentation">
		<tbody>
			<!-- Account ID Field -->
			<tr>
				<th scope="row"><?php echo _e( 'Account ID', 'gpls-sli-wp-screenleap-integration' ); ?></th>
				<td>
					<fieldset>
						<label for="<?php echo esc_attr( $plugin_info['name'] ) . '-account-id'; ?>">
							<input type="text" class="regular-text" name="<?php echo esc_attr( $plugin_info['name'] ) . '-account-id'; ?>" value="<?php echo esc_html( $settings['account_id'] ); ?>" >
						</label>
					</fieldset>
				</td>
			</tr>
			<!-- Authentication Token Field -->
			<tr>
				<th scope="row"><?php echo _e( 'Authentication Token', 'gpls-sli-wp-screenleap-integration' ); ?></th>
				<td>
					<fieldset>
						<label for="<?php echo esc_attr( $plugin_info['name'] ) . '-auth-token'; ?>">
							<input type="text" class="regular-text" name="<?php echo esc_attr( $plugin_info['name'] ) . '-auth-token'; ?>" value="<?php echo esc_html( $settings['auth_token'] ); ?>" >
						</label>
					</fieldset>
				</td>
			</tr>
			<!-- Handle -->
			<tr>
				<th scope="row"><?php echo _e( 'Handle', 'gpls-sli-wp-screenleap-integration' ); ?></th>
				<td>
					<fieldset>
						<label for="<?php echo esc_attr( $plugin_info['name'] ) . '-handle'; ?>">
							<input type="text" class="regular-text" name="<?php echo esc_attr( $plugin_info['name'] ) . '-handle'; ?>" value="<?php echo esc_html( $settings['handle'] ); ?>" >
						</label>
					</fieldset>
				</td>
			</tr>
		</tbody>
	</table>
	<?php wp_nonce_field( $plugin_info['name'] . '-settings-nonce', esc_attr( $plugin_info['name'] ) . '-settings-nonce', false ); ?>
	<input type="hidden" name="_wp_http_referer" value="<?php echo admin_url( 'tools.php?page=' . esc_attr( $plugin_info['name'] ) ); ?>">
	<input type="hidden" name="tab_name" value="<?php echo ( ! empty( $_GET['tab'] ) ? esc_attr( $_GET['tab'] ) : 'general' ); ?>" >
	<input type="hidden" name="<?php echo esc_attr( $plugin_info['name'] ) . '-export-submit'; ?>" value="1" >
	<p class="submit">
		<button data-cpt_type="xml" class="d-block my-2 button button-primary" type="submit" ><?php _e( 'Submit' ); ?></button>
	</p>

</form>
