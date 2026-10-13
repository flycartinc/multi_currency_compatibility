<?php

namespace WDRCS\App\Compatibility;

use WDR\Core\Helpers\WC;
use WDRCS\App\Currency\Providers\WoocsProvider;

defined( 'ABSPATH' ) || exit;

/**
 * Bridge for "WOOCS - WooCommerce Currency Switcher" by realmag777 (FOX).
 *
 * FOX converts a cart line's price again whenever WooCommerce reads it, and puts its own fixed / geo / user-role price back over
 * it. From FOX 1.5.5 a price can skip that (`woocs_skip_conversion`): the price Discount Rules sets stays in the visitor's
 * currency, as set (as CURCY pro). Older FOX: the base-currency hand-back.
 */
class RealMag extends Currency {
	/**
	 * Cart item prices set by Discount Rules, by product object.
	 *
	 * @var array<string, string>
	 */
	private static $prices = [];

	/**
	 * Initiates action.
	 *
	 * @return void
	 */
	function run() {
		$this->registerHooks();
		add_filter( 'wdr_discount_product_data', [ __CLASS__, 'getProductData' ], 10, 1 );
		// FOX's cache mode redraws prices in admin-ajax, before FOX's own handler (priority 10)
		foreach ( [ 'woocs_get_products_price_html', 'woocs_get_variation_products_price_html' ] as $action ) {
			add_action( 'wp_ajax_' . $action, [ __CLASS__, 'addPriceHtmlHooks' ], 1 );
			add_action( 'wp_ajax_nopriv_' . $action, [ __CLASS__, 'addPriceHtmlHooks' ], 1 );
		}
		// after Discount Rules' price HTML (priority 100)
		add_filter( 'woocommerce_get_price_html', [ __CLASS__, 'addFoxPriceHtml' ], 101, 2 );
		if ( self::keepsOurPrice() ) {
			add_filter( 'woocs_skip_conversion', [ __CLASS__, 'skipConversion' ], 10, 3 );
		}
	}

	/**
	 * FOX 1.5.5+ has the `woocs_skip_conversion` filter.
	 * ponytail: by FOX's version number — check it again on a FOX Pro zip (same plugin, its numbering not seen yet).
	 *
	 * @return bool
	 */
	private static function keepsOurPrice() {
		return defined( 'WOOCS_VERSION' ) && version_compare( WOOCS_VERSION, '1.5.5', '>=' );
	}

	protected static function convertsCartItemPrice() {
		return ! self::keepsOurPrice();
	}

	/**
	 * Remember the price Discount Rules sets on the cart item (FOX 1.5.5+: in the visitor's currency).
	 *
	 * @param int|float $price Discounted price in the visitor's currency.
	 * @param array $cart_item Cart item.
	 *
	 * @return float|int
	 */
	static function getCartConvertedPrice( $price, $cart_item ) {
		if ( self::keepsOurPrice() && ! empty( $cart_item['data'] ) && is_object( $cart_item['data'] ) ) {
			self::$prices[ spl_object_hash( $cart_item['data'] ) ] = (string) wc_format_decimal( $price );
		}

		return parent::getCartConvertedPrice( $price, $cart_item );
	}

	/**
	 * FOX converts a price (and puts a fixed / geo / user-role price over it) unless this says skip: the price Discount Rules
	 * set, as set_price stored it.
	 *
	 * @param bool $skip Whether FOX leaves the price as it is.
	 * @param mixed $price Price being read.
	 * @param \WC_Product|null $product Product.
	 *
	 * @return bool
	 */
	public static function skipConversion( $skip, $price, $product ) {
		if ( $skip || ! is_object( $product ) ) {
			return $skip;
		}

		return ( self::$prices[ spl_object_hash( $product ) ] ?? null ) === (string) $price;
	}

	protected static function provider() {
		return WoocsProvider::class;
	}

	/**
	 * WOOCS only converts cart-facing prices when one of its own "convert to current
	 * currency" flags is enabled (geoip manipulation, multiple currencies allowed, or
	 * fixed-price mode).
	 *
	 * @param \WOOCS $WOOCS Woocommerce currency switcher object.
	 *
	 * @return bool
	 */
	public static function isConvertToCurrentCurrency( \WOOCS $WOOCS ) {
		return ( isset( $WOOCS->is_geoip_manipulation ) && $WOOCS->is_geoip_manipulation )
			|| ( isset( $WOOCS->is_multiple_allowed ) && $WOOCS->is_multiple_allowed )
			|| ( isset( $WOOCS->woocs_is_fixed_enabled ) && $WOOCS->woocs_is_fixed_enabled );
	}

	/**
	 * Without one of WOOCS's "convert" flags the shop keeps showing base-currency prices, so there
	 * is nothing to convert.
	 *
	 * @return float
	 */
	protected static function getRate() {
		global $WOOCS;
		if ( ! is_object( $WOOCS ) || ! self::isConvertToCurrentCurrency( $WOOCS ) ) {
			return 1.0;
		}

		return parent::getRate();
	}

	/**
	 * WOOCS reads a dynamic coupon's amount as a plain visitor-currency value, so the amount WDR
	 * computed (already in the visitor's currency) is handed over unchanged.
	 *
	 * @return bool
	 */
	protected static function convertsCouponAmount() {
		return false;
	}

	/**
	 * FOX's display-only mode (*multiple allowed* off) keeps the cart and the order in the store currency, whatever currency it
	 * labels them with (a Store API order placed without the checkout page is saved as USD over ₹ amounts): rate 1 on such an
	 * order, so a purchase-history condition reads its amounts as they are.
	 *
	 * @param \WC_Order $order Order.
	 *
	 * @return void
	 */
	static function saveOrderRate( $order ) {
		global $WOOCS;
		if ( ! is_object( $WOOCS ) || self::isConvertToCurrentCurrency( $WOOCS ) ) {
			parent::saveOrderRate( $order );
			return;
		}
		if ( $order instanceof \WC_Order && $order->get_currency() !== WC::getStoreCurrency() ) {
			$order->update_meta_data( '_wdr_exchange_rate', 1 );
		}
	}

	/**
	 * FOX formats every price HTML at priority 1 — its wrapper that cache mode redraws, the customer price format, hide cents,
	 * price info, the approximate price. A discounted product's HTML that Discount Rules builds new at priority 100 has none of
	 * it: format it again with FOX's own method.
	 *
	 * @param string $html Price HTML.
	 * @param \WC_Product $product Product.
	 *
	 * @return string
	 */
	public static function addFoxPriceHtml( $html, $product ) {
		global $WOOCS;
		if ( '' === (string) $html || false !== strpos( (string) $html, 'woocs_price_code' ) || ! is_object( $WOOCS )
			|| ! method_exists( $WOOCS, 'woocommerce_price_html' ) ) {
			return $html;
		}

		return $WOOCS->woocommerce_price_html( $html, $product );
	}

	/**
	 * Re-fetches the product as a fresh WC_Product instance before WDR Core uses it for
	 * shop-page pricing, so WOOCS's own price filter applies cleanly on read.
	 *
	 * @param \WC_Product|int $product Product object or ID.
	 *
	 * @return \WC_Product|int
	 */
	public static function getProductData( $product ) {
		$item_id = is_object( $product ) && method_exists( $product, 'get_id' ) ? $product->get_id() : $product;

		return function_exists( 'wc_get_product' ) && ! empty( wc_get_product( $item_id ) ) ? wc_get_product( $item_id ) : $product;
	}
}
