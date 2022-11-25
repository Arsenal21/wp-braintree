<?php
/**
 * Plugin Name: WP BrainTree
 * Plugin URI: https://www.tipsandtricks-hq.com/wordpress-braintree-plugin
 * Description: Create "Buy Now" buttons for BrainTree payment gateway to accept payment for a product or service.
 * Version: 2.0.4
 * Author: Tips and Tricks HQ, alexanderfoxc, wptipsntricks
 * Author URI: https://www.tipsandtricks-hq.com/
 * License: GPL2
 */
DEFINE( 'WP_BRAINTREE_PLUGIN_VERSION', '2.0.4' );

class wp_braintree {

	// Setup options used for this plugin
	protected $option_name    = 'wp_braintree_opts';
	protected $api_keys_name  = 'wp_braintree_api_keys';
	protected $recaptcha_name = 'wp_braintree_recaptcha';
	// These options will be used for default data
	protected $data      = array(
		'api_keys_tab' => array(
			'merchant_id' => '',
			'public_key'  => '',
			'private_key' => '',
		),
		'opts_tab'     => array(
			'sandbox_mode' => '0',
			'auth_only'    => '0',
			//'create_customer' => '0',
			'success_url'  => '',
			'jq_theme'     => 'smoothness',
		),
	);
	var $buttons_on_page = 0;

	// Private function for accessing the BrainTree API
	private function wp_braintree_get_api() {

		// Get plugin api keys from database
		$api_keys = get_option( $this->api_keys_name );

		$api_keys_merchant_id = isset( $api_keys['merchant_id'] ) ? $api_keys['merchant_id'] : '';
		$api_keys_public_key  = isset( $api_keys['public_key'] ) ? $api_keys['public_key'] : '';
		$api_keys_private_key = isset( $api_keys['private_key'] ) ? $api_keys['private_key'] : '';

		// Is this plugin running in sandbox or production?
		$options = get_option( $this->option_name );
		$sandbox = $options['sandbox_mode'] == '1' ? 'sandbox' : 'production';

		// Include BrainTree library
		require_once 'braintree/lib/autoload.php';
		try {
			// Initiate BrainTree
			Braintree_Configuration::environment( $sandbox );
			Braintree_Configuration::merchantId( $api_keys_merchant_id );
			Braintree_Configuration::publicKey( $api_keys_public_key );
			Braintree_Configuration::privateKey( $api_keys_private_key );
		} catch ( Exception $e ) {
			return 'Error!';
		}
	}

	// Construct the plugin
	public function __construct() {

		define( 'WPB_URL', plugins_url( '', __FILE__ ) );
		define( 'WPB_PATH', dirname( __FILE__ ) );

		load_plugin_textdomain( 'wp_braintree_lang', false, dirname( plugin_basename( __FILE__ ) ) . '/lang' );  // Load plugin text domain
		require_once WPB_PATH . '/includes/admin/post.class.php';

		add_action( 'admin_init', array( $this, 'admin_init' ) );  // Used for registering settings
		add_action( 'admin_menu', array( $this, 'add_page' ) );  // Creates admin menu page and conditionally loads scripts and styles on admin page
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_scripts' ) );
		add_action( 'init', array( 'wp_braintree_post', 'register_post_type' ) );
		add_action( 'admin_menu', array( $this, 'add_plugin_admin_menu' ) );
		add_action( 'init', array( $this, 'wp_braintree_tinymce_button' ) );  // Create tinymce button
		add_action( 'wp_enqueue_scripts', array( $this, 'head_styles_scripts' ) );  // Add scripts and styles to frontend (shortcode used to filter posts so it is not added to all)
		add_shortcode( 'wp_braintree_button', array( $this, 'wp_braintree_button_shortcode' ) );  // Generate shortcode output
		add_filter( 'mce_external_languages', array( $this, 'localize_tinymce' ) );  // Localize tinymce langs for translation
		register_activation_hook( __FILE__, array( $this, 'activate' ) );  // Activate plugin
	}

	// Register Plugin settings array
	public function admin_init() {
		register_setting( 'wp_braintree_options', $this->option_name, array( $this, 'validate_options' ) );
		register_setting( 'wp_braintree_api_keys', $this->api_keys_name, array( $this, 'validate_api_keys' ) );
		register_setting( 'wp_braintree_recaptcha', $this->recaptcha_name, array( $this, 'validate_recaptcha_options' ) );
	}

	// Validate plugin input fields
	// Currently not used.. so value is set to input
	public function validate_api_keys( $input ) {

		$options = get_option( $this->api_keys_name );

		$valid                = array();
		$valid['merchant_id'] = $input['merchant_id'];
		$valid['public_key']  = $input['public_key'];
		$valid['private_key'] = $input['private_key'];

		return $valid;
	}

	public function validate_options( $input ) {

		$options = get_option( $this->option_name );

		$valid                 = array();
		$valid['sandbox_mode'] = isset( $input['sandbox_mode'] ) ? '1' : '0';
		$valid['auth_only']    = isset( $input['auth_only'] ) ? '1' : '0';
		//$valid['create_customer'] = isset($input['create_customer']) ? '1' : '0';
		$valid['success_url'] = isset( $input['success_url'] ) ? esc_url( $input['success_url'] ) : '';
		$valid['jq_theme']    = isset( $input['jq_theme'] ) ? $input['jq_theme'] : 'smoothness';

		return $valid;
	}

	public function validate_recaptcha_options( $input ) {
		$valid               = array();
		$valid['enabled']    = isset( $input['enabled'] ) ? true : false;
		$valid['site_key']   = isset( $input['site_key'] ) ? sanitize_text_field( $input['site_key'] ) : '';
		$valid['secret_key'] = isset( $input['secret_key'] ) ? sanitize_text_field( $input['secret_key'] ) : '';

		return $input;
	}

	// Initialize admin page
	public function add_page() {
		//$wp_braintree_page = add_options_page(__('WP BrainTree', 'wp_braintree_lang'), __('WP BrainTree', 'wp_braintree_lang'), 'manage_options', 'wp_braintree_options', array($this, 'options_do_page'));
	}

	public function enqueue_admin_scripts( $hook ) {
		if ( 'braintree_payment_page_wp_braintree_settings' != $hook ) {
			return;
		}
		wp_enqueue_script( 'jquery-ui-tabs' );  // For admin panel page tabs
		wp_enqueue_script( 'jquery-ui-dialog' );  // For admin panel popup alerts
		wp_enqueue_script( 'wp_braintree_scripts', plugins_url( '/js/admin_page.js', __FILE__ ), array( 'jquery' ) );  // Apply admin page scripts
		wp_enqueue_style( 'wp_braintree_styles', plugins_url( '/css/admin_page.css', __FILE__ ) );  // Apply admin page styles
		wp_enqueue_style( 'jquery-ui-theme', plugins_url( '/css/themes/smoothness/jquery-ui.css', __FILE__ ) );  //include jquery-ui.css (smoothness theme)
	}

	public function add_plugin_admin_menu() {

		add_submenu_page(
			'edit.php?post_type=braintree_payment',
			__( 'Settings', 'wp_braintree_lang' ),
			__( 'Settings', 'wp_braintree_lang' ),
			'manage_options',
			'wp_braintree_settings',
			array( $this, 'options_do_page' )
		);
	}

	// Generate admin options page
	public function options_do_page() {

		$options_opts = get_option( $this->option_name );
		$options_api  = get_option( $this->api_keys_name );
		$options_re   = get_option( $this->recaptcha_name );
		?>
<div class="wrap">
	<div id="icon-themes" class="icon32"></div>
	<h2><?php _e( 'WP BrainTree Options', 'wp_braintree_lang' ); ?></h2>

		<?php
		$active_tab = isset( $_GET['tab'] ) ? $_GET['tab'] : 'api_keys';
		?>

	<h2 class="nav-tab-wrapper">
		<a href="?post_type=braintree_payment&page=wp_braintree_settings&tab=api_keys"
			class="nav-tab <?php echo $active_tab == 'api_keys' ? 'nav-tab-active' : ''; ?>">API Keys</a>
		<a href="?post_type=braintree_payment&page=wp_braintree_settings&tab=options"
			class="nav-tab <?php echo $active_tab == 'options' ? 'nav-tab-active' : ''; ?>">Options</a>
		<a href="?post_type=braintree_payment&page=wp_braintree_settings&tab=recaptcha"
			class="nav-tab <?php echo $active_tab == 'recaptcha' ? 'nav-tab-active' : ''; ?>">reCaptcha</a>
		<a href="?post_type=braintree_payment&page=wp_braintree_settings&tab=help"
			class="nav-tab <?php echo $active_tab == 'help' ? 'nav-tab-active' : ''; ?>">Help</a>
		<a href="?post_type=braintree_payment&page=wp_braintree_settings&tab=active_buttons"
			class="nav-tab <?php echo $active_tab == 'active_buttons' ? 'nav-tab-active' : ''; ?>">Active Buttons</a>
	</h2>

	<div
		style="background: none repeat scroll 0 0 #ECECEC;border: 1px solid #CFCFCF;color: #363636;margin: 10px 0 15px;padding:15px;text-shadow: 1px 1px #FFFFFF;">
		For usage documentation and updates, please visit the plugin page at the following URL:<br>
		<a target="_blank" href="https://www.tipsandtricks-hq.com/wordpress-braintree-plugin">WordPress Braintree
			Plugin</a>
	</div>

	<form method="post" action="options.php">

		<?php
		if ( $active_tab == 'options' ) { // OPTIONS TAB
			?>
		<div class="postbox">
			<?php settings_fields( 'wp_braintree_options' ); ?>
			<h3><?php _e( 'Additional Options:', 'wp_braintree_lang' ); ?></h3>
			<table class="form-table">
				<tr valign="top">
					<th scope="row"><?php _e( 'Sandbox Mode:', 'wp_braintree_lang' ); ?></th>
					<td>
						<input id="sandbox_mode" type="checkbox" name="<?php echo $this->option_name; ?>[sandbox_mode]"
							value="<?php echo esc_attr( $options_opts['sandbox_mode'] ); ?>"
											  <?php
												if ( $options_opts['sandbox_mode'] ) {
													echo 'checked=checked';}
												?>
												 />
						<br />
						<?php _e( 'Check to run the plugin in sandbox mode.', 'wp_braintree_lang' ); ?>
					</td>
				</tr>
				<tr valign="top">
					<th scope="row"><?php _e( 'Authorize Only:', 'wp_braintree_lang' ); ?></th>
					<td>
						<input id="auth_only" type="checkbox" name="<?php echo $this->option_name; ?>[auth_only]"
							value="<?php echo esc_attr( $options_opts['auth_only'] ); ?>"
											  <?php
												if ( $options_opts['auth_only'] ) {
													echo 'checked=checked';}
												?>
												 />
						<br />
						<?php _e( 'Checking this option processes transactions in an "Authorized" status.', 'wp_braintree_lang' ); ?>
						<br />
						<?php _e( 'Unchecking this option processes transactions in a "Submitted for Settlement" status.', 'wp_braintree_lang' ); ?>
					</td>
				</tr>
				<!--
				<tr valign="top"><th scope="row"><?php //_e('Create Customer:', 'wp_braintree_lang') ?></th>
					<td>
					<input id="create_customer" type="checkbox" name="<?php //echo $this->option_name ?>[create_customer]" value="<?php //echo $options_opts['create_customer']; ?>" <?php //if($options_opts['create_customer']) echo 'checked=checked' ?>/>
					<br />
				<?php //_e('Checking this option will create a new customer on each successful transaction.', 'wp_braintree_lang') ?>
					</td>
				</tr>
				-->
				<tr valign="top">
					<th scope="row"><?php _e( 'Success URL:', 'wp_braintree_lang' ); ?></th>
					<td>
						<input id="success_url" type="text" size="60"
							name="<?php echo $this->option_name; ?>[success_url]"
							value="<?php echo esc_url_raw( $options_opts['success_url'] ); ?>"
											  <?php
												if ( $options_opts['success_url'] ) {
													echo 'checked=checked';}
												?>
												 />
						<br />
						<?php _e( 'Enter a return url for successful transactions (a thank you page).', 'wp_braintree_lang' ); ?>
						<br />
						<?php _e( 'If no url is specified (blank), the user will be redirected to the home page.', 'wp_braintree_lang' ); ?>
					</td>
				</tr>
				<tr valign="top">
					<th scope="row"><?php _e( 'jQuery Theme', 'wp_braintree_lang' ); ?></th>
					<td>
						<select name="<?php echo $this->option_name; ?>[jq_theme]" />
						<?php
						$jquery_themes = array( /* 'base', */ 'black-tie', 'blitzer', 'cupertino', 'dark-hive', 'dot-luv', 'eggplant', 'excite-bike', 'flick', 'hot-sneaks', 'humanity', 'le-frog', 'mint-choc', 'overcast', 'pepper-grinder', 'redmond', 'smoothness', 'south-street', 'start', 'sunny', 'swanky-purse', 'trontastic', 'ui-darkness', 'ui-lightness', 'vader' );

						foreach ( $jquery_themes as $jquery_theme ) {
							$selected = ( $options_opts['jq_theme'] == $jquery_theme ) ? 'selected="selected"' : '';
							echo "<option value='" . esc_attr( $jquery_theme ) . "' $selected>$jquery_theme</option>";
						}
						?>
						</select>
						<br />
						<?php _e( 'Select jQuery theme used for user notifications.', 'wp_braintree_lang' ); ?>
					</td>
				</tr>
			</table>
		</div>
			<?php
		} elseif ( $active_tab === 'recaptcha' ) {  // reCaptcha tab
			?>
		<div class="postbox">
			<?php settings_fields( 'wp_braintree_recaptcha' ); ?>
			<p>
				<?php _e( 'Use Google reCAPTCHA if your payment forms are getting spammed by bots.', 'wp_braintree_lang' ); ?>
			</p>
			<p>
				<?php _e( 'To start using it, you need to <a href="http://www.google.com/recaptcha/admin" target="_blank">get Google reCAPTCHA API keys</a> for your site and enter the details below.', 'wp_braintree_lang' ); ?>
			</p>
			<table class="form-table">
				<tr valign="top">
					<th scope="row"><?php _e( 'Enable reCaptcha:', 'wp_braintree_lang' ); ?></th>
					<td><input id="re_enabled" type="checkbox" name="<?php echo $this->recaptcha_name; ?>[enabled]"
							value="1" <?php echo isset( $options_re['enabled'] ) ? 'checked' : ''; ?> /></td>
				</tr>
				<tr valign="top">
					<th scope="row"><?php _e( 'Site Key:', 'wp_braintree_lang' ); ?></th>
					<td><input id="re_site_key" type="text" name="<?php echo $this->recaptcha_name; ?>[site_key]"
							value="<?php echo esc_attr( $options_re['site_key'] ); ?>" /></td>
				</tr>
				<tr valign="top">
					<th scope="row"><?php _e( 'Secret Key:', 'wp_braintree_lang' ); ?></th>
					<td><input id="re_secret_key" type="text" name="<?php echo $this->recaptcha_name; ?>[secret_key]"
							value="<?php echo esc_attr( $options_re['secret_key'] ); ?>" /></td>
				</tr>
			</table>
		</div>
			<?php
		} elseif ( $active_tab == 'help' ) {  // HELP TAB
			?>
		<div class="postbox">
			<h3><?php _e( 'Acquire API Keys', 'wp_braintree_lang' ); ?></h3>
			<p>
				<?php _e( 'It is first necessary to register for an account with <a target="_blank" href="https://www.braintreepayments.com/">BrainTree</a>.', 'wp_braintree_lang' ); ?>
				<br />
				<?php _e( 'Once an account is acquired, the following information can be found by logging in and clicking "Account -> My User -> API Keys".', 'wp_braintree_lang' ); ?>
				<br />
				<?php _e( 'This plugin is set to run in the BrainTree "Production" environment. If desired, the plugin may be switched to the "Sandbox" environment via the appropriate option.', 'wp_braintree_lang' ); ?>
			</p>

			<h3><?php _e( 'Sandbox Mode', 'wp_braintree_lang' ); ?></h3>
			<p>
				<?php _e( 'By default, this plugin will perform all transactions assuming the API keys are from a BrainTree Live Production Account.', 'wp_braintree_lang' ); ?>
				<br />
				<?php _e( 'The plugin may be switched to perform transactions into a BrainTree Sandbox Account; commonly used for testing.', 'wp_braintree_lang' ); ?>
				<br />
				<?php _e( 'Remember; a BrainTree Production Account and a BrainTree Sandbox Account will have different API keys.', 'wp_braintree_lang' ); ?>
			</p>

			<h3><?php _e( 'Authorize Only', 'wp_braintree_lang' ); ?></h3>
			<p>
				<?php _e( 'By default, this plugin will submit transactions immediatly for settlement as they are transacted.', 'wp_braintree_lang' ); ?>
				<br />
				<?php _e( 'If preferred, the plugin can be switched to perform all transactions as an Authorized status.', 'wp_braintree_lang' ); ?>
				<br />
				<?php _e( 'This means the funds will be authorized by BrainTree, but will not be available until the transaction is manually submitted for settlement.', 'wp_braintree_lang' ); ?>
				<br />
				<?php _e( 'Authorized transactions may be manually submitted for settlement via the BrainTree admin control panel.', 'wp_braintree_lang' ); ?>
			</p>

			<!--
				<h3><?php //_e('Create Customer' ,'wp_braintree_lang'); ?></h3>
				<p>
			<?php //_e('By default, this plugin will display a "quick form" asking the customer only for the credit card number, card cvv code and card expiration date.' ,'wp_braintree_lang'); ?>
				<br />
			<?php //_e('If preferred, the plugin can be switched to create customer accounts on each transaction.' ,'wp_braintree_lang'); ?>
				<br />
			<?php //_e('This will add additional form fields for the customers first name, last name and postal code.' ,'wp_braintree_lang'); ?>
				<br />
			<?php //_e('Additional form options may be available in the future. Please ask your administrator to contact us with suggestions.' ,'wp_braintree_lang'); ?>
				</p>
				-->

			<h3><?php _e( 'jQuery Theme', 'wp_braintree_lang' ); ?></h3>
			<p>
				<?php _e( 'By default, this plugin will use the "Smoothness" jQuery dialog theme when display dialog alerts during the checkout process.', 'wp_braintree_lang' ); ?>
				<br />
				<?php _e( 'Additional jQuery themes are available to help match the look and appearance of the website.', 'wp_braintree_lang' ); ?>
				<br />
				<?php _e( "If you've never seen them before, take a moment to view a few. It's fun!", 'wp_braintree_lang' ); ?>
			</p>
		</div>
			<?php
		} elseif ( $active_tab == 'active_buttons' ) {  // ACTIVE BUTTONS TAB
			?>
		<div class="postbox">
			<h3><?php _e( 'Active Pages Currently Using Shortcode', 'wp_braintree_lang' ); ?></h3>
			<p>
				<?php _e( 'Here is a convenient list of all pages currently using the WP BrainTree button shortcode.', 'wp_braintree_lang' ); ?>
				<br />
				<?php _e( 'The titles link direcly to the permalinks of the pages (new window).', 'wp_braintree_lang' ); ?>
			</p>

			<?php
			// Let's be nice and display all pages using the button shortcode to the admin user
			$args      = array( 's' => '[wp_braintree_button ' );
			$the_query = new WP_Query( $args );
			if ( $the_query->have_posts() ) {
				echo '<ul>';
				while ( $the_query->have_posts() ) {
					$the_query->the_post();
					?>
			<li><a target="_blank" href="<?php the_permalink(); ?>"><?php the_title(); ?></a></li>
					<?php
				}
				echo '</ul>';
			} else {
				_e( 'There are currently no posts using the shortcode.', 'wp_braintree_lang' );
			}
			wp_reset_postdata();
			?>
		</div>
			<?php
		} else {  // API KEYS TAB
			?>
		<div class="postbox">
			<?php settings_fields( 'wp_braintree_api_keys' ); ?>
			<p>
				<?php _e( 'It is first necessary to register for an account with <a target="_blank" href="https://www.braintreepayments.com/">BrainTree</a>.', 'wp_braintree_lang' ); ?>
				<br />
				<?php _e( 'Once an account is acquired, the following information can be found by logging in and clicking "Account -> My User -> API Keys".', 'wp_braintree_lang' ); ?>
			</p>
			<table class="form-table">
				<tr valign="top">
					<th scope="row"><?php _e( 'Merchant ID:', 'wp_braintree_lang' ); ?></th>
					<td><input id="merchant_id" type="text" name="<?php echo $this->api_keys_name; ?>[merchant_id]"
							value="<?php echo esc_attr( $options_api['merchant_id'] ); ?>" /></td>
				</tr>
				<tr valign="top">
					<th scope="row"><?php _e( 'Public Key:', 'wp_braintree_lang' ); ?></th>
					<td><input id="public_key" type="text" name="<?php echo $this->api_keys_name; ?>[public_key]"
							value="<?php echo esc_attr( $options_api['public_key'] ); ?>" /></td>
				</tr>
				<tr valign="top">
					<th scope="row"><?php _e( 'Private Key:', 'wp_braintree_lang' ); ?></th>
					<td><input id="private_key" type="text" name="<?php echo $this->api_keys_name; ?>[private_key]"
							value="<?php echo esc_attr( $options_api['private_key'] ); ?>" /></td>
				</tr>
			</table>
		</div>
			<?php
		}
		?>
		<p class="submit">
			<input type="submit" class="button-primary"
				value="<?php esc_attr_e( 'Save Changes', 'wp_braintree_lang' ); ?>" />
		</p>
	</form>

	<div
		style="background: none repeat scroll 0 0 #FFF6D5;border: 1px solid #D1B655;color: #3F2502;margin: 10px 0;padding: 5px 5px 5px 10px;text-shadow: 1px 1px #FFFFFF;">
		<p>If you need a feature rich and supported plugin for accepting Braintree payments then check out our <a
				target="_blank"
				href="https://www.tipsandtricks-hq.com/wordpress-estore-plugin-complete-solution-to-sell-digital-products-from-your-wordpress-blog-securely-1059">WP
				eStore Plugin</a> (You will love the WP eStore Plugin).</p>
	</div>

</div> <!-- End wrap -->
		<?php
	}

	// Localize tinymce button and popup language strings
	public function localize_tinymce() {

		$arr[] = WP_CONTENT_DIR . '/plugins/wp_braintree/lang/mce_lang.php';
		return $arr;
	}

	// This function gets loaded into the HEAD of any post/page using the shortcode
	public function head_styles_scripts() {
		//register Braintree frontend scripts to enqueue them later if needed
		wp_register_script( 'wp-braintree-braintree-client', 'https://js.braintreegateway.com/web/3.88.4/js/client.min.js', null, null );
		wp_register_script( 'wp-braintree-braintree-hosted-fields', 'https://js.braintreegateway.com/web/3.88.4/js/hosted-fields.min.js', null, null );
		wp_register_script( 'wp-braintree-braintree-three-d-secure', 'https://js.braintreegateway.com/web/3.88.4/js/three-d-secure.min.js', null, null );
		wp_register_style( 'wp_braintree_styles_front', plugins_url( '/css/front_page.css', __FILE__ ), null, WP_BRAINTREE_PLUGIN_VERSION );    // Apply frontend styles
		wp_register_script( 'wp_braintree_scripts_front', plugins_url( '/js/front_page.js', __FILE__ ), array( 'jquery' ), WP_BRAINTREE_PLUGIN_VERSION, true );  // Apply frontend scripts
		wp_register_script( 'wp_braintree_recaptcha_script', 'https://www.google.com/recaptcha/api.js?onload=wp_braintree_re_loaded&render=explicit' );
	}

	// End public function
	// Output the shortcode
	public function wp_braintree_button_shortcode( $atts ) {

		$opts        = get_option( $this->option_name );
		$success_url = ( isset( $opts['success_url'] ) && ! empty( $opts['success_url'] ) ) ? $opts['success_url'] : home_url();

		$button_form = '';

		$re_enabled = false;
		$re_opts    = get_option( $this->recaptcha_name );

		if ( $this->buttons_on_page === 0 ) {
			$select_theme = isset( $opts['jq_theme'] ) ? $opts['jq_theme'] : 'smoothness';

			if ( ! wp_script_is( 'jquery' ) ) {
				wp_enqueue_script( 'jquery' );
			}
			if ( ! wp_script_is( 'jquery-ui-dialog' ) ) {
				wp_enqueue_script( 'jquery-ui-dialog' );
			}

			if ( isset( $re_opts['enabled'] ) && $re_opts['enabled'] ) {
				$re_enabled = true;
				wp_enqueue_script( 'wp_braintree_recaptcha_script' );
			}

			wp_enqueue_style( 'jquery-ui-theme', plugins_url( '/css/themes/' . $select_theme . '/jquery-ui.css', __FILE__ ) );    // jquery ui styling
			// Localize js langs
			wp_localize_script(
				'wp_braintree_scripts_front',
				'wp_braintree_scripts_front_js_vars',
				array(
					'success_url'      => $success_url,
					'cc_no_valid'      => __( 'The Card Number is not a valid number.', 'wp_braintree_lang' ),
					'cc_digits'        => __( 'The Card Number must be between nine (9) and sixteen (16) digits.', 'wp_braintree_lang' ),
					'cvv_number'       => __( 'The CVV Number is not a valid number.', 'wp_braintree_lang' ),
					'cvv_digits'       => __( 'The CVV Number must be three (3) digits exactly.', 'wp_braintree_lang' ),
					'exp_month_number' => __( 'The Expiration Month is not a valid number.', 'wp_braintree_lang' ),
					'exp_month_digits' => __( 'The Expiration Month must be two (2) digits exactly.', 'wp_braintree_lang' ),
					'exp_year_number'  => __( 'The Expiration Year is not a valid number.', 'wp_braintree_lang' ),
					'exp_year_digits'  => __( 'The Expiration Year must be four (4) digits exactly.', 'wp_braintree_lang' ),
					'check_fields'     => __( 'Please check fields.', 'wp_braintree_lang' ),
					'fill_fields'      => __( 'Please fill fields.', 'wp_braintree_lang' ),
					'val_errors'       => __( 'Validation Errors:', 'wp_braintree_lang' ),
					'error_occurred'   => __( 'Error Occurred:', 'wp_braintree_lang' ),
					'confirm_trans'    => __( 'You are about to submit this transaction. Continue?', 'wp_braintree_lang' ),
				)
			);
			ob_start();
			?>
<div id="wp-braintree-3ds-modal-container" class="wp-braintree-3ds-modal-container" style="display: none;">
	<div id="wp-braintree-3ds-modal" class="wp-braintree-3ds-modal">
		<div class="wp-braintree-3ds-modal-header">
			<span class="wp-braintree-3ds-modal-close-btn">&times;</span>
		</div>
		<div id="wp-braintree-3ds-modal-content" class="wp-braintree-3ds-modal-content">
		</div>
	</div>
</div>
<div id="wp-braintree-spinner-container" class="wp-braintree-spinner-container" style="display: none;">
	<div class="wp-braintree-spinner"><i></i><i></i><i></i><i></i></div>
</div>
			<?php
			$button_form = ob_get_clean();
		}

		if ( isset( $_POST['wp-braintree-nonce'] ) && ! empty( $_POST['wp-braintree-nonce'] ) ) {
			if ( $re_enabled ) {
				$re_payload = filter_input( INPUT_POST, 'g-recaptcha-response', FILTER_SANITIZE_STRING );
				$res        = wp_remote_post(
					'https://www.google.com/recaptcha/api/siteverify',
					array(
						'body' => array(
							'secret'   => $re_opts['secret_key'],
							'response' => $re_payload,
						),
					)
				);
				if ( is_wp_error( $res ) ) {
					wp_die( $res->get_error_message() );
				}
				if ( 200 !== $res['response']['code'] ) {
					wp_die( 'HTTP error occurred during reCaptcha check: ' . $res['response']['code'] );
				}

				$result = json_decode( $res['body'], true );

				if ( ! $result['success'] ) {
					wp_die( 'Error(s) occurred during reCaptcha check: ' . implode( ',', $result['error-codes'] ) );
				}
			}

			//payment has been posted, let's process it
			$opts_api = get_option( $this->api_keys_name );

			$this->wp_braintree_get_api();  // Call braintree api above
			// Enqueue scripts and styles on front page (only if they are not already called)

			$wpb_nonce  = sanitize_text_field( $_POST['wp-braintree-nonce'] );
			$wpb_amount = sanitize_text_field( $_POST['item_amount'] );

			//let's unset wp-braintree-nonce so it wouldn't be processed again if there are multiple buttons on a page
			unset( $_POST['wp-braintree-nonce'] );

			// Setup query string to be processed by braintree
			// This string gets added to the redirect url (has to be current WP post/page displaying the form)
			// Find out if user wants to authorize only
			// If not, we are going to submit the transaction for settlement immediately
			$auth_only = $opts['auth_only'] == '1' ? 'yes' : 'no';

			$full_name = sanitize_text_field( $_POST['wp-braintree-name'] );

			$parts      = explode( ' ', $full_name );
			$last_name  = array_pop( $parts );
			$first_name = implode( ' ', $parts );

			$customer = array(
				'firstName' => $first_name,
				'lastName'  => $last_name,
				'email'     => sanitize_email( $_POST['wp-braintree-email'] ),
			);

			try {
				if ( $auth_only == 'no' ) {
					// Submit transaction for settlement
					$result = Braintree_Transaction::sale(
						array(
							'amount'             => $wpb_amount,
							'paymentMethodNonce' => $wpb_nonce,
							'channel'            => 'TipsandTricks_SP',
							'customer'           => $customer,
							'options'            => array(
								'submitForSettlement' => true,
							),
						)
					);
				} else {
					//Authorize only
					$result = Braintree_Transaction::sale(
						array(
							'amount'             => $wpb_amount,
							'channel'            => 'TipsandTricks_SP',
							'paymentMethodNonce' => $wpb_nonce,
							'customer'           => $customer,
						)
					);
				}
			} catch ( Excepction $e ) {
				$eClass = get_class( $e );
				$ret    = 'Braintree Error: ' . $eClass;
				if ( $eClass == 'Braintree\Exception\Authentication' ) {
					$ret = __( 'Braintree Authentication Error. Check your API keys.', 'wp_braintree_lang' );
				}
				wp_die( $ret );
			}

			if ( $result->success ) {

				$wp_braintree_order_id = sanitize_text_field( $_POST['wpb_order_id'] );
				if ( ! isset( $wp_braintree_order_id ) || empty( $wp_braintree_order_id ) ) {
					wp_die( __( 'Error! Braintree order id could not be found!', 'wp_braintree_lang' ) );
				}
				//Item name
				$trans_name = 'wp-braintree-' . $wp_braintree_order_id;
				$item_name  = get_transient( $trans_name ); //Read the item name from the system.
				if ( ! isset( $item_name ) || empty( $item_name ) ) {
					wp_die( __( 'Item name could not be found!', 'wp_braintree_lang' ) );
				}
				//Item price
				$trans_name = 'wp-braintree-' . md5( $item_name );
				$item_price = get_transient( $trans_name ); //Read the price for this item from the system.
				if ( ! isset( $item_price ) || ! is_numeric( $item_price ) ) {
					wp_die( __( 'Error! Item price is missing or invalid!', 'wp_braintree_lang' ) );
				}
				if ( $result->transaction->amount < $item_price ) {
					$error_message  = __( 'Price Validation Error', 'wp_braintree_lang' ) . '<br />';
					$error_message .= sprintf( __( 'Item Price: %1$d, Amount paid: %2$d', 'wp_braintree_lang' ), $item_price, $result->transaction->amount );
					wp_die( $error_message );
				}
				$url_data = '';
				if ( isset( $_SESSION[ $wp_braintree_order_id ] ) ) {
					$args     = $_SESSION[ $wp_braintree_order_id ];
					$url_data = esc_url_raw( $args['url'] );
				}

				$payment_data              = array();
				$payment_data['name']      = sanitize_text_field( $_POST['wp-braintree-name'] );
				$payment_data['email']     = sanitize_email( $_POST['wp-braintree-email'] );
				$payment_data['amount']    = $item_price;
				$payment_data['item_name'] = $item_name;
				$payment_data['trans_id']  = $result->transaction->id;
				$payment_data['order_id']  = $wp_braintree_order_id;
				$payment_data['date']      = time();

				wp_braintree_post::insert_post( $payment_data );

				//Trigger the purchase success action hook
				do_action( 'wp_braintree_payment_completed', $wp_braintree_order_id );

				echo( "<div id='dialog-message-success' title='" . __( 'Purchase Success!', 'wp_braintree_lang' ) . "'>" );
				echo( __( 'Congratulations! The transaction has completed successfully. Please keep the Transaction ID for your records.', 'wp_braintree_lang' ) );
				echo( '<br /><br />' );
				if ( ! empty( $url_data ) ) {
					echo '<a href="' . esc_url( $url_data ) . '">' . __( 'Click here', 'wp_braintree_lang' ) . '</a>';
					echo( __( ' to download the item.<br /><br />', 'wp_braintree_lang' ) );
				}
				echo( '<strong>' . __( 'Transaction ID:', 'wp_braintree_lang' ) . ' </strong>' . $result->transaction->id );
				echo( '</div>' );
			}
			// I have no idea what this is for.  I'm assuming it contains output if the braintree server encounters an error.
			// I have not come across a situation where this response was available.
			elseif ( $result->transaction ) {
				echo( "<div id='dialog-message-error' title='" . __( 'Transaction Error', 'wp_braintree_lang' ) . "'>" );
				echo( __( 'Error: ', 'wp_braintree_lang' ) . $result->message );
				echo( '<br/>' );
				echo( __( 'Code: ', 'wp_braintree_lang' ) . $result->transaction->processorResponseCode );
				echo( '<br /><br />' );
				echo( __( "Please use the browsers 'back' button to verify the input fields, and try again.", 'wp_braintree_lang' ) );
				echo( '</div>' );
			}
			// Else the transaction contains validation errors
			else {
				echo( "<div id='dialog-message-error' title='" . __( 'Validation Error', 'wp_braintree_lang' ) . "'>" );
				echo( __( 'Validation errors:<br/>', 'wp_braintree_lang' ) );
				foreach ( ( $result->errors->deepAll() ) as $error ) {
					echo( '- ' . $error->message . '<br/>' );
				}
				echo( '<br />' . __( 'No transaction was submitted for processing.', 'wp_braintree_lang' ) );
				echo( '<br />' );
				echo( __( "Please press the 'Back' button to verify the input fields, and re-submit.", 'wp_braintree_lang' ) );
				echo( '</div>' );
			}
		}
		// Extract shortcode args
		extract(
			shortcode_atts(
				array(
					'item_name'   => '',
					'item_amount' => '',
					'url'         => '',
					'show_form'   => '',
					'button_text' => 'Buy Now',
				),
				$atts
			)
		);
		if ( empty( $item_name ) ) {
			return 'Item name cannot be left empty';
		}
		if ( ! is_numeric( $item_amount ) ) {
			return 'Invalid item price';
		}
		// Call braintree api above
		$this->wp_braintree_get_api();

		//Generate an order ID to reference the transaction
		$order_id = md5( uniqid() );

		// Get url of current page (used for the redirect - which adds a hash to the current page - which MUST be read from the redirect url)
		$cur_page = $this->curPageURL();

		if ( ! empty( $url ) ) {//Append the order ID in the redirect URL
			$_SESSION[ $order_id ] = $atts;
		}
		$cur_page = add_query_arg( array( 'wpb_order_id' => $order_id ), $cur_page );

		// Setup protected table data for the price of the item.
		// This prevents tampering of the price via the browser.
		// Other values may be passed (and protected) by adding to this array
		try {
			$clientToken = Braintree_ClientToken::generate();
		} catch ( Exception $e ) {
			$eClass = get_class( $e );
			$ret    = 'Braintree Error: ' . $eClass;
			if ( $eClass == 'Braintree\Exception\Authentication' ) {
				$ret = __( 'Braintree Authentication Error. Check your API keys.', 'wp_braintree_lang' );
			}
			return '<b style="color:red;">' . $ret . '</b>';
		}

		// Begin shortcode div
		$button_form .= '<link rel="stylesheet" href="' . plugin_dir_url( __FILE__ ) . 'css/pure-min.css">';
		$button_form .= '<link rel="stylesheet" href="' . plugin_dir_url( __FILE__ ) . 'css/forms-min.css">';
		$button_form .= '<link rel="stylesheet" href="' . plugin_dir_url( __FILE__ ) . 'css/grids-responsive-min.css">';
		$button_form .= '<div class="wp_braintree_button">';

		if ( empty( $button_text ) ) {
			$button_text = 'Buy Now';
		}
		$trans_name = 'wp-braintree-' . $order_id; //Create key using the order ID.
		set_transient( $trans_name, $item_name, 24 * 3600 ); //Save the item name for this item for 2 hours.
		$trans_name = 'wp-braintree-' . md5( $item_name ); //Create key using the item name.
		set_transient( $trans_name, $item_amount, 24 * 3600 ); //Save the price for this item for 2 hours.
		// Generate the associated form output for payment processing
		// These are hidden on page load via jquery.
		// Clicking the button[code] above.. shows/hides the associated form.

		ob_start();
		?>

<div class="dialog-form<?php echo empty( $show_form ) ? '' : '-show'; ?>"
	data-wp-braintree-button-id="<?php echo $this->buttons_on_page; ?>"
		<?php echo empty( $show_form ) ? 'style="display: none;"' : ''; ?>>
	<h3><?php echo __( 'Credit Card Transaction Form', 'wp_braintree_lang' ); ?></h3>
	<form method="POST" id="braintree-payment-form-<?php echo $this->buttons_on_page; ?>"
		data-wp-braintree-button-id="<?php echo $this->buttons_on_page; ?>"
		class="braintree-payment-form pure-form pure-form-stacked" style="position:relative;">

		<?php
		if ( $re_enabled ) {
			?>
		<style>
		.wp-braintree-re-cont div {
			margin: 0 auto;
		}
		</style>
		<div id="wp-braintree-re-form-<?php echo $this->buttons_on_page; ?>" style="display:none;">
			<div id="wp-braintree-re-form-back-<?php echo $this->buttons_on_page; ?>"
				style="position:absolute;width:100%;height:100%;background:white;opacity:0.9;"></div>
			<div id="wp-braintree-re-form-cont-<?php echo $this->buttons_on_page; ?>"
				style="position:absolute;width:100%;height:100%;">
				<div class="wp-braintree-re-cont" id="wp-braintree-re-cont-<?php echo $this->buttons_on_page; ?>"
					style="margin:0 auto;position:absolute;top:40%;text-align:center;width:100%;"></div>
			</div>
		</div>
			<?php
		}
		?>

		<input type="hidden" id="wp-braintree-nonce-<?php echo $this->buttons_on_page; ?>" name="wp-braintree-nonce"
			value="">
		<input type="hidden" name="wp-braintree-submit" value="1">
		<input type="hidden" name="item_name" value="<?php echo $item_name; ?>" />
		<input type="hidden" name="item_amount" value="<?php echo $item_amount; ?>" />
		<input type="hidden" name="wpb_order_id" value="<?php echo $order_id; ?>" />
		<fieldset>
			<div class="pure-g">
				<div class="pure-u-1 pure-u-md-1-2">
					<div class="pure-control-group">
						<label><?php echo __( 'Item Name', 'wp_braintree_lang' ); ?></label>
						<span name="item_name_form"><?php echo $item_name; ?></span>
					</div>
				</div>
				<div class="pure-u-1 pure-u-md-1-2">
					<div class="pure-control-group">
						<label><?php echo __( 'Item Price', 'wp_braintree_lang' ); ?></label>
						<span name="item_price_form"><?php echo $item_amount; ?></span>
					</div>
				</div>
			</div>
		</fieldset>
		<fieldset>
			<div class="pure-control-group">
				<label for="wp-braintree-name"><?php echo __( 'Name', 'wp_braintree_lang' ); ?></label>
				<input type="text" id="wp-braintree-name-<?php echo $this->buttons_on_page; ?>" name="wp-braintree-name"
					placeholder="<?php echo __( 'Name', 'wp_braintree_lang' ); ?>" required>
			</div>
			<div class="pure-control-group">
				<label for="wp-braintree-email"><?php echo __( 'Email', 'wp_braintree_lang' ); ?></label>
				<input type="email" id="wp-braintree-email-<?php echo $this->buttons_on_page; ?>"
					name="wp-braintree-email" placeholder="<?php echo __( 'Email', 'wp_braintree_lang' ); ?>" required>
			</div>
			<div class="pure-control-group">
				<label><?php echo __( 'Card Number', 'wp_braintree_lang' ); ?></label>
				<div class="wpb-form-control" id="wp-braintree-card-number-<?php echo $this->buttons_on_page; ?>"
					data-braintree-field="number"></div>
			</div>
			<div class="pure-g">
				<div class="pure-u-1 pure-u-md-2-3">
					<div class="pure-control-group">
						<label><?php echo __( 'Expiration (MM / YY)', 'wp_braintree_lang' ); ?></label>
						<div class="wpb-form-control wp-braintree-expiration-month"
							id="wp-braintree-expiration-month-<?php echo $this->buttons_on_page; ?>"></div>
						<div class="wpb-slash"> / </div>
						<div class="wpb-form-control wp-braintree-expiration-year"
							id="wp-braintree-expiration-year-<?php echo $this->buttons_on_page; ?>"></div>
					</div>
				</div>
				<div class="pure-u-1 pure-u-md-1-3">
					<div class="pure-control-group">
						<label><?php echo __( 'CVV', 'wp_braintree_lang' ); ?></label>
						<div class="wpb-form-control" id="wp-braintree-cvv-<?php echo $this->buttons_on_page; ?>"
							data-braintree-field="cvv"></div>
					</div>
				</div>
			</div>
		</fieldset>
		<div id="wp-braintree-re-container-<?php echo $this->buttons_on_page; ?>"></div>
		<div class="pure-controls">
			<button type="submit" class="pure-button pure-button-primary"
				data-wp-braintree-button-id="<?php echo $this->buttons_on_page; ?>"><?php echo esc_attr( $button_text ); ?></button>
		</div>
	</form>
</div>
		<?php if ( empty( $show_form ) ) { ?>
<div class="wp_braintree_button_div">
	<form name="wp_braintree_button_submit" action="" method="POST" class="pure-form pure-form-stacked">
		<button type="button" class="pure-button pure-button-primary submit_buy_now"
			data-wp-braintree-button-id="<?php echo $this->buttons_on_page; ?>"><?php echo esc_attr( $button_text ); ?></button>
	</form>
</div>
		<?php } ?>
		<?php
		wp_localize_script(
			'wp_braintree_scripts_front',
			'wp_braintree_buttons_data_' . $this->buttons_on_page,
			array(
				'client_token' => $clientToken,
				're_enabled'   => $re_enabled,
				're_site_key'  => isset( $re_opts['site_key'] ) ? $re_opts['site_key'] : '',
			)
		);
		wp_enqueue_script( 'wp-braintree-braintree-client' );
		wp_enqueue_script( 'wp-braintree-braintree-hosted-fields' );
		wp_enqueue_script( 'wp-braintree-braintree-three-d-secure' );
		wp_enqueue_style( 'wp_braintree_styles_front', plugins_url( '/css/front_page.css', __FILE__ ), null, WP_BRAINTREE_PLUGIN_VERSION );    // Apply frontend styles
		wp_enqueue_script( 'wp_braintree_scripts_front', plugins_url( '/js/front_page.js', __FILE__ ), array( 'jquery' ), WP_BRAINTREE_PLUGIN_VERSION, true );  // Apply frontend scripts

		$this->buttons_on_page ++;

		$button_form .= ob_get_clean();
		return $button_form;
	}

	// This function will populate the options if the plugin is activated for the first time.
	// It will also protect the options if the plugin is deactivated (common in troublshooting WP related issues)
	// We may want to add an option to remove DB entries...
	public function activate() {

		$options     = get_option( $this->option_name );
		$options_api = get_option( $this->api_keys_name );

		$options_api['merchant_id'] = isset( $options_api['merchant_id'] ) ? $options_api['merchant_id'] : '';
		$options_api['public_key']  = isset( $options_api['public_key'] ) ? $options_api['public_key'] : '';
		$options_api['private_key'] = isset( $options_api['private_key'] ) ? $options_api['private_key'] : '';

		$options['sandbox_mode'] = isset( $options['sandbox_mode'] ) ? $options['sandbox_mode'] : '0';
		$options['auth_only']    = isset( $options['auth_only'] ) ? $options['auth_only'] : '0';
		//$options['create_customer'] = isset($options['create_customer']) ? $options['create_customer'] : '0';
		$options['success_url'] = isset( $options['success_url'] ) ? $options['success_url'] : '';
		$options['jq_theme']    = isset( $options['jq_theme'] ) ? $options['jq_theme'] : 'smoothness';

		update_option( $this->option_name, $options );
		update_option( $this->api_keys_name, $options_api );
	}

	// Add the tinymce buttton and js file
	public function wp_braintree_tinymce_button() {

		if ( ! current_user_can( 'edit_posts' ) && ! current_user_can( 'edit_pages' ) ) {
			return;
		}
		if ( get_user_option( 'rich_editing' ) == 'true' ) {
			add_filter( 'mce_external_plugins', array( $this, 'add_plugin' ) );
			add_filter( 'mce_buttons', array( $this, 'register_button' ) );
		}
	}

	// Add tinymce button js file
	public function add_plugin( $plugin_array ) {
		$plugin_array['wp_braintree'] = plugins_url( '/js/editor_plugin.js', __FILE__ );
		return $plugin_array;
	}

	// Add tinymce button to editor
	public function register_button( $buttons ) {
		array_push( $buttons, '|', 'wp_braintree' );
		return $buttons;
	}

	// Function used to get current page url
	public function curPageURL() {

		$pageURL  = 'http';
		$is_https = isset( $_SERVER['HTTPS'] ) ? $_SERVER['HTTPS'] : '';

		if ( $is_https == 'on' ) {
			$pageURL .= 's';
		}
		$pageURL .= '://';

		if ( $_SERVER['SERVER_PORT'] != '80' ) {
			$pageURL .= $_SERVER['SERVER_NAME'] . ':' . $_SERVER['SERVER_PORT'] . $_SERVER['REQUEST_URI'];
		} else {
			$pageURL .= $_SERVER['SERVER_NAME'] . $_SERVER['REQUEST_URI'];
		}

		return $pageURL;
	}

	// Used for tabbed content
	public function wp_braintree_admin_tabs( $current = 'api_keys' ) {

		$tabs = array(
			'api_keys'       => 'API Keys',
			'options'        => 'Options',
			'recaptcha'      => 'reCAPTCHA',
			'help'           => 'Help',
			'active_buttons' => 'Active Buttons',
		);
		echo '<div id="icon-themes" class="icon32"><br></div>';
		echo '<h2 class="nav-tab-wrapper">';
		foreach ( $tabs as $tab => $name ) {
			$class = ( $tab == $current ) ? ' nav-tab-active' : '';
			echo "<a class='nav-tab$class' href='?page=wp_braintree_options&tab=$tab'>$name</a>";
		}
		echo '</h2>';
	}

}

$wp_braintree = new wp_braintree();
