<?php
/*
 * Plugin Name: Smart Countdown FX Tribe Events Bridge
 * Text Domain: smart-countdown-tribe-events
 * Domain Path: /languages
 * Plugin URI: http://smartcalc.es/wp
 * Description: This plugin adds Modern Tribe Events Calendar support to Smart Cowntdown FX.
 * Version: 1.1
 * Author: Alex Polonski
 * Author URI: http://smartcalc.es/wp
 * License: GPL2
 */

defined( 'ABSPATH' ) or die();

final class SmartCountdownTEBridge_Plugin {
	private static $instance = null;
	
	private static $options_page_slug = 'scd-tribe-events-settings';
	public static $option_prefix = 'scd_tribe_events_settings_';
	private static $text_domain = 'smart-countdown-tribe-events';
	public static $provider_alias = 'scd_tribe_events';
	public static $provider_name;
	
	private static $defaults = array(
			'title'					=> '',
			'all_day_event_start'	=> '',
			'filter_cat_id'			=> -1,
			'show_title'			=> 1,
			'link_title'			=> 0,
			'show_location'			=> 0,
			'show_date'				=> 0,
			'title_css'				=> '',
			'date_css'				=> '',
			'location_css'			=> ''
	);
	
	public static function get_instance() {
		if( is_null( self::$instance) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}
	
	private function __construct() {
		
		require_once( dirname( __FILE__ ) . '/includes/helper.php');
		
		load_plugin_textdomain( 'smart-countdown-tribe-events', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
		
		add_action( 'admin_init', array( $this, 'register_my_settings' ) );
		
		add_action( 'admin_menu', array( $this, 'add_my_menu' ) );
		
		add_action( 'plugin_action_links_' . plugin_basename(__FILE__), array( $this, 'add_plugin_actions' ) );
		
		add_filter( 'smartcountdownfx_get_event',  array( $this, 'get_current_events' ), 10, 2 );
		
		add_filter( 'smartcountdownfx_get_import_configs',  array( $this, 'get_configs' ) );
		
		self::$provider_name = __( 'Events Calendar', self::$text_domain );
		
		add_action( 'admin_enqueue_scripts', array (
				$this,
				'admin_scripts'
		) );
		add_action( 'wp_enqueue_scripts', array (
				$this,
				'front_scripts'
		) );
		
		add_action( 'add_meta_boxes',  array( $this, 'add_timezone_meta') );
		add_action( 'save_post',  array( $this, 'save_timezone_meta'), 10, 2 );
	}
	
	public function add_timezone_meta() {
		if( class_exists( 'Tribe__Events__Main' ) ) {
			$posttype = Tribe__Events__Main::POSTTYPE;
		} else { // fallback to deprecated, not sure if we need this
			$posttype = TribeEvents::POSTTYPE;
		}
		add_meta_box( self::$option_prefix . '_time_zone', 'Time zone', array( $this, 'render_time_zone' ), Tribe__Events__Main::POSTTYPE, 'side', 'default' );
	}
	public function render_time_zone() {
		$value = get_metadata( 'post', get_the_ID(), 'scd_time_zone', true );
		if( empty( $value ) ) {
			$value = -1;
		}

		$html = array();
		
		$html[] = '<select class="chosen" id="scd_meta_timezone" name="scd_meta_timezone" aria-describedby="timezone-description">';
		$html[] = '<option value="-1">' . __( 'Default' ) . '</option>';
		$html[] = wp_timezone_choice( $value );
		$html[] = '</select>';
		
		echo implode( "\n", $html );
	}
	
	public function save_timezone_meta( $post_id , $post ) {
		if( !isset( $_POST['scd_meta_timezone'] ) ) {
			return;
		}
		$time_zone = sanitize_text_field( $_POST['scd_meta_timezone'] );
		$old_values = get_post_custom_values( 'scd_time_zone', $post_id );
		if( !is_null( $old_values ) ) {
			// we render time_zone meta in a separate meta box, but technically
			// it is possible to add a custom field with the same name ("scd_time_zone")
			// using core Custom fields control in a post (event). This action is quite
			// rare but we have to make sure that we never have multiple values for
			// "scd_time_zone" key
			if( count( $old_values ) > 1 ) {
				// yes - we have orphan value, delete all, later the correct value
				// will be added by update_post_meta()
				delete_post_meta( $post_id, 'scd_time_zone' );
			}
			$old_value = reset( $old_values );
		} else {
			$old_value = -1;
		}
		update_post_meta( $post_id, 'scd_time_zone', $time_zone, $old_value );
	}
	
	public static function admin_scripts() {
		$plugin_url = plugins_url() . '/' . dirname( plugin_basename( __FILE__ ) );

		wp_register_script( self::$provider_alias . '_script', $plugin_url . '/admin/admin.js', array( 'jquery' ) );
		wp_enqueue_script( self::$provider_alias . '_script' );
	}
	public static function front_scripts() {
		$plugin_url = plugins_url() . '/' . dirname( plugin_basename( __FILE__ ) );
	
		wp_register_style( 'te-front-css', $plugin_url . '/css/styles.css' );
		wp_enqueue_style( 'te-front-css' );
	}
	public function add_my_menu() {
		add_options_page( __( 'Smart Countdown FX Events Calendar Bridge Settings', self::$text_domain ), __( 'Events Calendar Bridge', self::$text_domain ), 'administrator', self::$options_page_slug, array( $this, 'add_plugin_options_page' ) );
	}
	
	public function register_my_settings() {
		self::registerSettings(1);
		self::registerSettings(2);
	}
	
	public function add_plugin_options_page() {
?>
		<div class="wrap">
		<h2><?php _e( 'Smart Countdown FX Events Calendar Bridge Settings', self::$text_domain ); ?></h2>
				 
			<form method="post" action="options.php">
				<?php settings_fields( self::$options_page_slug ); ?>
				<?php do_settings_sections( self::$options_page_slug ); ?>
				<table class="form-table">
					<?php echo self::displaySettings(1); ?>
				</table>
				<hr />
				<table class="form-table">
					<?php echo self::displaySettings(2); ?>
				</table>
				<?php submit_button(); ?>
			</form>
		</div>
<?php
	}
	
	public function add_plugin_actions( $links ) {
		$new_links = array();
		$new_links[] = '<a href="options-general.php?page=' . self::$options_page_slug . '">' . __( 'Settings' ) . '</a>';
		return array_merge( $new_links, $links );
	}
	
	public function get_current_events( $instance ) {
		$active_config = $instance['import_config'];
		if( empty( $active_config ) ) {
			return $instance;
		}
		
		$parts = explode( '::', $active_config );
		if( $parts[0] != self::$provider_alias ) {
			return $instance;
		}
		array_shift($parts);
		
		$configs = array();
		foreach( $parts as $preset_index ) {
			$configs[] = self::getOptions( $preset_index );
		}
		
		return SmartCountdownTEBRidge_Helper::getEvents( $instance, $configs );
	}
	
	public function get_configs( $configs ) {
		return array_merge( $configs, array(
				self::$provider_name => array(
						self::$provider_alias . '::1' => self::getTitle( 1 ),
						self::$provider_alias . '::2' => self::getTitle( 2 )
				)
		) );
	}
	private static function getTitle( $preset_index ) {
		$options = self::getOptions( $preset_index );
		return !empty( $options['title'] ) ? $options['title'] : __( 'Untitled' );
	}
	
	private static function registerSettings( $preset_index ) {
		register_setting( self::$options_page_slug, self::$option_prefix . $preset_index,  'SmartCountdownTEBridge_Plugin::validateSettings' . $preset_index );
	}
	
	public static function validateSettings1( $input ) {
		return self::validateSettings( $input, 1 );
	}
	public static function validateSettings2( $input ) {
		return self::validateSettings( $input, 2 );
	}
	
	public static function validateSettings( $input, $preset_index )
	{
		foreach( self::$defaults as $key => $value ) {
			if( !isset( $input[$key] ) ) {
				$input[$key] = $value;
			}
			if( $key == 'title' ) {
				if( trim( $input[$key] ) == '' ) {
					$input[$key] = __( 'Untitled' );
				}
				$input[$key] = strip_tags( $input[$key] );
			}
		}
		return $input;
	}
	
	private static function getOptions( $preset_index ) {
		$options = get_option( self::$option_prefix . $preset_index, array() );
		foreach( self::$defaults as $key => $value ) {
			if( !isset( $options[$key] ) ) {
				$options[$key] = $value;
			}
		}
		return $options;
	}
	
	private static function displaySettings( $preset_index ) {
		$options = self::getOptions( $preset_index );
		
		ob_start();
		?>
			<tr>
				<th colspan="2"><h4><?php _e( 'Configuration', self::$text_domain ); ?> <?php echo $preset_index; ?></h4></th>
			</tr>
			<tr valign="top">
				<th scope="row"><label for="<?php echo self::$option_prefix; ?>title_<?php echo $preset_index; ?>"><?php _e( 'Title' ); ?></label></th>
				<td>
					<input type="text" class="regular-text" name="<?php echo self::$option_prefix . $preset_index; ?>[title]" value="<?php echo esc_attr( $options['title'] ); ?>" />
					<p class="description"><?php _e( 'This title will appear in available event import profiles list in Smart Countdown FX configuration', self::$text_domain ); ?></p>
				</td>
			</tr>
			<tr valign="top">
				<th scope="row"><label for="<?php echo self::$option_prefix; ?>filter_cat_id_<?php echo $preset_index; ?>"><?php _e( 'Categories' ); ?></label></th>
				<td>
					<?php
						$cat_filter_args = array(
								'taxonomy'			=> 'tribe_events_cat',
								'hide_empty'		=> false,
								'hierarchical'		=> true,
								'value_field'		=> 'term_id',
								'show_option_none'	=> __( 'Disabled' ),
								'show_option_all'	=> __( 'All Categories' ),
								'id'	 			=> self::$option_prefix . 'filter_cat_id_' . $preset_index,
								'name'				=> self::$option_prefix . $preset_index . '[filter_cat_id]',
								'selected'			=> $options['filter_cat_id'],
								'show_count'		=> true,
								'class'				=> 'postform scd-te-hide-control'
						);
						
						// wp_dropdown_categories() doesn't show "All Categories" option if no categories are found.
						// For correct import we need this options (in most cases it will be real users' choice), so
						// we apply a workaround here: if no categories are found we replace core "wp_dropdown_categories"
						// by a simple select with 2 options.
						$categories = get_terms( 'tribe_events_cat', array( 'hide_empty' => false ) );
						if( count( $categories ) ) {
							// we have categories, rely on core dropdown
							wp_dropdown_categories( $cat_filter_args );
						} else {
							// no categories - render simple "All Categories/Disabled" select
							echo SmartCountdownTEBRidge_Helper::selectInput( self::$option_prefix . 'filter_cat_id_' . $preset_index, self::$option_prefix . $preset_index . '[filter_cat_id]', $options['filter_cat_id'], array(
									'options' => array(
											'0' => __( 'All Categories' ),
											'-1' => __( 'Disabled' )
									),
									'type' => 'optgroups',
									'class' => 'scd-te-hide-control'
							) );
						}
					?>
					<p class="description"><?php _e( 'Select events category to import. "All Categories" - import all events, "Disabled" - disable configuration', self::$text_domain ); ?></p>
				</td>
			</tr>
			<tr valign="top" class="scd-te-hide scd-te-general">
				<th scope="row">
					<label for="<?php echo self::$option_prefix; ?>all_day_event_start_<?php echo $preset_index; ?>"><?php _e( 'All-day events start time', self::$text_domain ); ?></label>
				</th>
				<td>
			<?php
				$time_format = get_option( 'time_format' );	
				echo SmartCountdownTEBRidge_Helper::selectInput( self::$option_prefix . 'all_day_event_start_' . $preset_index, self::$option_prefix . $preset_index . '[all_day_event_start]', $options['all_day_event_start'], array(
						'options' => array(
								'' => __( 'Discard all-day events', self::$text_domain ),
								'00:00:00' => __( 'Midnight', self::$text_domain ),
								'06:00:00' => date_format( new DateTime( '0000-00-00 06:00:00' ), $time_format),
								'07:00:00' => date_format( new DateTime( '0000-00-00 07:00:00' ), $time_format),
								'08:00:00' => date_format( new DateTime( '0000-00-00 08:00:00' ), $time_format),
								'09:00:00' => date_format( new DateTime( '0000-00-00 09:00:00' ), $time_format),
								'10:00:00' => date_format( new DateTime( '0000-00-00 10:00:00' ), $time_format),
								'11:00:00' => date_format( new DateTime( '0000-00-00 11:00:00' ), $time_format),
								'12:00:00' => date_format( new DateTime( '0000-00-00 12:00:00' ), $time_format),
						),
						'type' => 'optgroups'
				) );
			?>
				<p class="description"><?php _e( 'All-day events is a special case for a countdown. Select "Discard all-day events" to ignore this kind of events or choose an option that suits better for your calendar all-day events start time (midnight is not always the best option)', self::$text_domain ); ?></p>
				</td>
			</tr>
			<tr valign="top" class="scd-te-hide scd-te-general">
			<th scope="row">
				<label for="<?php echo self::$option_prefix; ?>show_title_<?php echo $preset_index; ?>"><?php _e( 'Show event title', self::$text_domain ); ?></label>
			</th>
			<td>
		<?php
				echo SmartCountdownTEBRidge_Helper::selectInput( self::$option_prefix . 'show_title_' . $preset_index, self::$option_prefix . $preset_index . '[show_title]', $options['show_title'], array(
						'options' => array(
								'0' => __( 'No' ),
								'1' => __( 'Yes' ),
						),
						'type' => 'optgroups' 
				) );
		?>
				<span><label for="<?php echo self::$option_prefix; ?>title_css_<?php echo $preset_index; ?>"><?php _e( 'Title CSS: ', self::$text_domain ); ?></label>
				<input type="text" class="regular-text" 
					id="<?php echo self::$option_prefix; ?>title_css_<?php echo $preset_index; ?>" 
					name="<?php echo self::$option_prefix . $preset_index; ?>[title_css]" 
					value="<?php echo esc_attr( $options['title_css'] ); ?>" /></span>
			</td>
		</tr>
		<tr valign="top" class="scd-te-hide scd-te-general">
			<th scope="row">
				<label for="<?php echo self::$option_prefix; ?>show_date_<?php echo $preset_index; ?>"><?php _e( 'Show event date', self::$text_domain ); ?></label>
			</th>
			<td>
		<?php
				echo SmartCountdownTEBRidge_Helper::selectInput( self::$option_prefix . 'show_date_' . $preset_index, self::$option_prefix . $preset_index . '[show_date]', $options['show_date'], array(
						'options' => array(
								'0' => __( 'No' ),
								'1' => __( 'Yes' ),
								'2' => __( 'Show with year', self::$text_domain )
						),
						'type' => 'optgroups' 
				) );
		?>
				<span><label for="<?php echo self::$option_prefix; ?>date_css_<?php echo $preset_index; ?>"><?php _e( 'Date CSS: ', self::$text_domain ); ?></label>
				<input type="text" class="regular-text" 
					id="<?php echo self::$option_prefix; ?>date_css_<?php echo $preset_index; ?>" 
					name="<?php echo self::$option_prefix . $preset_index; ?>[date_css]" 
					value="<?php echo esc_attr( $options['date_css'] ); ?>" /></span>
			</td>
		</tr>
		<tr valign="top" class="scd-te-hide scd-te-general">
			<th scope="row">
				<label for="<?php echo self::$option_prefix; ?>show_location_<?php echo $preset_index; ?>"><?php _e( 'Show event location', self::$text_domain ); ?></label>
			</th>
			<td>
		<?php
				echo SmartCountdownTEBRidge_Helper::selectInput( self::$option_prefix . 'show_location_' . $preset_index, self::$option_prefix . $preset_index . '[show_location]', $options['show_location'], array(
						'options' => array(
								'0' => __( 'No' ),
								'1' => __( 'Venue name', self::$text_domain ),
								'2' => __( 'Full address', self::$text_domain ),
						),
						'type' => 'optgroups' 
				) );
		?>
				<span><label for="<?php echo self::$option_prefix; ?>location_css_<?php echo $preset_index; ?>"><?php _e( 'Location CSS: ', self::$text_domain ); ?></label>
				<input type="text" class="regular-text" 
					id="<?php echo self::$option_prefix; ?>location_css_<?php echo $preset_index; ?>" 
					name="<?php echo self::$option_prefix . $preset_index; ?>[location_css]" 
					value="<?php echo esc_attr( $options['location_css'] ); ?>" /></span>
			</td>
		</tr>
		<tr valign="top" class="scd-te-hide scd-te-general">
			<th scope="row">
				<label for="<?php echo self::$option_prefix; ?>link_title_<?php echo $preset_index; ?>"><?php _e( 'Link event title', self::$text_domain ); ?></label>
			</th>
			<td>
		<?php
				echo SmartCountdownTEBRidge_Helper::selectInput( self::$option_prefix . 'link_title_' . $preset_index, self::$option_prefix . $preset_index . '[link_title]', $options['link_title'], array(
						'options' => array(
								'0' => __( 'No' ),
								'1' => __( 'Yes' ),
						),
						'type' => 'optgroups' 
				) );
		?>
			</td>
		</tr>
	<?php
		return ob_get_clean();
	}
}

SmartCountdownTEBridge_Plugin::get_instance();

function smartcountdown_tribe_events_bridge_uninstall() {
	foreach( array( 1, 2 ) as $preset_index ) {
		// delete options
		delete_option( SmartCountdownTEBridge_Plugin::$option_prefix . $preset_index );
		delete_site_option( SmartCountdownTEBridge_Plugin::$option_prefix . $preset_index );
	}
}
register_uninstall_hook( __FILE__, 'smartcountdown_tribe_events_bridge_uninstall' );
