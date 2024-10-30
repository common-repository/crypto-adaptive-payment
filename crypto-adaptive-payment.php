<?php
/*
Plugin Name: 		Crypto Adaptive Payment
Plugin URI: 		https://3D-Prototype.co.uk
Description: 		Crypto adaptive payments which takes the payment and send shares to multiple wallet.
Version: 			1.2.8
Author: 			Ankur Sharma
Author URI: 		https://gourl.io
WC requires at least: 	2.1.0
WC tested up to: 	3.9.0

*/



if (!defined( 'ABSPATH' )) exit; // Exit if accessed directly

if (!function_exists('cryptoAdaPaymentgateway_load') && !function_exists('cryptoAdaPaymentaction_links')) // Exit if duplicate
{


	DEFINE('CRYPTOADAP', 'gourl-woocommerce');
	DEFINE('CRYPTOADAP_2WAY', json_encode(array("BTC", "BCH", "BSV", "LTC", "DASH", "DOGE")));


	if (!defined('CRYPTOADAP_AFFILIATE_KEY'))
	{
		DEFINE('CRYPTOADAP_AFFILIATE_KEY', 	'gourl');
		add_action( 'plugins_loaded', 		'cryptoAdaPaymentgateway_load', 20 );
		add_filter( 'plugin_action_links', 	'cryptoAdaPaymentaction_links', 10, 2 );
	}
	

	
	function cryptoAdaPaymentaction_links($links, $file)
	{
		static $this_plugin;

		if (!class_exists('WC_Payment_Gateway')) return $links;

		if (false === isset($this_plugin) || true === empty($this_plugin)) {
			$this_plugin = plugin_basename(__FILE__);
		}

		if ($file == $this_plugin) {
			$settings_link = '<a href="'.admin_url('admin.php?page=wc-settings&tab=checkout&section=wc_gateway_cryptoAdaPayment').'">'.__( 'Settings', CRYPTOADAP ).'</a>';
			array_unshift($links, $settings_link);

			if (defined('GOURL'))
			{
				$unrecognised_link = '<a href="'.admin_url('admin.php?page='.GOURL.'payments&s=unrecognised').'">'.__( 'Unrecognised', CRYPTOADAP ).'</a>';
				array_unshift($links, $unrecognised_link);
				$payments_link = '<a href="'.admin_url('admin.php?page='.GOURL.'payments&s=cryptoadacallback').'">'.__( 'Payments', CRYPTOADAP ).'</a>';
				array_unshift($links, $payments_link);
			}
		}

		return $links;
	}



	
 /*
  *	Plugin Load
  */
 function cryptoAdaPaymentgateway_load()
 {

	// WooCommerce required
	if (!class_exists('WC_Payment_Gateway') || class_exists('WC_Gateway_CryptoAdaPayment')) return;

	add_filter( 'woocommerce_payment_gateways', 		'cryptoAdaPaymentgateway_add' );
	add_action( 'woocommerce_view_order', 				'cryptoAdaPaymentpayment_history', 10, 1 );
	add_action( 'woocommerce_email_after_order_table', 	'cryptoAdaPaymentpayment_link', 15, 2 );
	add_filter( 'woocommerce_currency_symbol', 			'cryptoAdaPaymentcurrency_symbol', 10, 2);
	add_filter( 'wc_get_price_decimals',                'cryptoAdaPaymentcurrency_decimals', 10, 1 );
	add_filter( 'woocommerce_get_price_html',           'cryptoAdaPaymentprice_html', 10, 2 );




	// Set price in USD/EUR/GBR in the admin panel and display that price in Bitcoin for the front-end user
	if (!current_user_can('manage_options'))
	{
	    if (true === version_compare(WOOCOMMERCE_VERSION, '3.0', '<'))
	    { // WooCommerce 2.x+
		  add_filter( 'woocommerce_get_sale_price', 	'cryptoAdaPaymentcrypto_price', 10, 2 );
		  add_filter( 'woocommerce_get_regular_price', 	'cryptoAdaPaymentcrypto_price', 10, 2 );
		  add_filter( 'woocommerce_get_price', 			'cryptoAdaPaymentcrypto_price', 10, 2 );
	    }
	    else
	    {  // WooCommerce 3.x+
	        add_filter( 'woocommerce_product_get_sale_price',              'cryptoAdaPaymentcrypto_price', 10, 2 );
	        add_filter( 'woocommerce_product_get_regular_price',           'cryptoAdaPaymentcrypto_price', 10, 2 );
	        add_filter( 'woocommerce_product_get_price', 			       'cryptoAdaPaymentcrypto_price', 10, 2 );

	        add_filter( 'woocommerce_product_variation_get_sale_price',    'cryptoAdaPaymentcrypto_price', 10, 2 );
	        add_filter( 'woocommerce_product_variation_get_regular_price', 'cryptoAdaPaymentcrypto_price', 10, 2 );
	        add_filter( 'woocommerce_product_variation_get_price',         'cryptoAdaPaymentcrypto_price', 10, 2 );

    		add_filter('woocommerce_variation_prices_sale_price',          'cryptoAdaPaymentcrypto_price', 10, 2 );
    		add_filter('woocommerce_variation_prices_regular_price',       'cryptoAdaPaymentcrypto_price', 10, 2 );
    		add_filter('woocommerce_variation_prices_price',               'cryptoAdaPaymentcrypto_price', 10, 2 );
	    }
	}

	add_filter('woocommerce_get_variation_prices_hash',              'gourl_wc_variation_prices_hash', 10, 1 );
	add_action('woocommerce_admin_order_data_after_billing_address', 'cryptoAdaPaymentorder_stats');



	/*
	 *  4. You set product prices in USD/EUR/etc in the admin panel, and display those prices in Cryptocurrency (Bitcoin, BCH, BSV, LTC, DASH, DOGE)  for front-end users
	 *  Admin user - if current_user_can('manage_options') return true
	*/
	function cryptoAdaPaymentcurrency_type( $currency = "" )
	{
	    static $res = array();

	    if (!$currency && function_exists('get_woocommerce_currency')) $currency = get_woocommerce_currency();

	    if ($currency && isset($res[$currency]["user"]) && $res[$currency]["user"]) return $res[$currency];

	    if (in_array(strlen($currency), array(6, 7)) && in_array(substr($currency, 3), json_decode(CRYPTOADAP_2WAY, true)) && in_array(substr($currency, 0, 3), array_keys(json_decode(GOURL_RATES, true))))
	    {
	        $user_currency  = substr($currency, 3);
	        $admin_currency = substr($currency, 0, 3);
	        $twoway = true;
	    }
	    else
	    {
	        $user_currency  = $admin_currency = $currency;
	        $twoway = false;
	    }

	    $res[$currency] = array(   "2way"  => $twoway,
            	                   "admin" => $admin_currency,
            	                   "user"  => $user_currency
            	                );

	    return $res[$currency];
	}




	/*
	 *	5. Currency symbol
	 */
	function cryptoAdaPaymentcurrency_symbol ( $currency_symbol, $currency )
	{
	    global $post;


	    if (!function_exists('gourl_bitcoin_live_price') || !function_exists('gourl_altcoin_btc_price')) return substr($currency, 0, 3);

	    if (cryptoAdaPaymentcurrency_type($currency)["2way"])
	    {
	        if (current_user_can('manage_options') && isset($post->post_type) && $post->post_type == "product")
	        {
	            $currency_symbol = get_woocommerce_currency_symbol(substr($currency, 0, 3));
	            if (!$currency_symbol) $currency_symbol = substr($currency, 0, 3);
	        }
	        elseif (current_user_can('manage_options') && isset($_GET["page"]) && $_GET["page"] == "wc-settings" && (!isset($_GET["tab"]) || $_GET["tab"] == "general"))
	        {
	            $currency_symbol = substr($currency, 0, 3) . " &#10143; " . substr($currency, 3);  // Currency options Menu
	        }
	        else $currency_symbol = substr($currency, 3);
	    }
	    elseif (class_exists('gourlclass') && defined('GOURL') && defined('GOURL_ADMIN'))
	    {
	        $arr = gourlclass::coin_names();

	        if (isset($arr[$currency])) $currency_symbol = $currency;
	    }

	    if ($currency_symbol == "BTC") $currency_symbol = "&#579;";
	    if ($currency == "IRR") $currency_symbol = "&#65020;";
	    if ($currency == "IRT") $currency_symbol = "&#x62A;&#x648;&#x645;&#x627;&#x646;";


	    return $currency_symbol;
	}



	function cryptoAdaPaymentprice_html ( $price, $obj )
	{
        global $woocommerce;
        static $cryptoprice = '';
        static $rates = array();

        if (!class_exists('gourlclass') || is_admin()) return $price;

        // Settings
         if (!$cryptoprice)
         {
             $gateways = $woocommerce->payment_gateways->payment_gateways();
             if (isset($gateways['gourlpayments'])) $cryptoprice = $gateways['gourlpayments']->get_option('cryptoprice');
         }

        $val = (true === version_compare(WOOCOMMERCE_VERSION, '3.0', '<')) ? $obj->price : $obj->get_price();
        if (!$val || !$cryptoprice) return $price;

        if (function_exists('get_woocommerce_currency')) $currency = get_woocommerce_currency();
        if (!$currency || in_array(strlen($currency), array(6, 7))) return $price;

        $arr = gourlclass::coin_names();
        if (isset($arr[$currency])) return $price;


        // Get Live Rates for BTC-USD, BTC-EUR, BTC-AUD, etc.
        if (!isset($rates["BTC"]) || !$rates["BTC"]) $rates["BTC"] = gourl_bitcoin_live_price ($currency);

        $priceBTC = 0;
        if ($rates["BTC"]) $priceBTC  = sprintf('%.5f', $val / $rates["BTC"]);
        if (strpos($priceBTC, ".") && substr($priceBTC, -1) == "0") $priceBTC = rtrim(rtrim($priceBTC, "0"), ".");
        if (!$rates["BTC"] || !$priceBTC) return $price;


        $prices = array();
        $arr2   = explode("_", $cryptoprice);

        foreach($arr2 as $v)
        if (isset($arr[$v]))
        {
            if ($v == "BTC")
            {
                $prices[] = "<span style='white-space:nowrap'>" . $priceBTC . (count($arr2) == 2 ? " BTC" : " &#579;") . "</span>";
            }
            else
            {
                // Get Altcoins Live Rates to BTC - DASH/BTC, LTC/BTC, BCH/BTC, BSV/BTC
                if (!isset($rates[$v]) || !$rates[$v]) $rates[$v] = gourl_altcoin_btc_price ($v);

                $priceAlt = 0;
                if ($rates[$v])  $priceAlt = sprintf('%.4f', $priceBTC / $rates[$v]);
                if (strpos($priceAlt, ".") && substr($priceAlt, -1) == "0") $priceAlt = rtrim(rtrim($priceAlt, "0"), ".");
                if ($priceAlt) $prices[] = "<span style='white-space:nowrap'>" . $priceAlt . " " . $v  . "</span>";
            }
        }

        if (count($prices) == 2) $price .= "<br>" . implode(" &#160;/&#160; ", $prices);
        elseif (count($prices) == 1) $price .= " &#160;/&#160; " . current($prices);

        return $price;
	}




 	/*
	 *	 6. Allowance: For fiat - 0..2 decimals, for cryptocurrency 0..4 decimals
	 */
	function cryptoAdaPaymentcurrency_decimals( $decimals )
	{
	    global $post;
	    static $res;

	    if ($res) return $res;

	    $arr = cryptoAdaPaymentcurrency_type();

	    // Set price in USD/EUR/GBR in the admin panel and display that price in Bitcoin/BitcoinCash/Litcoin/DASH/Dogecoin for the front-end user
        if ($arr["2way"])
        {
            $decimals = absint($decimals);


            if (current_user_can('manage_options') && isset($post->post_type) && $post->post_type == "product")
            {
                $decimals = 2;
            }
            elseif (function_exists('get_woocommerce_currency'))
            {
                $currency = $arr["user"]; // user visible currency
                if (in_array($currency, array("BTC", "BCH", "BSV", "DASH")) && !in_array($decimals, array(3,4))) $decimals = 4;
                if (in_array($currency, array("LTC")) && !in_array($decimals, array(2,3)))                       $decimals = 3;
                if (in_array($currency, array("DOGE")) && !in_array($decimals, array(0)))                        $decimals = 0;
            }
        }

        $res = $decimals;

        return $decimals;
	}





	/*
	 *   7. You set product prices in USD/EUR/etc in the admin panel, and display those prices in Cryptocurrency (2way mode)
	 *      Fix 'View Cart' preview 2way mode for admin
	
	function gourl_wc_2way_prices( $cart_object )
	{

	    if (cryptoAdaPaymentcurrency_type()["2way"] && current_user_can('manage_options'))
	        foreach ( $cart_object->cart_contents as $value )
	        {
	            if (true === version_compare(WOOCOMMERCE_VERSION, '3.0', '<')) $value['data']->price = cryptoAdaPaymentcrypto_price( $value['data']->price );
	            else $value['data']->set_price( cryptoAdaPaymentcrypto_price( $value['data']->get_price() ) );
	        }
	}
      */



	/*
	 *	 8. Convert Fiat to cryptocurrency for end user
	 */
	function cryptoAdaPaymentcrypto_price ( $price, $product = '' )
	{
	    global $woocommerce;
	    static $emultiplier = 0;
	    static $btc = 0;

	    $live = 0;

	    if (!$price) return $price;
	    if (!function_exists('gourl_bitcoin_live_price') || !function_exists('gourl_altcoin_btc_price')) return $price;

	    $arr = cryptoAdaPaymentcurrency_type();

	    if ($arr["2way"])
	    {
	        if (!$emultiplier)
	        {
	            $gateways = $woocommerce->payment_gateways->payment_gateways();
	            if (isset($gateways['gourlpayments'])) $emultiplier = trim(str_replace(array("%", ","), array("", "."), $gateways['gourlpayments']->get_option('emultiplier')));
	            if (!$emultiplier || !is_numeric($emultiplier) || $emultiplier < 0.01) $emultiplier = 1;
	        }

	        if (!$btc) $btc = gourl_bitcoin_live_price ($arr["admin"]); // 1BTC bitcoin price  in USD/EUR/AUD/RUB/GBP/etc.

	        if ($arr["user"] == "BTC") $live = $btc;
	        elseif (in_array($arr["user"], json_decode(CRYPTOADAP_2WAY, true))) $live = $btc * gourl_altcoin_btc_price ($arr["user"]); // altcoins 1LTC/1DASH/1BCH/1DOGE  in USD/EUR/AUD/RUB/GBP/etc.

	        if ($live > 0) $price = floatval($price) / floatval($live) * 1.01 * floatval($emultiplier);
	        else  $price = 99999;
	    }


	    return $price;

	}




	/*
	 *   9. Clear cache for live 2way crypto prices (update every hour)
	 */
	function gourl_wc_variation_prices_hash( $hash )
	{
	    $arr = cryptoAdaPaymentcurrency_type();
	    if ($arr["2way"]) $hash[] = (current_user_can('manage_options') ? $arr["admin"] : $arr["user"]."-".date("Ymdh"));

	    return $hash;
	}





	/*
	 *	10. Add GoUrl gateway
	 */
	function cryptoAdaPaymentgateway_add( $methods )
	{
		if (!in_array('WC_Gateway_CryptoAdaPayment', $methods)) {
			$methods[] = 'WC_Gateway_CryptoAdaPayment';
		}
		return $methods;
	}





	/*
	 *	11. Transactions history
	 */
	function cryptoAdaPaymentpayment_history( $order_id )
	{
		$order = new WC_Order( $order_id );

		$order_id     = (true === version_compare(WOOCOMMERCE_VERSION, '3.0', '<')) ? $order->id          : $order->get_id();
		$order_status = (true === version_compare(WOOCOMMERCE_VERSION, '3.0', '<')) ? $order->status      : $order->get_status();
		$post_status  = (true === version_compare(WOOCOMMERCE_VERSION, '3.0', '<')) ? $order->post_status : get_post_status( $order_id );
		$userID       = (true === version_compare(WOOCOMMERCE_VERSION, '3.0', '<')) ? $order->user_id     : $order->get_user_id();
		$method_title = (true === version_compare(WOOCOMMERCE_VERSION, '3.0', '<')) ? $order->payment_method_title  : $order->get_payment_method_title();

		$coin = get_post_meta($order_id, '_gourl_worder_coinname', true);
		if (!$coin) $coin = get_post_meta($order_id, 'coinname', true); // compatible with old version gourl wc plugin

		if (is_user_logged_in() && ($coin || (stripos($method_title, "bitcoin")!==false && ($order_status == "pending" || $post_status=="wc-pending"))) && (is_super_admin() || get_current_user_id() == $userID))
		{
			echo "<br><a href='".$order->get_checkout_order_received_url()."&".CRYPTOBOX_COINS_HTMLID."=".strtolower($coin)."&prvw=1' class='button wc-forward'>".__( 'View Payment Details', CRYPTOADAP )." </a>";

		}

		return true;
	}





	/*
	 *	12.
	*/
	function cryptoAdaPaymentpayment_link( $order, $is_admin_email )
	{
		$order_id    = (true === version_compare(WOOCOMMERCE_VERSION, '3.0', '<')) ? $order->id          : $order->get_id();

		$coin = get_post_meta($order_id, '_gourl_worder_coinname', true);
		if (!$coin) $coin = get_post_meta($order_id, 'coinname', true); // compatible with old version gourl wc plugin

		if ($coin) echo "<br><h4><a href='".$order->get_checkout_order_received_url()."&".CRYPTOBOX_COINS_HTMLID."=".strtolower($coin)."&prvw=1'>".__( 'View Payment Details', CRYPTOADAP )." </a></h4><br>";

		return true;
	}





	/*
	 *	13. Payment info on order page
	*/
	function cryptoAdaPaymentorder_stats( $order )
	{
	    global $gourl;

	    $order_id     = (true === version_compare(WOOCOMMERCE_VERSION, '3.0', '<')) ? $order->id          : $order->get_id();
	    $order_status = (true === version_compare(WOOCOMMERCE_VERSION, '3.0', '<')) ? $order->status      : $order->get_status();
	    $post_status  = (true === version_compare(WOOCOMMERCE_VERSION, '3.0', '<')) ? $order->post_status : get_post_status( $order_id );


	    echo "<script>
                        jQuery(document).ready(function() {
                        	jQuery('.woocommerce-Order-customerIP').replaceWith(function() {
                        		var ip = jQuery.trim(jQuery(this).text());
                        		return '<a href=\"https://myip.ms/info/whois/'+ip+'\" target=\"_blank\">' + ip + '</a>';
                        	});
                        });
        	    </script>";

	    if (!class_exists('gourlclass') || !defined('GOURL') || !is_object($gourl)) return;


	    $original_orderID     = get_post_meta( $order_id, '_gourl_worder_orderid', true );
	    $original_userID      = get_post_meta( $order_id, '_gourl_worder_userid', 	true );
	    $original_createtime  = get_post_meta( $order_id, '_gourl_worder_createtime',  true );

	    if ($original_orderID && $original_orderID == $order_id && strtotime($original_createtime))
	    {

	        $coinName      = get_post_meta( $order_id, '_gourl_worder_coinname', true );
	        $confirmed     = get_post_meta( $order_id, '_gourl_worder_confirmed', true );
	        $orderpage     = get_post_meta( $order_id, '_gourl_worder_orderpage', true );
	        $created       = get_post_meta( $order_id, '_gourl_worder_created', true );
	        $preceived     = get_post_meta( $order_id, '_gourl_worder_preceived', true );
	        $paymentID     = get_post_meta( $order_id, '_gourl_worder_paymentid', true );
	        $pdetails      = get_post_meta( $order_id, '_gourl_worder_pdetails', true );
	        $pcountry      = get_post_meta( $order_id, '_gourl_worder_pcountry', true );
	        $pcountrycode  = get_post_meta( $order_id, '_gourl_worder_pcountrycode', true );

	        $amountcrypto  = get_post_meta( $order_id, '_gourl_worder_amountcrypto', true );
	        $amountfiat    = get_post_meta( $order_id, '_gourl_worder_amountfiat', true );

	        $pamountcrypto = get_post_meta( $order_id, '_gourl_worder_pamountcrypto', true ); // 1.1 BTC
	        $pamountusd    = get_post_meta( $order_id, '_gourl_worder_pamountusd', true );    // 4350 USD
	        $pamountmain   = get_post_meta( $order_id, '_gourl_worder_pamountmain', true );   // 3300 GBP/EUR/DASH/BTC

	        $txID          = get_post_meta( $order_id, '_gourl_worder_txid', true );
	        $txURL         = $gourl->blockexplorer_tr_url($txID, $coinName);
	        $addr          = get_post_meta( $order_id, '_gourl_worder_addrid', true );
	        $addrURL       = $gourl->blockexplorer_addr_url($addr, $coinName);

	        $userprofile   = (!$original_userID) ? __('Guest', CRYPTOADAP) : "<a href='".admin_url("user-edit.php?user_id=".$original_userID)."'>user".$original_userID."</a>";

	        if (!$confirmed) $pdetails .= "&b=".$paymentID;

	        $tmp = "<div class='clear'></div>
        	        <div>";

	       $h = "";
	       if ($coinName)  // payment received
	       {
	           $h .= sprintf(__( "%s Payment Received", CRYPTOADAP ), strtoupper($coinName)) . " - ";
	           if ($confirmed == "1") $h .= "<span style='color:green'>".__( 'CONFIRMED', CRYPTOADAP )."</span>";
	           else $h .= "<a title='".__( 'Check Live Status', CRYPTOADAP )."' href='".$pdetails."'><span style='color:red'>".__( 'unconfirmed', CRYPTOADAP )."</span></a>";
	       }
	       elseif ($order_status == "pending" || $post_status=="wc-pending" || $order_status == "cancelled" || $post_status=="wc-cancelled") $h .= "<span style='color:red'>".__( 'CRYPTO PAYMENT NOT RECEIVED YET !', CRYPTOADAP )."</span>";

	       if ($h) $tmp .= "<br><h3 class='gourlnowrap'>$h</h3>";

	       if ($coinName) $tmp .= "<p>"; else $tmp .= "<br>";
	       $tmp .= "<table cellspacing='5' class='gourlnowrap'>
	                <tr><td>".__( 'Order created', CRYPTOADAP )."</td><td>&#160; ".$created." ".__( 'GMT', CRYPTOADAP )."</td><td>/ &#160;<a href='".$orderpage."'>".__( 'view', CRYPTOADAP )."</a></td></tr>";

	       if ($coinName)
	       {
	           $tmp .= "<tr><td>".__( 'Payment received', CRYPTOADAP )."</td><td>&#160; ".$preceived." ".__( 'GMT', CRYPTOADAP )."</td><td>/ &#160;<a href='".$pdetails."'>#".$paymentID."</a></td></tr>";
	           $tmp .= "<tr><td colspan='2'>".sprintf(__( "Paid by %s located in %s", CRYPTOADAP ), " &#160;".$userprofile." &#160; &#160; &#160; ", "<img width='16' border='0' style='margin:0 3px 0 6px' src='".GOURL_IMG."flags/".$pcountrycode.".png'> ".$pcountry)."</td></tr>";
	       }

	       $tmp .= "</table>";
	       if ($coinName) $tmp .= "</p>";
	       $tmp .= "<table cellspacing='5' class='gourlnowrap'>
                    <tr><td>".__( 'Original order', CRYPTOADAP ).":</td><td>&#160; ".$amountcrypto."</td><td>".($amountfiat!=$amountcrypto?"/ ".$amountfiat:"")."</td></tr>";

	       if ($coinName)
	       {
	           $v = "/ ";
	           if ($pamountmain) $v .= "<b>~".$pamountmain."</b>";
	           if ($pamountmain != $pamountusd)
	           {
	               if ($pamountmain) $v .= "  &#160;(" . $pamountusd . ")";
	               else $v .= $pamountusd;
	           }
	           $tmp .= "<tr><td>".__( 'Actual Received', CRYPTOADAP ).":</td><td><b>&#160; ".$pamountcrypto."</b></td><td>".$v."</td></tr>";
	       }

	       $refunded = $order->get_total_refunded();
	       if ($refunded > 0)
	       {
	           $currencies = get_post_meta( $order_id, '_gourl_worder_currencies', false )[0];
	           $tmp .= "<tr><td>".__( 'Refunded', CRYPTOADAP ).":</td><td colspan='2' style='color:red'><b>&#160; -".$refunded." ".$currencies["user"]."</b></td></tr>";
	       }

	       $tmp .= "</table>";

	       if ($coinName)
	       {
	           $tmp .= "<p>".sprintf(__( "%s Transaction", CRYPTOADAP ), $coinName)." <a target='_blank' href='".$txURL."'>".$txID."</a> ".__( "on address", CRYPTOADAP )." <a target='_blank' href='".$addrURL."'>".$addr."</a></p>";
	       }

	       $tmp .= "</div>";

	       echo $tmp;
	   }

	   return;
	}










	/*
	 *	14. Payment Gateway WC Class
	 */
	class WC_Gateway_CryptoAdaPayment extends WC_Payment_Gateway
	{

		private $payments           = array();
		private $languages          = array();
		private $coin_names         = array('BTC' => 'bitcoin', 'BCH' => 'bitcoincash', 'BSV' => 'bitcoinsv', 'LTC' => 'litecoin', 'DASH' => 'dash', 'DOGE' => 'dogecoin', 'SPD' => 'speedcoin', 'RDD' => 'reddcoin', 'POT' => 'potcoin', 'FTC' => 'feathercoin', 'VTC' => 'vertcoin', 'PPC' => 'peercoin', 'UNIT' => 'universalcurrency', 'MUE' => 'monetaryunit');
		private $statuses           = array('processing' => 'Processing Payment', 'on-hold' => 'On Hold', 'completed' => 'Completed');
		private $cryptorices        = array();
		private $showhidemenu       = array('show' => 'Show Menu', 'hide' => 'Hide Menu');
		private $mainplugin_url     = '';
		private $url                = '';
		private $url2               = '';
		private $url3               = '';
		private $cointxt            = '';

		private $logo               =  0;
		private $emultiplier        = '';
		private $ostatus            = '';
		private $ostatus2           = '';
		private $cryptoprice        = '';
		private $deflang            = '';
		private $defcoin            = '';
		private $iconwidth          = '';

		private $customtext         = '';
		private $qrcodesize         = '';
		private $langmenu           = '';
		private $redirect           = '';



		/*
		 * 14.1
		*/
	    public function __construct()
	    {
	    	global $gourl;

			$this->id                 	= 'gourlpayments';
			$this->mainplugin_url 		= admin_url("plugin-install.php?tab=search&type=term&s=GoUrl+Official+Bitcoin+Payment+Gateway");
			$this->method_title       	= __( 'GoUrl Bitcoin/Altcoins', CRYPTOADAP );
			$this->method_description  	= "<a target='_blank' href='https://gourl.io/'><img border='0' style='float:left; margin-right:15px' src='https://gourl.io/images/gourlpayments.png'></a>";
			$this->method_description  .= "<a target='_blank' href='https://gourl.io/bitcoin-payments-woocommerce.html'>".__( 'Plugin Homepage', CRYPTOADAP )."</a> &#160;&amp;&#160; <a target='_blank' href='https://gourl.io/bitcoin-payments-woocommerce.html#screenshot'>".__( 'screenshots', CRYPTOADAP )." &#187;</a><br>";
			$this->method_description  .= "<a target='_blank' href='https://github.com/cryptoapi/Bitcoin-Payments-Woocommerce'>".__( 'Plugin on Github - 100% Free Open Source', CRYPTOADAP )." &#187;</a><br><br>";
			$this->has_fields         	= false;
			$this->supports 			= array( 'subscriptions', 'products' );

			$enabled = ((CRYPTOADAP_AFFILIATE_KEY=='gourl' && $this->get_option('enabled')==='') || $this->get_option('enabled') == 'yes' || $this->get_option('enabled') == '1' || $this->get_option('enabled') === true) ? true : false;

			if (class_exists('gourlclass') && defined('GOURL') && defined('GOURL_ADMIN') && is_object($gourl))
			{
				if (true === version_compare(GOURL_VERSION, '1.4.17', '<'))
				{
					if ($enabled) $this->method_description .= '<div class="error"><p><b>' .sprintf(__( "Your GoUrl Bitcoin Gateway <a href='%s'>Main Plugin</a> version is too old. Requires 1.4.17 or higher version. Please <a href='%s'>update</a> to latest version.", CRYPTOADAP ), GOURL_ADMIN.GOURL, $this->mainplugin_url)."</b> &#160; &#160; &#160; &#160; " .
							  __( 'Information', CRYPTOADAP ) . ": &#160; <a href='https://gourl.io/bitcoin-wordpress-plugin.html'>".__( 'Main Plugin Homepage', CRYPTOADAP )."</a> &#160; &#160; &#160; " .
							  "<a href='https://wordpress.org/plugins/gourl-bitcoin-payment-gateway-paid-downloads-membership/'>".__( 'WordPress.org Plugin Page', CRYPTOADAP )."</a></p></div>";
				}
				elseif (true === version_compare(WOOCOMMERCE_VERSION, '2.1', '<'))
				{
					if ($enabled) $this->method_description .= '<div class="error"><p><b>' .sprintf(__( "Your WooCommerce version is too old. The GoUrl payment plugin requires WooCommerce 2.1 or higher to function. Please update to <a href='%s'>latest version</a>.", CRYPTOADAP ), admin_url('plugin-install.php?tab=search&type=term&s=WooCommerce+excelling+eCommerce+WooThemes+Beautifully')).'</b></p></div>';
				}
				else
				{
					$this->payments 			= $gourl->payments(); 		// Activated Payments
					$this->coin_names			= $gourl->coin_names(); 	// All Coins
					$this->languages			= $gourl->languages(); 		// All Languages
				}

				$this->url		= GOURL_ADMIN.GOURL."settings";
				$this->url2		= GOURL_ADMIN.GOURL."payments&s=cryptoadacallback";
				$this->url3		= GOURL_ADMIN.GOURL;
				$this->cointxt 	= (implode(", ", $this->payments)) ? implode(", ", $this->payments) : __( '- Please setup -', CRYPTOADAP );
			}
			else
			{
				if ($enabled) $this->method_description .= '<div class="error" style="color:red"><p><b>' .
								sprintf(__( "You need to install GoUrl Bitcoin Gateway Main Plugin also. Go to - <a href='%s'>Automatic installation</a> or <a href='%s'>Manual</a>.", CRYPTOADAP ), $this->mainplugin_url, "https://gourl.io/bitcoin-wordpress-plugin.html") . "</b> &#160; &#160; &#160; &#160; " .
								__( 'Information', CRYPTOADAP ) . ": &#160; &#160;<a href='https://gourl.io/bitcoin-wordpress-plugin.html'>".__( 'Main Plugin Homepage', CRYPTOADAP )."</a> &#160; &#160; &#160; <a href='https://wordpress.org/plugins/gourl-bitcoin-payment-gateway-paid-downloads-membership/'>" .
								__( 'WordPress.org Plugin Page', CRYPTOADAP ) . "</a></p></div>";

				$this->url		= $this->mainplugin_url;
				$this->url2		= $this->url;
				$this->url3		= $this->url;
				$this->cointxt 	= '<b>'.__( 'Please install GoUrl Bitcoin Gateway WP Plugin', CRYPTOADAP ).' &#187;</b>';

			}

			$this->method_description  .= "<b>" . __( "White Label Product. Secure payments with virtual currency. <a target='_blank' href='https://bitcoin.org/'>What is Bitcoin?</a>", CRYPTOADAP ) . '</b><br>';
			$this->method_description  .= sprintf(__( 'Accept %s payments online in WooCommerce.', CRYPTOADAP ), __( ucwords(implode(", ", $this->coin_names)), CRYPTOADAP )).'<br>';
			if ($enabled) $this->method_description .= sprintf(__( "If you use multiple stores/sites online, please create separate <a target='_blank' href='%s'>GoUrl Payment Box</a> (with unique payment box public/private keys) for each of your stores/websites. Do not use the same GoUrl Payment Box with the same public/private keys on your different websites/stores.", CRYPTOADAP ), "https://gourl.io/editrecord/coin_boxes/0") . '<br>'.sprintf(__( "Add additional altcoins (Litecoin/DASH/Bitcoin Cash/etc) to payment box <a href='%s'>here &#187;</a>", CRYPTOADAP ), $this->url).'<br><br>';
			else $this->method_description .= '<br>';

			$this->cryptorices = array("Original Price only");
			foreach ($this->coin_names as $k => $v) $this->cryptorices[$k] = sprintf(__( "Fiat + %s", CRYPTOADAP ), ucwords($v));

			foreach ($this->coin_names as $k => $v)
			    foreach ($this->coin_names as $k2 => $v2)
			         if ($k != $k2) $this->cryptorices[$k."_".$k2] = sprintf(__( "Fiat + %s + %s", CRYPTOADAP ), ucwords($v), ucwords($v2));



			// Update some WooCommerce settings
			// --------------------------------
			// for WooCommerce 2.1x
			if ($enabled && cryptoAdaPaymentcurrency_type()["2way"] && !function_exists('wc_get_price_decimals')) update_option( 'woocommerce_price_num_decimals', 4 );

			// increase Hold stock to 200 minutes
			if ($enabled && get_option( 'woocommerce_hold_stock_minutes' ) > 0 && get_option( 'woocommerce_hold_stock_minutes' ) < 80) update_option( 'woocommerce_hold_stock_minutes', 200 );



			// Load the settings.
			$this->init_form_fields();
			$this->init_settings();
			$this->gourl_settings();

			// Logo on Checkout Page
			if ($this->logo) $this->icon = apply_filters('woocommerce_gourlpayments_icon', plugins_url("/images/crypto".$this->logo.".png", __FILE__));


			// Hooks
			add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
			add_action( 'woocommerce_thankyou_gourlpayments', array( $this, 'cryptocoin_payment' ) );


			// Subscriptions
			if ( class_exists( 'WC_Subscriptions_Order' ) ) {
			add_action( 'woocommerce_subscription_unable_to_update_status', array( $this, 'unable_to_update_subscription_status' ), 10, 3 );
			}


			if (isset($_GET["page"]) && isset($_GET["section"]) && $_GET["page"] == "wc-settings" && $_GET["section"] == "wc_gateway_cryptoAdaPayment") add_action( 'admin_footer_text', array(&$this, 'admin_footer_text'), 25);


			return true;
	    }




	    /*
	     * 14.2
	    */
	    private function gourl_settings()
	    {
	    	// Define user set variables
	    	$this->enabled      = $this->get_option( 'enabled' );
	    	$this->title        = $this->get_option( 'title' );
	    	$this->description  = $this->get_option( 'description' );
	    	$this->logo         = $this->get_option( 'logo' );
	    	$this->emultiplier  = trim(str_replace(array("%", ","), array("", "."), $this->get_option( 'emultiplier' )));
	    	$this->ostatus      = $this->get_option( 'ostatus' );
	    	$this->ostatus2     = $this->get_option( 'ostatus2' );
	    	$this->cryptoprice  = $this->get_option( 'cryptoprice' );
	    	$this->deflang      = $this->get_option( 'deflang' );
	    	$this->defcoin      = $this->get_option( 'defcoin' );
	    	$this->iconwidth    = trim(str_replace("px", "", $this->get_option( 'iconwidth' )));

	    	$this->customtext   = $this->get_option( 'customtext' );
	    	$this->qrcodesize   = trim(str_replace("px", "", $this->get_option( 'qrcodesize' )));
	    	$this->langmenu     = $this->get_option( 'langmenu' );
	    	$this->redirect     = $this->get_option( 'redirect' );


	    	// Re-check
	    	if (!$this->title)                                  $this->title 		= __('GoUrl Bitcoin/Altcoins', CRYPTOADAP);
	    	if (!$this->description)                            $this->description 	= __('Secure, anonymous payment with virtual currency', CRYPTOADAP);
	    	if (!isset($this->statuses[$this->ostatus]))        $this->ostatus  	= 'processing';
	    	if (!isset($this->statuses[$this->ostatus2]))       $this->ostatus2 	= 'processing';
	    	if (!isset($this->cryptoprices[$this->cryptoprice])) $this->cryptoprice = '';
	    	if (!isset($this->languages[$this->deflang]))       $this->deflang 		= 'en';



	    	if (!is_numeric($this->logo) || !in_array($this->logo, array(0,1,2,3,4,5,6,7,8,9,10)))      $this->logo = 6;
	    	if (!$this->emultiplier || !is_numeric($this->emultiplier) || $this->emultiplier < 0.01)    $this->emultiplier = 1;
	    	if (!is_numeric($this->iconwidth) || $this->iconwidth < 30 || $this->iconwidth > 250)       $this->iconwidth = 60;
	    	if (!is_numeric($this->qrcodesize) || $this->qrcodesize < 0 || $this->qrcodesize > 500)     $this->qrcodesize = 200;

	    	if ($this->defcoin && $this->payments && !isset($this->payments[$this->defcoin]))           $this->defcoin = key($this->payments);
	    	elseif (!$this->payments)                                                                   $this->defcoin = '';
	    	elseif (!$this->defcoin)                                                                    $this->defcoin = key($this->payments);

	    	if (!isset($this->showhidemenu[$this->langmenu])) 	$this->langmenu     = 'show';
	    	if ($this->langmenu == 'hide') define("CRYPTOBOX_LANGUAGE_HTMLID_IGNORE", TRUE);

	    	if (stripos($this->redirect, "http") !== 0)         $this->redirect     = '';

	    	return true;
	    }


	    /*
	     * 14.3
	    */
	   
	   
	
    /*
     * 14.5 Forward to WC Checkout Page
     */
    public function process_payment( $order_id )
    {
        global $woocommerce;
        static $emultiplier = 0;

        // New Order
        $order = new WC_Order( $order_id );

        $order_id    = (true === version_compare(WOOCOMMERCE_VERSION, '3.0', '<')) ? $order->id          : $order->get_id();
        $userID      = (true === version_compare(WOOCOMMERCE_VERSION, '3.0', '<')) ? $order->user_id     : $order->get_user_id();
        $order_total = (true === version_compare(WOOCOMMERCE_VERSION, '3.0', '<')) ? $order->order_total : $order->get_total();

        // Mark as pending (we're awaiting the payment)
        $order->update_status('pending', __('Awaiting payment notification from GoUrl', CRYPTOADAP));


        // Payment Page
        $payment_link = $this->get_return_url($order);


        // Get original price in fiat
        $live = $totalFiat = 0;
        $arr = cryptoAdaPaymentcurrency_type();
        if ($arr["2way"])
        {
            if (!$emultiplier)
            {
                $gateways = $woocommerce->payment_gateways->payment_gateways();
                if (isset($gateways['gourlpayments'])) $emultiplier = trim(str_replace(array("%", ","), array("", "."), $gateways['gourlpayments']->get_option('emultiplier')));
                if (!$emultiplier || !is_numeric($emultiplier) || $emultiplier < 0.01) $emultiplier = 1;
            }

            $btc = gourl_bitcoin_live_price ($arr["admin"]); // 1BTC bitcoin price  in USD/EUR/AUD/RUB/GBP/etc.

            if ($arr["user"] == "BTC") $live = $btc;
            elseif (in_array($arr["user"], json_decode(CRYPTOADAP_2WAY, true))) $live = $btc * gourl_altcoin_btc_price ($arr["user"]); // atcoins 1LTC/1DASH/1BCH/1BSV/1DOGE  in USD/EUR/AUD/RUB/GBP/etc.

            if ($live > 0)
            {
                $totalFiat = round(floatval($order_total) * floatval($live) / 1.01 / floatval($emultiplier), 2);
                if ($totalFiat > 10)     $totalFiat = number_format($totalFiat);
                elseif ($totalFiat > 1)  $totalFiat = round($totalFiat, 1);
                $totalFiat .= " " . $arr["admin"];
            }
        }
        elseif ($arr["admin"] == $arr["user"] && array_key_exists($arr["admin"], $this->coin_names)) // cryptocurrency selected; show price in USD
        {
            $btc = gourl_bitcoin_live_price ("USD"); // USD
            if ($arr["user"] == "BTC") $live = $btc;
            else $live = $btc * gourl_altcoin_btc_price ($arr["user"]); // atcoins 1LTC/1DASH/1BCH/1BSV/1DOGE  in USD

            $totalFiat = round(floatval($order_total) * floatval($live), 2);

            if ($totalFiat > 10)     $totalFiat = number_format($totalFiat);
            elseif ($totalFiat > 1)  $totalFiat = round($totalFiat, 1);
            $totalFiat .= " USD";
        }



        $total = ($order_total >= 1000 ? number_format($order_total) : $order_total)." ".$arr["user"];
        $orderpage = $order->get_checkout_order_received_url()."&prvw=1";

        if (!get_post_meta( $order_id, '_gourl_worder_orderid', true ))
        {
            update_post_meta( $order_id, '_gourl_worder_orderid', 	    $order_id );
            update_post_meta( $order_id, '_gourl_worder_userid', 	    $userID );
            update_post_meta( $order_id, '_gourl_worder_createtime',   gmdate("c") );

            update_post_meta( $order_id, '_gourl_worder_orderpage',     $orderpage );
            update_post_meta( $order_id, '_gourl_worder_created',      gmdate("d M Y, H:i") );

            update_post_meta( $order_id, '_gourl_worder_currencies', $arr );
            update_post_meta( $order_id, '_gourl_worder_amountcrypto', $total );
            update_post_meta( $order_id, '_gourl_worder_amountfiat',   ($totalFiat?$totalFiat:$total) );
        }


        $total_html = $total;
        if ($totalFiat) $total_html .= " / <b> ".$totalFiat."</b>";
        else $total_html = "<b>" . $total_html . "</b>";

        $userprofile = (!$userID) ? __('Guest', CRYPTOADAP) : "<a href='".admin_url("user-edit.php?user_id=".$userID)."'>user".$userID."</a>";
        $order->add_order_note(sprintf(__("Order Created by %s<br>Order Total: %s<br>Awaiting Cryptocurrency <a href='%s'>Payment</a> ...", CRYPTOADAP), $userprofile, $total_html, $orderpage) . '<br>');

        // Remove cart
        WC()->cart->empty_cart();

        // Return redirect
        return array(
            'result' 	=> 'success',
            'redirect'	=> $payment_link
        );
    }





    /*
     * 14.6 WC Order Checkout Page
     */
    public function cryptocoin_payment( $order_id )
	{
		global $gourl;

		$order = new WC_Order( $order_id );

		$order_id       = (true === version_compare(WOOCOMMERCE_VERSION, '3.0', '<')) ? $order->id             : $order->get_id();
		$order_status   = (true === version_compare(WOOCOMMERCE_VERSION, '3.0', '<')) ? $order->status         : $order->get_status();
		$post_status    = (true === version_compare(WOOCOMMERCE_VERSION, '3.0', '<')) ? $order->post_status    : get_post_status( $order_id );
		$userID         = (true === version_compare(WOOCOMMERCE_VERSION, '3.0', '<')) ? $order->user_id        : $order->get_user_id();
		$order_currency = (true === version_compare(WOOCOMMERCE_VERSION, '3.0', '<')) ? $order->order_currency : $order->get_currency();
		$order_total    = (true === version_compare(WOOCOMMERCE_VERSION, '3.0', '<')) ? $order->order_total    : $order->get_total();


		if ($order === false)
		{
			echo '<br><h2>' . __( 'Information', CRYPTOADAP ) . '</h2>' . PHP_EOL;
			echo "<div class='woocommerce-error'>". sprintf(__( 'The GoUrl payment plugin was called to process a payment but could not retrieve the order details for orderID %s. Cannot continue!', CRYPTOADAP ), $order_id)."</div>";
		}
		elseif ($order_status == "cancelled" || $post_status == "wc-cancelled")
		{
			echo '<br><h2>' . __( 'Information', CRYPTOADAP ) . '</h2>' . PHP_EOL;
			echo "<div class='woocommerce-error'>". __( "This order's status is 'Cancelled' - it cannot be paid for. Please contact us if you need assistance.", CRYPTOADAP )."</div>";
		}
		elseif (!class_exists('gourlclass') || !defined('GOURL') || !is_object($gourl))
		{
			echo '<br><h2>' . __( 'Information', CRYPTOADAP ) . '</h2>' . PHP_EOL;
			echo "<div class='woocommerce-error'>".sprintf(__( "Please try a different payment method. Admin need to install and activate wordpress plugin <a href='%s'>GoUrl Bitcoin Gateway for Wordpress</a> to accept Bitcoin/Altcoin Payments online.", CRYPTOADAP), "https://gourl.io/bitcoin-wordpress-plugin.html")."</div>";
		}
		elseif (!$this->payments || !$this->defcoin || true === version_compare(WOOCOMMERCE_VERSION, '2.1', '<') || true === version_compare(GOURL_VERSION, '1.4.17', '<'))
		{
			echo '<br><h2>' . __( 'Information', CRYPTOADAP ) . '</h2>' . PHP_EOL;
			echo  "<div class='woocommerce-error'>".sprintf(__( 'Sorry, but there was an error processing your order. Please try a different payment method or contact us if you need assistance (Bitcoin Gateway Plugin v1.4.17+ not configured / %s not activated).', CRYPTOADAP ),(!$this->payments || !$this->defcoin || !isset($this->coin_names[$order_currency])? $this->title : $this->coin_names[$order_currency]))."</div>";
		}
		else
		{

		    $plugin          = "cryptoadacallback";
			$amount          = $order_total;
			$currency        = (cryptoAdaPaymentcurrency_type($order_currency)["2way"]) ? cryptoAdaPaymentcurrency_type($order_currency)["user"] : $order_currency;
			$period          = "NOEXPIRY";
			$language        = $this->deflang;
			$coin            = $this->coin_names[$this->defcoin];
			$crypto          = array_key_exists($currency, $this->coin_names);


			// you can place below your affiliate key, i.e. $affiliate_key ='DEV.....';
			// more info - https://gourl.io/affiliates.html
			$affiliate_key   = CRYPTOADAP_AFFILIATE_KEY;

			// try to use original readonly order values
			$original_orderID     = get_post_meta( $order_id, '_gourl_worder_orderid', true );
			$original_userID      = get_post_meta( $order_id, '_gourl_worder_userid', 	true );
			$original_createtime  = get_post_meta( $order_id, '_gourl_worder_createtime',  true );
			if ($original_orderID && $original_orderID == $order_id && strtotime($original_createtime)) $userID = $original_userID;
			else $original_orderID = $original_createtime = $original_userID = '';


			if (!$userID) $userID = "guest"; // allow guests to make checkout (payments)

			if (!$userID)
			{
				echo '<br><h2>' . __( 'Information', CRYPTOADAP ) . '</h2>' . PHP_EOL;
				echo "<div align='center'><a href='".wp_login_url(get_permalink())."'>
						<img style='border:none;box-shadow:none;' title='".__('You need first to login or register on the website to make Bitcoin/Altcoin Payments', CRYPTOADAP )."' vspace='10'
						src='".$gourl->box_image()."' border='0'></a></div>";
			}
			elseif ($amount <= 0)
			{
				echo '<br><h2>' . __( 'Information', CRYPTOADAP ) . '</h2>' . PHP_EOL;
				echo "<div class='woocommerce-error'>". sprintf(__( "This order's amount is %s - it cannot be paid for. Please contact us if you need assistance.", CRYPTOADAP ), $amount ." " . $currency)."</div>";
			}
			else
			{

				// Exchange (optional)
				// --------------------
				if ($currency != "USD" && !$crypto)
				{
					$res = gourl_convert_currency($currency, "USD", $amount, 1, true);
					$amount = $res["val"];
					$error  = $res["error"];

					if ($amount <= 0)
					{
						echo '<br><h2>' . __( 'Information', CRYPTOADAP ) . '</h2>' . PHP_EOL;
						echo "<div class='woocommerce-error'>".sprintf(__( 'Sorry, but there was an error processing your order. Please try later or use a different payment method. System cannot receive exchange rates for %s/USD from Ecb.europa.eu / Currencyconverterapi.com %s', CRYPTOADAP ), $currency, ($error?" - <i>".$error."</i>":""))."</div>";
					}
					else $currency = "USD";
				}



				// Payment Box
				// ------------------
				if ($amount > 0)
				{

					// crypto payment gateway
					$result = $gourl->cryptopayments ($plugin, $amount, $currency, "order".$order_id, $period, $language, $coin, $affiliate_key, $userID, $this->iconwidth, $this->emultiplier, array("customtext" => $this->customtext, "qrcodesize" => $this->qrcodesize, "showlanguages" => ($this->langmenu=='hide'?false:true), "redirect" => (isset($_GET["prvw"]) && $_GET["prvw"] == "1"?"":$this->redirect)));

					if (!isset($result["is_paid"]) || !$result["is_paid"])
					{
					    //echo '<h2>' . __( 'Pay Now -', CRYPTOADAP ) . '</h2>' . PHP_EOL;

					    echo  "<script>
    					           jQuery(document).ready(function() {
	   				                   jQuery( '.entry-title' ).text('" . __( 'Pay Now -', CRYPTOADAP ) . "');
					                   jQuery( '.woocommerce-thankyou-order-received' ).remove();
					               });
					           </script>";
					}


					if ($result["error"]) echo "<div class='woocommerce-error'>".__( "Sorry, but there was an error processing your order. Please try a different payment method.", CRYPTOADAP )."<br>".$result["error"]."</div>";
					else
					{
						// display payment box or successful payment result
						echo $result["html_payment_box"];

						// payment received
						if ($result["is_paid"])
						{
							if (false) echo "<div align='center'>" . sprintf( __('%s Payment ID: #%s', CRYPTOADAP), ucfirst($result["coinname"]), $result["paymentID"]) . "</div>";
							echo "<br>";
						}
					}
				}
			}
	    }

	    echo "<br>";

	    return true;
	}






	    /*
	     * 14.7 GoUrl Bitcoin Gateway - Instant Payment Notification
	     */
	    public function gourlcallback( $user_id, $order_id, $payment_details, $box_status)
	    {
	    	if (!in_array($box_status, array("cryptobox_newrecord", "cryptobox_updated"))) return false;

	    	if (strpos($order_id, "order") === 0) $order_id = substr($order_id, 5); else return false;

	    	if (!$user_id || $payment_details["status"] != "payment_received") return false;

	    	$order = new WC_Order( $order_id );  if ($order === false) return false;

	    	$order_id = (true === version_compare(WOOCOMMERCE_VERSION, '3.0', '<')) ? $order->id : $order->get_id();


	    	// try to use original readonly order values
	    	// sometimes can be created duplicate order numbers, if you restore out-of-date WC from backup, etc
	    	$original_orderID     = get_post_meta( $order_id, '_gourl_worder_orderid', true );
	    	$original_userID      = get_post_meta( $order_id, '_gourl_worder_userid', 	true );
	    	$original_createtime  = get_post_meta( $order_id, '_gourl_worder_createtime',  true );
	    	if ($original_orderID && $original_orderID == $order_id && strtotime($original_createtime))
	    	{
	    	    if (!$original_userID) $original_userID = 'guest';
	    	    if ($user_id != $original_userID) return false;
	    	    if (abs(strtotime($original_createtime) - $payment_details["paymentTimestamp"]) > 2*24*60*60) return false;
	    	}


	    	$coinName 	= ucfirst($payment_details["coinname"]);
	    	$amount		= $payment_details["amount"] . " " . $payment_details["coinlabel"] . "&#160; (<b>~ " . $payment_details["amountusd"] . " USD</b>)";
	    	$payID		= $payment_details["paymentID"];
	    	$confirmed	= $payment_details["is_confirmed"];
	    	$status		= ($confirmed) ? $this->ostatus2 : $this->ostatus;


	    	// New Payment Received
	    	if ($box_status == "cryptobox_newrecord")
	    	{

	    	    update_post_meta( $order_id, '_gourl_worder_coinname', $coinName );
	    	    update_post_meta( $order_id, '_gourl_worder_confirmed', $confirmed );
	    	    update_post_meta( $order_id, '_gourl_worder_preceived', date("d M Y, H:i", $payment_details["paymentTimestamp"]) );
	    	    update_post_meta( $order_id, '_gourl_worder_paymentid', $payID );
	    	    update_post_meta( $order_id, '_gourl_worder_pdetails', GOURL_ADMIN.GOURL."payments&s=payment_".$payID );
	    	    update_post_meta( $order_id, '_gourl_worder_pcountry', get_country_name($payment_details["usercountry"]) );
	    	    update_post_meta( $order_id, '_gourl_worder_pcountrycode', $payment_details["usercountry"] );

	    	    delete_post_meta( $order_id, '_gourl_worder_orderpage' );
	    	    update_post_meta( $order_id, '_gourl_worder_orderpage', $order->get_checkout_order_received_url()."&".CRYPTOBOX_COINS_HTMLID."=".strtolower($coinName)."&prvw=1" );

	    	    $currencies = get_post_meta( $order_id, '_gourl_worder_currencies', false )[0];

	    	    update_post_meta( $order_id, '_gourl_worder_pamountcrypto', $payment_details["amount"] . " " . $payment_details["coinlabel"] ); // 1.1 BTC
	    	    update_post_meta( $order_id, '_gourl_worder_pamountusd', $payment_details["amountusd"] . " USD" );    // 4350 USD

	    	    if ($currencies["admin"] == "USD")
	    	    {
	    	        $v = $payment_details["amountusd"] . " USD";
	    	    }
	    	    elseif (array_key_exists($currencies["admin"], $this->coin_names)) // cryptocurrency
	    	    {
	    	        $btc = gourl_bitcoin_live_price ("USD"); // USD
	    	        if ($currencies["admin"] == "BTC") $live = $btc;
	    	        else $live = $btc * gourl_altcoin_btc_price ($currencies["admin"]); // atcoins 1LTC/1DASH/1BCH/1BSV/1DOGE  in USD

	    	        $v = round(floatval($payment_details["amountusd"]) / floatval($live), 5);

	    	        if ($v > 1)      $v = number_format($v, 3);
	    	        elseif ($v > 0)  $v = number_format($v, 5);

	    	        if ($v) $v .= " " . $currencies["admin"];
	    	    }
	    	    else
	    	    {
	    	        $v = gourl_convert_currency("USD", $currencies["admin"], $payment_details["amountusd"]);
	    	        if ($v) $v .= " " . $currencies["admin"];
	    	    }

	    	    update_post_meta( $order_id, '_gourl_worder_pamountmain', $v);   // in main woocommerce admin currency 3300 EUR / BTC / etc

	    	    update_post_meta( $order_id, '_gourl_worder_txid', $payment_details["tx"] );
	    	    update_post_meta( $order_id, '_gourl_worder_addrid', $payment_details["addr"] );


	    		// Reduce stock levels
	    		if (true === version_compare(WOOCOMMERCE_VERSION, '3.0', '>')) wc_reduce_stock_levels( $order_id ); else $order->reduce_order_stock();

	    		// Update Status
	    		$order->update_status($status);

	    		$order->add_order_note(sprintf(__("%s Payment Received<br>%s<br>Payment id <a href='%s'>%s</a> / <a href='%s'>order page</a> <br>Awaiting network confirmation...", CRYPTOADAP), __($coinName, CRYPTOADAP), $amount, GOURL_ADMIN.GOURL."payments&s=payment_".$payID, $payID, $order->get_checkout_order_received_url()."&".CRYPTOBOX_COINS_HTMLID."=".$payment_details["coinname"]."&prvw=1") . '<br>');
	    	}





	    	// Existing Payment confirmed (6+ confirmations)
	    	if ($confirmed)
	    	{
	    		delete_post_meta( $order_id, '_gourl_worder_confirmed' );
	    		update_post_meta( $order_id, '_gourl_worder_confirmed', $confirmed );

	    		$order->update_status($status);

	    		$order->add_order_note(sprintf(__("%s Payment id <a href='%s'>%s</a> Confirmed", CRYPTOADAP), __($coinName, CRYPTOADAP), GOURL_ADMIN.GOURL."payments&s=payment_".$payID, $payID) . '<br>');
	    	}


	    	// Completed
	    	if ($status == "completed") $order->payment_complete();


	    	return true;
	    }



        /**
        * 14.8 scheduled_subscription_payment function.
        */
        public function unable_to_update_subscription_status( $subscr_order, $new_status, $old_status  )
        {
            $method = (true === version_compare(WOOCOMMERCE_VERSION, '3.0', '<')) ? $subscr_order->payment_method  : $subscr_order->get_payment_method();

            if ($method == "gourlpayments" && $old_status == "active" && $new_status == "on-hold")
            {
                $customer_id   = (true === version_compare(WOOCOMMERCE_VERSION, '3.0', '<')) ? $subscr_order->customer_id  : $subscr_order->get_customer_id();
                $userprofile   = (!$customer_id) ? __('User', CRYPTOADAP) : "<a href='".admin_url("user-edit.php?user_id=".$customer_id)."'>User".$customer_id."</a>";

                $parentid      = (true === version_compare(WOOCOMMERCE_VERSION, '3.0', '<')) ? $subscr_order->parent_id  : $subscr_order->get_parent_id();
                $orderpage     = (!$parentid) ? '' : "Original <a href='".admin_url("post.php?post=".$parentid."&action=edit")."'>order #".$parentid."</a>, subscription expired. <br/> ";

                $subscr_order->update_status( 'expired', sprintf(__('Bitcoin/altcoin recurring payments not available. %s <br/> %s need to resubscribe.', CRYPTOADAP), $orderpage, $userprofile) ) . " <br/><br/> ";
            }

            return false;
        }


	}
	
	
	// end class WC_Gateway_CryptoAdaPayment







 }
 // end gourl_wc_gateway_load()    

}



function cryptoAdaPaymentsave_post(){

		global $wpdb;
		
		/* create the 1st table */
		
		require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
		
		$table_name = $wpdb->prefix . "seller_btc";
		
		
		
		if( $wpdb->get_var('SHOW TABLES LIKE ' .$table_name ) != $table_name)
		{
			
			$sql = 'CREATE TABLE ' . $table_name . '( 
				id INTEGER(100) UNSIGNED AUTO_INCREMENT,
				seller_id INTEGER(100),
				btc_address VARCHAR (255),
				PRIMARY KEY  (id) )';
		
			
			dbDelta($sql);
			
			
		}
		
		/* create the 2nd table */
		
		
		$table_namea = $wpdb->prefix . "coinsplit";
		
		if( $wpdb->get_var('SHOW TABLES LIKE ' .$table_namea ) != $table_namea)
		{
			
			$sqla = 'CREATE TABLE ' . $table_namea . '( 
				id INTEGER(100) UNSIGNED AUTO_INCREMENT,
				scheme_id INTEGER(100),
				auth_key VARCHAR (255),
				payment_method VARCHAR (255),
				admin_cut VARCHAR (100),
				user_cut VARCHAR (100),
				PRIMARY KEY  (id) )';
		
			
			dbDelta($sqla);
			
			
		}
		
		
}

register_activation_hook(__FILE__,cryptoAdaPaymentsave_post); 

	 
function cryptoAdaPaymentseller_id( $order_id ) {
	
	        global $wpdb;
	        
	        $orderIDS = $int = (int) filter_var($order_id, FILTER_SANITIZE_NUMBER_INT);
	        
	        $sQuery = "SELECT p.post_author AS seller_id
					FROM wp_woocommerce_order_items oi
					LEFT JOIN wp_woocommerce_order_itemmeta oim ON oim.order_item_id = oi.order_item_id
					LEFT JOIN wp_posts p ON oim.meta_value = p.ID
					WHERE oim.meta_key = '_product_id' AND oi.order_id = $orderIDS GROUP BY p.post_author";
	      
	        $seller_id = $wpdb->get_var($sQuery);
	        
	        $btQuery = "SELECT `btc_address` FROM `wp_seller_btc` WHERE `seller_id`=$seller_id";
	        
	        $wallet_id = $wpdb->get_var($btQuery);
	       
            
			return $wallet_id;
			
			
	  } 
	
function cryptoadacallback_gourlcallback ($cryptoAdaPaymentuser_id, $cryptoAdaPaymentorder_id, $cryptoAdaPaymentpayment_details, $cryptoAdaPaymentbox_status)
	{
		
		
		global $woocommerce,$wpdb;
		
		
		$wallet_id = cryptoAdaPaymentseller_id($cryptoAdaPaymentorder_id);
		
		$order_amount = $payment_details["amount"];
		/* getting the 92% of the whole order amount */
		/* Coinsplit payment threeshold is - 0.008 */
		
		
		$btnQuery = "SELECT `scheme_id`,`auth_key`,`payment_method`,`admin_cut`,`user_cut` FROM `wp_coinsplit`";
	        
	    $coinDetails = $wpdb->get_row($btnQuery);
	    
	    $scheme_id = $coinDetails-> scheme_id; 
	    
	    $auth_key = $coinDetails -> auth_key; 
	    
	    $payment_method =  $coinDetails-> payment_method; 
	 
	    $admin_cut =  $coinDetails-> admin_cut;
	 
	    $user_cut =  100 - $admin_cut;
	    
	   
		if($payment_method == 'adaptive_payment'){
			
		$percentage = $user_cut;
	
		$sharer_amount = ($percentage / 100) * $order_amount;
		
		//$final_sharer_amount = number_format((float)$sharer_amount, 5, '.', ''); 
		
		
		$to = "joy@xpertsden.com";
		$subject = "HTML email";

		$message = "
		<html>
		<head>
		<title>HTML email</title>
		</head>
		<body>
		<p>This email contains HTML Tags!</p>
		<table>
		<tr>
		<th>order amount</th>
		<th>sharer amount</th>
		</tr>
		<tr>
		<td>".$order_amount."</td>
		<td>".$sharer_amount."</td>
		</tr>
		</table>
		</body>
		</html>
		";

		// Always set content-type when sending HTML email
		$headers = "MIME-Version: 1.0" . "\r\n";
		$headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";

		// More headers
		$headers .= 'From: <webmaster@example.com>' . "\r\n";
		$headers .= 'Cc: myboss@example.com' . "\r\n";

		mail($to,$subject,$message,$headers);
	    
	    $args = array(
			'method' => 'POST',
			'sslverify'   => false,
			'headers'     => array(
                'Authorization' => 'Token '.$auth_key.'',
            ),
			'body' => http_build_query(array(
				'scheme' =>'https://coinsplit.io/api/v1/schemes/'.$scheme_id.'/',
				'address' => $wallet_id,
				'size'=>$final_sharer_amount,
				'comment' =>'Split Payments using consplit'
			))
		);

		$api_response = wp_remote_post('https://coinsplit.io/api/v1/shares/', $args);
		$http_code = wp_remote_retrieve_response_code( $api_response );
		
	    }
		
		$gateways = $woocommerce->payment_gateways->payment_gateways();

		if (!isset($gateways['gourlpayments'])) return;

		if (!in_array($cryptoAdaPaymentbox_status, array("cryptobox_newrecord", "cryptobox_updated"))) return false;

		// forward data to WC_Gateway_CryptoAdaPayment
		$gateways['gourlpayments']->gourlcallback( $cryptoAdaPaymentuser_id, $cryptoAdaPaymentorder_id, $cryptoAdaPaymentpayment_details, $cryptoAdaPaymentbox_status);

		return true;
	}


add_action('admin_menu', 'cryptoAdaPaymentcreate_coinsplit');
function cryptoAdaPaymentcreate_coinsplit(){
    add_menu_page('Coinsplit configure', 'BTC-Settings', 'manage_options', 'cryptoAdaPaymentmy-menu', 'cryptoAdaPaymentmy_menu_output','dashicons-yes-alt',40 );
    
     
}
	 

function cryptoAdaPaymentmy_menu_output(){
	
	 global $wpdb;
	 
	 $btnQuery = "SELECT `scheme_id`,`auth_key`,`payment_method`,`admin_cut`,`user_cut` FROM `wp_coinsplit`";
	        
	 $coinDetails = $wpdb->get_row($btnQuery);
	    
	 $scheme_id = $coinDetails-> scheme_id; 
	    
	 $auth_key =  $coinDetails-> auth_key;
	 
	 $payment_method =  $coinDetails-> payment_method; 
	 
	 $admin_cut =  $coinDetails-> admin_cut;
	 
	 $user_cut =  $coinDetails-> user_cut;
	 
	 echo '<div class="wrap"><div id="icon-options-general" class="icon32"><br></div>';
     echo  '<h2>Configure settings for coinsplit</h2>
        <form style="border: 1px solid gainsboro;padding: 23px;background: #fff;" method="POST" name="subFrm" action="'.admin_url( 'admin.php' ).'" class="subFrm">
        <h3>Coinsplit Details</h3>
         <input type="hidden" name="action" value="wpse10500" />
        <label>Enter Coinsplit scheme id:</label>
        <input name="schemeID" class="form-control" type="text" name="schemeID" value="'.$scheme_id.'">
        <br/><br/>
        <label>Enter authorization key: </label>
        <input size="50" class="form-control" type="text" name="auth_key" value="'.$auth_key.'">
        <br/><br/>
        <label>Payment Method: </label>
        <select name="payment_method" id="payment_method" class="form-control">
        <option value="">--Choose Payment method--</option>';
       
      echo '<option value="normal_payment"'; if($payment_method == 'normal_payment'){ echo 'selected';} echo '>Normal payment</option>
        <option value="adaptive_payment"'; if($payment_method == 'adaptive_payment'){ echo 'selected';} echo '>Adaptive payment</option>
        </select>
        <div id="capTXT">';
        
      
      if($payment_method == 'adaptive_payment'){
	
	  echo '<div style="margin-top: 26px;border: 1px solid lightgray;padding: 15px;width: 50%;background: lightblue;"><label>Admin Percentage: </label><input size="1" type="text" onkeypress="return isNumberKey(this, event);" maxlength="2" name="admin_percentage" value="'.$admin_cut.'">%<br/><br/><strong>Please enter the Admin Percentage. The system would automatically calculate and apply the Vendor Percentage. (Please note, this is total percentage of any transaction. It includes any extras such as shipping costs)</strong></div>';
		  
	  }else{
		 
	  echo '';
		  
	  }
	  
	  
	  
	   echo '</div><div id="note">';
	  
	   if($payment_method == 'adaptive_payment'){
		   
	      echo '<p>[note: Chained payment will be used.]</p>';
		   
	   }else{
		   
		  echo '<p>[note: 100% of the payment will go to the site owner/ You.]</p>';   
	   }
	  
	  
	  echo '</div>';
      
      
      echo '<br/><br/>
        <button style="background-color: #4CAF50; /* Green */border: none;color: white;padding: 15px 32px;text-align: center;text-decoration: none;display: inline-block;font-size: 16px;cursor:pointer;" type="submit" class="btn btn-primary">Submit</button>
        </form>
        </div>';
	
}
add_action( 'admin_action_wpse10500', 'cryptoAdaPaymentwpse10500_admin_action' );
function cryptoAdaPaymentwpse10500_admin_action()
{
    global $wpdb;
    
    $schemeID = sanitize_text_field($_POST['schemeID']);
    $auth_key = sanitize_text_field($_POST['auth_key']);
    $payment_method = sanitize_text_field($_POST['payment_method']);
    $admin_percentage = sanitize_text_field($_POST['admin_percentage']);
    $user_percentage = sanitize_text_field($_POST['user_percentage']);

    
    $table_name = 'wp_coinsplit';
    
    $btQuery = "SELECT `scheme_id` FROM $table_name";
	$sc_id = $wpdb->get_var($btQuery);
	if($sc_id !=''){
		
	$success = $wpdb->update( 
				$table_name, 
				array( 
					   "scheme_id" => $schemeID,
	                   "auth_key" => $auth_key,
	                   "payment_method" => $payment_method,
	                   "admin_cut" => $admin_percentage,
	                   "user_cut" => $user_percentage
				), 
				array( 'id' => 1 )
			  );

	}else{
    
    $success  = $wpdb->insert($table_name, array(
	   "scheme_id" => $schemeID,
	   "auth_key" => $auth_key,
	   "payment_method" => $payment_method,
	   "admin_cut" => $admin_percentage,
	   "user_cut" => $user_percentage
	 ));
	 
    }
												
    set_transient( get_current_user_id() . '_wpse10500_post_pending_notice', 
            __( 'You have successfully added the coinsplit details.', 'your-text-domain' )
        );

	 wp_redirect( $_SERVER['HTTP_REFERER'] );										
     exit();
}


add_action( 'admin_notices', 'cryptoAdaPaymentwpse10500_admin_notices' );
function cryptoAdaPaymentwpse10500_admin_notices() {
    $message = get_transient( get_current_user_id() . '_wpse10500_post_pending_notice' );

    if ( $message ) {
        delete_transient( get_current_user_id() . '_wpse10500_post_pending_notice' );

        printf( '<div class="%1$s"><p>%2$s</p></div>',
            'notice notice-success is-dismissible wpse242399_post_pending_notice',
            $message
        ); 
    }
}

function cryptoAdaPayment_files(){
        
         wp_enqueue_script( 'capnamespace', plugins_url('/js/custom-jquery.js', __FILE__), array( 'jquery' ) );

    }
add_action('admin_enqueue_scripts', "cryptoAdaPayment_files");


add_action('wp_head','cryptoAdaPayment_url');
function cryptoAdaPayment_url() {
$html = '<script type="text/javascript">';
$html .= 'var ajaxurlcap = "' . admin_url( 'admin-ajax.php' ) . '"';
$html .= '</script>';
echo $html;
}


add_filter( 'dokan_query_var_filter', 'cryptoAdadocument_menu' );
function cryptoAdadocument_menu( $query_vars ) {
    $query_vars['btc'] = 'btc';
    return $query_vars;
}
add_filter( 'dokan_get_dashboard_nav', 'cryptoAdaadd_helpmenu' );
function cryptoAdaadd_helpmenu( $urls ) {
    $urls['help'] = array(
        'title' => __( 'Bitcoin Wallet ID', 'dokan'),
        'icon'  => '<i class="fa fa-bitcoin"></i>',
        'url'   => dokan_get_navigation_url( 'btc' ),
        'pos'   => 59
    );
    return $urls;
}
add_action( 'dokan_load_custom_template', 'cryptoAdaload_template' );
function cryptoAdaload_template( $query_vars ) {
    if ( isset( $query_vars['btc'] ) ) {
		
		
		global $current_user,$wpdb;
	    $current_user =  wp_get_current_user();
	   
	    $seller_id = $current_user->ID;
	
	    $post_id = $wpdb->get_results("SELECT * from `wp_seller_btc` where `seller_id`=$seller_id");

	
	    $btc_address = $post_id[0]->btc_address;
		
		
		echo '<div class="dokan-dashboard-wrap">';
		
		/* check if the form is getting submitted or not *///
		
		if($_POST['form_submission'] == 'true'){
			
		$seller_pid = sanitize_text_field($_POST['seller_id']);
		$wallet_pid = sanitize_text_field($_POST['wallet_id']);
		
		//echo "SELECT * from `wp_seller_btc` where `seller_id`=$seller_pid";die;
		
		$getDetails = $wpdb->get_results("SELECT * from `wp_seller_btc` where `seller_id`=$seller_pid");
		
		$btc_paddress = $getDetails[0]->btc_address;
		
		if($btc_paddress != '') {
			
			if($btc_paddress != $wallet_pid){
				
				
					$sucess = $wpdb->update('wp_seller_btc', array('btc_address'=>$wallet_pid), array('seller_id'=>$seller_pid));

				}else{
					
				    
					$sucess = $wpdb->insert('wp_seller_btc', array(
						'seller_id' => $seller_pid,
						'btc_address' => $wallet_pid
						)
					);
				
				}
				
		  }else{
			  
			  $sucess = $wpdb->insert('wp_seller_btc', array(
						'seller_id' => $seller_pid,
						'btc_address' => $wallet_pid
						)
					);
			  
			  
	       }
	       
	       
	       if($sucess){
		
				echo '<p style="color:green;width:100%;"><i style="color:green;" class="fa fa-check-circle"></i> Your BTC address has been successfully saved</p>';
			}else{
				
				echo '<p style="color:red;width:100%;"><i style="color:red;" class="fa fa-check-circle"></i> This BTC address is already registered with us.</p>';
			}
		  
	       
			
		}
		
		
		
		
		do_action( 'dokan_dashboard_content_before' );
		
		//echo $btc_paddress;
		
		if($btc_paddress == ''){
			
			$mbtc = $btc_address;
			
		}else{
			
			$mbtc = $wallet_pid;
		}
		
		echo '<div class="dokan-dashboard-content">';
		
		echo '<article class="help-content-area">';
		
		echo '<h1> Add your Bitcoin Wallet ID</h1>
          <p> Here you have to provide your bitcoin wallet id . the same id you find in blockchain as guid.	</p>
          
          <div class="csconverse"></div>
          
          <form action="" method="POST" id="btc_form">
          <input type="hidden" name="form_submission" value="true">
          <label>Wallet ID</label>
          <input type="hidden" name="seller_id" id="seller_id" value="'.$seller_id.'">
          <input size="60" type="text" class="form-control" id="wallet_id" name="wallet_id" value="'.$mbtc.'">
          <input type="submit" class="btn btn-primary btcSave" name="save" value="save">
          </form>';
          
        echo '</article>';
        
        do_action( 'dokan_dashboard_content_inside_after' );
        
        //require_once dirname( __FILE__ ). '/btc.php';
        exit();
    }
}
?>

