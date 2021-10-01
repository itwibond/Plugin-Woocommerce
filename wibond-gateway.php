<?php
/*
 * Plugin Name: Wibond for WooCommerce
 * Description: Cobrá con Wibond, configurá opciones de pago para tus clientes.
 * Author: Wibond
 * Author URI: https://www.wibond.co
 * Version: 1.0.3
 *
*/
 
defined( 'ABSPATH' ) or exit;


// Make sure WooCommerce is active
if ( ! in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {
	return;
}


/**
 * Add the gateway to WC Available Gateways
 * 
 * @since 1.0.0
 * @param array $gateways all available WC gateways
 * @return array $gateways all WC gateways + wibond gateway
 */
function wc_wibond_add_to_gateways( $gateways ) {
	$gateways[] = 'WC_Gateway_Wibond';
	return $gateways;
}
add_filter( 'woocommerce_payment_gateways', 'wc_wibond_add_to_gateways' );


/**
 * Adds plugin page links
 * 
 * @since 1.0.0
 * @param array $links all plugin links
 * @return array $links all plugin links + our custom links (i.e., "Settings")
 */
function wc_wibond_gateway_plugin_links( $links ) {

	$plugin_links = array(
		'<a href="' . admin_url( 'admin.php?page=wc-settings&tab=checkout&section=wibond_gateway' ) . '">' . __( 'Configure', 'wc-gateway-wibond' ) . '</a>'
	);

	return array_merge( $plugin_links, $links );
}
add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'wc_wibond_gateway_plugin_links' );


/**
 * Wibond Payment Gateway
 *
 * Provides an Wibond Payment Gateway; mainly for testing purposes.
 * We load it later to ensure WC is loaded first since we're extending it.
 *
 * @class 		WC_Gateway_Wibond
 * @extends		WC_Payment_Gateway
 * @version		1.0.0
 * @package		WooCommerce/Classes/Payment
 * @author 		SkyVerge
 */
add_action( 'plugins_loaded', 'wc_wibond_gateway_init', 11 );

function wc_wibond_gateway_init() {

	class WC_Gateway_Wibond extends WC_Payment_Gateway {

		/**
		 * Constructor for the gateway.
		 */
		public function __construct() {
	  
			$this->id                 = 'wibond_gateway';
			$this->icon               = apply_filters('woocommerce_wibond_icon', '');
			$this->has_fields         = false;
			$this->method_title       = __( 'Wibond', 'wc-gateway-wibond' );
			$this->method_description = __( 'Sin necesidad de tarjetas de crédito. Elegí Wibond cuando compres en tus
comercios favoritos. Dividí el total de tu compra en cómodas cuotas o paga el 100% dentro de 30 días.', 'wc-gateway-wibond' );
		  
			// Load the settings.
			$this->init_form_fields();
			$this->init_settings();
		  
			// Define user set variables
			$this->title        = $this->get_option( 'title' );
			$this->description  = $this->get_option( 'description' );
			$this->instructions = $this->get_option( 'instructions', $this->description );
		  
			// Actions
			add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
			add_action( 'woocommerce_thankyou_' . $this->id, array( $this, 'thankyou_page' ) );
			add_action( 'woocommerce_api_callback'. strtolower( get_class($this) ), array( $this, 'callback_handler' ) );
		  
			// Customer Emails
			//add_action( 'woocommerce_email_before_order_table', array( $this, 'email_instructions' ), 10, 3 );
		}
	
	
		/**
		 * Initialize Gateway Settings Form Fields
		 */
		public function init_form_fields() {
	  
			$this->form_fields = apply_filters( 'wc_wibond_form_fields', array(
		  
				'enabled' => array(
					'title'   => __( 'Activar/Desactivar', 'wc-gateway-wibond' ),
					'type'    => 'checkbox',
					'label'   => __( 'Activar Wibond', 'wc-gateway-wibond' ),
					'default' => 'yes'
				),
				'entorno' => array(
					'title'   => __( 'Entorno', 'wc-gateway-wibond' ),
					'type'    => 'select',
					'label'   => __( '', 'wc-gateway-wibond' ),
					'options' => array(
          				'api' => 'Producción',
          				'api-preprod' => 'Testing'
     				),
				),
				'title' => array(
					'title'       => __( 'Título', 'wc-gateway-wibond' ),
					'type'        => 'text',
					'description' => __( 'Esto controla el título del método de pago que el cliente ve durante el pago.', 'wc-gateway-wibond' ),
					'default'     => __( 'Pagá con Wibond <img src="https://www.wibond.co/assets/images/logomark.svg" alt="Pagá con Wibond - En cuotas, sin tarjeta">', 'wc-gateway-wibond' ),
					'desc_tip'    => true,
				),
				'description' => array(
						'title'       => __( 'Descripción', 'wc-gateway-wibond' ),
						'type'        => 'text',
						'description' => __( 'Esto controla la descripción del método de pago que el cliente ve durante el pago.', 'wc-gateway-wibond' ),
						'default'     => __( 'Pagá con Wibond en cuotas, sin tarjeta', 'wc-gateway-wibond' ),
						'desc_tip'    => true,
					),
				

				// 	'email' => array(
				// 	'title'       => __( 'E-mail o Usuario*', 'wc-gateway-wibond' ),
				// 	'description' => __( 'Obtiene tus credenciales haciendo <a href="https://app.wibond.com.ar/profile/developer">clic aqu&iacute;</a>', 'wc-gateway-wibond' ),
				// 	'type'        => 'text',
				// 	'required'      => true
				// ),

				// 	'password' => array(
				// 	'title'       => __( 'Contraseña*', 'wc-gateway-wibond' ),
				// 	'type'        => 'password',
				// 	'required'      => true
				// ),

					'secretKey' => array(
					'title'       => __( 'Secret Key*', 'wc-gateway-wibond' ),
					'description' => __( 'Obtiene tus credenciales haciendo <a href="https://app.wibond.com.ar/profile/developer">clic aqu&iacute;</a>', 'wc-gateway-wibond' ),
					'type'        => 'password',
					'required'      => true
				),
					
					'wallet' => array(
					'title'       => __( 'Wallet Id*', 'wc-gateway-wibond' ),
					'type'        => 'number',
					'required'      => TRUE
				),

					'tenant' => array(
					'title'       => __( 'Tenant Id*', 'wc-gateway-wibond' ),
					'type'        => 'number',
					'required'      => TRUE
				),

					'titulo_con3' => array(
					'title'       => __( ' ', 'wc-gateway-wibond' ),
					'type'        => 'hidden'
				),

					'titulo_con2' => array(
					'title'       => __( 'PLANES DE FINANCIACIÓN:', 'wc-gateway-wibond' ),
					'type'        => 'hidden'
				),

				'pago_ahora' => array(
					'title'   => __( ' ', 'wc-gateway-wibond' ),
					'type'    => 'checkbox',
					'label'   => __( 'Pago ahora', 'wc-gateway-wibond' ),
					'default' => 'yes'
				),

				'cuotas_1' => array(
					'title'   => __( ' ', 'wc-gateway-wibond' ),
					'type'    => 'checkbox',
					'label'   => __( '1 cuota sin interés', 'wc-gateway-wibond' ),
					'default' => 'yes'
				),

				'cuotas_3' => array(
					'title'   => __( ' ', 'wc-gateway-wibond' ),
					'type'    => 'checkbox',
					'label'   => __( '3 cuotas sin interés', 'wc-gateway-wibond' ),
					'default' => 'yes'
				),

				'cuotas_6' => array(
					'title'   => __( ' ', 'wc-gateway-wibond' ),
					'type'    => 'checkbox',
					'label'   => __( '6 cuotas sin interés', 'wc-gateway-wibond' ),
					'default' => 'yes'
				),

				'cuotas_9' => array(
					'title'   => __( ' ', 'wc-gateway-wibond' ),
					'type'    => 'checkbox',
					'label'   => __( '9 cuotas sin interés', 'wc-gateway-wibond' ),
					'default' => 'yes'
				),

				'cuotas_12' => array(
					'title'   => __( ' ', 'wc-gateway-wibond' ),
					'type'    => 'checkbox',
					'label'   => __( '12 cuotas sin interés', 'wc-gateway-wibond' ),
					'default' => 'yes'
				),

				'cuotas_f' => array(
					'title'   => __( ' ', 'wc-gateway-wibond' ),
					'type'    => 'checkbox',
					'label'   => __( 'Cuotas financiadas (interés)', 'wc-gateway-wibond' ),
					'default' => 'yes'
				),

			) );
		}
	
	
		/**
		 * Output for the order received page.
		 */
		public function thankyou_page() {
			if ( $this->instructions ) {
				echo wpautop( wptexturize( $this->instructions ) );
			}
		}
	
	
		/**
		 * Add content to the WC emails.
		 *
		 * @access public
		 * @param WC_Order $order
		 * @param bool $sent_to_admin
		 * @param bool $plain_text
		 */
		public function email_instructions( $order, $sent_to_admin, $plain_text = false ) {
		
			if ( $this->instructions && ! $sent_to_admin && $this->id === $order->payment_method && $order->has_status( 'on-hold' ) ) {
				echo wpautop( wptexturize( $this->instructions ) ) . PHP_EOL;
			}
		}
	
	
		/**
		 * Process the payment and return the result
		 *
		 * @param int $order_id
		 * @return array
		 */
		public function process_payment( $order_id ) {
	
			$order = wc_get_order( $order_id );
			
			// Mark as on-hold (we're awaiting the payment)
			$order->update_status( 'pending-payment', __( 'Pendiente de pago', 'wc-gateway-wibond' ) );
			
			global $woocommerce;
		    $isConfig = get_option('woocommerce_wibond_gateway_settings');
			$url=get_option('siteurl');
			$callback=$url.'wp-content/plugins/wibond-gateway/wibond-callback.php';
			$secretKey=$isConfig['secretKey'];
			$walletId=$isConfig['wallet'];
			$tenantId=$isConfig['tenant'];
			$interes=$isConfig['interes'];
			$frecuencia=$isConfig['cuotas_p'];
			$total_cuotas=$isConfig['cuotas_f_n'];
			$max_cuotas=$isConfig['cuotas_con'];
			$entorno=$isConfig['entorno'];

			$options = array();

			if($isConfig['pago_ahora']=='yes'){

				array_push($options, array("id"=>1,"code"=>"OPT_PLAN_01"));
			}

			if($isConfig['cuotas_1']=='yes'){
				array_push($options, array("id"=>2,"code"=>"OPT_PLAN_02"));
			}

			if($isConfig['cuotas_3']=='yes'){
				array_push($options, array("id"=>3,"code"=>"OPT_PLAN_03"));
			}

			if($isConfig['cuotas_12']=='yes'){
				array_push($options, array("id"=>6,"code"=>"OPT_PLAN_06"));
			}

			if($isConfig['cuotas_6']=='yes'){
				array_push($options, array("id"=>5,"code"=>"OPT_PLAN_05"));
			}

			if($isConfig['cuotas_9']=='yes'){
				array_push($options, array("id"=>7,"code"=>"OPT_PLAN_07"));
			}

			if($isConfig['cuotas_f']=='yes'){
				array_push($options, array("id"=>4,"code"=>"OPT_PLAN_04"));
			}


			$total_order=$order->get_total();

			if ($order && !is_wp_error($order)) {
			    $order_key = $order->get_order_key();
			}

			$carro = wc_get_checkout_url();

			$body = array(
				'productName'        => 'Orden #'.$order_id,
				'amount'     => $total_order,
				'options' => $options,
				'urlNotification' => $url.'/wc-api/wc_gateway_wibond',
				'urlSuccess'    => $carro.'order-received/'.$order_id.'/?key='.$order_key,
				'urlCheckout'     => $carro,
				'externalId' => $order_id
			);


			$args = array(
				'body'        =>  json_encode($body),
				'timeout'     => '5',
				'redirection' => '5',
				'httpversion' => '1.0',
				'blocking'    => true,
				'headers'     => array(
					'Content-Type' => 'application/json;charset=utf-8',
					'Authorization' => $secretKey,
					'Cache-Control' => 'no-cache',
				),
				'method'      => 'POST',
				'data_format' => 'body',
			);
			$urlApi = 'https://'.$entorno.'.wibond.com.ar/api/v1/payment-link/anonymous/create-payment-link/'.$tenantId.'/'.'wallet/'.$walletId;
			$response = wp_remote_post($urlApi,$args);
			if ( is_wp_error( $response ) ) {
				$error_message = $response->get_error_message();
				echo "Something went wrong: $error_message";
			 }
			 else {
				$row= json_decode($response['body']);
				
			 }
			 return array(
				'result' 	=> 'success',
				'redirect'	=> $row -> {'urlLink'}
			);
			
		}
		
		public function callback_handler() {
				$json= file_get_contents('php://input');
				$data= json_decode($json, true);
				
			    

				$order_id=$data['externalId'];
				//$order = new WC_Order( $order_id );
				$order = wc_get_order( $order_id );

				//die($data['externalId']);
				
				if ($data['status']=='COMPLETED') {
					// Set order status to processing
					// Reduce stock levels
					$order->reduce_order_stock();
				
					if($data['payNow']){
						if($data['paymentMethod']=='CASH_RAPIPAGO_PAGOFACIL'){
							update_post_meta( $order->id, '_payment_method_title', "WIBOND: Efectivo" );
						}
						
					}else{
						
						update_post_meta( $order->id, '_payment_method_title', "WIBOND: ".$data['feesToPay']." cuota/s" );
					}

					
					// Remove cart
					WC()->cart->empty_cart();
					$order->update_status( 'processing', sprintf( __( 'Autorizado  %s %s en Wibond.', 'wc-gateway-wibond' ), get_woocommerce_currency(), $order->get_total()));
					header("HTTP/1.1 200 OK");
	
				} 

				if ($data['status']=='IN_PROGRESS') {
					// Reduce stock levels
					$order->reduce_order_stock();
			
					update_post_meta( $order->id, '_payment_method_title', "WIBOND: ".$data['feesToPay']." cuota/s" );
					
					
					// Remove cart
					WC()->cart->empty_cart();
					// Set order status to processing
					$order->update_status( 'processing', sprintf( __( 'Autorizado  %s %s en Wibond.', 'wc-gateway-wibond' ), get_woocommerce_currency(), $order->get_total()));
					header("HTTP/1.1 200 OK");
					
				}

				if ($data['status']=='PENDING') {	

					WC()->cart->empty_cart();
					header("HTTP/1.1 200 OK");
				} 

				

				die();
		}

		

  } // end \WC_Gateway_Wibond class
}