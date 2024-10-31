<?php defined( 'ABSPATH' ) || exit(); ?>

<div class="wrap">
	<nav class="nav-tab-wrapper woo-nav-tab-wrapper wp-clearfix">
		<!-- Settings -->
		<a href="<?php echo esc_url( admin_url( 'edit.php?post_type=' . $settings_obj->post_type_name . '&page=' . $plugin_info['name'] ) ); ?>" class="nav-tab<?php echo ( ! isset( $_GET['tab'] ) || isset( $_GET['tab'] ) && 'general' == sanitize_text_field( wp_unslash( $_GET['tab'] ) ) ) ? ' nav-tab-active' : ''; ?>"><?php _e( 'General', 'ultimate-maintenance-mode-for-woocommerce' ); ?></a>
		<!-- Pro -->
		<a href="<?php echo esc_url( admin_url( 'edit.php?post_type=' . $settings_obj->post_type_name . '&page=' . $plugin_info['name'] . '&tab=pro' ) ); ?>" class="nav-tab<?php echo ( isset( $_GET['tab'] ) && 'pro' == sanitize_text_field( wp_unslash( $_GET['tab'] ) ) ) ? ' nav-tab-active' : ''; ?>"><?php _e( 'Pro', 'ultimate-maintenance-mode-for-woocommerce' ); ?></a>
	</nav>
	<?php
	if ( empty( $_GET['tab'] ) || ( ! empty( $_GET['tab'] ) && ( 'general' === sanitize_text_field( wp_unslash( $_GET['tab'] ) ) ) ) ) :
		require_once $plugin_info['path'] . '/views/general-settings.php';
	elseif ( ! empty( $_GET['tab'] ) && ( 'pro' === sanitize_text_field( wp_unslash( $_GET['tab'] ) ) ) ) :
		$GLOBALS['hide_save_button'] = true;
		do_action( $plugin_info['name'] . '-pro-tab-content' );
	endif;
	?>
</div>
