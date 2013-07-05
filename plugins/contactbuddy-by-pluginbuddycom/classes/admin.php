<?php
if ( !class_exists( "contactbuddy_admin" ) ) {
    class contactbuddy_admin {
	
		function contactbuddy_admin(&$parent) {
			$this->_parent = &$parent;
			$this->_var = &$parent->_var;
			$this->_name = &$parent->_name;
			$this->_options = &$parent->_options;
			$this->_pluginPath = &$parent->_pluginPath;
			$this->_pluginURL = &$parent->_pluginURL;
			$this->_selfLink = &$parent->_selfLink;

			add_action('admin_menu', array(&$this, 'admin_menu')); // Add menu in admin.
			// SHORTCODE BUTTON
			add_action( 'media_buttons_context', array( &$this, 'add_post_button' ) );
		}
		function alert( $arg1, $arg2 = false ) {
			$this->_parent->alert( $arg1, $arg2 );
		}
		function nonce() {
			wp_nonce_field( $this->_parent->_var . '-nonce' );
		}
		function savesettings() {
			check_admin_referer( $this->_parent->_var . '-nonce' );
			if(empty($_POST[$this->_var . '-recipemail'])) {
				$this->_parent->_errors[] = 'recipemail';
				$this->_parent->_showErrorMessage( 'Recipient email address is empty' );
			}
			if(empty($_POST[$this->_var . '-subject'])) {
				$this->_parent->_errors[] = 'subject';
				$this->_parent->_showErrorMessage( 'Subject is empty' );
			}
			if($_POST[$this->_var . '-recaptcha'] == '1') {
				if(empty($_POST[$this->_var . '-recaptcha-pubkey'])) {
					$this->_parent->_errors[] = 'recaptcha-pubkey';
					$this->_parent->_showErrorMessage( 'If you are using recaptcha you must input a reCAPTCHA public key' );
				}
				if(empty($_POST[$this->_var . '-recaptcha-privkey'])) {
					$this->_parent->_errors[] = 'recaptcha-privkey';
					$this->_parent->_showErrorMessage( 'If you are using recaptcha you must input a reCAPTCHA private key' );
				}
			}
			
			if ( isset( $this->_parent->_errors ) ) {
				$this->_parent->_showErrorMessage( 'Please correct the ' . ngettext( 'error', 'errors', count( $this->_parent->_errors ) ) . ' in order to save changes.' );
			} else {

				$this->_options['recipemail'] = $_POST[$this->_var . '-recipemail'];
				$this->_options['subject'] = $_POST[$this->_var . '-subject'];
				$this->_options['recaptcha'] = $_POST[$this->_var . '-recaptcha'];
				$this->_options['recaptcha-pubkey'] = $_POST[$this->_var . '-recaptcha-pubkey'];
				$this->_options['recaptcha-privkey'] = $_POST[$this->_var . '-recaptcha-privkey'];
				$this->_options['defaultcss'] = $_POST[$this->_var . '-defaultcss'];
				
				$this->_parent->save();
				$this->alert( 'Settings saved...' );
			}
		}

		
		
		function admin_scripts() {
			//wp_enqueue_script( 'jquery' );
			wp_enqueue_script( 'pluginbuddy-tooltip-js', $this->_parent->_pluginURL . '/js/tooltip.js' );
			wp_print_scripts( 'pluginbuddy-tooltip-js' );
			wp_enqueue_script( 'pluginbuddy-swiftpopup-js', $this->_parent->_pluginURL . '/js/swiftpopup.js' );
			wp_print_scripts( 'pluginbuddy-swiftpopup-js' );
			wp_enqueue_script( 'pluginbuddy-'.$this->_var.'-admin-js', $this->_parent->_pluginURL . '/js/admin.js' );
			wp_print_scripts( 'pluginbuddy-'.$this->_var.'-admin-js' );
			echo '<link rel="stylesheet" href="'.$this->_pluginURL . '/css/admin.css" type="text/css" media="all" />';
		}

		/**
		 *	get_feed()
		 *
		 *	Gets an RSS or other feed and inserts it as a list of links...
		 *
		 *	$feed		string		URL to the feed.
		 *	$limit		integer		Number of items to retrieve.
		 *	$append		string		HTML to include in the list. Should usually be <li> items including the <li> code.
		 *	$replace	string		String to replace in every title returned. ie twitter includes your own username at the beginning of each line.
		 */
		function get_feed( $feed, $limit, $append = '', $replace = '' ) {
			require_once(ABSPATH.WPINC.'/feed.php');  
			$rss = fetch_feed( $feed );
			if (!is_wp_error( $rss ) ) {
				$maxitems = $rss->get_item_quantity( $limit ); // Limit 
				$rss_items = $rss->get_items(0, $maxitems); 
				
				echo '<ul class="pluginbuddy-nodecor">';

				$feed_html = get_transient( md5( $feed ) );
				if ( $feed_html == '' ) {
					foreach ( (array) $rss_items as $item ) {
						$feed_html .= '<li>- <a href="' . $item->get_permalink() . '">';
						$title =  $item->get_title(); //, ENT_NOQUOTES, 'UTF-8');
						if ( $replace != '' ) {
							$title = str_replace( $replace, '', $title );
						}
						if ( strlen( $title ) < 30 ) {
							$feed_html .= $title;
						} else {
							$feed_html .= substr( $title, 0, 32 ) . ' ...';
						}
						$feed_html .= '</a></li>';
					}
					set_transient( md5( $feed ), $feed_html, 300 ); // expires in 300secs aka 5min
				}
				echo $feed_html;
				
				echo $append;
				echo '</ul>';
			} else {
				echo 'Temporarily unable to load feed...';
			}
		}

		function add_post_button( $content ){
			return $content . '
				<a onclick="cb_add_post();" title="Add simple contact form"><img src="' . $this->_pluginURL . '/images/atsymbol.png" alt="Add simple contact form" /></a>
				<script>
					function cb_add_post() {
						var win = window.dialogArguments || opener || parent || top;
						win.send_to_editor( \'[contactbuddy]\' );
					}
				</script>';
		}

		function view_gettingstarted() {
			// Needed for fancy boxes...
			wp_enqueue_style('dashboard');
			wp_print_styles('dashboard');
			wp_enqueue_script('dashboard');
			wp_print_scripts('dashboard');
			// Load scripts and CSS used on this page.
			$this->admin_scripts();
			
			// If they clicked the button to reset plugin defaults...
			if (!empty($_POST['reset_defaults'])) {
				$this->_options = $this->_parent->_defaults;
				$this->_parent->save();
				$this->_parent->_showStatusMessage( 'Plugin settings have been reset to defaults.' );
			}
			?>
			
			<div class="wrap">
				<div class="postbox-container" style="width:70%;">
					<h2>Getting Started with <?php echo $this->_parent->_name; ?> v<?php echo $this->_parent->_version; ?></h2>
					
					
					
					
					
					<p>
						ContactBuddy allows you to easily
						add a contact form to your site by placing it
						in a widget or using shortcode button in the
						posts and page editors.
					</p>
					<ol>
						<li>Go to the <a href="<?php echo $this->_selfLink; ?>-settings">ContactBuddy Settings</a> link.</li>
						<li>Set the "Recipient email address" to the address you would like the entries to be sent to.</li>
						<li>Set the "Subject" to the subject you would like to appear on the emails from the ContactBuddy form.</li>
						<li>Choose to enable or disable reCAPTCHA validation for your ContactBuddy form.</li>
						<li>
							Add into your widgets on your site or
							add into posts and pages by clicking the shortcode button <img src="<?php echo $this->_pluginURL . '/images/atsymbol.png'; ?>" alt="shortcodebutton" /> in the post and page editors.
							
						</li>
					</ol>
					
					
					
					
					
					
					<h3>Version History</h3>
					<textarea rows="7" cols="65"><?php readfile( $this->_parent->_pluginPath . '/history.txt' ); ?></textarea>
					<br /><br />
					<script type="text/javascript">
						jQuery(document).ready(function() {
							jQuery("#pluginbuddy_debugtoggle").click(function() {
								jQuery("#pluginbuddy_debugtoggle_div").slideToggle();
							});
						});
					</script>
					
					<a id="pluginbuddy_debugtoggle" class="button secondary-button">Debugging Information</a>
					<div id="pluginbuddy_debugtoggle_div" style="display: none;">
						<h3>Debugging Information</h3>
						<?php
						echo '<textarea rows="7" cols="65">';
						echo 'Plugin Version = '.$this->_name.' '.$this->_parent->_version.' ('.$this->_parent->_var.')'."\n";
						echo 'WordPress Version = '.get_bloginfo("version")."\n";
						echo 'PHP Version = '.phpversion()."\n";
						global $wpdb;
						echo 'DB Version = '.$wpdb->db_version()."\n";
						echo "\n".serialize($this->_options);
						echo '</textarea>';
						?>
						<p>
						<form method="post" action="<?php echo $this->_selfLink; ?>">
							<input type="hidden" name="reset_defaults" value="true" />
							<input type="submit" name="submit" value="Reset Plugin Settings & Defaults" id="reset_defaults" class="button secondary-button" onclick="if ( !confirm('WARNING: This will reset all settings associated with this plugin to their defaults. Are you sure you want to do this?') ) { return false; }" />
						</form>
						</p>
					</div>
					<br /><br /><br />
					<a href="http://pluginbuddy.com" style="text-decoration: none;"><img src="<?php echo $this->_pluginURL; ?>/images/pluginbuddy.png" style="vertical-align: -3px;" /> PluginBuddy.com</a><br /><br />
				</div>
				<div class="postbox-container" style="width:20%; margin-top: 35px; margin-left: 15px;">
					<div class="metabox-holder">	
						<div class="meta-box-sortables">
							<div id="breadcrumbslike" class="postbox">
								<div class="handlediv" title="Click to toggle"><br /></div>
								<h3 class="hndle"><span>Want more form options?</span></h3>
								<div class="inside">
									<ul>
										<li>Try <a href="https://www.e-junkie.com/ecom/gb.php?cl=54585&c=ib&aff=14589" target="_blank">Gravity forms</a></li>
									</ul>
								</div>
							</div>
							<div id="breadcrumbslike" class="postbox">
								<div class="handlediv" title="Click to toggle"><br /></div>
								<h3 class="hndle"><span>Things to do...</span></h3>
								<div class="inside">
									<ul class="pluginbuddy-nodecor">
										<li>- <a href="http://twitter.com/home?status=<?php echo urlencode('Check out this awesome plugin, ' . $this->_parent->_name . '! ' . $this->_parent->_url . ' @pluginbuddy'); ?>" title="Share on Twitter" onClick="window.open(jQuery(this).attr('href'),'ithemes_popup','toolbar=0,status=0,width=820,height=500,scrollbars=1'); return false;">Tweet about this plugin.</a></li>
										<li>- <a href="http://pluginbuddy.com/purchase/">Check out PluginBuddy plugins.</a></li>
										<li>- <a href="http://pluginbuddy.com/purchase/">Check out iThemes themes.</a></li>
										<li>- <a href="http://secure.hostgator.com/cgi-bin/affiliates/clickthru.cgi?id=ithemes">Get HostGator web hosting.</a></li>
									</ul>
								</div>
							</div>

							<div id="breadcrumsnews" class="postbox">
								<div class="handlediv" title="Click to toggle"><br /></div>
								<h3 class="hndle"><span>Latest news from PluginBuddy</span></h3>
								<div class="inside">
									<p style="font-weight: bold;">PluginBuddy.com</p>
									<?php $this->get_feed( 'http://pluginbuddy.com/feed/', 5 );  ?>
									<p style="font-weight: bold;">Twitter @pluginbuddy</p>
									<?php
									$twit_append = '<li>&nbsp;</li>';
									$twit_append .= '<li><img src="'.$this->_pluginURL.'/images/twitter.png" style="vertical-align: -3px;" /> <a href="http://twitter.com/pluginbuddy/">Follow @pluginbuddy on Twitter.</a></li>';
									$twit_append .= '<li><img src="'.$this->_pluginURL.'/images/feed.png" style="vertical-align: -3px;" /> <a href="http://pluginbuddy.com/feed/">Subscribe to RSS news feed.</a></li>';
									$twit_append .= '<li><img src="'.$this->_pluginURL.'/images/email.png" style="vertical-align: -3px;" /> <a href="http://pluginbuddy.com/subscribe/">Subscribe to Email Newsletter.</a></li>';
									$this->get_feed( 'http://twitter.com/statuses/user_timeline/108700480.rss', 5, $twit_append, 'pluginbuddy: ' );
									?>
								</div>
							</div>
							
							<div id="breadcrumbssupport" class="postbox">
								<div class="handlediv" title="Click to toggle"><br /></div>
								<h3 class="hndle"><span>Need support?</span></h3>
								<div class="inside">
									<p>See our <a href="http://pluginbuddy.com/tutorials/">tutorials & videos</a> or visit our <a href="http://pluginbuddy.com/support/">support forum</a> for additional information and help.</p>
								</div>
							</div>
							
						</div>
					</div>
				</div>
			</div>
			<?php
		}



		
		function view_settings() {
			$this->_parent->load();
			$this->admin_scripts();
			/*
			echo '<pre>';
			print_r($this->_options);
			echo '</pre>';
			*/
			if ( !empty( $_POST['save_recip'] ) ) {
				$this->savesettings();
			}
			
			?>
			<div class="wrap">
				<h2><?php echo $this->_name; ?> Settings</h2>
				<form method="post" action="<?php echo $this->_selfLink; ?>-settings">
					<?php // Ex. for saving in a group you might do something like: $this->_options['groups'][$_GET['group_id']['settings'] which would be: ['groups'][$_GET['group_id']['settings'] ?>
					<input type="hidden" name="savepoint" value="" />
					<table class="form-table">
						<tr>
							<td><label for="first_name">Recipient email address <a class="pluginbuddy_tip" title=" - Enter the email for the form submissions to go to.">(?)</a>:</label></td>
							<td><input type="text" name="<?php echo $this->_var; ?>-recipemail" size="45" maxlength="45" value="<?php echo $this->_options['recipemail']; ?>" /></td>
						</tr>
						<tr>
							<td><label for="last_name">Subject <a class="pluginbuddy_tip" title=" - Enter the email subject.">(?)</a>:</label></td>
							<td><input type="text" name="<?php echo $this->_var; ?>-subject" size="45" maxlength="45" value="<?php echo $this->_options['subject']; ?>" /></td>
						</tr>
						<tr>
							<td>
								<label for="recaptcha">
									Enable reCAPTCHA <a class="pluginbuddy_tip" title=" - [Default: disabled] - When enabled, a recaptcha input will be added to your contact form to insure entries weren't made by a robot">(?)</a>:
								</label>
							</td>
							<input type="hidden" name="<?php echo $this->_var; ?>-recaptcha" value="0" />
							<?php
								if (($this->_options['recaptcha'] == '1') || (isset($_POST[$this->_var . '-recaptcha']) && ($_POST[$this->_var . '-recaptcha'] == '1'))) {
									$checked = 'checked';
								}
								else {
									$checked = '';
								}
							?>
							<td><input class="option_toggle" type="checkbox" name="<?php echo $this->_var; ?>-recaptcha" id="recaptcha" value="1" <?php echo $checked; ?> /></td>
						</tr>

						<?php
							// Check if recaptcha is checked
							if (($this->_options['recaptcha'] != '1') || (isset($_POST[$this->_var . '-recaptcha']) && ($_POST[$this->_var . '-recaptcha'] != '1'))) {
								$keyshow = 'style="display: none;"';
							}
							else {
								$keyshow = '';
							}
						?>
						<tr class="recaptcha_toggle" <?php echo $keyshow; ?>>
							<td colspan="2"><h3>With reCAPTCHA enabled you can only have one <?php echo $this->_name; ?> form on each page.</h3></td>
						</tr>
						<tr class="recaptcha_toggle" <?php echo $keyshow; ?>>
							<td colspan="2"><h3><a href="https://www.google.com/recaptcha" target="_blank">Click here to get a <span style="color: #C52C03";>reCAPTCHA</span> keys</a></h3></td>
						</tr>
						<tr class="recaptcha_toggle" <?php echo $keyshow; ?>>
							<td><label for="recaptcha-pubkey">reCAPTCHA public key <a class="pluginbuddy_tip" title=" - Enter your reCAPTCHA public key from https://www.google.com/recaptcha">(?)</a>:</label></td>
							<td><input type="text" name="<?php echo $this->_var; ?>-recaptcha-pubkey" id="recaptcha-pubkey" size="45" maxlength="45" value="<?php if ( isset( $this->_options['recaptcha-pubkey'] ) ) { echo $this->_options['recaptcha-pubkey']; } ?>" /></td>
						</tr>
						<tr class="recaptcha_toggle" <?php echo $keyshow; ?>>
							<td><label for="recaptcha-privkey">reCAPTCHA private key <a class="pluginbuddy_tip" title=" - Enter your reCAPTCHA private key from https://www.google.com/recaptcha">(?)</a>:</label></td>
							<td><input type="text" name="<?php echo $this->_var; ?>-recaptcha-privkey" id="recaptcha-privkey" size="45" maxlength="45" value="<?php if ( isset( $this->_options['recaptcha-privkey'] ) ) { echo $this->_options['recaptcha-privkey']; } ?>" /></td>
						</tr>
						<tr><td colspan="2"><h3>CSS Options</h3></td></tr>
						<tr>
							<td>
								<label for="cb-defaultcss">Style Options <a class="pluginbuddy_tip" title=" -  - Choose a from multiple styles for your contact form.">(?)</a>:</label>
							</td>
							<td>
								<?php $cbstylelist = array(
									'on'		=> 'default',
									'light'		=> 'light',
									'dark'		=> 'dark',
									'skinny'	=> 'skinny',
									'fat'		=> 'fat',
									'full-width'		=> 'full width',
									'compressed'	=> 'compressed',
									'off'		=> 'no styles'
								); ?>
								<select name="<?php echo $this->_var; ?>-defaultcss" id="cb-defaultcss">
								<?php
									foreach( $cbstylelist as $stylekey => $styleval ) {
										$select = '';
										if ( isset( $this->_options['defaultcss'] ) ) {								
											if ( $this->_options['defaultcss'] == $stylekey) { $select = " selected "; }
										}
										echo '<option value="' . $stylekey . '"' . $select . '>' . $styleval . '</option>';
									}
								?>
								</select>
							</td>
						</tr>
					</table>
					<p class="submit"><input type="submit" name="save_recip" value="Save Settings" class="button-primary" id="save" /></p>
					<?php $this->nonce(); ?>
				</form>
				<p>CSS tip: the class used to style the ContactBuddy form is "contactbuddy-form".</p>
			</div>
			<?php
		}
		
		function Gravity_Forms() {
			?>
			<h3>Need more flexibility with your forms? <a href="https://www.e-junkie.com/ecom/gb.php?cl=54585&c=ib&aff=14589">Try Gravity Forms</a>.</h3>
			<p>Gravity Forms is a beautiful and simple form creation and management plugin that makes it quick and easy to add simple or advanced forms.</p>
			<p>Here are some of the great ways to use Gravity Forms:</p>
			<ul>
				<li>- Allow people to submit guest posts to your WordPress blog</li>
				<li>- Create multiple forms and have them emailed to you</li>
				<li>- Get customer input with surveys forms</li>
				<li>- Import/Export forms</li>
				<li>- and much more!</li>
			</ul>
			<h4>Learn more about Gravity Forms <a href="https://www.e-junkie.com/ecom/gb.php?cl=54585&c=ib&aff=14589">here</a></h4>
			<?php
		}
		
		/** admin_menu()
		 *
		 * Initialize menu for admin section.
		 *
		 */		
		function admin_menu() {
			// Add main menu (default when clicking top of menu)
			add_menu_page($this->_parent->_name.' Getting Started', $this->_parent->_name, 'administrator', $this->_parent->_var, array(&$this, 'view_gettingstarted'), $this->_parent->_pluginURL.'/images/pluginbuddy.png');
			// Add sub-menu items (first should match default page above)
			add_submenu_page( $this->_parent->_var, $this->_parent->_name.' Getting Started', 'Getting Started', 'administrator', $this->_parent->_var, array(&$this, 'view_gettingstarted'));
			//add_submenu_page( $this->_parent->_var, $this->_parent->_name.' Themes & Devices', 'Themes & Devices', 'administrator', $this->_parent->_var.'-themes', array(&$this, 'view_themes'));
			add_submenu_page( $this->_parent->_var, $this->_parent->_name.' Settings', 'Settings', 'administrator', $this->_parent->_var.'-settings', array(&$this, 'view_settings'));
			add_submenu_page( $this->_parent->_var, $this->_parent->_name.' Gravity Forms', 'Try Gravity Forms', 'administrator', $this->_parent->_var.'-GravityForms', array(&$this, 'Gravity_Forms'));

		}


    } // End class
	
	$contactbuddy_admin = new contactbuddy_admin($this); // Create instance
}
