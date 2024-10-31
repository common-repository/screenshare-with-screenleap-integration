<?php
namespace GPLSCore\GPLS_PLUGIN_SLI;

use GPLSCore\GPLS_PLUGIN_SLI\Modules\Services\Helpers;
use GPLSCore\GPLS_PLUGIN_SLI\Modules\Services\Pro_Tab;


defined( 'ABSPATH' ) || exit();

/**
 * Core Class
 */
class Core {

	use Helpers;

	/**
	 * Plugin Info
	 *
	 * @var array
	 */
	protected $plugin_info;

	/**
	 * Core Path
	 *
	 * @var string
	 */
	public $core_path;

	/**
	 * Core URL
	 *
	 * @var string
	 */
	public $core_url;

	/**
	 * Core Assets PATH
	 *
	 * @var string
	 */
	public $core_assets_path;

	/**
	 * Core Assets URL
	 *
	 * @var string
	 */
	public $core_assets_url;

	/**
	 * Constructor.
	 *
	 * @param array $plugin_info
	 */
	public function __construct( $plugin_info ) {
		$this->init( $plugin_info );
		$this->hooks();
		$this->init_modules();
	}

	/**
	 * Init constants and other variables.
	 *
	 * = Set the Plugin Update URL
	 *
	 * @return void
	 */
	public function init( $plugin_info ) {
		$this->plugin_info      = $plugin_info;
		$this->core_path        = plugin_dir_path( __FILE__ );
		$this->core_url         = plugin_dir_url( __FILE__ );
		$this->core_assets_path = $this->core_path . 'assets';
		$this->core_assets_url  = $this->core_url . 'assets';
	}

	/**
	 * Initialize Module Classes
	 *
	 * @return void
	 */
	public function init_modules() {
		Pro_Tab::init( $this->plugin_info, $this );
	}

	/**
	 * Core Hooks.
	 *
	 * @return void
	 */
	public function hooks() {
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_scripts' ), 100, 1 );
	}

	/**
	 * Core Admin Scripts.
	 *
	 * @param string $hook_prefix
	 * @return void
	 */
	public function admin_scripts( $hook_suffix ) {
		if ( ! empty( $_GET['page'] ) && $this->plugin_info['options_page'] === sanitize_text_field( wp_unslash( $_GET['page'] ) ) ) {
			wp_enqueue_style( $this->plugin_info['name'] . '-admin-main-styles', $this->core_assets_file( 'style', 'css', 'css' ), array(), 'all' );
		}
	}

	/**
	 * Pro Tab Link
	 *
	 * @param string $main_page
	 * @param string $post_type_name
	 * @param boolean $echo
	 *
	 * @return string
	 */
	public function pro_tab( $main_page, $post_type_name = '', $echo = false ) {
		return Pro_Tab::init( $this->plugin_info, $this )->pro_tab( $main_page, $post_type_name, $echo );
	}

	/**
	 * Get Core assets file
	 *
	 * @param string $asset_file    Assets File Name
	 * @param string $type          Assets File Folder Type [ js / css /images / etc.. ]
	 * @param string $suffix        Assets File Type [ js / css / png /jpg / etc ... ]
	 * @param string $prefix        [ .min ]
	 * @return string
	 */
	public function core_assets_file( $asset_file, $type, $suffix, $prefix = 'min' ) {
		return $this->core_assets_url . '/dist/' . $type . '/' . $asset_file . ( ! empty( $prefix ) ? ( '.' . $prefix ) : '' ) . '.' . $suffix;
	}

	/**
	 * Get Core assets lib file
	 *
	 * @param string $asset_file    Assets File Name
	 * @param string $suffix        Assets File Type [ js / css / png /jpg / etc ... ]
	 * @param string $prefix        [ .min ]
	 * @return string
	 */
	public function core_assets_lib( $asset_file, $suffix, $prefix = 'min' ) {
		return $this->core_assets_url . '/libs/' . $asset_file . ( ! empty( $prefix ) ? ( '.' . $prefix ) : '' ) . '.' . $suffix;
	}

	/**
	 * Plugin Activation Hub function
	 *
	 * @return void
	 */
	public function plugin_activated() {
		// set the main options value.
		$main_options = get_option( $this->plugins_main_options );
		if ( ! $main_options ) {
			$main_options                         = array();
			$main_options['installed_plugins']    = array();
			$main_options['plugins_update_check'] = array(
				'timestamp' => microtime( true ),
			);
		}
		$main_options['installed_plugins'][ $this->plugin_info['name'] ] = array(
			'public_name' => $this->plugin_info['public_name'],
			'basename'    => $this->plugin_info['basename'],
			'id'          => $this->plugin_info['id'],
			'name'        => $this->plugin_info['name'],
			'type'        => $this->plugin_info['type'],
			'status'      => 'active',
		);
		update_option( $this->plugins_main_options, $main_options, true );
	}

	/**
	 * Plugin Deactivation Hub function
	 *
	 * @return void
	 */
	public function plugin_deactivated() {
		$main_options = get_option( $this->plugins_main_options );
		$main_options['installed_plugins'][ $this->plugin_info['name'] ]['status'] = 'inactive';
		update_option( $this->plugins_main_options, $main_options, true );
	}

	/**
	 * Uninstall the plugin hook.
	 *
	 * @return void
	 */
	public function plugin_uninstalled() {
		if ( ! is_plugin_active( $this->plugin_info['text_domain'] . '-pro/' . $this->plugin_info['name'] . '-pro.php' ) ) {
			$main_options = get_option( $this->plugins_main_options );
			unset( $main_options['installed_plugins'][ $this->plugin_info['name'] ] );
			if ( empty( $main_options['installed_plugins'] ) ) {
				delete_option( $this->plugins_main_options );
			} else {
				update_option( $this->plugins_main_options, $main_options, true );
			}
		}
	}

	/**
	 * Default Footer Section
	 *
	 * @return void
	 */
	public function default_footer_section() {
		?>
		<style>
		#wpfooter {display: block !important;}
		.wrap.woocommerce {position: relative;}
		.gpls-contact {position: absolute; bottom: 0px; right: 20px; max-width: 350px; z-index: 1000;}
		.gpls-contact .link { color: #acde86!important; }
		.gpls-contact .text { background-color: #176875!important; }
		</style>
		<div class="gpls-contact">
		  <p class="p-3 bg-light text-center text text-white">in case you want to report a bug, submit a new feature or request a custom plugin, Please <a class="link" target="_blank" href="https://grandplugins.com/contact-us"> contact us </a></p>
		</div>
		<?php
	}
}
