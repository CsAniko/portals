<?php
/**
* Some Branding in the admin header/footer
*/
function WE_custom_admin_branding() {
?>
    <style>
    #wp_welcome_panel-hide { display:none; } /* welcome screen */
    .ozhmenu_toplevel ul { clear:both; }
    .ozhmenu_sublevel { clear:both; width:auto; }
    #wp-admin-bar-wp-logo>.ab-item .ab-icon{ background-position:0px; background-image:url('<?php echo get_option('wecms_admin_logo') ?>'); }
    #wpadminbar.nojs #wp-admin-bar-wp-logo:hover>.ab-item .ab-icon,#wpadminbar #wp-admin-bar-wp-logo.hover>.ab-item .ab-icon{  background-position:0px; background-image:url('<?php echo get_option('wecms_admin_logo') ?>'); }
    </style>
    <script type="text/javascript">
        document.getElementById('footer-ozh-oam').innerHTML = '';
        document.getElementById('footer-thankyou').innerHTML = '<?php echo get_option('wecms_footer_info'); ?>';
        jQuery(document).ready(function(){
            jQuery('#wp-admin-bar-wp-logo>a.ab-item').attr({
                title: '',
                href: 'javascript:void(0)'
            });
        });
        jQuery('#wp_welcome_panel-hide').parent().hide(); // welcome screen
    </script>
<?php
}
add_action('admin_footer', 'WE_custom_admin_branding');


/*
add_action('wp_dashboard_setup', 'get_dashboard_widgets' );
function get_dashboard_widgets() {
    echo "<br><br><br>";
    global $wp_meta_boxes;
    foreach($wp_meta_boxes['dashboard']['normal']['core'] as $key => $value) {
        echo '<pre>'; echo $value['id']; echo '</pre>';
    }
}
*/


/**
* 
*/
function remove_admin_bar_links()
{
    global $wp_admin_bar;
    $wp_admin_bar->remove_menu('feedback');
    $wp_admin_bar->remove_menu('documentation');
    $wp_admin_bar->remove_menu('about');
    //$wp_admin_bar->remove_menu('wp-logo');
    $wp_admin_bar->remove_menu('support-forums');
    $wp_admin_bar->remove_menu('wporg');
    $wp_admin_bar->remove_menu('comments');
}
add_action( 'wp_before_admin_bar_render', 'remove_admin_bar_links');
/**
* 
*/
function my_admin_bar_menu()
{
    global $wp_admin_bar;
    $wp_admin_bar->add_menu( array(
        'parent' => 'wp-logo',
        'id' => 'we-support',
        'title' => __( 'Customer Support'),
        'href' => __(get_option("wecms_admin_support") ),
        'target' => '_blank'
    ));
    $wp_admin_bar->add_menu( array(
        'parent' => 'wp-logo-external',
        'id' => 'we-help',
        'title' => __( 'Help Documentation'),
        'href' => __(get_option("wecms_admin_docs") ),
        'target' => '_blank'
    ));
}
add_action('admin_bar_menu', 'my_admin_bar_menu');