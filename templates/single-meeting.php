<?php defined( 'ABSPATH' ) || exit();

get_header();

/* Start the Loop */
while ( have_posts() ) :
	the_post();
	$meeting_id               = get_the_ID();
	$settings                 = get_query_var( 'settings' );
	$meeting_viewer           = get_query_var( 'meeting_viewer' );
	$meeting_settings         = $meeting_viewer->get_meeting_settings_for_view( $meeting_id );
	$meeting_agenda           = $meeting_viewer->get_meeting_agenda( $meeting_id );
	$meeting_status           = $meeting_viewer->get_meeting_status( $meeting_id );
	$viewer_configuration     = $meeting_viewer->get_viewer_configuration( $meeting_id );
	$meeting_type             = $meeting_viewer->get_meeting_type_only( $meeting_id );
	$show_remaining_countdown = $meeting_viewer->show_remaining_countdown( $meeting_id );
	$meeting_time_remaining   = $meeting_viewer->get_meeting_remaining( $meeting_id );
	$needs_login              = $meeting_viewer->is_login_required( $meeting_id );
	$post_thumbnail           = get_the_post_thumbnail( $meeting_id, 'post-thumbnail', array( 'class' => 'img-thumbnail shadow-lg' ) );
	?>
<article id="post-<?php the_ID(); ?>" <?php post_class( $settings->plugin_info['name'] . '-single-meeting' ); ?>>
	<!-- Title -->
	<header class="entry-header container text-center">
		<?php the_title( '<h1 class="entry-title">', '</h1>' ); ?>
	</header>
	<!-- Featured Image -->
	<?php if ( ! empty( $post_thumbnail ) ) : ?>
	<figure class="entry-featured container my-5">
		<div class="col-12 medium d-flex justify-content-center">
			<?php
			echo $post_thumbnail;
			$caption = get_the_post_thumbnail_caption();
			if ( $caption ) {
				?>
				<figcaption class="wp-caption-text"><?php echo esc_html( $caption ); ?></figcaption>
				<?php
			}
			?>
		</div>
	</figure>
	<?php endif; ?>
	<!-- Starting Count Down -->
	<?php if ( $show_remaining_countdown && $meeting_time_remaining && ( 'start' == $meeting_status ) ) : ?>
	<div class="starting-count-down bg-light col-12 d-flex justify-content-center flex-column mb-5 py-5 shadow-lg">
		<h3 class="count-down mb-3 mx-auto"><?php _e( 'Meeting starts in:', 'screenshare-with-screenleap-integration' ); ?></h3>
		<div id="meeting-starting-count-down" data-remaining="<?php echo esc_attr( $meeting_time_remaining ); ?>" class="flipdown d-block mx-auto"></div>
	</div>
	<?php endif; ?>
	<!-- Needs Login before access -->
	<?php if ( $needs_login && 'active' === $meeting_status && ! is_user_logged_in() ) : ?>
	<div class="d-block meeting-requires-login d-block bg-light py-5 my-3 shadow-lg mx-auto">
		<div class="container d-flex justify-content-center flex-column">
			<h4 class="text-center"><?php _e( 'Login is required to join the meeting', 'screenshare-with-screenleap-integration' ); ?></h4>
			<div class="login-container">
				<?php
				wp_login_form(
					array(
						'redirect' => get_the_permalink(),
					)
				);
				?>
			</div>
		</div>
	</div>
	<?php endif; ?>
	<!-- Meeting Content Container -->
	<div class="container-fluid">
		<div class="row">
			<!-- Meeting Content -->
			<div class="col-md-8">
				<?php if ( ! empty( $meeting_agenda ) ) : ?>
				<div class="meeting-agenda shadow-lg">
					<div class="card">
						<div class="card-header">
							<h5><?php _e( 'Meeting Agenda', 'screenshare-with-screenleap-integration' ); ?></h5>
						</div>
						<div class="card-body">
							<div class="card-text">
								<?php echo apply_filters( 'the_content', $meeting_agenda ); ?>
							</div>
						</div>
					</div>
				</div>
				<?php endif; ?>
				<?php
				$post_content = get_the_content();
				if ( ! empty( $post_content ) ) :
					?>
				<!-- Meeting Post Content -->
				<div class="entry-content my-5 card shadow-lg">
					<div class="card-body">
						<?php
						the_content();
						?>
					</div>
				</div><!-- .entry-content -->
				<?php endif; ?>
			</div>
			<div class="col-md-4">
				<!-- Meeting Settings -->
				<div class="metting-settings shadow-lg">
					<div class="card">
						<div class="card-header">
							<h5><?php _e( 'Meeting Details', 'screenshare-with-screenleap-integration' ); ?></h5>
						</div>
						<div class="card-body">
						<?php foreach ( $meeting_settings as $title => $value ) : ?>
							<?php if ( ! empty( $value ) ) : ?>
							<div class="setting-row my-5 <?php echo ( 'Status' === $title ? 'status-row' : '' ); ?>">
								<div class="row no-gutters">
									<div class="d-flex col-4 align-items-center">
										<h6 class="card-title p-0 m-0">
										<?php
										echo sprintf(
											/* translators: %s: Meeting Detail Title */
											__( '%s', 'screenshare-with-screenleap-integration' ),
											esc_html( $title )
										);
										?>
										</h6>
									</div>
									<div class="d-flex col-8 align-items-center justify-content-start">
										<?php if ( 'Status' === $title ) : ?>
										<span class="ml-2 status-led led <?php echo ( ( 'Started' === $value ) ? 'led-green' : 'led-red' ); ?>"></span>
										<?php endif; ?>
										<p class="pl-2 status-text card-text">
										<?php
										echo sprintf(
											/* translators: %s: Meeting Detail Value */
											__( '%s', 'screenshare-with-screenleap-integration' ),
											esc_html( $value )
										);
										?>
										</p>
									</div>
								</div>
							</div>
							<?php endif; ?>
						<?php endforeach; ?>
						</div>
						<div class="card-footer">
							<!-- Meeting Viewer Actions -->
							<div class="container meeting-actions my-3">
								<div class="row meeting-details-wrapper justify-content-center">
									<?php
									if ( ( 'active' === $meeting_status ) && ( ! $needs_login || ( $needs_login && is_user_logged_in() ) ) ) :
										if ( ( 'iframe' === $viewer_configuration['view_method'] ) && ( 'api' === $meeting_type ) ) :
											?>
											<button data-meeting-id="<?php echo esc_attr( $meeting_id ); ?>" class="<?php echo esc_attr( $settings->plugin_info['name'] ); ?>-viewer-screen-request-iframe btn btn-secondary"><?php _e( 'Join the meeting', 'screenshare-with-screenleap-integration' ); ?></button>
										<?php else : ?>
											<a type="button" data-meeting-id="<?php echo esc_attr( $meeting_id ); ?>" href="<?php echo esc_url( $meeting_viewer->get_meeting_url( $meeting_id ) ); ?>" class="<?php echo esc_attr( $settings->plugin_info['name'] ); ?>-viewer-screen-request-redirect btn btn-secondary"><?php _e( 'Join the meeting', 'screenshare-with-screenleap-integration' ); ?></a>
										<?php endif; ?>
									<?php endif; ?>
								</div>
							</div>
						</div>
					</div>
				</div>
			</div>

			<?php if ( ( 'iframe' === $viewer_configuration['view_method'] ) && ( 'api' === $meeting_type ) && ( 'active' === $meeting_status ) && ( ! $needs_login || ( $needs_login && is_user_logged_in() ) ) ) : ?>
			<div class="col-12 meeting-iframe-container my-5">
				<iframe class="w-100 d-none" id="sli-viewer-meeting-iframe" data-src="<?php echo esc_url( $meeting_viewer->get_meeting_url( $meeting_id ) ); ?>" src="about:blank" height="100vh" style="overflow:hidden" frameborder="0" >
				</iframe>
			</div>
			<?php endif; ?>
		</div>
	</div>

	<div class="meeting-notice-dialog"></div>

</article><!-- #post-${ID} -->
	<?php
endwhile; // End of the loop.

get_footer();
