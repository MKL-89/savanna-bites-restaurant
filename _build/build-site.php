<?php
/**
 * Savanna Bites Restaurant – site builder.
 *
 * Builds (or rebuilds) all content and settings for the site:
 * logo, photos, WooCommerce menu items, reservations, pages,
 * navigation, header, footer and global styles.
 *
 * Needs these plugins active first: woocommerce, contact-form-7,
 * restaurant-reservations and savanna-bites-extras.
 *
 * Run with:  studio wp eval-file _build/build-site.php
 * Safe to re-run: it updates the same items each time instead of duplicating them.
 */

require_once ABSPATH . 'wp-admin/includes/plugin.php';
foreach ( array( 'woocommerce/woocommerce.php', 'contact-form-7/wp-contact-form-7.php', 'restaurant-reservations/restaurant-reservations.php' ) as $required ) {
	if ( ! is_plugin_active( $required ) ) {
		WP_CLI::error( "Please activate the plugin first: $required" );
	}
}

require_once ABSPATH . 'wp-admin/includes/file.php';
require_once ABSPATH . 'wp-admin/includes/media.php';
require_once ABSPATH . 'wp-admin/includes/image.php';

$BUILD = __DIR__;

/* =====================================================================
 * 1. MEDIA – import photos from _build/images into the Media Library
 * ===================================================================== */

function sb_import_image( $key, $file ) {
	$title = 'savanna-' . $key;
	$found = get_posts( array( 'post_type' => 'attachment', 'title' => $title, 'numberposts' => 1, 'post_status' => 'inherit' ) );
	if ( $found ) {
		return $found[0]->ID;
	}
	$ext = pathinfo( $file, PATHINFO_EXTENSION );
	$tmp = wp_tempnam( $title . '.' . $ext );
	copy( $file, $tmp );
	$id = media_handle_sideload( array( 'name' => $title . '.' . $ext, 'tmp_name' => $tmp ), 0, $title );
	if ( is_wp_error( $id ) ) {
		WP_CLI::error( "Could not import $key: " . $id->get_error_message() );
	}
	return $id;
}

$photo_keys = array(
	'hero', 'chicken', 'grill', 'interior', 'dining', 'spread', 'salad', 'terrace', 'platter', 'skewers', 'veggiebowl',
	'biryani', 'pilau', 'beefstew', 'friedfish', 'orange', 'mango', 'water', 'soda', 'fruitsalad', 'icecream',
);

// Remove photos imported by an earlier version of this script under names that no longer exist.
foreach ( get_posts( array( 'post_type' => 'attachment', 'numberposts' => -1, 'post_status' => 'inherit', 's' => 'savanna-' ) ) as $old ) {
	$key = substr( $old->post_title, strlen( 'savanna-' ) );
	if ( 0 === strpos( $old->post_title, 'savanna-' ) && ! in_array( $key, $photo_keys, true ) && 'logo' !== $key ) {
		wp_delete_attachment( $old->ID, true );
	}
}

$img_id = array();
$img    = array();
foreach ( $photo_keys as $key ) {
	$img_id[ $key ] = sb_import_image( $key, "$BUILD/images/$key.jpg" );
	$src            = wp_get_attachment_image_src( $img_id[ $key ], 'full' );
	// Root-relative URLs keep working if the port or domain changes.
	$img[ $key ] = wp_make_link_relative( $src[0] );
}

/* =====================================================================
 * 2. LOGO – drawn with PHP's GD library (orange sun + acacia tree)
 * ===================================================================== */

$logo_file = "$BUILD/images/logo.png";
if ( ! file_exists( $logo_file ) ) {
	$S  = 1024;                       // draw big, then shrink for smooth edges
	$im = imagecreatetruecolor( $S, $S );
	imagesavealpha( $im, true );
	imagefill( $im, 0, 0, imagecolorallocatealpha( $im, 0, 0, 0, 127 ) );

	$orange = imagecolorallocate( $im, 0xB8, 0x5A, 0x14 );
	$gold   = imagecolorallocate( $im, 0xF4, 0xC5, 0x42 );
	$dark   = imagecolorallocate( $im, 0x2A, 0x1A, 0x10 );
	$cream  = imagecolorallocate( $im, 0xFF, 0xF9, 0xF0 );

	$c = $S / 2;
	imagefilledellipse( $im, $c, $c, $S - 8, $S - 8, $cream );           // cream ring
	imagefilledellipse( $im, $c, $c, $S - 60, $S - 60, $orange );        // orange sky
	imagefilledellipse( $im, $c, (int) ( $S * 0.52 ), (int) ( $S * 0.46 ), (int) ( $S * 0.46 ), $gold ); // setting sun
	// Ground: the bottom slice of the circle (points along the arc from 20° to 160°).
	$r      = ( $S - 60 ) / 2;
	$ground = array();
	for ( $a = 20; $a <= 160; $a += 2 ) {
		$ground[] = (int) round( $c + $r * cos( deg2rad( $a ) ) );
		$ground[] = (int) round( $c + $r * sin( deg2rad( $a ) ) );
	}
	imagefilledpolygon( $im, $ground, $dark );

	// Acacia tree: trunk, two branches and a flat-topped canopy.
	imagefilledpolygon( $im, array( 488, 830, 536, 830, 528, 470, 498, 470 ), $dark );
	imagefilledpolygon( $im, array( 500, 560, 520, 540, 380, 420, 360, 440 ), $dark );
	imagefilledpolygon( $im, array( 520, 560, 500, 540, 650, 410, 670, 430 ), $dark );
	imagefilledellipse( $im, 512, 400, 600, 120, $dark );
	imagefilledellipse( $im, 420, 365, 300, 90, $dark );
	imagefilledellipse( $im, 620, 372, 280, 80, $dark );

	$out = imagecreatetruecolor( 512, 512 );
	imagesavealpha( $out, true );
	imagealphablending( $out, false );
	imagefill( $out, 0, 0, imagecolorallocatealpha( $out, 0, 0, 0, 127 ) );
	imagecopyresampled( $out, $im, 0, 0, 0, 0, 512, 512, $S, $S );
	imagepng( $out, $logo_file );
}
$logo_id = sb_import_image( 'logo', $logo_file );
set_theme_mod( 'custom_logo', $logo_id );
update_option( 'site_logo', $logo_id );
update_option( 'site_icon', $logo_id );   // browser-tab icon (favicon)

/* =====================================================================
 * 3. ONLINE ORDERING – WooCommerce settings and menu items
 * ===================================================================== */

$wc_options = array(
	'woocommerce_currency'                              => 'KES',
	'woocommerce_currency_pos'                          => 'left_space',
	'woocommerce_price_num_decimals'                    => '0',
	'woocommerce_price_thousand_sep'                    => ',',
	'woocommerce_default_country'                       => 'KE',
	'woocommerce_store_city'                            => 'Nakuru',
	'woocommerce_allowed_countries'                     => 'specific',
	'woocommerce_specific_allowed_countries'            => array( 'KE' ),
	'woocommerce_ship_to_countries'                     => 'disabled',   // orders are collected or delivered locally
	'woocommerce_calc_taxes'                            => 'no',
	'woocommerce_enable_guest_checkout'                 => 'yes',
	'woocommerce_enable_signup_and_login_from_checkout' => 'yes',
	'woocommerce_enable_reviews'                        => 'no',
	'woocommerce_checkout_phone_field'                  => 'required',   // we call customers about their order
	'woocommerce_checkout_company_field'                => 'hidden',
	'woocommerce_checkout_address_2_field'              => 'hidden',
	'woocommerce_coming_soon'                           => 'no',          // make the shop public
	'woocommerce_store_pages_only'                      => 'no',
	'woocommerce_allow_tracking'                        => 'no',
	'woocommerce_task_list_hidden'                      => 'yes',
	'woocommerce_show_marketplace_suggestions'          => 'no',
	'woocommerce_cod_settings'                          => array(
		'enabled'            => 'yes',
		'title'              => 'Pay on Delivery or Pickup',
		'description'        => 'Pay with cash or M-Pesa when you collect your order or when it is delivered.',
		'instructions'       => 'Thank you for ordering with Savanna Bites! Please have cash or M-Pesa ready when you collect your food.',
		'enable_for_methods' => array(),
		'enable_for_virtual' => 'yes',
	),
);
foreach ( $wc_options as $name => $value ) {
	update_option( $name, $value );
}
update_option( 'woocommerce_onboarding_profile', array( 'skipped' => true, 'completed' => true ) );

function sb_category( $name, $slug, $order ) {
	$term = get_term_by( 'slug', $slug, 'product_cat' );
	$id   = $term ? $term->term_id : wp_insert_term( $name, 'product_cat', array( 'slug' => $slug ) )['term_id'];
	update_term_meta( $id, 'order', $order );
	return $id;
}
$cat = array(
	'mains'    => sb_category( 'Main Dishes', 'main-dishes', 1 ),
	'drinks'   => sb_category( 'Drinks', 'drinks', 2 ),
	'desserts' => sb_category( 'Desserts', 'desserts', 3 ),
);
// Hide the empty default "Uncategorized" category from the shop.
$uncat = get_term_by( 'slug', 'uncategorized', 'product_cat' );
if ( $uncat ) {
	update_option( 'default_product_cat', $cat['mains'] );
	wp_delete_term( $uncat->term_id, 'product_cat' );
}

$menu_items = array(
	// SKU, name, price, category, photo, short description
	array( 'SB-M1', 'Grilled Chicken', 850, 'mains', 'chicken', 'Tender chicken marinated in our house spices and grilled over an open flame. Served with a side of fresh vegetables.' ),
	array( 'SB-M2', 'Fried Fish', 750, 'mains', 'friedfish', 'Crispy golden fish fillet served with lemon and a fresh garden salad.' ),
	array( 'SB-M3', 'Chicken Biryani', 650, 'mains', 'biryani', 'Fragrant spiced rice layered with slow-cooked chicken and served with kachumbari.' ),
	array( 'SB-M4', 'Beef Pilau', 500, 'mains', 'pilau', 'Classic Swahili pilau rice cooked with tender beef and aromatic spices.' ),
	array( 'SB-M5', 'Chapati and Beef Stew', 450, 'mains', 'beefstew', 'Soft layered chapati served with a rich, hearty beef stew.' ),
	array( 'SB-D1', 'Fresh Orange Juice', 200, 'drinks', 'orange', 'Freshly squeezed oranges, served chilled.' ),
	array( 'SB-D2', 'Mango Juice', 200, 'drinks', 'mango', 'Smooth, sweet juice made from ripe Kenyan mangoes.' ),
	array( 'SB-D3', 'Bottled Water', 100, 'drinks', 'water', '500 ml bottle of chilled mineral water.' ),
	array( 'SB-D4', 'Soda', 100, 'drinks', 'soda', 'Your choice of chilled soft drink.' ),
	array( 'SB-S1', 'Fruit Salad', 250, 'desserts', 'fruitsalad', 'A colourful bowl of fresh seasonal fruit.' ),
	array( 'SB-S2', 'Ice Cream', 200, 'desserts', 'icecream', 'Two creamy scoops of vanilla or strawberry ice cream.' ),
);
foreach ( $menu_items as $i => $m ) {
	list( $sku, $name, $price, $c, $photo, $desc ) = $m;
	$pid     = wc_get_product_id_by_sku( $sku );
	$product = $pid ? wc_get_product( $pid ) : new WC_Product_Simple();
	if ( ! $pid ) {
		$product->set_sku( $sku );
	}
	$product->set_name( $name );
	$product->set_status( 'publish' );
	$product->set_regular_price( (string) $price );
	$product->set_category_ids( array( $cat[ $c ] ) );
	$product->set_image_id( $img_id[ $photo ] );
	$product->set_short_description( $desc );
	$product->set_description( $desc );
	$product->set_menu_order( $i );
	$product->set_virtual( true );          // no shipping address needed
	$product->set_reviews_allowed( false );
	$product->save();
}

$shop_id     = (int) get_option( 'woocommerce_shop_page_id' );
$cart_id     = (int) get_option( 'woocommerce_cart_page_id' );
$checkout_id = (int) get_option( 'woocommerce_checkout_page_id' );
wp_update_post( array( 'ID' => $shop_id, 'post_title' => 'Order Online' ) );

// Sort the shop by our menu order (mains first, then drinks, then desserts).
update_option( 'woocommerce_default_catalog_orderby', 'menu_order' );

/* =====================================================================
 * 4. HELPERS for writing block content
 * ===================================================================== */

function sb_banner( $url, $title, $sub, $height = 380 ) {
	return <<<HTML
<!-- wp:cover {"url":"$url","dimRatio":60,"overlayColor":"contrast","isUserOverlayColor":true,"minHeight":$height,"align":"full","layout":{"type":"constrained"}} -->
<div class="wp-block-cover alignfull" style="min-height:{$height}px"><span aria-hidden="true" class="wp-block-cover__background has-contrast-background-color has-background-dim-60 has-background-dim"></span><img class="wp-block-cover__image-background" alt="" src="$url" data-object-fit="cover"/><div class="wp-block-cover__inner-container"><!-- wp:heading {"textAlign":"center","level":1,"fontSize":"xx-large"} -->
<h1 class="wp-block-heading has-text-align-center has-xx-large-font-size">$title</h1>
<!-- /wp:heading -->

<!-- wp:paragraph {"align":"center","fontSize":"large"} -->
<p class="has-text-align-center has-large-font-size">$sub</p>
<!-- /wp:paragraph --></div></div>
<!-- /wp:cover -->
HTML;
}

function sb_section_open( $bg = '' ) {
	$pad = '"style":{"spacing":{"padding":{"top":"var:preset|spacing|60","bottom":"var:preset|spacing|60"}}}';
	$css = 'padding-top:var(--wp--preset--spacing--60);padding-bottom:var(--wp--preset--spacing--60)';
	if ( $bg ) {
		return '<!-- wp:group {"align":"full",' . $pad . ',"backgroundColor":"' . $bg . '","layout":{"type":"constrained"}} -->' . "\n"
			. '<div class="wp-block-group alignfull has-' . $bg . '-background-color has-background" style="' . $css . '">';
	}
	return '<!-- wp:group {"align":"full",' . $pad . ',"layout":{"type":"constrained"}} -->' . "\n"
		. '<div class="wp-block-group alignfull" style="' . $css . '">';
}
function sb_section_close() {
	return "</div>\n<!-- /wp:group -->";
}
function sb_section( $inner, $bg = '' ) {
	return sb_section_open( $bg ) . "\n" . implode( "\n\n", $inner ) . "\n" . sb_section_close();
}

function sb_h2( $text, $center = true ) {
	if ( $center ) {
		return "<!-- wp:heading {\"textAlign\":\"center\"} -->\n<h2 class=\"wp-block-heading has-text-align-center\">$text</h2>\n<!-- /wp:heading -->";
	}
	return "<!-- wp:heading -->\n<h2 class=\"wp-block-heading\">$text</h2>\n<!-- /wp:heading -->";
}
function sb_h3( $text ) {
	return "<!-- wp:heading {\"level\":3} -->\n<h3 class=\"wp-block-heading\">$text</h3>\n<!-- /wp:heading -->";
}
function sb_p( $text, $center = false ) {
	if ( $center ) {
		return "<!-- wp:paragraph {\"align\":\"center\"} -->\n<p class=\"has-text-align-center\">$text</p>\n<!-- /wp:paragraph -->";
	}
	return "<!-- wp:paragraph -->\n<p>$text</p>\n<!-- /wp:paragraph -->";
}
function sb_eyebrow( $text, $center = true ) {
	$a  = $center ? '"align":"center",' : '';
	$cl = $center ? 'has-text-align-center ' : '';
	return "<!-- wp:paragraph {{$a}\"style\":{\"typography\":{\"textTransform\":\"uppercase\",\"letterSpacing\":\"2px\",\"fontWeight\":\"700\"}},\"textColor\":\"accent-1\",\"fontSize\":\"small\"} -->\n<p class=\"{$cl}has-accent-1-color has-text-color has-small-font-size\" style=\"font-weight:700;letter-spacing:2px;text-transform:uppercase\">$text</p>\n<!-- /wp:paragraph -->";
}
function sb_image( $url, $alt, $caption = '' ) {
	$cap = $caption ? "<figcaption class=\"wp-element-caption\">$caption</figcaption>" : '';
	// Every photo is cropped to the same 4:3 shape so grids and columns line up.
	return "<!-- wp:image {\"aspectRatio\":\"4/3\",\"scale\":\"cover\",\"sizeSlug\":\"large\",\"linkDestination\":\"none\"} -->\n<figure class=\"wp-block-image size-large\"><img src=\"$url\" alt=\"$alt\" style=\"aspect-ratio:4/3;object-fit:cover\"/>$cap</figure>\n<!-- /wp:image -->";
}
function sb_button( $label, $url, $outline = false ) {
	if ( $outline ) {
		return "<!-- wp:button {\"className\":\"is-style-outline\"} -->\n<div class=\"wp-block-button is-style-outline\"><a class=\"wp-block-button__link wp-element-button\" href=\"$url\">$label</a></div>\n<!-- /wp:button -->";
	}
	return "<!-- wp:button -->\n<div class=\"wp-block-button\"><a class=\"wp-block-button__link wp-element-button\" href=\"$url\">$label</a></div>\n<!-- /wp:button -->";
}
function sb_buttons( $inner, $center = true ) {
	$layout = $center ? '{"layout":{"type":"flex","justifyContent":"center"}}' : '';
	return "<!-- wp:buttons $layout -->\n<div class=\"wp-block-buttons\">" . implode( "\n\n", $inner ) . "</div>\n<!-- /wp:buttons -->";
}
function sb_columns( $cols, $align = 'wide' ) {
	$out = "<!-- wp:columns {\"align\":\"$align\"} -->\n<div class=\"wp-block-columns align$align\">";
	foreach ( $cols as $c ) {
		$out .= "<!-- wp:column -->\n<div class=\"wp-block-column\">" . implode( "\n\n", $c ) . "</div>\n<!-- /wp:column -->\n\n";
	}
	return $out . "</div>\n<!-- /wp:columns -->";
}
function sb_menu_table( $rows ) {
	$tr = '';
	foreach ( $rows as $name => $price ) {
		$tr .= "<tr><td>$name</td><td class=\"has-text-align-right\" data-align=\"right\">$price</td></tr>";
	}
	return "<!-- wp:table {\"className\":\"is-style-stripes\"} -->\n<figure class=\"wp-block-table is-style-stripes\"><table class=\"has-fixed-layout\"><tbody>$tr</tbody></table></figure>\n<!-- /wp:table -->";
}
function sb_price( $text ) {
	return "<!-- wp:paragraph {\"style\":{\"typography\":{\"fontWeight\":\"700\"}},\"textColor\":\"accent-1\",\"fontSize\":\"large\"} -->\n<p class=\"has-accent-1-color has-text-color has-large-font-size\" style=\"font-weight:700\">$text</p>\n<!-- /wp:paragraph -->";
}
function sb_card( $url, $alt, $title, $text, $price = '' ) {
	$parts = array( sb_image( $url, $alt ), sb_h3( $title ) );
	if ( $price ) {
		$parts[] = sb_price( $price );
	}
	$parts[] = sb_p( $text );
	return $parts;
}
/** A testimonial card: stars, quote and name, on a white rounded box. */
function sb_review( $quote, $name, $place ) {
	return array(
		'<!-- wp:group {"style":{"spacing":{"padding":{"top":"var:preset|spacing|40","bottom":"var:preset|spacing|40","left":"var:preset|spacing|40","right":"var:preset|spacing|40"}},"border":{"radius":"16px"}},"backgroundColor":"base","layout":{"type":"default"}} -->'
		. "\n" . '<div class="wp-block-group has-base-background-color has-background" style="border-radius:16px;padding-top:var(--wp--preset--spacing--40);padding-right:var(--wp--preset--spacing--40);padding-bottom:var(--wp--preset--spacing--40);padding-left:var(--wp--preset--spacing--40)">'
		. "<!-- wp:paragraph {\"textColor\":\"accent-1\",\"fontSize\":\"large\"} -->\n<p class=\"has-accent-1-color has-text-color has-large-font-size\" aria-label=\"5 out of 5 stars\">★★★★★</p>\n<!-- /wp:paragraph -->\n\n"
		. sb_p( "“{$quote}”" ) . "\n\n"
		. "<!-- wp:paragraph {\"style\":{\"typography\":{\"fontWeight\":\"700\"}}} -->\n<p style=\"font-weight:700\">{$name} <span class=\"has-accent-4-color has-text-color\" style=\"font-weight:400\">· {$place}</span></p>\n<!-- /wp:paragraph -->"
		. "</div>\n<!-- /wp:group -->",
	);
}

/** Create the page, or update it if a page with this slug already exists. */
function sb_upsert_page( $title, $slug, $content ) {
	$existing = get_page_by_path( $slug );
	$id       = $existing ? $existing->ID : 0;
	$data = array(
		'post_title'   => $title,
		'post_name'    => $slug,
		'post_content' => $content,
		'post_status'  => 'publish',
		'post_type'    => 'page',
	);
	if ( $id ) {
		$data['ID'] = $id;
		wp_update_post( wp_slash( $data ) );
	} else {
		$id = wp_insert_post( wp_slash( $data ) );
	}
	update_post_meta( $id, '_wp_page_template', 'page-no-title' );
	return $id;
}

$url = array(
	'menu'     => home_url( '/menu/' ),
	'order'    => get_permalink( $shop_id ),
	'reserve'  => home_url( '/reservations/' ),
	'gallery'  => home_url( '/food-gallery/' ),
	'about'    => home_url( '/about-us/' ),
	'contact'  => home_url( '/contact-us/' ),
	'privacy'  => home_url( '/privacy-policy/' ),
	'whatsapp' => 'https://wa.me/254700000000',
);

/* =====================================================================
 * 5. PAGES
 * ===================================================================== */

/* ---------- HOME ---------- */

$home  = <<<HTML
<!-- wp:cover {"url":"{$img['hero']}","dimRatio":60,"overlayColor":"contrast","isUserOverlayColor":true,"minHeight":620,"align":"full","layout":{"type":"constrained"}} -->
<div class="wp-block-cover alignfull" style="min-height:620px"><span aria-hidden="true" class="wp-block-cover__background has-contrast-background-color has-background-dim-60 has-background-dim"></span><img class="wp-block-cover__image-background" alt="" src="{$img['hero']}" data-object-fit="cover"/><div class="wp-block-cover__inner-container"><!-- wp:paragraph {"align":"center","style":{"typography":{"textTransform":"uppercase","letterSpacing":"3px","fontWeight":"700"}},"textColor":"accent-2"} -->
<p class="has-text-align-center has-accent-2-color has-text-color" style="font-weight:700;letter-spacing:3px;text-transform:uppercase">Welcome to Savanna Bites Restaurant</p>
<!-- /wp:paragraph -->

<!-- wp:heading {"textAlign":"center","level":1,"style":{"typography":{"fontSize":"clamp(2.6rem, 6vw, 4.5rem)","fontWeight":"600"}}} -->
<h1 class="wp-block-heading has-text-align-center" style="font-size:clamp(2.6rem, 6vw, 4.5rem);font-weight:600">Delicious Food, Unforgettable Moments</h1>
<!-- /wp:heading -->

<!-- wp:paragraph {"align":"center","fontSize":"large"} -->
<p class="has-text-align-center has-large-font-size">Where great food meets excellent service. Enjoy freshly prepared meals, affordable prices and a memorable dining experience in the heart of Nakuru.</p>
<!-- /wp:paragraph -->

HTML;
$home .= sb_buttons( array( sb_button( 'Order Online', $url['order'] ), sb_button( 'Book a Table', $url['reserve'], true ) ) );
$home .= "</div></div>\n<!-- /wp:cover -->\n\n";

// Special offer strip.
$home .= '<!-- wp:group {"align":"full","style":{"spacing":{"padding":{"top":"var:preset|spacing|30","bottom":"var:preset|spacing|30"}}},"backgroundColor":"accent-2","textColor":"contrast","layout":{"type":"flex","flexWrap":"wrap","justifyContent":"center"}} -->' . "\n"
	. '<div class="wp-block-group alignfull has-contrast-color has-accent-2-background-color has-text-color has-background" style="padding-top:var(--wp--preset--spacing--30);padding-bottom:var(--wp--preset--spacing--30)">'
	. "<!-- wp:paragraph {\"style\":{\"typography\":{\"fontWeight\":\"700\"}},\"fontSize\":\"large\"} -->\n<p class=\"has-large-font-size\" style=\"font-weight:700\">🔥 This Week’s Offer: Happy Hour 4–6 PM – <strong>20% off all fresh juices!</strong></p>\n<!-- /wp:paragraph -->\n\n"
	. "<!-- wp:paragraph {\"style\":{\"typography\":{\"fontWeight\":\"700\"}}} -->\n<p style=\"font-weight:700\"><a href=\"{$url['order']}\">Order now →</a></p>\n<!-- /wp:paragraph -->"
	. "</div>\n<!-- /wp:group -->\n\n";

// About strip.
$home .= sb_section( array(
	sb_columns( array(
		array( sb_image( $img['interior'], 'Inside Savanna Bites Restaurant' ) ),
		array(
			sb_eyebrow( 'About Savanna Bites', false ),
			sb_h2( 'Fresh meals, warm welcome', false ),
			sb_p( 'Savanna Bites Restaurant is dedicated to serving delicious, freshly prepared meals in a comfortable and welcoming environment. Our goal is to provide quality food, affordable prices and excellent customer service.' ),
			sb_buttons( array( sb_button( 'Read Our Story', $url['about'], true ) ), false ),
		),
	) ),
) ) . "\n\n";

// Specialties.
$home .= sb_section( array(
	sb_eyebrow( 'Our Specialties' ),
	sb_h2( 'Explore Our Delicious Menu' ),
	sb_columns( array(
		sb_card( $img['chicken'], 'Grilled chicken', 'Grilled Chicken', 'Tender chicken marinated in our house spices and grilled over an open flame.', 'KSh 850' ),
		sb_card( $img['biryani'], 'Chicken biryani', 'Chicken Biryani', 'Fragrant spiced rice layered with slow-cooked chicken.', 'KSh 650' ),
		sb_card( $img['beefstew'], 'Beef stew', 'Chapati &amp; Beef Stew', 'Soft layered chapati served with a rich, hearty beef stew.', 'KSh 450' ),
	) ),
	sb_buttons( array( sb_button( 'See the Full Menu', $url['menu'] ), sb_button( 'Order Online', $url['order'], true ) ) ),
), 'accent-5' ) . "\n\n";

// Why choose us.
$home .= sb_section( array(
	sb_eyebrow( 'Why Choose Us' ),
	sb_h2( 'Good food, done right' ),
	sb_columns( array(
		array( sb_h3( '🥗 Fresh Ingredients' ), sb_p( 'Every dish is prepared fresh each day with quality local ingredients.' ) ),
		array( sb_h3( '💰 Affordable Prices' ), sb_p( 'Generous portions and great taste at prices that suit every budget.' ) ),
		array( sb_h3( '🛵 Order or Book Online' ), sb_p( 'Order your favourite meals for pickup or delivery, or reserve a table in seconds.' ) ),
	) ),
) ) . "\n\n";

// Reviews.
$home .= sb_section( array(
	sb_eyebrow( 'Reviews' ),
	sb_h2( 'What our customers say' ),
	sb_columns( array(
		sb_review( 'The grilled chicken is the best in Nakuru! Friendly staff and the food arrived quickly.', 'Wanjiku M.', 'Nakuru' ),
		sb_review( 'We booked a table for a family birthday and everything was perfect. The biryani is a must-try.', 'Brian O.', 'Naivasha' ),
		sb_review( 'Great prices and generous portions. I order chapati and beef stew for lunch every week.', 'Amina K.', 'Nakuru' ),
	) ),
), 'accent-5' ) . "\n\n";

// Call to action.
$home .= <<<HTML
<!-- wp:cover {"url":"{$img['dining']}","dimRatio":70,"overlayColor":"accent-3","isUserOverlayColor":true,"minHeight":360,"align":"full","layout":{"type":"constrained"}} -->
<div class="wp-block-cover alignfull" style="min-height:360px"><span aria-hidden="true" class="wp-block-cover__background has-accent-3-background-color has-background-dim-70 has-background-dim"></span><img class="wp-block-cover__image-background" alt="" src="{$img['dining']}" data-object-fit="cover"/><div class="wp-block-cover__inner-container"><!-- wp:heading {"textAlign":"center"} -->
<h2 class="wp-block-heading has-text-align-center">Reserve your table today</h2>
<!-- /wp:heading -->

<!-- wp:paragraph {"align":"center","fontSize":"large"} -->
<p class="has-text-align-center has-large-font-size">Open Monday – Sunday, 8:00 AM – 10:00 PM · Call +254 700 000 000</p>
<!-- /wp:paragraph -->

HTML;
$home .= sb_buttons( array( sb_button( 'Book a Table', $url['reserve'] ) ) );
$home .= "</div></div>\n<!-- /wp:cover -->";

$home_id = sb_upsert_page( 'Home', 'home', $home );

/* ---------- MENU ---------- */

$menu  = sb_banner( $img['spread'], 'Our Delicious Menu', 'Welcome to Savanna Bites! Explore our freshly prepared meals.' ) . "\n\n";
$menu .= sb_section( array(
	sb_columns( array(
		array(
			sb_h2( 'Main Dishes', false ),
			sb_menu_table( array(
				'Grilled Chicken'       => 'KSh 850',
				'Fried Fish'            => 'KSh 750',
				'Chicken Biryani'       => 'KSh 650',
				'Beef Pilau'            => 'KSh 500',
				'Chapati and Beef Stew' => 'KSh 450',
			) ),
			sb_image( $img['grill'], 'Grilled meat platter' ),
		),
		array(
			sb_h2( 'Drinks', false ),
			sb_menu_table( array(
				'Fresh Orange Juice' => 'KSh 200',
				'Mango Juice'        => 'KSh 200',
				'Bottled Water'      => 'KSh 100',
				'Soda'               => 'KSh 100',
			) ),
			sb_h2( 'Desserts', false ),
			sb_menu_table( array(
				'Fruit Salad' => 'KSh 250',
				'Ice Cream'   => 'KSh 200',
			) ),
		),
	) ),
) ) . "\n\n";
$menu .= sb_section( array(
	sb_h2( 'Hungry already?' ),
	sb_p( 'Order online for pickup or delivery and pay on arrival with cash or M-Pesa, or reserve a table and dine with us.', true ),
	sb_buttons( array( sb_button( 'Order Online', $url['order'] ), sb_button( 'Book a Table', $url['reserve'], true ) ) ),
), 'accent-5' );

$menu_id = sb_upsert_page( 'Menu', 'menu', $menu );

/* ---------- RESERVATIONS (form added by the Restaurant Reservations plugin) ---------- */

$reserve  = sb_banner( $img['dining'], 'Book a Table', 'Reserve your table online in under a minute.' ) . "\n\n";
$reserve .= sb_section( array(
	sb_columns( array(
		array( sb_h3( '📅 Choose a date &amp; time' ), sb_p( 'We take bookings every day from 8:00 AM to 9:00 PM.' ) ),
		array( sb_h3( '👨‍👩‍👧 Up to 20 guests' ), sb_p( 'Planning something bigger? Call us on +254 700 000 000.' ) ),
		array( sb_h3( '✅ Quick confirmation' ), sb_p( 'Our team reviews your request and confirms it by email.' ) ),
	) ),
) );
$reserve_id = sb_upsert_page( 'Reservations', 'reservations', $reserve );

$rtb                   = get_option( 'rtb-settings', array() );
$rtb                   = is_array( $rtb ) ? $rtb : array();
$rtb['booking-page']   = $reserve_id;
$rtb['party-size-min'] = 1;
$rtb['party-size']     = 20;
$rtb['time-interval']  = 30;
$rtb['early-bookings'] = '60';
$rtb['schedule-open']  = array(
	array(
		'weekdays' => array_fill_keys( array( 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday' ), '1' ),
		'time'     => array( 'start' => '08:00 AM', 'end' => '09:00 PM' ),
	),
);
$rtb['success-message'] = 'Thank you! Your table request has been received and is waiting for confirmation. We will email you shortly.';
update_option( 'rtb-settings', $rtb );
update_option( 'rtb-getting-started', 1 );   // skip the plugin's setup wizard

/* ---------- FOOD GALLERY ---------- */

$photos = array(
	array( 'chicken', 'Grilled chicken', 'Grilled Chicken' ),
	array( 'biryani', 'Chicken biryani', 'Chicken Biryani' ),
	array( 'grill', 'Grilled ribs platter', 'Mixed Grill' ),
	array( 'skewers', 'Grilled meat skewers', 'Nyama Choma Skewers' ),
	array( 'friedfish', 'Fried fish fillet', 'Fried Fish' ),
	array( 'beefstew', 'Beef stew', 'Beef Stew' ),
	array( 'pilau', 'Beef pilau', 'Beef Pilau' ),
	array( 'salad', 'Fresh salad', 'Fresh Salad' ),
	array( 'veggiebowl', 'Vegetable bowl', 'Veggie Bowl' ),
	array( 'platter', 'Sharing platter', 'Party Platter' ),
	array( 'fruitsalad', 'Fruit salad', 'Fruit Salad' ),
	array( 'mango', 'Mango juice', 'Mango Juice' ),
);
$gal = "<!-- wp:gallery {\"columns\":3,\"linkTo\":\"none\",\"sizeSlug\":\"large\",\"align\":\"wide\"} -->\n<figure class=\"wp-block-gallery alignwide has-nested-images columns-3 is-cropped\">";
foreach ( $photos as $ph ) {
	$gal .= sb_image( $img[ $ph[0] ], $ph[1], $ph[2] ) . "\n\n";
}
$gal .= "</figure>\n<!-- /wp:gallery -->";

$gallery  = sb_banner( $img['spread'], 'Explore Our Food Gallery', 'Every dish is prepared with fresh ingredients and served with love.' ) . "\n\n";
$gallery .= sb_section( array(
	sb_eyebrow( 'Our Food Collection' ),
	sb_h2( 'A taste of Savanna Bites' ),
	sb_p( 'Take a look at some of the delicious meals prepared at Savanna Bites Restaurant. Enjoy our variety of delicious meals, refreshing drinks and tasty desserts.', true ),
	$gal,
) );
$gallery_id = sb_upsert_page( 'Food Gallery', 'food-gallery', $gallery );

/* ---------- ABOUT US ---------- */

$about  = sb_banner( $img['terrace'], 'About Savanna Bites', 'Quality food, affordable prices and excellent customer service.' ) . "\n\n";
$about .= sb_section( array(
	sb_columns( array(
		array(
			sb_h2( 'Our Story', false ),
			sb_p( 'Savanna Bites Restaurant is dedicated to serving delicious, freshly prepared meals in a comfortable and welcoming environment. Inspired by the warmth of the Kenyan savanna, we bring together classic local favourites like pilau, chapati and stew alongside grilled dishes and fresh juices.' ),
			sb_p( 'Whether you are stopping by for a quick lunch, celebrating with family or meeting friends for dinner, our team is here to make every visit special.' ),
		),
		array( sb_image( $img['interior'], 'Our dining area' ) ),
	) ),
) ) . "\n\n";
$about .= sb_section( array(
	sb_columns( array(
		array( sb_h3( 'Our Mission' ), sb_p( 'To provide quality food, affordable prices and excellent customer service to every guest who walks through our doors.' ) ),
		array( sb_h3( 'Our Vision' ), sb_p( 'To be Nakuru’s favourite place to share a great meal with family and friends.' ) ),
		array( sb_h3( 'Our Values' ), sb_p( 'Freshness, hospitality, cleanliness and respect for every customer.' ) ),
	) ),
), 'accent-5' );
$about_id = sb_upsert_page( 'About Us', 'about-us', $about );

/* ---------- CONTACT US ---------- */

$forms   = get_posts( array( 'post_type' => 'wpcf7_contact_form', 'numberposts' => 1, 'orderby' => 'ID', 'order' => 'ASC' ) );
$form_id = $forms ? $forms[0]->ID : 0;

$contact  = sb_banner( $img['dining'], 'Contact Us', 'We would love to hear from you! Visit us or contact us for enquiries and reservations.' ) . "\n\n";
$contact .= sb_section( array(
	sb_columns( array(
		array(
			sb_h2( 'Get in touch', false ),
			sb_p( '<strong>📍 Location:</strong> Nakuru, Kenya' ),
			sb_p( '<strong>📞 Phone:</strong> <a href="tel:+254700000000">+254 700 000 000</a>' ),
			sb_p( '<strong>💬 WhatsApp:</strong> <a href="' . $url['whatsapp'] . '">Chat with us</a>' ),
			sb_p( '<strong>✉️ Email:</strong> <a href="mailto:info@savannabites.com">info@savannabites.com</a>' ),
			sb_p( '<strong>🕗 Opening Hours:</strong><br>Monday – Sunday, 8:00 AM – 10:00 PM' ),
			sb_buttons( array( sb_button( 'Book a Table', $url['reserve'] ) ), false ),
		),
		array(
			sb_h2( 'Send us a message', false ),
			sb_p( 'Questions, feedback or event enquiries – we usually reply within a day.' ),
			"<!-- wp:shortcode -->\n[contact-form-7 id=\"$form_id\" title=\"Contact form 1\"]\n<!-- /wp:shortcode -->",
		),
	) ),
) ) . "\n\n";
$contact .= "<!-- wp:html -->\n<iframe title=\"Map showing Nakuru, Kenya\" src=\"https://www.google.com/maps?q=Nakuru,Kenya&amp;output=embed\" width=\"100%\" height=\"400\" style=\"border:0;display:block\" loading=\"lazy\"></iframe>\n<!-- /wp:html -->";
$contact_id = sb_upsert_page( 'Contact Us', 'contact-us', $contact );

/* ---------- PRIVACY POLICY ---------- */

$privacy = sb_section( array(
	"<!-- wp:heading {\"level\":1} -->\n<h1 class=\"wp-block-heading\">Privacy Policy</h1>\n<!-- /wp:heading -->",
	sb_p( 'Last updated: ' . gmdate( 'j F Y' ) ),
	sb_p( 'Savanna Bites Restaurant (“we”, “us”) respects your privacy. This policy explains what information we collect through this website and how we use it.' ),
	sb_h2( 'Information we collect', false ),
	"<!-- wp:list -->\n<ul class=\"wp-block-list\"><!-- wp:list-item -->\n<li><strong>Contact form:</strong> your name, email address and message.</li>\n<!-- /wp:list-item -->\n\n<!-- wp:list-item -->\n<li><strong>Table reservations:</strong> your name, email, phone number, date, time and number of guests.</li>\n<!-- /wp:list-item -->\n\n<!-- wp:list-item -->\n<li><strong>Online orders:</strong> your name, phone number, email and the items you order.</li>\n<!-- /wp:list-item --></ul>\n<!-- /wp:list -->",
	sb_h2( 'How we use it', false ),
	sb_p( 'We only use your information to reply to your messages, manage your reservations and prepare your orders. We do not sell or share your personal information with third parties.' ),
	sb_h2( 'Payments', false ),
	sb_p( 'Orders are paid on delivery or pickup by cash or M-Pesa. We do not collect or store card details on this website.' ),
	sb_h2( 'Your rights', false ),
	sb_p( 'You can ask us to see, correct or delete the information we hold about you at any time by emailing <a href="mailto:info@savannabites.com">info@savannabites.com</a>.' ),
) );
$privacy_id = sb_upsert_page( 'Privacy Policy', 'privacy-policy', $privacy );
update_option( 'wp_page_for_privacy_policy', $privacy_id );

/* ---------- tidy up extra pages (moved to Draft, never deleted) ---------- */

$sample = get_page_by_path( 'sample-page' );
if ( $sample ) {
	wp_update_post( array( 'ID' => $sample->ID, 'post_status' => 'draft' ) );
}
// The original site had an untitled early draft of the home page (ID 11).
$untitled = get_post( 11 );
if ( $untitled && 'page' === $untitled->post_type && '' === $untitled->post_title ) {
	wp_update_post( array( 'ID' => 11, 'post_status' => 'draft' ) );
}

/* =====================================================================
 * 6. SITE SETTINGS, NAVIGATION, HEADER, FOOTER
 * ===================================================================== */

update_option( 'blogname', 'Savanna Bites Restaurant' );
update_option( 'blogdescription', 'Delicious Food, Unforgettable Moments' );
update_option( 'timezone_string', 'Africa/Nairobi' );   // Kenya time for orders and bookings
update_option( 'gmt_offset', 3 );
update_option( 'show_on_front', 'page' );
update_option( 'page_on_front', $home_id );

$nav_items = array(
	$home_id    => 'Home',
	$menu_id    => 'Menu',
	$shop_id    => 'Order Online',
	$reserve_id => 'Reservations',
	$gallery_id => 'Gallery',
	$about_id   => 'About',
	$contact_id => 'Contact',
);
$nav = '';
foreach ( $nav_items as $pid => $label ) {
	$nav .= '<!-- wp:navigation-link {"label":"' . $label . '","type":"page","id":' . $pid . ',"url":"' . get_permalink( $pid ) . '","kind":"post-type"} /-->' . "\n";
}
$nav_posts = get_posts( array( 'post_type' => 'wp_navigation', 'post_status' => 'publish', 'numberposts' => 1, 'orderby' => 'ID', 'order' => 'ASC' ) );
$nav_data  = array( 'post_type' => 'wp_navigation', 'post_status' => 'publish', 'post_title' => 'Main Menu', 'post_content' => $nav );
if ( $nav_posts ) {
	$nav_data['ID'] = $nav_posts[0]->ID;
	$nav_id         = wp_update_post( wp_slash( $nav_data ) );
} else {
	$nav_id = wp_insert_post( wp_slash( $nav_data ) );
}

/** Save a site-editor template part (header/footer) for the active theme. */
function sb_template_part( $slug, $title, $area, $content ) {
	$theme    = get_stylesheet();
	$existing = get_posts( array(
		'post_type'   => 'wp_template_part',
		'name'        => $slug,
		'post_status' => 'any',
		'numberposts' => 1,
		'tax_query'   => array( array( 'taxonomy' => 'wp_theme', 'field' => 'name', 'terms' => $theme ) ),
	) );
	$part = array(
		'post_type'    => 'wp_template_part',
		'post_name'    => $slug,
		'post_title'   => $title,
		'post_status'  => 'publish',
		'post_content' => $content,
	);
	if ( $existing ) {
		$part['ID'] = $existing[0]->ID;
		$id         = wp_update_post( wp_slash( $part ) );
	} else {
		$id = wp_insert_post( wp_slash( $part ) );
	}
	wp_set_object_terms( $id, $theme, 'wp_theme' );
	wp_set_object_terms( $id, $area, 'wp_template_part_area' );
	return $id;
}

// Header: logo + name on the left, menu + cart on the right; stays at the top while scrolling.
$header = '<!-- wp:group {"align":"full","style":{"position":{"type":"sticky","top":"0px"},"spacing":{"padding":{"top":"var:preset|spacing|20","bottom":"var:preset|spacing|20"}},"border":{"bottom":{"color":"var:preset|color|accent-6","width":"1px"}}},"backgroundColor":"base","layout":{"type":"constrained"}} -->' . "\n"
	. '<div class="wp-block-group alignfull has-base-background-color has-background" style="border-bottom-color:var(--wp--preset--color--accent-6);border-bottom-width:1px;padding-top:var(--wp--preset--spacing--20);padding-bottom:var(--wp--preset--spacing--20)">'
	. '<!-- wp:group {"align":"wide","layout":{"type":"flex","flexWrap":"nowrap","justifyContent":"space-between"}} -->' . "\n"
	. '<div class="wp-block-group alignwide">'
	. '<!-- wp:group {"style":{"spacing":{"blockGap":"var:preset|spacing|20"}},"layout":{"type":"flex","flexWrap":"nowrap"}} -->' . "\n"
	. '<div class="wp-block-group"><!-- wp:site-logo {"width":52,"shouldSyncIcon":false} /-->' . "\n\n"
	. '<!-- wp:site-title {"level":0,"fontSize":"large"} /--></div>' . "\n" . '<!-- /wp:group -->' . "\n\n"
	. '<!-- wp:group {"style":{"spacing":{"blockGap":"var:preset|spacing|30"}},"layout":{"type":"flex","flexWrap":"nowrap"}} -->' . "\n"
	. '<div class="wp-block-group"><!-- wp:navigation {"ref":' . $nav_id . ',"overlayBackgroundColor":"base","overlayTextColor":"contrast","layout":{"type":"flex","justifyContent":"right","flexWrap":"wrap"}} /-->' . "\n\n"
	. '<!-- wp:woocommerce/mini-cart /--></div>' . "\n" . '<!-- /wp:group --></div>' . "\n" . '<!-- /wp:group --></div>' . "\n" . '<!-- /wp:group -->';
sb_template_part( 'header', 'Header', 'header', $header );

// Footer.
$social = '<!-- wp:social-links {"iconColor":"contrast","iconColorValue":"#2A1A10","iconBackgroundColor":"accent-2","iconBackgroundColorValue":"#F4C542","className":"is-style-default","style":{"spacing":{"blockGap":{"left":"var:preset|spacing|20"}}}} -->' . "\n"
	. '<ul class="wp-block-social-links has-icon-color has-icon-background-color is-style-default">'
	. '<!-- wp:social-link {"url":"https://facebook.com/","service":"facebook"} /-->'
	. '<!-- wp:social-link {"url":"https://instagram.com/","service":"instagram"} /-->'
	. '<!-- wp:social-link {"url":"https://tiktok.com/","service":"tiktok"} /-->'
	. '<!-- wp:social-link {"url":"' . $url['whatsapp'] . '","service":"whatsapp"} /-->'
	. "</ul>\n<!-- /wp:social-links -->";

$footer = '<!-- wp:group {"style":{"spacing":{"padding":{"top":"var:preset|spacing|60","bottom":"var:preset|spacing|40"}},"elements":{"link":{"color":{"text":"var:preset|color|accent-2"}}}},"backgroundColor":"contrast","textColor":"base","layout":{"type":"constrained"}} -->' . "\n"
	. '<div class="wp-block-group has-base-color has-contrast-background-color has-text-color has-background has-link-color" style="padding-top:var(--wp--preset--spacing--60);padding-bottom:var(--wp--preset--spacing--40)">'
	. sb_columns( array(
		array(
			"<!-- wp:heading {\"level\":3,\"textColor\":\"accent-2\"} -->\n<h3 class=\"wp-block-heading has-accent-2-color has-text-color\">Savanna Bites</h3>\n<!-- /wp:heading -->",
			sb_p( 'Delicious Food, Unforgettable Moments. Freshly prepared meals, affordable prices and excellent service.' ),
			$social,
		),
		array( sb_h3( 'Visit Us' ), sb_p( 'Nakuru, Kenya<br><a href="tel:+254700000000">+254 700 000 000</a><br><a href="mailto:info@savannabites.com">info@savannabites.com</a>' ) ),
		array( sb_h3( 'Opening Hours' ), sb_p( 'Monday – Sunday<br>8:00 AM – 10:00 PM' ) ),
		array( sb_h3( 'Quick Links' ), sb_p( '<a href="' . $url['menu'] . '">Menu</a><br><a href="' . $url['order'] . '">Order Online</a><br><a href="' . $url['reserve'] . '">Reservations</a><br><a href="' . $url['gallery'] . '">Food Gallery</a><br><a href="' . $url['contact'] . '">Contact Us</a>' ) ),
	) )
	. "\n\n<!-- wp:separator {\"align\":\"wide\",\"backgroundColor\":\"accent-4\"} -->\n<hr class=\"wp-block-separator alignwide has-text-color has-accent-4-color has-alpha-channel-opacity has-accent-4-background-color has-background\"/>\n<!-- /wp:separator -->\n\n"
	. "<!-- wp:paragraph {\"align\":\"center\",\"fontSize\":\"small\"} -->\n<p class=\"has-text-align-center has-small-font-size\">© " . gmdate( 'Y' ) . " Savanna Bites Restaurant. All rights reserved. · <a href=\"{$url['privacy']}\">Privacy Policy</a></p>\n<!-- /wp:paragraph -->"
	. "</div>\n<!-- /wp:group -->";
sb_template_part( 'footer', 'Footer', 'footer', $footer );

/* =====================================================================
 * 7. GLOBAL STYLES – colours and fonts (Appearance → Editor → Styles)
 * ===================================================================== */

$fonts = json_decode( file_get_contents( get_template_directory() . '/styles/typography/typography-preset-4.json' ), true );

$styles = array(
	'version'                     => 3,
	'isGlobalStylesUserThemeJSON' => true,
	'settings'                    => array(
		'color'      => array(
			'palette' => array(
				'theme' => array(
					array( 'name' => 'Cream', 'slug' => 'base', 'color' => '#FFF9F0' ),
					array( 'name' => 'Dark Coffee', 'slug' => 'contrast', 'color' => '#2A1A10' ),
					array( 'name' => 'Savanna Orange', 'slug' => 'accent-1', 'color' => '#B85A14' ),
					array( 'name' => 'Sunset Gold', 'slug' => 'accent-2', 'color' => '#F4C542' ),
					array( 'name' => 'Acacia Green', 'slug' => 'accent-3', 'color' => '#3F5E3A' ),
					array( 'name' => 'Clay', 'slug' => 'accent-4', 'color' => '#8A7563' ),
					array( 'name' => 'Sand', 'slug' => 'accent-5', 'color' => '#F6EAD7' ),
					array( 'name' => 'Coffee Transparent', 'slug' => 'accent-6', 'color' => '#2A1A1033' ),
				),
			),
		),
		'typography' => $fonts['settings']['typography'],
	),
	'styles'                      => array(
		'typography' => array( 'fontFamily' => 'var:preset|font-family|manrope' ),
		'elements'   => array(
			'heading' => array(
				'typography' => array( 'fontFamily' => 'var:preset|font-family|roboto-slab', 'fontWeight' => '600', 'lineHeight' => '1.2' ),
			),
			'link'    => array(
				'color'  => array( 'text' => 'var:preset|color|accent-1' ),
				':hover' => array( 'color' => array( 'text' => 'var:preset|color|accent-3' ) ),
			),
			'button'  => array(
				'color'      => array( 'background' => 'var:preset|color|accent-1', 'text' => '#FFFFFF' ),
				'border'     => array( 'radius' => '999px' ),
				'typography' => array( 'fontWeight' => '700' ),
				':hover'     => array( 'color' => array( 'background' => 'var:preset|color|accent-3', 'text' => '#FFFFFF' ) ),
			),
		),
		'blocks'     => array(
			'core/site-title' => array(
				'typography' => array( 'fontFamily' => 'var:preset|font-family|roboto-slab', 'fontWeight' => '700' ),
				'color'      => array( 'text' => 'var:preset|color|accent-1' ),
			),
			'core/navigation' => array(
				'typography' => array( 'fontWeight' => '600', 'fontSize' => '0.95rem' ),
				'spacing'    => array( 'blockGap' => '1.25rem' ),
			),
			'core/image'      => array(
				'border' => array( 'radius' => '12px' ),
			),
			'core/button'     => array(
				'variations' => array(
					'outline' => array(
						'color'  => array( 'background' => 'transparent', 'text' => 'currentColor' ),
						'border' => array( 'color' => 'currentColor', 'width' => '2px', 'style' => 'solid' ),
					),
				),
			),
		),
	),
);
// Finds (or creates) the "Custom Styles" record that the Site Editor saves to.
$styles_id = WP_Theme_JSON_Resolver::get_user_global_styles_post_id();
wp_update_post( wp_slash( array( 'ID' => $styles_id, 'post_content' => wp_json_encode( $styles ) ) ) );

flush_rewrite_rules();
wp_cache_flush();

WP_CLI::success( sprintf(
	'Built Home %d, Menu %d, Order Online %d, Reservations %d, Gallery %d, About %d, Contact %d, Privacy %d; %d menu items; logo %d.',
	$home_id, $menu_id, $shop_id, $reserve_id, $gallery_id, $about_id, $contact_id, $privacy_id, count( $menu_items ), $logo_id
) );
