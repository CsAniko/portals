<?php
if ( !class_exists( "PluginBuddyEmailBuddy_public" ) ) {
    class PluginBuddyEmailBuddy_public {
		function PluginBuddyEmailBuddy_public(&$parent) {
			$this->_parent = &$parent;
			
			// Example to call the function display_page when post content is being displayed on a page:
			// add_filter('the_content', array(&$this, 'display_page'));
		}
	}
	$PluginBuddyEmailBuddy_public = new PluginBuddyEmailBuddy_public($this);
}
?>
