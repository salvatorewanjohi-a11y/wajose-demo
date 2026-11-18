<?php
/**
 * Builds the Wajose Main Shop site inside WordPress Playground (same pipeline as our Beauty Galore and RealBeef builds).
 * Run by the blueprint after WooCommerce and the theme are installed. Product photos are read from /wordpress/wj-images.
 *
 * Every price here is a SAMPLE until Wajose confirms its retail and wholesale price list.
 * Staff edit names, prices, wholesale prices, sizes, colours, photos and badges under Products in wp-admin;
 * the home page reads them live.
 */
require_once '/wordpress/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/media.php';
require_once ABSPATH . 'wp-admin/includes/file.php';
require_once ABSPATH . 'wp-admin/includes/image.php';

// Photos are already web-sized: copy each into uploads and register it, without generating thumbnails.
function wj_sideload( $src, $parent, $title ) {
	$up   = wp_upload_dir();
	$file = wp_unique_filename( $up['path'], basename( $src ) );
	$dest = trailingslashit( $up['path'] ) . $file;
	if ( ! copy( $src, $dest ) ) {
		return 0;
	}
	$type = wp_check_filetype( $file );
	$size = @getimagesize( $dest ) ?: [ 0, 0 ];
	$id   = wp_insert_attachment( [ 'post_mime_type' => $type['type'] ?: 'image/jpeg', 'post_title' => $title, 'post_status' => 'inherit', 'guid' => trailingslashit( $up['url'] ) . $file ], $dest, $parent, true, false );
	if ( is_wp_error( $id ) ) {
		return 0;
	}
	wp_update_attachment_metadata( $id, [ 'width' => $size[0], 'height' => $size[1], 'file' => _wp_relative_upload_path( $dest ), 'sizes' => [], 'image_meta' => [] ] );
	return $id;
}

/** A visible, non-variation product attribute (Size, Colour) that staff edit under Product data → Attributes. */
function wj_attr( $name, $options, $position ) {
	$a = new WC_Product_Attribute();
	$a->set_name( $name );
	$a->set_options( $options );
	$a->set_position( $position );
	$a->set_visible( true );
	$a->set_variation( false );
	return $a;
}

wp_defer_term_counting( true );
wp_suspend_cache_invalidation( true );
$wpdb->query( 'START TRANSACTION' );

if ( defined( 'WJ_FAST' ) && WJ_FAST ) {
	add_filter( 'intermediate_image_sizes_advanced', '__return_empty_array' );
	add_filter( 'big_image_size_threshold', '__return_false' );
	add_filter( 'woocommerce_background_image_regeneration', '__return_false' );
	add_filter( 'woocommerce_resize_images', '__return_false' );
}

foreach ( [
	'blogname'                       => 'Wajose Main Shop',
	'blogdescription'                => 'Uncompromising Style • Premium Footwear & Fashion • Wholesale & Retail Excellence',
	'timezone_string'                => 'Africa/Nairobi',
	'woocommerce_currency'           => 'KES',
	'woocommerce_currency_pos'       => 'left_space',
	'woocommerce_price_num_decimals' => '0',
	'woocommerce_price_thousand_sep' => ',',
	'woocommerce_default_country'    => 'KE:KE30',
	'woocommerce_store_city'         => 'Nairobi',
	'woocommerce_store_address'      => 'Nairobi',
	'woocommerce_manage_stock'       => 'no',
	'woocommerce_enable_reviews'     => 'no',
	'woocommerce_onboarding_profile' => [ 'skipped' => true ],
	'woocommerce_task_list_hidden'   => 'yes',
	'woocommerce_coming_soon'        => 'no',
	'woocommerce_enable_coupons'     => 'no',
] as $k => $v ) {
	update_option( $k, $v );
}

foreach ( get_posts( [ 'post_type' => [ 'post', 'page' ], 'name' => 'hello-world', 'numberposts' => 1 ] ) as $p ) wp_delete_post( $p->ID, true );
$sample_page = get_page_by_path( 'sample-page' );
if ( $sample_page ) wp_delete_post( $sample_page->ID, true );

// Shop categories in tab order.
$cats = [
	'executive' => [ 'Executive Footwear & Loafers', 'Leather slip-ons, horsebit and chain-detail loafers, oxfords and smart-casual drivers.' ],
	'sneakers'  => [ 'Streetwear & Sneakers', 'Everyday trainers, chunky soles and limited drops for the city.' ],
	'apparel'   => [ 'Menswear & Apparel', 'Polos, chinos, tailored trousers, overshirts and knits.' ],
	'wholesale' => [ 'Wholesale Bulk Packs', 'Curated packs with a full size run, priced for boutiques and resellers across Kenya.' ],
];
$cat_ids = [];
$order   = 0;
foreach ( $cats as $slug => [ $name, $blurb ] ) {
	$t                = wp_insert_term( $name, 'product_cat', [ 'slug' => $slug, 'description' => $blurb ] );
	$cat_ids[ $slug ] = is_wp_error( $t ) ? (int) $t->get_error_data() : $t['term_id'];
	update_term_meta( $cat_ids[ $slug ], 'order', $order++ );
}

$shoe   = [ '39', '40', '41', '42', '43', '44', '45', '46' ];
$sneak  = [ '39', '40', '41', '42', '43', '44', '45' ];
$tops   = [ 'S', 'M', 'L', 'XL', 'XXL' ];
$waist  = [ '30', '32', '34', '36', '38' ];
$runs   = [ 'Standard run 40–45', 'Large run 42–46', 'Custom (tell us)' ];

/*
 * name, retail KSh, wholesale KSh per piece (0 = none), category, short description, photo, sizes, colours, badge, new.
 * SAMPLE prices: confirm every figure with Wajose before the preview flag comes off.
 */
$products = [
	[ 'Horsebit Leather Loafer', 5800, 4300, 'executive', 'Full-grain leather, polished gold horsebit, leather-lined', 'horsebit-loafer-tan', $shoe, [ 'Cognac', 'Black' ], 'Bestseller', false ],
	[ 'Chain-Detail Velvet Slip-On', 6500, 4900, 'executive', 'Embossed velvet-touch upper with metallic chain trim', 'chain-velvet-slipon', $shoe, [ 'Bronze', 'Black' ], 'Statement', true ],
	[ 'Gold-Bit Suede Loafer', 5500, 4100, 'executive', 'Soft suede, gold bit hardware, cushioned insole', 'gold-bit-suede-loafer', $shoe, [ 'Camel', 'Navy' ], '', true ],
	[ 'Pebble-Grain Penny Loafer', 5200, 3900, 'executive', 'Tumbled leather, hand-stitched apron, stacked heel', 'penny-loafer-burgundy', $shoe, [ 'Burgundy', 'Black' ], '', false ],
	[ 'Classic Penny Loafer', 4800, 3600, 'executive', 'Burnished leather, the office-to-weekend staple', 'penny-loafer-cognac', $shoe, [ 'Cognac', 'Dark brown' ], '', false ],
	[ 'Patent Leather Loafer', 4900, 3700, 'executive', 'High-shine patent, made for suits and evenings', 'patent-loafer-black', $shoe, [ 'Black' ], '', false ],
	[ 'Brogue Oxford', 5500, 4100, 'executive', 'Wingtip brogue detailing, lace-up, rubber-tipped sole', 'brogue-oxford-black', $shoe, [ 'Black', 'Brown' ], '', false ],
	[ 'Croc-Embossed Slip-On', 5900, 4400, 'executive', 'Croc-texture leather with a sleek almond toe', 'croc-slipon', $shoe, [ 'Mocha', 'Black' ], 'New', true ],
	[ 'Woven Driving Moccasin', 3800, 2800, 'executive', 'Supple leather, buckle strap, grip-dot driving sole', 'driving-moccasin-tan', $shoe, [ 'Tan', 'Navy' ], '', false ],
	[ 'Suede Penny Loafer', 4600, 3400, 'executive', 'Brushed suede, unlined for warm Nairobi days', 'suede-penny-brown', $shoe, [ 'Tobacco', 'Taupe' ], '', false ],

	[ 'Minimal Leather Low-Top', 3800, 2700, 'sneakers', 'Clean leather upper, padded collar, everyday sole', 'minimal-lowtop-white', $sneak, [ 'White', 'Triple black' ], 'Bestseller', false ],
	[ 'Sculpted Trainer', 6200, 4600, 'sneakers', 'Sculpted chunky sole, suede overlays. Limited pairs', 'sculpted-trainer-drop', $sneak, [ 'Stone' ], 'Limited drop', true ],
	[ 'Wave-Sole Chunky Sneaker', 4800, 3400, 'sneakers', 'Wave-moulded platform sole, strap-and-lace closure', 'chunky-sole-cream', $sneak, [ 'Cream' ], 'New', true ],
	[ 'Chunky Trainer', 4500, 3200, 'sneakers', 'Layered mesh and suede, cushioned midsole', 'chunky-trainer-sand', $sneak, [ 'Sand', 'Black' ], '', false ],
	[ 'Retro Runner', 4200, 3000, 'sneakers', 'Retro running silhouette with bold colour blocking', 'retro-runner', $sneak, [ 'Multi', 'Grey' ], '', false ],
	[ 'Everyday Trainer', 3500, 2500, 'sneakers', 'Lightweight knit-and-mesh trainer for all-day wear', 'everyday-trainer-white', $sneak, [ 'White/Grey' ], '', false ],

	[ 'Smart Blazer', 6500, 4800, 'apparel', 'Unstructured single-breasted blazer, half-lined', 'smart-blazer-look', $tops, [ 'Camel', 'Navy' ], 'New', true ],
	[ 'Utility Overshirt', 3200, 2300, 'apparel', 'Heavy cotton twill, four pockets, wear open or buttoned', 'utility-overshirt', $tops, [ 'Tan', 'Olive' ], '', false ],
	[ 'Tailored Trousers', 2800, 1900, 'apparel', 'Slim tailored fit, pressed crease, stretch waistband', 'tailored-trousers', $waist, [ 'Grey', 'Charcoal' ], '', false ],
	[ 'Slim Stretch Chinos', 2200, 1500, 'apparel', 'Cotton-stretch chinos with a tapered leg', 'slim-chinos-grey', $waist, [ 'Grey', 'Khaki', 'Navy' ], '', false ],
	[ 'Piqué Polo Shirt', 1500, 950, 'apparel', 'Breathable cotton piqué, ribbed collar and cuffs', 'pique-polo', $tops, [ 'White', 'Navy', 'Black' ], 'Bestseller', false ],
	[ 'Cable-Knit Sweater', 2600, 1800, 'apparel', 'Chunky cable knit for cool mornings and evenings', 'knit-chelsea-set', $tops, [ 'Rust', 'Brown' ], '', false ],

	[ 'Loafer Reseller Pack · 12 pairs', 36000, 0, 'wholesale', 'Four loafer styles across a full size run. KSh 3,000 a pair', 'pack-loafers', $runs, [ 'Mixed', 'Dark tones', 'Brown tones' ], 'Best value', false ],
	[ 'Sneaker Starter Pack · 12 pairs', 30000, 0, 'wholesale', 'Our fastest-moving trainers in a balanced size run. KSh 2,500 a pair', 'pack-sneakers', $runs, [ 'Mixed', 'Neutrals' ], '', false ],
	[ 'Casual Shoe Bundle · 20 pairs', 42000, 0, 'wholesale', 'Slip-ons, drivers and casual loafers for market stalls. KSh 2,100 a pair', 'pack-casual', $runs, [ 'Mixed' ], '', false ],
	[ 'Menswear Boutique Pack · 24 pieces', 26400, 0, 'wholesale', 'Shirts, chinos and polos in S–XXL. KSh 1,100 a piece', 'pack-menswear', [ 'Size spread S–XXL', 'Custom (tell us)' ], [ 'Mixed' ], '', false ],
];

$count = 0;
foreach ( $products as [ $name, $price, $whole, $cat, $desc, $photo, $sizes, $colours, $badge, $new ] ) {
	$p = new WC_Product_Simple();
	$p->set_name( $name );
	$p->set_status( 'publish' );
	$p->set_menu_order( $count++ );
	$p->set_regular_price( (string) $price );
	$p->set_short_description( $desc );
	$p->set_category_ids( [ $cat_ids[ $cat ] ] );
	$p->set_attributes( [ wj_attr( $cat === 'wholesale' ? 'Size run' : 'Size', $sizes, 0 ), wj_attr( 'Colour', $colours, 1 ) ] );
	if ( $whole ) $p->update_meta_data( '_wj_wholesale', (string) $whole );
	if ( $badge ) $p->update_meta_data( '_wj_badge', $badge );
	if ( $new ) $p->update_meta_data( '_wj_new', 'yes' );
	$p->update_meta_data( '_wj_sample', 'yes' );
	$pid = $p->save();
	$file = "/wordpress/wj-images/$photo.jpg";
	if ( file_exists( $file ) ) {
		$img = wj_sideload( $file, $pid, $name );
		if ( $img ) set_post_thumbnail( $pid, $img );
	}
}

// One-page shop: WooCommerce's own cart, checkout and account pages aren't used (functions.php redirects products).
foreach ( [ 'cart', 'checkout', 'myaccount' ] as $wc_page ) {
	$id = wc_get_page_id( $wc_page );
	if ( $id > 0 ) wp_delete_post( $id, true );
}

update_option( 'permalink_structure', '/%postname%/' );
update_option( 'woocommerce_task_list_hidden_lists', [ 'setup', 'extended' ] );
update_option( 'woocommerce_task_list_complete', 'yes' );
update_option( 'woocommerce_show_marketplace_suggestions', 'no' );
update_option( 'woocommerce_admin_install_timestamp', time() - WEEK_IN_SECONDS );

$wpdb->query( 'COMMIT' );
wp_suspend_cache_invalidation( false );
wp_defer_term_counting( false );
delete_option( 'product_cat_children' );
clean_taxonomy_cache( 'product_cat' );
_get_term_hierarchy( 'product_cat' );
flush_rewrite_rules();
echo "Wajose: $count products\n";
