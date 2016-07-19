<?php
/*
Plugin Name: Gravity Forms PayDock Add-On
Plugin URI: https://houstonapps.co/
Description: Integrates Gravity Forms with <a href="http://thepaydock.com/">PayDock</a>
Version: 2.0.6
Author: Houston Apps
Author URI: https://houstonapps.co/
Text Domain: gravityforms-bb-paydock
Domain Path: /languages

------------------------------------------------------------------------
Copyright 2016 Houston Apps

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA 02111-1307 USA
*/
define('GF_PAYDOCK_VERSION', '2.0.6');

add_action('gform_loaded', array('GF_PayDock_Launch', 'load'), 5);

class GF_PayDock_Launch
{
    public static function load()
    {
        if (!method_exists('GFForms', 'include_payment_addon_framework')) {
            return;
        }

        require_once('gf-paydock.class.php');
        require_once('envoy-field-settings.php');

        GFAddOn::register('GFPayDock');
    }
}

register_activation_hook(__FILE__, 'migrate_ech_settings');
function migrate_ech_settings()
{
    $ech_options = get_option('gravityformsaddon_EnvoyRecharge_settings');
    $paydock_options = get_option('gravityformsaddon_PayDock_settings');
    if (!empty($ech_options['envoyapikey']) && empty($paydock_options)) {
        $paydock_options = array(
            'pd_production_api_key' => $ech_options['envoyapikey'],
        );
        update_option('gravityformsaddon_PayDock_settings', $paydock_options);
    }
}

// Enable the Gravity Forms credit card field
add_action("gform_enable_credit_card_field", "gf_paydock_enable_creditcard");
function gf_paydock_enable_creditcard($is_enabled)
{
    return true;
}


// My try to make custom field!


add_filter('gform_add_field_buttons', 'wps_add_paydock_secret_key_field');
function wps_add_paydock_secret_key_field($field_groups)
{
    foreach ($field_groups as &$group) {
        if ($group["name"] == "pricing_fields") {
            $group["fields"][] = array(
                "class" => "button",
                "value" => __("Paydock API Key", "gravityforms"),
                "onclick" => "StartAddField('paydock_secret_key');"
            );
            break;
        }
    }
    return $field_groups;
}


add_filter('gform_field_type_title', 'wps_paydock_secret_key_title');
function wps_paydock_secret_key_title($type)
{
    if ($type == 'paydock_secret_key') {
        return __('Paydock API Key', 'gravityforms');
    }
}

//Validate PayDock API field
add_filter( 'gform_field_validation_1_16', 'validate_paydock_field', 10, 4 );

function validate_paydock_field($result, $value, $form, $field )
{

    $value = trim($value);

    //required, letters and numbers only, 40 symbols
    $pattern = "/^[a-z0-9]{40}$/";
    if ( ! preg_match( $pattern, $value ) ) {
        $result['is_valid'] = false;
        $result['message']  = 'Please enter a valid PayDock API key';

    }
    else{
        //if response is successful return gateways
         $gateways = get_paydock_gateways($value);

        if($gateways == 'no gateways'){
            $result['is_valid'] = false;
            $result['message']  = 'There are no gateways connected in your Paydock account';
        }
        elseif(!$gateways){
            $result['is_valid'] = false;
            $result['message']  = 'Please enter a valid PayDock API key';
        }
        else{
            //if response is successful save gateways in POST
            $_POST['gateways'] = $gateways;
        }
    }

    return $result;

}



add_action("gform_field_input", "wps_paydock_secret_key_field_input", 10, 5);
function wps_paydock_secret_key_field_input($input, $field, $value, $lead_id, $form_id)
{
    $pdapikey = '';

    // TODO: detect key of PayDock API key in $_POST
    if (array_key_exists('input_16', $_POST)) {
        $pdapikey = trim($_POST['input_16']);
    }
    if ($field["type"] == "paydock_secret_key") {
        $max_chars = "";
        if (!IS_ADMIN) {
        $input_name = $form_id . '_' . $field["id"];
        $tabindex = GFCommon::get_tabindex();
        $css = isset($field['cssClass']) ? $field['cssClass'] : '';
        return sprintf("<div class='ginput_container'><input name='input_%s' id='%s'
class='textarea gform_paydock_secret_key %s' value= ' " . $pdapikey . " ' " . $tabindex ."></div>", $field['id'], 'paydock_secret_key-'
            . $field['id'], $field['type'] . ' ' . esc_attr($css) . ' ' . $field['size'], esc_html($value));
        }
    }

    return $input;
}

add_action("gform_editor_js", "wps_gform_editor_js");
function wps_gform_editor_js()
{
    ?>

    <script type='text/javascript'>

        jQuery(document).ready(function ($) {
//Add all textarea settings to the "TOS" field plus custom "tos_setting"
// fieldSettings["tos"] = fieldSettings["textarea"] + ", .tos_setting"; // this will show all fields that Paragraph Text field shows plus my custom setting

// from forms.js; can add custom "tos_setting" as well
            fieldSettings["paydock_secret_key"] = ".label_setting, .description_setting, .admin_label_setting, " +
                ".size_setting, .default_value_textarea_setting, .error_message_setting, .css_class_setting, " +
                ".visibility_setting,.rules_setting, .paydock_secret_key_setting"; //this will show all the fields of the Paragraph Text field minus a couple that I didn’t want to appear.

//binding to the load field settings event to initialize the checkbox
            $(document).bind("gform_load_field_settings", function (event, field, form) {
                jQuery("#field_paydock_secret_key").attr("checked", field["field_paydock_secret_key"] == true);
                $("#field_paydock_secret_key_value").val(field['paydock_secret_key']);
            });
        });

    </script>
    <?php
}


add_action("gform_field_advanced_settings", "wps_paydock_secret_key_settings", 10, 2);
function wps_paydock_secret_key_settings($position, $form_id)
{

// Create settings on position 50 (right after Field Label)
    if ($position == 50) {
        ?>

        <li class="paydock_secret_key_setting field_setting">

            <input type="checkbox" id="field_paydock_secret_key" onclick="SetFieldProperty('field_paydock_secret_key', this.checked);"/>
            <label for="field_paydock_secret_key" class="inline">
                <?php _e("Disable Submit Button", "gravityforms"); ?>
                <?php gform_tooltip("form_field_paydock_secret_key"); ?>
            </label>

        </li>
        <?php
    }
}

// Add a script to the display of the particular form only if tos field is being used
add_action('gform_enqueue_scripts', 'wps_gform_enqueue_scripts', 10, 2);
function wps_gform_enqueue_scripts($form, $ajax)
{
// cycle through fields to see if tos is being used
    foreach ($form['fields'] as $field) {
        if (($field['type'] == 'paydock_secret_key') && (isset($field['field_paydock_secret_key']))) {
            $url = plugins_url('gform_tos.js', __FILE__);
            wp_enqueue_script("gform_paydock_secret_key_script", $url, array("jquery"), '1.0'); // Note WPS_JS is a constant I’ve set for all my child theme’s custom JS.
            break;
        }
    }
}

add_action("gform_field_css_class", "custom_class", 10, 3);
function custom_class($classes, $field, $form)
{

    if ($field["type"] == "paydock_secret_key") {
        $classes .= " gform_paydock_secret_key";
    }

    return $classes;
}


// GATEWAY BUTTON AND LAYOUT


add_filter('gform_add_field_buttons', 'paydock_gateway_button');
function paydock_gateway_button($field_groups)
{
    foreach ($field_groups as &$group) {
        if ($group["name"] == "pricing_fields") {
            $group["fields"][] = array(
                "class" => "button",
                "value" => __("Paydock API Gateway", "gravityforms"),
                "onclick" => "StartAddField('paydock_gateway_id');"
            );
            break;
        }
    }
    return $field_groups;
}


add_filter('gform_field_type_title', 'paydock_gateway_title');
function paydock_gateway_title($type)
{
    if ($type == 'paydock_gateway_id') {
        return __('Paydock API Gateway', 'gravityforms');
    }
}



add_action("gform_field_input", "paydock_gateway_input", 10, 5);
function paydock_gateway_input($input, $field, $value, $lead_id, $form_id)
{
    $tabindex = GFCommon::get_tabindex();


    $gateways = array(); //foreach error fix with empty value

    if ($field['type'] !== 'paydock_gateway_id') {
        return $input;
    }
    $gateways_select = array();
    $css = isset($field['cssClass']) ? $field['cssClass'] : '';


    if (array_key_exists('gateways', $_POST)) {
        $gateways = $_POST['gateways'];
    }

    foreach ($gateways as $gateway) {
        $gateway['name'] = '[Sandbox Account Gateway] ' . $gateway['name'];
        $gateways_select[] = array(
            "label" => $gateway['name'],
            "value" => $gateway['_id']
        );
    }

    $selectTemplate = '<div class="ginput_container">
                <select name="input_%s" id="%s" class="textarea gform_paydock_gateway_id %s" %s rows="10" cols="50">%s</select>
                </div>';

    $optionTemplate = '<option value="%s">%s</option>';

    $options = '';

    foreach ($gateways_select as $gateway) {
        $options .= sprintf($optionTemplate, $gateway['value'], $gateway['label']);
    }

    $fieldName = $field['id'];
    $fieldId = 'paydock_gateway_id-' . $field['id'];
    $fieldClass = $field['type'] . ' ' . esc_attr($css) . ' ' . $field['size'];

    $input = sprintf($selectTemplate, $fieldName, $fieldId, $fieldClass, $tabindex, $options);


    return $input;
}

//get gateways array
function get_paydock_gateways($pdapikey)
{
    $endpointObj = new GFSC_SiteCreator_UserRegistration;
    $endpoint = $endpointObj->getPayDockEndPoint();

    $curl_header = array();
    $curl_header[] = 'x-user-secret-key:' . $pdapikey;
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $endpoint . 'gateways/');
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "GET");
    curl_setopt($ch, CURLOPT_HTTPHEADER, $curl_header);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); // this one is important
    curl_setopt($ch, CURLOPT_HEADER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    $result = curl_exec($ch);

    curl_close($ch);
    $json_string = json_decode($result, true);
    $status = $json_string['status'];
    $gateways = $json_string['resource']['data'];


    if (intval($status) != 200) {
        return false;
    }

    if (count($gateways) == 0){
        return 'no gateways';
    }

    return $gateways;
}


add_action("gform_editor_js", "paydock_gateway_id_gform_editor_js");
function paydock_gateway_id_gform_editor_js()
{
    ?>

    <script type='text/javascript'>

        jQuery(document).ready(function ($) {

            fieldSettings["paydock_gateway_id"] = ".label_setting, .description_setting, .admin_label_setting, " +
                ".size_setting, .default_value_textarea_setting, .error_message_setting, .css_class_setting, " +
                ".visibility_setting, .paydock_gateway_id_setting"; //this will show all the fields of the Paragraph Text field minus a couple that I didn’t want to appear.

            $(document).bind("gform_load_field_settings", function (event, field, form) {
                jQuery("#field_paydock_gateway_id").attr("checked", field["field_paydock_gateway_id"] == true);
                $("#field_paydock_gateway_id").val(field['paydock_gateway_id']);
            });
        });

    </script>
    <?php
}


add_action("gform_field_advanced_settings", "paydock_gateway_id_settings", 10, 2);
function paydock_gateway_id_settings($position, $form_id)
{

// Create settings on position 50 (right after Field Label)
    if ($position == 50) {
        ?>

        <li class="paydock_gateway_id_setting field_setting">

            <input type="checkbox" id="field_paydock_gateway_id" onclick="SetFieldProperty('field_paydock_gateway_id', this.checked);"/>
            <label for="field_paydock_gateway_id" class="inline">
                <?php _e("Disable Submit Button", "gravityforms"); ?>
                <?php gform_tooltip("form_field_paydock_gateway_id"); ?>
            </label>

        </li>
        <?php
    }
}


add_action("gform_field_css_class", "paydock_gateway_id_custom_class", 10, 3);
function paydock_gateway_id_custom_class($classes, $field, $form)
{

    if ($field["type"] == "paydock_gateway_id") {
        $classes .= " gform_paydock_gateway_id";
    }

    return $classes;
}
































































