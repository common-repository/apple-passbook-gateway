<?php
/*
Plugin Name: Apple Passbook Gateway
Plugin URI: http://digiworks.rushproject.com/wiki/display/PS/Wordpress+Passbook+Plugin
Description: Distribute your Apple Passbook coupons, membership cards, tickets and passes from your Wordpress powered website using Pass Gate server.
Version: 1.0
Author: Rush Project, Inc
Author URI: http://www.rushproject.com
License: GPL2
*/

/*
This program is free software; you can redistribute it and/or
modify it under the terms of the GNU General Public License
as published by the Free Software Foundation; either version 2
of the License, or (at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
*/

if (!function_exists( 'is_admin' )) {
    header( 'Status: 403 Forbidden' );
    header( 'HTTP/1.1 403 Forbidden' );
    exit();
}

define('PASSBOOK_REST_API_URL', 'https://www.passgate.com/api');
define('PASSBOOK_CLIP_TRIGGER', 'passbook-clip-it');


if (!class_exists( 'WP_Http' )) {
    include_once(ABSPATH . WPINC . '/class-http.php');
}

/*
 * Plugin options management
 */
add_action( 'admin_menu', 'passbook_plugin_menu' );
add_action( 'admin_init', 'passbook_options_init' );
register_deactivation_hook( __FILE__, 'passbook_options_remove' );

function passbook_plugin_menu() {
    add_options_page( 'Passbook Gateway Options', 'Passbook Gateway', 'manage_options', 'passbook_options', 'passbook_plugin_options' );
}

function passbook_options_init() {
    register_setting( 'passbook_gateway_options', 'passbook_gateway' );
}

function passbook_options_remove() {
    unregister_setting( 'passbook_gateway_options', 'passbook_gateway' );
}

function passbook_plugin_options() {
    if (!current_user_can( 'manage_options' )) {
        wp_die( __( 'You do not have sufficient permissions to access this page.' ) );
    }
    ?>
<div class="wrap">
    <h2>Passbook Gateway</h2>

    <form method="post" action="options.php">
        <?php settings_fields( 'passbook_gateway_options' ); ?>
        <?php $options = get_option( 'passbook_gateway' ) ?>
        <table class="form-table">
            <tr valign="top">
                <th scope="row">
                    Web App ID
                </th>
                <td>
                    <input name="passbook_gateway[site_id]" type="text" value="<?php echo $options[ 'site_id' ]; ?>"/>
                </td>
            </tr>
            <tr valign="top">
                <th scope="row">
                    API Key
                </th>
                <td>
                    <input name="passbook_gateway[api_key]" type="text" value="<?php echo $options[ 'api_key' ]; ?>"/>
                </td>
            </tr>
            <tr valign="top">
                <th scope="row">
                    Generator Name
                </th>
                <td>
                    <input name="passbook_gateway[generator]" type="text"
                           value="<?php echo $options[ 'generator' ]; ?>"/>
                </td>
            </tr>
        </table>
        <br/>
        No Pass Gate account? Start your <a href="https://www.passgate.com/signUp/site">free trial</a> today!
        <?php submit_button(); ?>
    </form>
</div>
<?php
}

/*
 * Short code [passbook-member-card title="Clip It!" signin=""]
 *
 * Will generate a link to download the pass.
 *
 * Parameters:
 *
 * title - link title
 * signin - text to show if visitor is not signed in
 *
 */
add_shortcode( "passbook-member-card", "passbook_membercard_handler" );

function passbook_membercard_handler( $atts ) {
    extract( shortcode_atts( array(
        'title' => 'Clip It!',
        'signin' => ''
    ), $atts ) );
    $result = passbook_membercard_function( $title, $signin );
    return $result;
}

function passbook_membercard_function( $title, $signin ) {
    if (is_user_logged_in()) {
        return '<a href="' . home_url( '?' . PASSBOOK_CLIP_TRIGGER . '=yes' ) . '">' . "$title</a>";
    } else {
        return wp_login_form( array('echo' => false, 'redirect' => get_permalink()) );
    }
}

add_filter( 'query_vars', 'passbook_query_vars' );
function passbook_query_vars( $public_query_vars ) {
    $public_query_vars[ ] = PASSBOOK_CLIP_TRIGGER;
    return $public_query_vars;
}

/**
 * Passbook clipper interceptor. Will initiate pass clipping if non empty input with the name defined
 * by PASSBOOK_CLIP_TRIGGER was detected.
 */
add_action( 'template_redirect', 'passbook_clip_it' );
function passbook_clip_it() {
    $clip_it = get_query_var( PASSBOOK_CLIP_TRIGGER );
    if (!empty($clip_it)) {
        if (!is_user_logged_in()) {
            header( 'Status: 403 Forbidden' );
            header( 'HTTP/1.1 403 Forbidden' );
            exit();
        }
        global $current_user;
        get_currentuserinfo();
        $options = get_option( 'passbook_gateway' );
        $site_id = $options[ 'site_id' ];

// Prepare data dictionary

        $first_name = $current_user->user_firstname;
        if (empty($first_name)) {
            $first_name = 'Club';
        }
        $last_name = $current_user->user_lastname;
        if (empty($last_name)) {
            $last_name = 'Member';
        }

        // We use [siteID]-[user ID] as a pass serial number.

        $serial = "$site_id-" . $current_user->ID;

        $dictionary = array(
            'user_id' => $current_user->ID,
            'email' => trim( $current_user->user_email ),
            'email_hash' => md5( strtolower( trim( $current_user->user_email ) ) ),
            'serial' => $serial,
            'first_name' => trim( $first_name ),
            'last_name' => trim( $last_name )
        );

// Will try to retrieve existing pass first

        $pass = passbook_get_pass( $serial );

        if ($pass == null) {

// No pass found, will create new one
            $serial = passbook_create_pass( $dictionary );
        } else {
            $serial = passbook_update_pass( $serial, $dictionary );
        }
        if ($serial != null) {
            $pass = passbook_get_pass( $serial );
        } else {
            $pass = null;
        }
        if ($pass) {
            header( 'Content-disposition: attachment; filename=pass.pkpass' );
            header( 'Content-type:application/vnd.apple.pkpass' );
            header( 'Content-length: ' . strlen( $pass ) );
            echo $pass;
        }
        exit;
    }
}

/**
 * Create Apple Passbook pass using PassGate.com Rest API
 *
 * @param array $data_dictionary Data dictionary
 *
 * @return mixed Returns null if failed to create new pass. Returns new pass serial number otherwise.
 */
function passbook_create_pass( $data_dictionary ) {
    $options = get_option( 'passbook_gateway' );
    $api_key = $options[ 'api_key' ];
    $site_id = $options[ 'site_id' ];
    $generator = $options[ 'generator' ];

    $url = PASSBOOK_REST_API_URL . "/$site_id/$api_key/$generator/pass";
    $headers = array(
        'User-Agent' => 'Wordpress Passbook plugin',
        'Content-Type' => 'application/json;charset=UTF-8'
    );
    $request = new WP_Http;
    $response = $request->request( $url, array(
        'method' => 'POST',
        'timeout' => '30',
        'httpversion' => '1.0',
        'blocking' => true,
        'headers' => $headers,
        'body' => json_encode( $data_dictionary )
    ) );
    if (is_wp_error( $response ) || (201 != $response[ 'response' ][ 'code' ])) {
        return null;
    }
    $json_response = json_decode( $response[ 'body' ] );
    return $json_response->{'serial'};
}

/**
 * Update Apple Passbook pass using PassGate.com Rest API
 *
 * @param string $serial Pass serial number
 * @param array  $data_dictionary Data dictionary
 *
 * @return mixed Returns null if failed to update new pass. Returns pass serial number otherwise.
 */
function passbook_update_pass( $serial, $data_dictionary ) {
    $options = get_option( 'passbook_gateway' );
    $api_key = $options[ 'api_key' ];
    $site_id = $options[ 'site_id' ];
    $generator = $options[ 'generator' ];

    $url = PASSBOOK_REST_API_URL . "/$site_id/$api_key/$generator/pass/$serial";
    $headers = array(
        'User-Agent' => 'Wordpress Passbook plugin',
        'Content-Type' => 'application/json;charset=UTF-8'
    );
    $request = new WP_Http;
    $response = $request->request( $url, array(
        'method' => 'PUT',
        'timeout' => '30',
        'httpversion' => '1.0',
        'blocking' => true,
        'headers' => $headers,
        'body' => json_encode( $data_dictionary )
    ) );
    if (is_wp_error( $response ) || (200 != $response[ 'response' ][ 'code' ])) {
        return null;
    }
    $json_response = json_decode( $response[ 'body' ] );
    return $json_response->{'serial'};
}

/**
 * Retrieve Apple Passbook pass using from PassGate.com using Rest API
 *
 * @param array $serial Pass serial number
 *
 * @return mixed Returns pass or null if failed to retireve the pass.
 */
function passbook_get_pass( $serial ) {
    $options = get_option( 'passbook_gateway' );
    $api_key = $options[ 'api_key' ];
    $site_id = $options[ 'site_id' ];
    $generator = $options[ 'generator' ];
    $url = PASSBOOK_REST_API_URL . "/$site_id/$api_key/$generator/pass/$serial";
    $headers = array(
        'User-Agent' => 'Wordpress Passbook plugin',
    );
    $request = new WP_Http;
    $response = $request->request( $url, array(
        'method' => 'GET',
        'timeout' => '30',
        'httpversion' => '1.0',
        'blocking' => true,
        'headers' => $headers
    ) );
    if (is_wp_error( $response ) || (200 != $response[ 'response' ][ 'code' ])) {
        return null;
    }
    $json_response = json_decode( $response[ 'body' ] );
    return base64_decode( $json_response->{'pass'} );
}

?>
