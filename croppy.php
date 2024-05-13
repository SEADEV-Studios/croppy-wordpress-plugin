<?php
/**
 * Plugin Name: Croppy - Intelligent Image Cropping
 * Plugin URI: https://croppy.at
 * Description: Elevate your image editing game with Croppy – the cutting-edge image cropping solution that simplifies the editing experience using the power of artificial intelligence and uploads directly to your WordPress.
 * Version: 1.0
 * Author: SEADEV Studios GmbH
 * Author URI: https://seadev-studios.com
 * Text Domain: croppy-wp
 * Domain Path: /languages
**/

// Add menu item to left sidebar
function croppy_add_menu_item() {
    // Add menu page with icon
    add_menu_page(
        'Croppy',              // Page title
        'Croppy',              // Menu title
        'manage_options',      // Capability required to access the page
        'croppy-settings',     // Menu slug
        'croppy_settings_page',// Callback function to render the page
        'dashicons-format-image',
        10                      // Position in the menu
    );
}

$configs = include('config.php');

// Callback function to render the settings page
function croppy_settings_page() {
    global $configs;
    $croppy_link_uid = get_option('croppy_link_uid');
    $croppy_token = get_option('croppy_token');
    $croppy_tenant = get_option('croppy_tenant');

    // Get the URL of the logo image
    $logo_url = plugins_url('assets/logo.png', __FILE__);
    $cropper_route = $configs['CROPPER'];

    if (!empty($croppy_link_uid) && !empty($croppy_token) && croppy_validate_token($croppy_link_uid, $croppy_token)) {
        $server_domain = base64_encode(site_url());
        $iframe_url = $cropper_route . '/interface?uuid=' . urlencode($croppy_link_uid) . '&token=' . urlencode($croppy_token) . '&tenant=' . urlencode($croppy_tenant) . '&server_domain=' . urlencode($server_domain);
        ?>
        <div class="croppy-wrap">
            <h1 class="croppy-header">
                <img class="croppy-logo" src="<?php echo esc_url($logo_url); ?>" alt="Croppy Logo">
                <strong><?php echo esc_html(get_admin_page_title()); ?></strong>
                <button class="croppy-logout"><?php echo esc_html(__('Logout', 'croppy-wp')); ?></button>
            </h1>
            <div style="width: 100%; text-align: center">
                <!-- <form action="<?php echo esc_url($iframe_url); ?>" method="get" target="croppy-iframe">
                    <button type="submit" class="croppy-button" formtarget="croppy-iframe">Submit</button>
                </form> -->
                <a href="<?php echo esc_url($iframe_url); ?>" class="croppy-button" target="_blank"><?php echo esc_html(__('Open Croppy', 'croppy-wp')); ?></a>
            </div>
            <!-- <iframe class="croppy-iframe" src="<?php echo esc_url($iframe_url); ?>" sandbox="allow-scripts allow-forms allow-same-origin allow-top-navigation"></iframe> -->
            <iframe name="croppy-iframe" class="croppy-iframe"></iframe>
        </div>
        <?php
    } else {
        // Get the currently logged-in user's email address
        $current_user = wp_get_current_user();
        $default_email = $current_user->user_email;

        // Extract domain from email address
        $email_parts = explode('@', $default_email);
        $domain = isset($email_parts[1]) ? $email_parts[1] : '';

        ?>
        <div class="croppy-wrap">
            <h1 class="croppy-header">
                <img class="croppy-logo" src="<?php echo esc_url($logo_url); ?>" alt="Croppy Logo">
                <strong><?php echo esc_html(get_admin_page_title()); ?></strong>
            </h1>
            <div class="croppy-card" style="margin-top: 32px">
                <h2>Croppy</h2>
                <p>This Plugin will help you to connect your WordPress Website with Croppy.<br/>
                <br/>
                By clicking on finish it will do the following:<br/>
                1. Create a new user called "croppy" with the email address you provide<br/>
                2. Generate a random password for the user<br/>
                3. Generate a new application password for the user<br/>
                4. Redirect you to the Croppy interface<br/>
                <br/>
                We need to create a user that has the rights to create posts. This is necessary to upload the cropped images to your WordPress media library.<br/>
                You can modify the password of the user any time, but please do not delete the user.<br/>
                <br/>
                If you have any questions or need help, please reach out to us on discord.
                </p>
                <p>
                    <form id="initialize-croppy-form" style="margin-left: 1rem; width: 100%;">
                        <label for="email"><?php echo esc_html(__('Email Address:', 'croppy-wp')); ?></label>
                        <input type="email" id="email" name="email" value="croppy@<?php echo esc_attr($domain); ?>" required style="min-width: 320px">
                        <button class="croppy-button" type="submit" id="configure-button"><?php echo esc_html(__('Configure', 'croppy-wp')); ?></button>
                    </form>
                </p>
            </div>
        </div>
        <div>
            <h3>Issues with security related plugins</h3>
            <p>
                <p>
                    Please note that certain security-related plugins, such as "WPSecurity" or "Remove XMLRPC Pingback Ping", may interfere with the functionality of this plugin. These plugins often disable or restrict the use of application passwords and the WordPress JSON-RPC API, which are essential for the proper operation of Croppy.
                </p>
                <p>
                    To find out more, check out the <a href="https://seadev-studios.atlassian.net/wiki/spaces/CD/pages/2960687366/Connecting+to+Wordpress" target="_blank">WordPress documentation</a> or visit the download page of the plugin.
                </p>
            </p>
        </div>
        <?php
    }
}
add_shortcode('initialize_croppy_form', 'initialize_croppy_form');

add_action('wp_ajax_initialize_croppy', 'initialize_croppy_function');
function initialize_croppy_function() {
    // Check if the request came from an authorized source
    if (!check_ajax_referer('croppy_ajax_nonce', 'nonce', false)) {
        custom_log('[CONFIG] Nonce check failed');
        wp_send_json_error('[CONFIG] Nonce check failed');
        return;
    }

    // Get the email address from the submitted form data
    $email = $_POST['email'];

    // instead of using the current user, check if there already is a croppy user, if not create a user specifically for the plugin, the user has to has the rights to create posts
    $croppy_user = get_user_by('login', 'croppy');
    $generated_pw = wp_generate_password();
    if (!$croppy_user) {
        $croppy_user_id = wp_create_user('croppy', $generated_pw, $email);
        $croppy_user = new WP_User($croppy_user_id);
        $croppy_user->set_role('editor');
    } else {
        // the generated password should be generated and the user then updated
        wp_set_password($generated_pw, $croppy_user->ID);
    }

    croppy_get_admin_panel_url($croppy_user->user_email, $croppy_user->ID, $generated_pw);
}

function croppy_get_admin_panel_url($user_email, $user_id, $generated_pw) {
    global $configs;
    // Generate a unique hash for the application password name
    $hash = md5(uniqid(rand(), true));
    $app_password_name = 'Croppy-' . $hash;

    // Create a new application password
    $data = WP_Application_Passwords::create_new_application_password($user_id, array('name' => $app_password_name));
    $new_application_password = $data[0];

    // Create a base64 string of the email, the application password, and the permalink structure
    $rest_route = rest_url();
    $base64_string = base64_encode($user_email . ':' . $new_application_password . ':' . $generated_pw);

    // Create the admin URL
    $admin_route = $configs['ADMIN'];
    $croppy_admin_url = $admin_route . '?server_domain=' . urlencode(site_url()) . '&redirect_url=' . urlencode(admin_url('admin.php?page=croppy-settings')) . '&auth=' . $base64_string . '&rest_route=' . urlencode($rest_route);

    // Return the URL
    echo json_encode(array('url' => $croppy_admin_url));
    wp_die();
}

function croppy_enqueue_custom_style() {
    // Use plugins_url function to include your custom css file
    wp_enqueue_style('croppy-custom-style', plugins_url('css/styling.css', __FILE__));
    wp_enqueue_script('croppy-custom-script', plugins_url('js/script.js', __FILE__), array('jquery'), '1.0', true);
    $nonce = wp_create_nonce('croppy_ajax_nonce');
    custom_log('[ENQUEUE] Enqueueing custom script with nonce: ' . $nonce);
    wp_localize_script('croppy-custom-script', 'croppy_ajax_object', array(
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce' => $nonce,
    ));
}

function croppy_validate_token($link_uid, $token) {
    global $configs;
    $croppy_tenant = get_option('croppy_tenant');
    $api_route = $configs['API'];

    $response = wp_remote_get($api_route . '/wp-plugin/validateApplicationToken/' . urlencode($token) . '?tenant=' . urlencode($croppy_tenant));

    if (is_wp_error($response)) {
        custom_log('[VALIDATE_TOKEN] Error validating token: ' . $response->get_error_message());
        return false;
    }

    return $response['response']['code'] === 200;
}

function custom_log($message) {
    $log_file = plugin_dir_path(__FILE__) . 'debug.log';
    $current_time = date('Y-m-d H:i:s');
    file_put_contents($log_file, $current_time . ' - ' . $message . "\n", FILE_APPEND);
}

function croppy_uninstall() {
    global $configs;
    $croppy_token = get_option('croppy_token');
    $croppy_tenant = get_option('croppy_tenant');
    $api_route = $configs['API'];
    custom_log('[UNINSTALL] Uninstalling Croppy plugin with route: ' . $api_route . ' and token: ' . $croppy_token . ' and tenant: ' . $croppy_tenant);

    if (!empty($croppy_token)) {
        $response = wp_remote_request($api_route . '/wp-plugin/deleteApplicationTokenByToken/' . urlencode($croppy_token) . '?tenant=' . urlencode($croppy_tenant), array(
            'method' => 'DELETE'
        ));

        if (is_wp_error($response)) {
            custom_log('[UNINSTALL] Error deleting token: ' . $response->get_error_message());
        }
    }

    // Get the 'croppy' user
    $croppy_user = get_user_by('login', 'croppy');

    // If the 'croppy' user exists, delete it
    if ($croppy_user) {
        require_once(ABSPATH.'wp-admin/includes/user.php');
        wp_delete_user($croppy_user->ID);
    }
}

function croppy_logout() {
    global $configs;
    if (!isset($configs) || !is_array($configs)) {
        custom_log('[LOGOUT] Error: $configs is not set or not an array, logout cannot be performed.');
        wp_send_json_error('[LOGOUT] Error: $configs is not set or not an array');
        return;
    }
    $nonce = $_POST['nonce'];
    if (!check_ajax_referer('croppy_ajax_nonce', 'nonce', false)) {
        custom_log('[LOGOUT] Nonce check failed');
        wp_send_json_error('[LOGOUT] Nonce check failed');
        return;
    }
    $croppy_token = get_option('croppy_token');
    $croppy_tenant = get_option('croppy_tenant');
    $api_route = $configs['API'];
    custom_log('[LOGOUT] Logging out with token: ' . $croppy_token . ' and tenant: ' . $croppy_tenant . ' and route: ' . $api_route);

    if (!empty($croppy_token)) {
        $response = wp_remote_request($api_route . '/wp-plugin/deleteApplicationTokenByToken/' . urlencode($croppy_token) . '?tenant=' . urlencode($croppy_tenant), array(
            'method' => 'DELETE'
        ));

        if (is_wp_error($response)) {
            custom_log('[LOGOUT] Error deleting token: ' . $response->get_error_message());
            wp_send_json_error('[LOGOUT] Error deleting token');
            return;
        }
    } else {
        custom_log('[LOGOUT] No token found');
    }

    if (!delete_option('croppy_token')) {
        custom_log('[LOGOUT] Failed to delete croppy_token option');
        wp_send_json_error('[LOGOUT] Failed to delete croppy_token option');
        return;
    }

    // Send the URL of the plugin page to the client-side
    wp_send_json_success(array('redirect_url' => admin_url('admin.php?page=croppy-settings')));
}

add_action('wp_ajax_croppy_logout', 'croppy_logout');
add_action('admin_menu', 'croppy_add_menu_item');
add_action('admin_init', function() {
    // load_plugin_textdomain( 'croppy-wp', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
    if (isset($_GET['page']) && $_GET['page'] === 'croppy-settings' && isset($_GET['linkUID']) && isset($_GET['token']) && isset($_GET['tenant'])) {
        custom_log('[INIT] Setting linkUID, token, and tenant options.');
        update_option('croppy_link_uid', $_GET['linkUID']);
        update_option('croppy_token', $_GET['token']);
        update_option('croppy_tenant', $_GET['tenant']);
    } else {
        custom_log('[INIT] Not setting linkUID, token, and tenant options.');
    }
});
add_action('admin_enqueue_scripts', 'croppy_enqueue_custom_style');
register_uninstall_hook(__FILE__, 'croppy_uninstall');

function my_plugin_load_my_own_textdomain( $mofile, $domain ) {
	if ( 'croppy-wp' === $domain && false !== strpos( $mofile, WP_LANG_DIR . '/plugins/' ) ) {
		$locale = apply_filters( 'plugin_locale', determine_locale(), $domain );
		$mofile = WP_PLUGIN_DIR . '/' . dirname( plugin_basename( __FILE__ ) ) . '/languages/' . $domain . '-' . $locale . '.mo';
	}
	return $mofile;
}
add_filter( 'load_textdomain_mofile', 'my_plugin_load_my_own_textdomain', 10, 2 );
