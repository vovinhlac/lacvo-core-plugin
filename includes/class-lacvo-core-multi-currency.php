<?php
/**
 * Focused multi-currency service for the public portfolio snapshot.
 *
 * @package Lacvo_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Lacvo_Core_Multi_Currency {
	private const OPTION_KEY = 'lacvo_multi_currency_settings';
	private const COOKIE_KEY = 'lacvo_currency';
	private static ?self $instance = null;
	private ?string $selected_currency = null;

	public static function instance(): self {
		return self::$instance ??= new self();
	}

	public function register(): void {
		add_action( 'template_redirect', array( $this, 'handle_currency_switch' ), 1 );
		add_filter( 'woocommerce_product_get_price', array( $this, 'convert_product_price' ), 30, 2 );
		add_filter( 'woocommerce_product_get_regular_price', array( $this, 'convert_product_price' ), 30, 2 );
		add_filter( 'woocommerce_product_get_sale_price', array( $this, 'convert_product_price' ), 30, 2 );
		add_filter( 'woocommerce_currency', array( $this, 'filter_currency' ), 30 );
		add_action( 'woocommerce_checkout_create_order', array( $this, 'set_order_currency' ), 5, 2 );
		add_action( 'woocommerce_checkout_order_created', array( $this, 'save_order_exchange_snapshot' ), 20 );
	}

	private function settings(): array {
		$defaults = array(
			'base_currency' => 'USD',
			'currencies'    => array(
				'USD' => array( 'enabled' => 'yes', 'rate' => 1.0, 'rounding' => 0.01 ),
				'EUR' => array( 'enabled' => 'yes', 'rate' => 0.92, 'rounding' => 0.01 ),
				'GBP' => array( 'enabled' => 'yes', 'rate' => 0.78, 'rounding' => 0.01 ),
				'MYR' => array( 'enabled' => 'yes', 'rate' => 4.5, 'rounding' => 0.10 ),
				'SGD' => array( 'enabled' => 'yes', 'rate' => 1.35, 'rounding' => 0.10 ),
			),
		);
		$value = get_option( self::OPTION_KEY, array() );
		return wp_parse_args( is_array( $value ) ? $value : array(), $defaults );
	}

	public function get_selected_currency(): string {
		if ( null !== $this->selected_currency ) {
			return $this->selected_currency;
		}
		$settings = $this->settings();
		$base     = strtoupper( (string) $settings['base_currency'] );
		$chosen   = isset( $_COOKIE[ self::COOKIE_KEY ] ) ? strtoupper( sanitize_key( wp_unslash( $_COOKIE[ self::COOKIE_KEY ] ) ) ) : '';
		$currencies = (array) $settings['currencies'];
		if ( empty( $currencies[ $chosen ]['enabled'] ) || 'yes' !== $currencies[ $chosen ]['enabled'] ) {
			$chosen = $base;
		}
		$this->selected_currency = $chosen;
		return $chosen;
	}

	public function handle_currency_switch(): void {
		if ( ! isset( $_GET['lacvo_currency'] ) ) {
			return;
		}
		$nonce = isset( $_GET['_lacvo_currency_nonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_lacvo_currency_nonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'lacvo_switch_currency' ) ) {
			wp_safe_redirect( remove_query_arg( array( 'lacvo_currency', '_lacvo_currency_nonce' ) ) );
			exit;
		}
		$currency = strtoupper( sanitize_key( wp_unslash( $_GET['lacvo_currency'] ) ) );
		$settings = $this->settings();
		if ( isset( $settings['currencies'][ $currency ] ) && 'yes' === ( $settings['currencies'][ $currency ]['enabled'] ?? 'no' ) ) {
			setcookie( self::COOKIE_KEY, $currency, array( 'expires' => time() + MONTH_IN_SECONDS, 'path' => COOKIEPATH ?: '/', 'secure' => is_ssl(), 'httponly' => true, 'samesite' => 'Lax' ) );
			$this->selected_currency = $currency;
		}
		wp_safe_redirect( remove_query_arg( array( 'lacvo_currency', '_lacvo_currency_nonce' ) ) );
		exit;
	}

	public function convert_base_amount( float $amount, ?string $currency = null ): float {
		$currency = $currency ? strtoupper( $currency ) : $this->get_selected_currency();
		$rate     = $this->rate( $currency );
		$rounding = $this->rounding( $currency );
		$converted = $amount * $rate;
		return $rounding > 0 ? round( $converted / $rounding ) * $rounding : $converted;
	}

	public function convert_product_price( $price, $product ) {
		unset( $product );
		return '' === $price ? $price : $this->convert_base_amount( (float) $price );
	}

	public function filter_currency( string $currency ): string {
		unset( $currency );
		return $this->get_selected_currency();
	}

	public function set_order_currency( WC_Order $order, array $data ): void {
		unset( $data );
		$order->set_currency( $this->get_selected_currency() );
	}

	/** Store an immutable FX snapshot so historical orders do not drift with live rates. */
	public function save_order_exchange_snapshot( $order ): void {
		if ( is_numeric( $order ) ) {
			$order = wc_get_order( (int) $order );
		}
		if ( ! $order instanceof WC_Order ) {
			return;
		}

		$currency   = $order->get_currency() ?: $this->get_selected_currency();
		$rate       = $this->rate( $currency );
		$base       = $this->base_currency();
		$total      = (float) $order->get_total();
		$base_total = $rate > 0 ? $total / $rate : $total;

		$order->update_meta_data( '_lacvo_base_currency', $base );
		$order->update_meta_data( '_lacvo_exchange_rate', wc_format_decimal( $rate, 8 ) );
		$order->update_meta_data( '_lacvo_base_total', wc_format_decimal( $base_total, 4 ) );
		$order->save();
	}

	private function base_currency(): string {
		$settings = $this->settings();
		return strtoupper( (string) $settings['base_currency'] );
	}

	private function rate( string $currency ): float {
		$settings = $this->settings();
		return max( 0.00000001, (float) ( $settings['currencies'][ $currency ]['rate'] ?? 1 ) );
	}

	private function rounding( string $currency ): float {
		$settings = $this->settings();
		return max( 0, (float) ( $settings['currencies'][ $currency ]['rounding'] ?? 0.01 ) );
	}
}
