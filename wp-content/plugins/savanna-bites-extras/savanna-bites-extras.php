<?php
/**
 * Plugin Name:       Savanna Bites Extras
 * Description:       Custom features for the Savanna Bites Restaurant website: a floating WhatsApp chat button and styling for the contact, reservation and ordering forms.
 * Version:           1.0.0
 * Requires at least: 6.5
 * Requires PHP:      7.4
 * Author:            Savanna Bites Restaurant
 * License:           GPL-2.0-or-later
 * Text Domain:       savanna-bites-extras
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Stop if this file is opened directly.
}

/** The restaurant's WhatsApp number in international format, digits only. */
const SAVANNA_BITES_WHATSAPP = '254700000000';

/**
 * Load the plugin stylesheet on the public website.
 */
function savanna_bites_enqueue_styles() {
	wp_enqueue_style(
		'savanna-bites-extras',
		plugins_url( 'assets/style.css', __FILE__ ),
		array(),
		filemtime( __DIR__ . '/assets/style.css' )
	);
}
add_action( 'wp_enqueue_scripts', 'savanna_bites_enqueue_styles' );

/**
 * Print the floating "Chat on WhatsApp" button at the bottom of every page.
 */
function savanna_bites_whatsapp_button() {
	$message = rawurlencode( 'Hello Savanna Bites! I would like to make an enquiry.' );
	$url     = 'https://wa.me/' . SAVANNA_BITES_WHATSAPP . '?text=' . $message;
	?>
	<a class="sb-whatsapp" href="<?php echo esc_url( $url ); ?>" target="_blank" rel="noopener" aria-label="<?php esc_attr_e( 'Chat with us on WhatsApp', 'savanna-bites-extras' ); ?>">
		<svg viewBox="0 0 32 32" width="28" height="28" aria-hidden="true" focusable="false"><path fill="currentColor" d="M16.04 3C9.4 3 4 8.38 4 15c0 2.12.56 4.18 1.62 6L4 29l8.2-1.58A12.06 12.06 0 0 0 16.04 27C22.67 27 28 21.62 28 15S22.67 3 16.04 3Zm0 21.8c-1.8 0-3.56-.48-5.1-1.4l-.37-.22-4.87.94.96-4.72-.24-.39A9.73 9.73 0 0 1 6.28 15c0-5.4 4.38-9.8 9.76-9.8 5.39 0 9.72 4.4 9.72 9.8 0 5.41-4.33 9.8-9.72 9.8Zm5.36-7.34c-.29-.15-1.73-.85-2-.95-.27-.1-.46-.15-.66.15-.2.29-.76.95-.93 1.14-.17.2-.34.22-.63.07-.29-.15-1.24-.46-2.36-1.45-.87-.78-1.46-1.73-1.63-2.02-.17-.29-.02-.45.13-.6.13-.13.29-.34.44-.51.15-.17.2-.29.29-.49.1-.2.05-.37-.02-.51-.07-.15-.66-1.58-.9-2.16-.24-.57-.48-.49-.66-.5h-.56c-.2 0-.51.07-.78.37-.27.29-1.02 1-1.02 2.43 0 1.43 1.05 2.82 1.2 3.01.15.2 2.06 3.14 4.99 4.4.7.3 1.24.48 1.67.61.7.22 1.34.19 1.84.12.56-.08 1.73-.7 1.97-1.39.24-.68.24-1.27.17-1.39-.07-.12-.27-.2-.56-.34Z"/></svg>
		<span><?php esc_html_e( 'Chat with us', 'savanna-bites-extras' ); ?></span>
	</a>
	<?php
}
add_action( 'wp_footer', 'savanna_bites_whatsapp_button' );

/**
 * Simplify the WooCommerce checkout for a restaurant in Kenya:
 * no postcode or county, and the address is only needed for delivery.
 */
function savanna_bites_checkout_fields( $locale ) {
	$locale['KE']['postcode']  = array( 'required' => false, 'hidden' => true );
	$locale['KE']['state']     = array( 'required' => false, 'hidden' => true );
	$locale['KE']['city']      = array( 'required' => false, 'label' => __( 'Area / Estate', 'savanna-bites-extras' ) );
	$locale['KE']['address_1'] = array( 'required' => false, 'label' => __( 'Delivery address (leave blank for pickup)', 'savanna-bites-extras' ) );
	return $locale;
}
add_filter( 'woocommerce_get_country_locale', 'savanna_bites_checkout_fields' );
