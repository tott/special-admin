<?php
/**
 * Plugin Name: Special Admin
 * Plugin URI:  http://thorsten-ott.de
 * Description: Special Admin functionality allows certain users to assume the identity of others
 * Version:     0.1.0
 * Author:      Thorsten Ott
 * Author URI:  http://thorsten-ott.de
 * License:     GPLv2+
 * Text Domain: spa
 * Domain Path: /languages
 */

/**
 * Copyright (c) 2013 Thorsten Ott (email : thorsten@thorsten-ott.de)
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License, version 2 or, at
 * your discretion, any later version, as published by the Free
 * Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
 */

//*
// Example definitions that should go in your wp-config.php
define( 'SPECIAL_ADMIN_USERS', 'savvis_trunk' );
define( 'SPECIAL_ADMIN_IPS', '127.0.0.1/32, 192.168.50.1' );
define( 'SPECIAL_ADMIN_ALLOW_PROXY_IP', true );
//*/
class Special_Admin {

	private static $__instance = NULL;

	private $settings = array();
	private $default_settings = array();
	private $settings_texts = array();

	private $plugin_prefix = 'spa';
	private $plugin_name = 'Special Admin';
	private $settings_page_name = null;
	private $dashed_name = 'special-admin';
	private $underscored_name = 'special_admin';
	private $js_version = '131127012512';
	private $css_version = '131127012512';

	public function __construct() {
		add_action( 'admin_init', array( &$this, 'register_setting' ) );
		add_action( 'admin_menu', array( &$this, 'register_settings_page' ) );

		/**
		 * Default settings that will be used for the setup. You can alter these value with a simple filter such as this
		 * add_filter( 'pluginprefix_default_settings', 'mypluginprefix_settings' );
		 * function mypluginprefix_settings( $settings ) {
		 * 		$settings['enable'] = false;
		 * 		return $settings;
		 * }
		 */
		$this->default_settings = (array) apply_filters( $this->plugin_prefix . '_default_settings', array(
			'enable'				=> 0,
			'timeout'				=> 180,
		) );

		/**
		 * Define fields that will be used on the options page
		 * the array key is the field_name the array then describes the label, description and type of the field. possible values for field types are 'text' and 'yesno' for a text field or input fields or 'echo' for a simple output
		 * a filter similar to the default settings (ie pluginprefix_settings_texts) can be used to alter this values
		 */
		$this->settings_texts = (array) apply_filters( $this->plugin_prefix . '_settings_texts', array(
			'enable' => array(
				'label' => sprintf( __( 'Enable %s', $this->plugin_prefix ), $this->plugin_name ),
				'desc' => sprintf( __( 'Enable %s', $this->plugin_prefix ), $this->plugin_name ),
				'type' => 'yesno'
			),
			'timeout' => array(
				'label' => sprintf( __( 'Duration' ) ),
				'desc' => sprintf( __( 'The duration the functionality will be enabled for in seconds. After this it will deactivate itsself again.' ) ),
				'type' => 'text'
			),

		) );

		$user_settings = get_option( $this->plugin_prefix . '_settings' );
		if ( false === $user_settings )
			$user_settings = array();

		// after getting default settings make sure to parse the arguments together with the user settings
		$this->settings = wp_parse_args( $user_settings, $this->default_settings );
	}

	public static function init() {
		self::instance()->settings_page_name = sprintf( __( '%s Settings', self::instance()->plugin_prefix ), self::instance()->plugin_name );

		if ( self::instance()->plugin_enabled() ) {
			add_action( 'init', self::instance()->init_hook_enabled() );
		}
		self::instance()->init_hook_always();
	}

	/*
	 * Use this singleton to address methods
	 */
	public static function instance() {
		if ( self::$__instance == NULL )
			self::$__instance = new Special_Admin;
		return self::$__instance;
	}

	/**
	 * Run these functions when the plugin is enabled
	 */
	public function init_hook_enabled() {
		add_action( 'wp_loaded', array( $this, 'special_admin_actions' ) );
	}

	/**
	 * Run these functions all the time
	 */
	public function init_hook_always() {
		/**
		 * If a css file for this plugin exists in ./css/wp-cron-control.css make sure it's included
		 */
		if ( file_exists( dirname( __FILE__ ) . "/css/" . $this->underscored_name . ".css" ) )
			wp_enqueue_style( $this->dashed_name, plugins_url( "css/" . $this->underscored_name . ".css", __FILE__ ), $deps = array(), $this->css_version );
		/**
		 * If a js file for this plugin exists in ./js/wp-cron-control.css make sure it's included
		 */
		if ( file_exists( dirname( __FILE__ ) . "/js/" . $this->underscored_name . ".js" ) )
			wp_enqueue_script( $this->dashed_name, plugins_url( "js/" . $this->underscored_name . ".js", __FILE__ ), array(), $this->js_version, true );

		/**
		 * Locale setup
		 */
		$locale = apply_filters( 'plugin_locale', get_locale(), $this->plugin_prefix );
		load_textdomain( $this->plugin_prefix, WP_LANG_DIR . '/' . $this->plugin_prefix . '/' . $this->plugin_prefix . '-' . $locale . '.mo' );
		load_plugin_textdomain( $this->plugin_prefix, false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );

	}

	public function register_settings_page() {
		add_options_page( $this->settings_page_name, $this->plugin_name, 'manage_options', $this->dashed_name, array( &$this, 'settings_page' ) );
	}

	public function register_setting() {
		register_setting( $this->plugin_prefix . '_settings', $this->plugin_prefix . '_settings', array( &$this, 'validate_settings') );
	}

	public function validate_settings( $settings ) {
		// reset to defaults
		if ( !empty( $_POST[ $this->dashed_name . '-defaults'] ) ) {
			$settings = $this->default_settings;
			$_REQUEST['_wp_http_referer'] = add_query_arg( 'defaults', 'true', $_REQUEST['_wp_http_referer'] );

		// or do some custom validations
		} else {
			if ( $settings['enable'] == '1' ) {
				set_transient( $this->plugin_prefix . '_timeout', time() , $expiration = (int) $settings['timeout'] );
			} else {
				delete_transient( $this->plugin_prefix . '_timeout' );
			}
		}
		return $settings;
	}

	public function plugin_enabled() {
		$time = get_transient( $this->plugin_prefix . '_timeout' );
		if ( 1 == self::instance()->settings['enable'] ) {
			if ( $time && time() - $time <= self::instance()->settings['timeout'] ) {
				return true;
			} else {
				// auto disable plugin again
				$user_settings = self::instance()->settings;
				$user_settings['enable'] = 0;
				update_option( $this->plugin_prefix . '_settings', $user_settings );
			}
		}
		return false;
	}

	public function settings_page() {
		if ( ! defined( 'SPECIAL_ADMIN_USERS' ) || ! $this->is_special_admin() ) {
			wp_die( __( 'You do not permission to access this page. Make sure that SPECIAL_ADMIN_USERS is defined and that you\'re a part of it', $this->plugin_prefix ) );
		}
		?>
		<div class="wrap">
		<?php if ( function_exists('screen_icon') ) screen_icon(); ?>
			<h2><?php echo $this->settings_page_name; ?></h2>
			<p>This plugin allows certain users to assume the identity of other users.</p>
			<?php if ( ! defined( 'SPECIAL_ADMIN_USERS' ) || ! SPECIAL_ADMIN_USERS ): ?>
				<b>Note: the plugin is currently not configured. Make sure to define the set of usernames who can benefit from this plugin</b>
			<?php endif; ?>
			<form method="post" action="options.php">

			<?php settings_fields( $this->plugin_prefix . '_settings' ); ?>

			<table class="form-table">
				<?php foreach( $this->settings as $setting => $value): ?>
				<tr valign="top">
					<th scope="row">
						<label for="<?php echo $this->dashed_name . '-' . $setting; ?>">
						<?php if ( isset( $this->settings_texts[$setting]['label'] ) ) {
							echo $this->settings_texts[$setting]['label'];
						} else {
							echo $setting;
						} ?>
						</label>
					</th>
					<td>
						<?php
						/**
						 * Implement various handlers for the different types of fields. This could be easily extended to allow for drop-down boxes, textareas and more
						 */
						?>
						<?php switch( $this->settings_texts[$setting]['type'] ):
							case 'yesno': ?>
								<select name="<?php echo $this->plugin_prefix; ?>_settings[<?php echo $setting; ?>]" id="<?php echo $this->dashed_name . '-' . $setting; ?>" class="postform">
									<?php
										$yesno = array( 0 => __( 'No', $this->plugin_prefix ), 1 => __( 'Yes', $this->plugin_prefix ) );
										foreach ( $yesno as $val => $txt ) {
											echo '<option value="' . esc_attr( $val ) . '"' . selected( $value, $val, false ) . '>' . esc_html( $txt ) . "&nbsp;</option>\n";
										}
									?>
								</select><br />
							<?php break;
							case 'text': ?>
								<div><input type="text" name="<?php echo $this->plugin_prefix; ?>_settings[<?php echo $setting; ?>]" id="<?php echo $this->dashed_name . '-' . $setting; ?>" class="postform" value="<?php echo esc_attr( $value ); ?>" /></div>
							<?php break;
							case 'echo': ?>
								<div><span id="<?php echo $this->dashed_name . '-' . $setting; ?>" class="postform"><?php echo esc_attr( $value ); ?></span></div>
							<?php break;
							default: ?>
								<?php echo $this->settings_texts[$setting]['type']; ?>
							<?php break;
						endswitch; ?>
						<?php if ( !empty( $this->settings_texts[$setting]['desc'] ) ) { echo $this->settings_texts[$setting]['desc']; } ?>
					</td>
				</tr>
				<?php endforeach; ?>
				<?php if ( 1 == $this->settings['enable'] ): ?>
					<tr>
						<td colspan="3">
							<p>The script has been enabled</p>
						</td>
					</tr>
				<?php endif; ?>
			</table>

			<p class="submit">
		<?php
				if ( function_exists( 'submit_button' ) ) {
					submit_button( null, 'primary', $this->dashed_name . '-submit', false );
					echo ' ';
					submit_button( __( 'Reset to Defaults', $this->plugin_prefix ), '', $this->dashed_name . '-defaults', false );
				} else {
					echo '<input type="submit" name="' . $this->dashed_name . '-submit" class="button-primary" value="' . __( 'Save Changes', $this->plugin_prefix ) . '" />' . "\n";
					echo '<input type="submit" name="' . $this->dashed_name . '-defaults" id="' . $this->dashed_name . '-defaults" class="button-primary" value="' . __( 'Reset to Defaults', $this->plugin_prefix ) . '" />' . "\n";
				}
		?>
			</p>

			</form>
		</div>

		<?php
	}

	public function is_special_admin( $user='' ) {
		if ( ! $user ) {
			$user = wp_get_current_user();
		} else {
			if ( is_numeric( $user ) ) {
				$user = get_user_by( 'id', $user );
			} else {
				$user = get_user_by( 'login', $user );
			}
		}
		//die( var_export( $user, true ) );
		// Check if user is a special admin
		if ( ! defined( 'SPECIAL_ADMIN_USERS' ) || ! SPECIAL_ADMIN_USERS ) {
			return false;
		}

		$special_admins = array_map( 'trim', explode( ',', SPECIAL_ADMIN_USERS ) );
		if ( ! is_array( $special_admins ) || empty( $special_admins ) || ! in_array( $user->user_login, $special_admins ) ) {
			return false;
		}

		// Check if the user's IP is valid.
		if ( defined( 'SPECIAL_ADMIN_IPS' ) && SPECIAL_ADMIN_IPS ) {
			$special_ips = array_map( 'trim', explode( ',', SPECIAL_ADMIN_IPS ) );
			if ( ! is_array( $special_ips ) || empty( $special_ips ) ) {
				return false;
			}

			$proxy_headers = array(
				'HTTP_VIA',
				'HTTP_X_FORWARDED_FOR',
				'HTTP_FORWARDED_FOR',
				'HTTP_X_FORWARDED',
				'HTTP_FORWARDED',
				'HTTP_CLIENT_IP',
				'HTTP_FORWARDED_FOR_IP',
				'VIA',
				'X_FORWARDED_FOR',
				'FORWARDED_FOR',
				'X_FORWARDED',
				'FORWARDED',
				'CLIENT_IP',
				'FORWARDED_FOR_IP',
				'HTTP_PROXY_CONNECTION'
			);
			$proxy_ip = false;
			foreach( $proxy_headers as $header ) {
				if ( isset( $_SERVER[$header] ) ) {
					$proxy_ip = $_SERVER[$header];
					break;
				}
			}

			$remote_ip = $_SERVER['REMOTE_ADDR'];

			$ip_match = false;
			foreach( $special_ips as $range ) {
				if ( defined( 'SPECIAL_ADMIN_ALLOW_PROXY_IP' ) && SPECIAL_ADMIN_ALLOW_PROXY_IP ) {
					if ( $this->ip_in_range( $remote_ip, $range ) || $this->ip_in_range( $proxy_ip, $range ) ) {
						$ip_match = true;
						break;
					}
				} else if ( $this->ip_in_range( $remote_ip, $range ) ) {
					$ip_match = true;
					break;
				}
			}
			if ( ! $ip_match ) {
				return false;
			}
		}
		return true;
	}

	public function special_admin_actions() {
		if ( $this->is_special_admin() ) {
			add_filter( 'user_row_actions', array( $this, 'add_user_row_action' ), 10, 2 );
			add_action( 'wp_ajax_assume_identity', array( $this, 'assume_identity' ) );
		}
	}

	public function add_user_row_action( $actions, $user_object ) {
		$ajax_url = admin_url( '/admin-ajax.php' );
		$ajax_url = add_query_arg( array(
			$this->plugin_prefix . '_nonce' => wp_create_nonce( $action = $this->plugin_prefix . '_assume_identity' ),
			'user_id' => $user_object->ID,
			'action' => 'assume_identity'
		), $ajax_url );
		$actions['assume_identity'] = '<a href="' . esc_url( $ajax_url ) . '">Assume Identity</a>';
		return $actions;
	}

	public function assume_identity() {
		check_ajax_referer( $action = $this->plugin_prefix . '_assume_identity', $query_arg = $this->plugin_prefix . '_nonce', $die = true );

		if ( ! $this->is_special_admin() ) {
			die( 'Nice try' );
		}

		$user_id = (int) $_GET['user_id'];
		wp_clear_auth_cookie();
		wp_set_auth_cookie( $user_id, true );
		wp_set_current_user( $user_id );

		wp_safe_redirect( site_url(), $status = 302 );
		exit;
	}

	/**
	 * Check if a given ip is in a network
	 * @param  string $ip    IP to check in IPV4 format eg. 127.0.0.1
	 * @param  string $range IP/CIDR netmask eg. 127.0.0.0/24, also 127.0.0.1 is accepted and /32 assumed
	 * @return boolean true if the ip is in this range / false if not.
	 */
	public function ip_in_range( $ip, $range ) {
		if ( strpos( $range, '/' ) == false ) {
			$range .= '/32';
		}
		// $range is in IP/CIDR format eg 127.0.0.1/24
		list( $range, $netmask ) = explode( '/', $range, 2 );
		$range_decimal = ip2long( $range );
		$ip_decimal = ip2long( $ip );
		$wildcard_decimal = pow( 2, ( 32 - $netmask ) ) - 1;
		$netmask_decimal = ~ $wildcard_decimal;
		return ( ( $ip_decimal & $netmask_decimal ) == ( $range_decimal & $netmask_decimal ) );
	}
}

function is_special_admin( $user='' ) {
	return Special_Admin::instance()->is_special_admin( $user );
}

// if we loaded wp-config then ABSPATH is defined and we know the script was not called directly to issue a cli call
if ( defined('ABSPATH') ) {
	Special_Admin::init();
} else {
	// otherwise parse the arguments and call the cron.
	if ( !empty( $argv ) && $argv[0] == basename( __FILE__ ) || $argv[0] == __FILE__ ) {
		if ( isset( $argv[1] ) ) {
			echo "You could do something here";
		} else {
			echo "Usage: php " . __FILE__ . " <param1>\n";
			echo "Example: php " . __FILE__ . " superduperparameter\n";
			exit;
		}
	}
}

