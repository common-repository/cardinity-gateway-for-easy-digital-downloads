<?php
/*
Plugin Name: Cardinity Gateway for Easy Digital Downloads
Plugin URI: https://cardinity.com/developers/module/easy-digital-downloads
Description: Cardinity Gateway for Easy Digital Downloads
Author: Cardinity
Author URI: https://cardinity.com/
Version: 2.2.1
 */



// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

//Require Cardinity SDK
require_once plugin_dir_path(__FILE__) . "vendor/autoload.php";

//Define gateway name
define("CARDINITY_GATEWAY_NAME", "edd_cardinity_gateway");

$is_external_enabled = false;
$eddSettings = get_option('edd_settings');
if(isset($eddSettings[CARDINITY_GATEWAY_NAME."_external_enable"])){
    if(get_option('edd_settings')[CARDINITY_GATEWAY_NAME.'_external_enable'] == 'on' || get_option('edd_settings')[CARDINITY_GATEWAY_NAME.'_external_enable'] == 1){
        $is_external_enabled = true;
    }
}

/**
 * Encode data to Base64URL
 * @param string $data
 * @return boolean|string
 */
function encodeBase64Url($data)
{
    $b64 = base64_encode($data);

    if ($b64 === false) {
        return false;
    }

    // Convert Base64 to Base64URL by replacing “+” with “-” and “/” with “_”
    $url = strtr($b64, '+/', '-_');

    // Remove padding character from the end of line and return the Base64URL result
    return rtrim($url, '=');
}

/**
 * Decode data from Base64URL
 * @param string $data
 * @param boolean $strict
 * @return boolean|string
 */
function decodeBase64url($data, $strict = false)
{
    // Convert Base64URL to Base64 by replacing “-” with “+” and “_” with “/”
    $b64 = strtr($data, '-_', '+/');

    return base64_decode($b64, $strict);
}




/**
 * Registering Cardinity Gateway as a Payment Gateway in EDD
 *
 * @param   array $gateways     Current EDD payment gateways
 * @return  array               Current EDD payment gateways with Cardinity Gateway
 */
function cardinity_edd_register_gateway($gateways)
{
    $gateways[CARDINITY_GATEWAY_NAME] = array(
        'admin_label' => 'Cardinity Gateway',
        'checkout_label' => __('Credit/Debit Card', 'easy-digital-downloads'),
    );
    return $gateways;
}
add_filter('edd_payment_gateways', 'cardinity_edd_register_gateway');

/**
 * Register a subsection for Cardinity Gateway in gateway options tab
 *
 * @param   array $gateway_sections     Current Gateway Tab Subsections
 * @return  array                       Gateway Tab Subsections with Cardinity Gateway
 */
function cardinity_edd_register_gateway_section($gateway_sections)
{
    $gateway_sections[CARDINITY_GATEWAY_NAME] = __('Cardinity Gateway', 'easy-digital-downloads');
    return $gateway_sections;
}
add_filter('edd_settings_sections_gateways', 'cardinity_edd_register_gateway_section');

/**
 * Register the Cardinity Gateway settings for Cardinity Gateway subsection
 *
 * @param   array $gateway_settings     Gateway Tab Settings
 * @return  array                       Gateway Tab Settings with Cardinity Gateway settings
 */
function cardinity_edd_add_gateway_settings($gateway_settings)
{
    $cardinity_settings = array(
        array(
            'id' => CARDINITY_GATEWAY_NAME . '_settings',
            'name' => '<strong>' . __('Cardinity Gateway Settings', 'easy-digital-downloads') . '</strong>',
            'desc' => __('Configure Cardinity Gateway Settings', 'easy-digital-downloads'),
            'type' => 'header',
        ),
        array(
            'id' => CARDINITY_GATEWAY_NAME . '_desc',
            'name' => __('API keys', 'easy-digital-downloads'),
            'type' => 'descriptive_text',
            'desc' => __('Enter your Cardinity credentials.
                            You can find them on your Cardinity members area under
                            Integration -> API Settings.', 'easy-digital-downloads'),
        ),
        array(
            'id' => CARDINITY_GATEWAY_NAME . '_test_api_key',
            'name' => __('Test API Key', 'easy-digital-downloads'),
            'type' => 'text',
            'desc' => __('Enter your test API key', 'easy-digital-downloads'),
            'size' => 'regular',
        ),
        array(
            'id' => CARDINITY_GATEWAY_NAME . '_test_api_secret',
            'name' => __('Test API Secret', 'easy-digital-downloads'),
            'type' => 'text',
            'desc' => __('Enter your test API secret', 'easy-digital-downloads'),
            'size' => 'regular',
        ),
        array(
            'id' => CARDINITY_GATEWAY_NAME . '_live_api_key',
            'name' => __('Live API Key', 'easy-digital-downloads'),
            'type' => 'text',
            'desc' => __('Enter your live API key', 'easy-digital-downloads'),
            'size' => 'regular',
        ),
        array(
            'id' => CARDINITY_GATEWAY_NAME . '_live_api_secret',
            'name' => __('Live API Secret', 'easy-digital-downloads'),
            'type' => 'text',
            'desc' => __('Enter your live API secret', 'easy-digital-downloads'),
            'size' => 'regular',
        ),
        array(
            'id' => CARDINITY_GATEWAY_NAME . '_external_enable',
            'name' => __('Enable External', 'easy-digital-downloads'),
            'type' => 'checkbox',
            'default' => 'no',
            'desc' => __('Enable to use hosted payment gateway', 'easy-digital-downloads'),
            'size' => 'regular',
        ),
        array(
            'id' => CARDINITY_GATEWAY_NAME . '_project_key',
            'name' => __('Cardinity Project ID', 'easy-digital-downloads'),
            'type' => 'text',
            'desc' => __('Enter your Project ID', 'easy-digital-downloads'),
            'size' => 'regular',
        ),
        array(
            'id' => CARDINITY_GATEWAY_NAME . '_project_secret',
            'name' => __('Cardinity Project Secret', 'easy-digital-downloads'),
            'type' => 'text',
            'desc' => __('Enter your Project Secret', 'easy-digital-downloads'),
            'size' => 'regular',
        ),
    );
    $cardinity_settings = apply_filters('edd_' . CARDINITY_GATEWAY_NAME . '_settings', $cardinity_settings);
    $gateway_settings[CARDINITY_GATEWAY_NAME] = $cardinity_settings;
    return $gateway_settings;
}
add_filter('edd_settings_gateways', 'cardinity_edd_add_gateway_settings');


/**
 * Add custom data to checkout form
 */
function cardinity_edd_browser_info_fields() {

    $threedv2config = "
                <input type='hidden' id='screen_width' name='cardinity_screen_width' value='1920' />
                <input type='hidden' id='screen_height' name='cardinity_screen_height' value='1080' />
                <input type='hidden' id='browser_language' name='cardinity_browser_language' value='en-US' />
                <input type='hidden' id='color_depth' name='cardinity_color_depth' value='24' />
                <input type='hidden' id='time_zone' name='cardinity_time_zone' value='-60' />
                ";

    echo wp_kses($threedv2config, array(
        'input' => array(
            'type' => true,
            'id' => true,
            'name' => true,
            'value' => true
        )
    ));

    $threedv2configscript = '
                <script type="text/javascript">
                    document.addEventListener("DOMContentLoaded", function() {
                        document.getElementById("screen_width").value = screen.availWidth;
                        document.getElementById("screen_height").value = screen.availHeight;
                        document.getElementById("browser_language").value = navigator.language;
                        document.getElementById("color_depth").value = screen.colorDepth;
                        document.getElementById("time_zone").value = new Date().getTimezoneOffset();
                    });
                </script>';

    echo wp_kses($threedv2configscript, array(
        'script' => array(
            'type' => true,
        )
    ));
}

add_action( 'edd_purchase_form_bottom', 'cardinity_edd_browser_info_fields' );


function cardinity_edd_external_nocc() {


	$logged_in = is_user_logged_in();
	$customer  = EDD()->session->get( 'customer' );
	$customer  = wp_parse_args( $customer, array( 'address' => array(
		'line1'   => '',
		'line2'   => '',
		'city'    => '',
		'zip'     => '',
		'state'   => '',
		'country' => ''
	) ) );

	$customer['address'] = array_map( 'sanitize_text_field', $customer['address'] );

	if( $logged_in ) {

		//$user_address = get_user_meta( get_current_user_id(), '_edd_user_address', true );
        $user_address = edd_get_customer_address(get_current_user_id());

		foreach( $customer['address'] as $key => $field ) {
			if ( empty( $field ) && ! empty( $user_address[ $key ] ) ) {
				$customer['address'][ $key ] = $user_address[ $key ];
			} else {
                $customer['address'][ $key ] = '';
			}
		}

	}

    //Add billing address fields
    do_action('edd_after_cc_fields');
}

//if external remove cc form, keep billing form
if($is_external_enabled){
    add_action("edd_" . CARDINITY_GATEWAY_NAME."_cc_form", 'cardinity_edd_external_nocc');
}


/**
 * Process purchase through Cardinitity Gateway
 *
 * @param   array $purchase_data    Purchase Data
 * @return  void
 */
function cardinity_edd_process_payment($purchase_data)
{
    //Verify nonce
    if (!wp_verify_nonce($purchase_data['gateway_nonce'], 'edd-gateway')) {
        edd_debug_log("fail from missing nonce", true);
        wp_die(__('Nonce verification has failed', 'easy-digital-downloads'),
            __('Error', 'easy-digital-downloads'), array('response' => 403));
    }

    //check for errors in the form
    $errors = edd_get_errors();

    if (!$errors) {
        $edd_payment_id = array(
            'price' => $purchase_data['price'],
            'date' => $purchase_data['date'],
            'user_email' => $purchase_data['user_email'],
            'purchase_key' => $purchase_data['purchase_key'],
            'currency' => edd_get_currency(),
            'downloads' => $purchase_data['downloads'],
            'cart_details' => $purchase_data['cart_details'],
            'user_info' => $purchase_data['user_info'],
            'status' => 'pending',
        );


        // create a record of the pending payment into EDD payment history
        $edd_payment_id = edd_insert_payment($edd_payment_id);

        //padding order ID in case the length is less than 2
        $cardinity_order_id = strval(edd_get_payment($edd_payment_id)->number);
        $cardinity_order_id = str_pad($cardinity_order_id, 2, '0', STR_PAD_LEFT);

        // Remove whitespace from card number
        $card_number = preg_replace('/\s+/','', $purchase_data['post_data']['card_number']);

        $get_params = array(
            "edd-listener" => CARDINITY_GATEWAY_NAME,
            'purchase_key' => $purchase_data['purchase_key'],
        );
        $notificationUrl = edd_get_checkout_uri($get_params);

        $paymentMethodParams = [
            'amount' => $purchase_data['price'],
            'currency' => edd_get_currency(),
            'settle' => true,
            'order_id' => $cardinity_order_id,
            'country' => $purchase_data['post_data']['billing_country'],
            'payment_method' => Cardinity\Method\Payment\Create::CARD,
            'payment_instrument' => [
                'pan' => $card_number,
                'exp_year' => (int) $purchase_data['post_data']['card_exp_year'],
                'exp_month' => (int) $purchase_data['post_data']['card_exp_month'],
                'cvc' => $purchase_data['post_data']['card_cvc'],
                'holder' => $purchase_data['post_data']['card_name'],
            ],
            'threeds2_data' =>  [
                "notification_url" =>  $notificationUrl,
                "browser_info" => [
                    "accept_header" => "text/html",
                    "browser_language" => $purchase_data['post_data']['cardinity_browser_language'] ?? "en-US",
                    "screen_width" => (int) $purchase_data['post_data']['cardinity_screen_width']  ?? 1920,
                    "screen_height" => (int) $purchase_data['post_data']['cardinity_screen_height']  ?? 1040,
                    'challenge_window_size' => $purchase_data['post_data']['cardinity_challenge_window_size'] ?? 'full-screen',
                    "user_agent" => $_SERVER['HTTP_USER_AGENT'] ?? "Mozilla/5.0 (X11; Ubuntu; Linux x86_64; rv:21.0) Gecko/20100101 Firefox/21.0",
                    "color_depth" => (int) $purchase_data['post_data']['cardinity_color_depth'] ?? 24,
                    "time_zone" =>  (int) $purchase_data['post_data']['cardinity_time_zone']?? -60
                ],
                'cardholder_info' => [
                    'email_address' => $purchase_data['user_email']
                ]
            ]
        ];


        //initialize cardinity payment
        $method = new Cardinity\Method\Payment\Create($paymentMethodParams);

        cardinity_edd_call_payment_api($edd_payment_id, $method);
    } else {

        edd_debug_log(print_r($errors, true), true);
    }
}


/**
 * Process payment through external gateway
 */
function cardinity_edd_external_payment($purchase_data)
{
    //Verify nonce
    if (!wp_verify_nonce($purchase_data['gateway_nonce'], 'edd-gateway')) {
        wp_die(__('Nonce verification has failed', 'easy-digital-downloads'),
            __('Error', 'easy-digital-downloads'), array('response' => 403));
    }

    //check for errors in the form
    $errors = edd_get_errors();
    if (!$errors) {
        $edd_payment_id = array(
            'price' => $purchase_data['price'],
            'date' => $purchase_data['date'],
            'user_email' => $purchase_data['user_email'],
            'purchase_key' => $purchase_data['purchase_key'],
            'currency' => edd_get_currency(),
            'downloads' => $purchase_data['downloads'],
            'cart_details' => $purchase_data['cart_details'],
            'user_info' => $purchase_data['user_info'],
            'status' => 'pending',
        );

        // create a record of the pending payment into EDD payment history
        $edd_payment_id = edd_insert_payment($edd_payment_id);

        //padding order ID in case the length is less than 2
        $cardinity_order_id = strval(edd_get_payment($edd_payment_id)->number);
        $cardinity_order_id = str_pad($cardinity_order_id, 2, '0', STR_PAD_LEFT);


        $get_params = array(
            "edd-listener" => CARDINITY_GATEWAY_NAME,
            "external-callback" => 'true',
            'purchase_key' => $purchase_data['purchase_key'],
            'cancel' => 'true',
        );
        $cancel_url = edd_get_checkout_uri($get_params);

        $get_params = array(
            "edd-listener" => CARDINITY_GATEWAY_NAME,
            "external-callback" => 'true',
            'purchase_key' => $purchase_data['purchase_key'],
        );
        $callback_url = edd_get_checkout_uri($get_params);

        $get_params = array(
            "edd-listener" => CARDINITY_GATEWAY_NAME,
            "external-callback" => 'true',
            "external-notification" => 'true',
            'purchase_key' => $purchase_data['purchase_key'],
        );
        $notification_url = edd_get_checkout_uri($get_params);

        $amount = number_format((float) $purchase_data['price'], 2, '.', '') ;
        $cancel_url = $cancel_url;
        $country = $purchase_data['post_data']['billing_country'];
        $currency = edd_get_currency();
        $description = $edd_payment_id ; //. '&crd=' . $cardinity_payment->getId();
        $order_id =  $cardinity_order_id;
        $return_url =  $callback_url;

        global $edd_options;
        $project_id = $edd_options[CARDINITY_GATEWAY_NAME . '_project_key'];
        $project_secret = $edd_options[CARDINITY_GATEWAY_NAME . '_project_secret'];

        $attributes = [
            "amount" => $amount,
            "currency" => $currency,
            "country" => $country,
            "order_id" => $order_id,
            "description" => $description,
            'email_address' => $purchase_data['user_email'],
            "project_id" => $project_id,
            "cancel_url" => $cancel_url,
            "return_url" => $return_url,
            "notification_url" => $notification_url
        ];

        ksort($attributes);

        $message = '';
        foreach($attributes as $key => $value) {
            $message .= $key.$value;
        }

        $signature = hash_hmac('sha256', $message, $project_secret);

        //Build the external request form
        $requestForm = '<html>
        <head>
            <title>Request Example | Hosted Payment Page</title>
            <script type="text/javascript">setTimeout(function() { document.getElementById("externalPaymentForm").submit(); }, 5000);</script>
        </head>
        <body>
            <div style="text-align: center; width: 300px; position: fixed; top: 30%; left: 50%; margin-top: -50px; margin-left: -150px;">
                <h2>You will be redirected to external gateway shortly. </h2>
                <p>If browser does not redirect after 5 seconds, press Submit</p>
                <form id="externalPaymentForm" name="checkout" method="POST" action="https://checkout.cardinity.com">
                    <button type=submit>Click Here</button>
                    <input type="hidden" name="amount" value="' . $attributes['amount'] . '" />
                    <input type="hidden" name="cancel_url" value="' . $attributes['cancel_url'] . '" />
                    <input type="hidden" name="country" value="' . $attributes['country'] . '" />
                    <input type="hidden" name="currency" value="' . $attributes['currency'] . '" />
                    <input type="hidden" name="description" value="' . $attributes['description'] . '" />
                    <input type="hidden" name="email_address" value="' . $attributes['email_address'] . '" />
                    <input type="hidden" name="order_id" value="' . $attributes['order_id'] . '" />
                    <input type="hidden" name="project_id" value="' . $attributes['project_id'] . '" />
                    <input type="hidden" name="return_url" value="' . $attributes['return_url'] . '" />
                    <input type="hidden" name="notification_url" value="' . $attributes['notification_url'] . '" />
                    <input type="hidden" name="signature" value="' . $signature . '" />
                </form>
            </div>
        </body>
        </html>';

        echo $requestForm;
        //we dont want to do anything else. just show html form and redirect
        exit();
    }
}


if($is_external_enabled){
    add_action("edd_gateway_" . CARDINITY_GATEWAY_NAME, 'cardinity_edd_external_payment');
}else{
    add_action("edd_gateway_" . CARDINITY_GATEWAY_NAME, 'cardinity_edd_process_payment');
}


/**
 * Listens for 3D Secure responses and forwards them to the processing function
 *
 * @return void
 */
function cardinity_edd_listen_for_3dsecure()
{
    if (isset($_GET['edd-listener']) && $_GET['edd-listener'] == CARDINITY_GATEWAY_NAME) {
        //this is here for 3ds
        do_action('cardinity_verify_3dsecure');
    }
}

/**
 * Listens for External responses and forwards them to the processing function
 *
 * @return void
 */
function cardinity_edd_listen_for_external()
{
    if (isset($_GET['edd-listener']) && $_GET['edd-listener'] == CARDINITY_GATEWAY_NAME) {
        if (isset($_GET['external-callback']) && $_GET['external-callback'] == "true") {
            //this is here for External
            do_action('cardinity_finalize_external');
        }
    }
}

if($is_external_enabled){
    add_action('init', 'cardinity_edd_listen_for_external');
}else{
    add_action('init', 'cardinity_edd_listen_for_3dsecure');
}


/**
 * Processes External Response
 *
 * @return void
 */
function cardinity_edd_process_external()
{
    //edd payment id is on description
    $edd_payment_id = $_POST['description'];
    $payment_id = $_POST['id'];
    $purchase_key = $_GET['purchase_key'];
    $order = edd_get_payment( $edd_payment_id );

    $is_notification_call = isset($_GET['external-notification'])
    && $_GET['external-notification'] == "true";

    if ($_POST['status'] == 'approved' && $order->key == $purchase_key) {
        // Double check the session and re establish from purchase key if not present
        $session = edd_get_purchase_session();
        if ( ! $session ) {
            $session = array();
        }
        $session['purchase_key'] = $purchase_key;
        edd_set_purchase_session( $session );


        //if order currently not complete
        if ($order->status != "complete"){

            //complete the order
            edd_update_payment_status($edd_payment_id, 'complete');

            //Stores Cardinity purchase key for further use in refunds
            edd_update_payment_meta($edd_payment_id, 'purchase_key', $payment_id);

            if($is_notification_call){
                edd_insert_payment_note($edd_payment_id, __('Cardinity payment approved by notification, ID: ' . $payment_id, 'easy-digital-downloads'));
            }else{
                edd_insert_payment_note($edd_payment_id, __('Cardinity payment approved, ID: ' . $payment_id, 'easy-digital-downloads'));
            }

            //finish
            edd_empty_cart();
        }

        if ($is_notification_call){
            echo "Notification Recieved by EDD";
        } else {
            edd_send_to_success_page();
        }

    } else { // Should never happen
        edd_set_error('unexpected_error', __('Unexpected error', 'easy-digital-downloads'));
        edd_insert_payment_note($edd_payment_id, __('Unexpected Error: Unexpected payment status', 'easy-digital-downloads'));
        edd_update_payment_status($edd_payment_id, 'failed');
        edd_send_back_to_checkout('?payment-mode=' . CARDINITY_GATEWAY_NAME);
    }

}
add_action('cardinity_finalize_external', 'cardinity_edd_process_external');



/**
 * Processes 3D secure responses
 *
 * @return void
 */
function cardinity_edd_process_3dsecure()
{
    $purchase_key = $_GET['purchase_key']; // added to the callback URL

    if(isset($_POST['MD']) && !isset($_POST['threeDSSessionData'])){
        //its version 1
        $params = explode('&crd=', $_POST['MD']);
        $edd_payment_id = $params[0];
        $order = edd_get_payment( $edd_payment_id );
        $method = new Cardinity\Method\Payment\Finalize($params[1], $_POST['PaRes']);

    }else{
        //its version 2
        $params = explode('&crd=', decodeBase64url($_POST['threeDSSessionData']));
        $edd_payment_id = $params[0];
        $order = edd_get_payment( $edd_payment_id );
        $method = new Cardinity\Method\Payment\Finalize($params[1], $_POST['cres'], true);
        //cardinity_edd_call_payment_api($edd_payment_id, $method);
    }

    if ($order->key == $purchase_key){
        // Double check the session handler and add the purchase key to it.
        $session = edd_get_purchase_session();
        if ( ! $session ) {
            $session = array();
        }
        $session['purchase_key'] = $purchase_key;
        edd_set_purchase_session( $session );
    }
    cardinity_edd_call_payment_api($edd_payment_id, $method);
}
add_action('cardinity_verify_3dsecure', 'cardinity_edd_process_3dsecure');



function prepareThreeDSecureV2Form($acs_url, $creq, $threeDSSessionData){
    return <<<EOD
        <p>
            Redirecting to 3D-Secure Version 2 Authorization page.
            If your browser does not start loading the page,
            press the button below.
            You will be sent back to this site after you
            authorize the transaction.
        </p>
        <form name="ThreeDForm" id="ThreeDForm" method="POST" action="$acs_url">
            <button type=submit>Click Here</button>
            <input type="hidden" name="creq" value="$creq" />
            <input type="hidden" name="threeDSSessionData" value="$threeDSSessionData" />
        </form>
        <script>
        window.onload=function(){
            window.setTimeout(document.ThreeDForm.submit.bind(document.ThreeDForm), 2000);
        };
        </script>
    EOD;
}
function prepareThreeDSecureForm($acsUrl, $paReq, $termUrl, $md) {
    return <<<EOD
    <p>
        Redirecting to 3D-Secure  Version 1 Authorization page.
        If your browser does not start loading the page,
        press the button below.
        You will be sent back to this site after you
        authorize the transaction.
    </p>
    <form name="ThreeDForm" id="ThreeDForm" method="POST" action="$acsUrl">
        <button type=submit>Click Here</button>
        <input type="hidden" name="PaReq" value="$paReq" />
        <input type="hidden" name="TermUrl" value="$termUrl" />
        <input type="hidden" name="MD" value="$md" />
    </form>
    <script>
    window.onload=function(){
        window.setTimeout(document.ThreeDForm.submit.bind(document.ThreeDForm), 2000);
    };
    </script>
EOD;
}


/**
 * Processes cardinity payment requests
 *
 * @param   int $edd_payment_id     EDD payment ID
 * @param   object $method          Cardinity API method
 * @return  void
 */
function cardinity_edd_call_payment_api($edd_payment_id, $method)
{
    try {
        $client = cardinity_edd_get_client();
        $cardinity_payment = $client->call($method);

        $payment_status = $cardinity_payment->getStatus();

        if ($payment_status == 'approved') {
            edd_update_payment_status($edd_payment_id, 'complete');
            $payment_id = $cardinity_payment->getId();

            //Stores Cardinity purchase key for further use in refunds
            edd_update_payment_meta($edd_payment_id, 'purchase_key', $payment_id);

            edd_insert_payment_note($edd_payment_id, __('Cardinity payment approved, ID: ' . $payment_id, 'easy-digital-downloads'));
            edd_empty_cart();
            edd_send_to_success_page();
        } else if ($payment_status == 'pending') {
            //Required 3D Secure authorization


            //Is v2
            if( $cardinity_payment->isThreedsV2() && ! $cardinity_payment->isThreedsV1()){

                $acs_url = $cardinity_payment->getThreeds2data()->getAcsUrl();
                $creq = $cardinity_payment->getThreeds2data()->getCReq();
                $threeDSSessionData = $edd_payment_id . '&crd=' . $cardinity_payment->getId();

                // Append Cardinity Gateway URL parameter for 3D-secure callback
                //$get_params = array("edd-listener" => CARDINITY_GATEWAY_NAME);
                //$termUrl = edd_get_checkout_uri($get_params);
                //Insert both payment EDD payment ID and Cardinity payment ID, separated by '&crd=' for later parsing
                //EDD payment for changing status in the system, Cardinity ID for payment finalizing
                //$md = $edd_payment_id . '&crd=' . $cardinity_payment->getId();

                //Proceed to 3D secure verification
                print_r(prepareThreeDSecureV2Form($acs_url, $creq, encodeBase64Url($threeDSSessionData)));
                exit();

            }else{

                $url = $cardinity_payment->getAuthorizationInformation()->getUrl();
                $paReq = $cardinity_payment->getAuthorizationInformation()->getData();
                // Append Cardinity Gateway URL parameter for 3D-secure callback
                $get_params = array("edd-listener" => CARDINITY_GATEWAY_NAME);
                $termUrl = edd_get_checkout_uri($get_params);
                //Insert both payment EDD payment ID and Cardinity payment ID, separated by '&crd=' for later parsing
                //EDD payment for changing status in the system, Cardinity ID for payment finalizing
                $md = $edd_payment_id . '&crd=' . $cardinity_payment->getId();

                //Proceed to 3D secure verification
                print_r(prepareThreeDSecureForm($url, $paReq, $termUrl, $md));
                exit();
            }




        } else { // Should never happen
            edd_set_error('unexpected_error', __('Unexpected error', 'easy-digital-downloads'));
            edd_insert_payment_note($edd_payment_id, __('Unexpected Error: Unexpected payment status', 'easy-digital-downloads'));
            $fail = true;
        }

    } catch (Cardinity\Exception\InvalidAttributeValue $exception) {
        $violations = $exception->getViolations();
        foreach ($violations as $violation) {
            edd_set_error('validation_error', __('Invalid Value '.$violation->getPropertyPath().' : ' . $violation->getMessage(), 'easy-digital-downloads'));
            edd_insert_payment_note($edd_payment_id, __('Payment Failed: ' . $violation->getMessage(), 'easy-digital-downloads'));
        }
        $fail = true;
    } catch (Cardinity\Exception\Unauthorized $exception) {
        edd_set_error('unauthorized_error', __('Authorization error. Check API key settings.', 'easy-digital-downloads'));
        edd_insert_payment_note($edd_payment_id, __('Payment Failed: Authorization error. Check API key settings.', 'easy-digital-downloads'));
        $fail = true;
    } catch (Cardinity\Exception\Request $exception) {
        $errors = $exception->getErrors();
        $errorString = '';
        foreach ($errors as $error) {
            $errorString .= $error['message'] .= ' ';
        }
        edd_set_error('request_error', __('Payment Failed. ' . $errorString, 'easy-digital-downloads'));
        edd_insert_payment_note($edd_payment_id, __('Payment Failed: ' . $errorString, 'easy-digital-downloads'));
        $fail = true;
    } catch (Cardinity\Exception\Runtime $exception) {
        edd_set_error('runtime_error', __('Payment Failed. Internal Error.', 'easy-digital-downloads'));
        edd_insert_payment_note($edd_payment_id, __('Payment Failed: ' . $exception->getMessage(), 'easy-digital-downloads'));
        $fail = true;
    }

    //Payment Error Handling
    if ($fail) {
        edd_update_payment_status($edd_payment_id, 'failed');
        edd_send_back_to_checkout('?payment-mode=' . CARDINITY_GATEWAY_NAME);
    }
}

function cardinity_edd_call_refund_api( $order_id, $refund_id, $all_refunded ) {

    $order = edd_get_order($order_id);
    $refund_order = edd_get_order($refund_id);

    $refund_success = false;
    $fail_message = '';

    // Get our data out of the serialized string.
    parse_str( $_POST['data'], $form_data );

    // Verify the nonce.
    $nonce = ! empty( $form_data['edd_process_refund'] ) ? sanitize_text_field( $form_data['edd_process_refund'] ) : false;
    if ( empty( $nonce ) || ! wp_verify_nonce( $nonce, 'edd_process_refund' ) ) {
        $fail_message = __( 'Nonce validation failed when submitting refund.', 'easy-digital-downloads' );
    }

    //in case partial refund requested, abort refund and show reason
    try {
        $client = cardinity_edd_get_client();

        $method = new Cardinity\Method\Refund\Create(
            edd_get_order_meta( $order->id, 'purchase_key', true ),
            (float)sprintf('%.2f', abs( $refund_order->total)) //refund order obj has total in negative
        );

        $cardinity_refund = $client->call($method);

        if ($cardinity_refund->isApproved()) {

            edd_add_note(array(
                'object_id' => $order_id,
                'object_type' => 'order',
                'content' => __('Refund successful. Refund ID: ','easy-digital-downloads').$cardinity_refund->getId()
            ));
            $refund_success = true;
        } else {
            if($cardinity_refund->isProcessing()){
                edd_add_note(array(
                    'object_id' => $order_id,
                    'object_type' => 'order',
                    'content' => __('Refund processing, check https://my.cardinity.com/ for latest status. Refund ID: ','easy-digital-downloads').$cardinity_refund->getId()
                ));

                //set refund status, and notes
                edd_update_order_status($refund_id,'processing');
                edd_add_note(array(
                    'object_id' => $refund_id,
                    'object_type' => 'order',
                    'content' => __('Refund processing, check https://my.cardinity.com/ for latest status. Refund ID: ','easy-digital-downloads').$cardinity_refund->getId()
                ));
                $refund_success = true;
            } else {
                $fail_message = __("Unable to refund");
            }

        }

    } catch (Cardinity\Exception\Request $exception) {
        $errorString = '';
        foreach ($exception->getErrors() as $error) {
            $errorString .= $error['message'] .= ' ';
        }
        edd_add_note(array(
            'object_id' => $order_id,
            'object_type' => 'order',
            'content' => __("Refund declined : ", "easy-digital-downloads").$errorString
        ));
        $fail_message = __("Refund declined : ", "easy-digital-downloads").$errorString;
    } catch (Cardinity\Exception\Runtime $exception) {
        edd_add_note(array(
            'object_id' => $order_id,
            'object_type' => 'order',
            'content' => __('Refund declined : ' , 'easy-digital-downloads'). $exception->getMessage()
        ));
        $fail_message = __("Refund declined : ", "easy-digital-downloads").$exception->getMessage();
    } catch (Exception $exception) {
        edd_add_note(array(
            'object_id' => $order_id,
            'object_type' => 'order',
            'content' => __('Refund declined : ' , 'easy-digital-downloads'). $exception->getMessage()
        ));
        $fail_message = __("Refund declined : ", "easy-digital-downloads").$exception->getMessage();
    }

    //if refund was not success or processing
    if(!$refund_success){

        //delete wp refund object and its items

        foreach ($refund_order->items as $item){
            edd_delete_order_item($item->id);
        }
        edd_delete_order($refund_id);

        //set to a status that allows to retry
        edd_update_order_status($order_id,'partially_refunded');

        //send error
        wp_send_json_error($fail_message);
    }
}
add_action( 'edd_refund_order', 'cardinity_edd_call_refund_api', 10, 3 );

function cardinity_edd_get_client()
{
    global $edd_options;
    if (edd_is_test_mode()) {
        $key = $edd_options[CARDINITY_GATEWAY_NAME . '_test_api_key'];
        $secret = $edd_options[CARDINITY_GATEWAY_NAME . '_test_api_secret'];
    } else {
        $key = $edd_options[CARDINITY_GATEWAY_NAME . '_live_api_key'];
        $secret = $edd_options[CARDINITY_GATEWAY_NAME . '_live_api_secret'];
    }

    //initiate cardinity client
    $client = Cardinity\Client::create([
        'consumerKey' => $key,
        'consumerSecret' => $secret,
    ]);
    return $client;
}
