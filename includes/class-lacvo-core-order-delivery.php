<?php
/**
 * Idempotent WooCommerce fulfilment from encrypted inventory.
 *
 * @package Lacvo_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Lacvo_Core_Order_Delivery {
	private Lacvo_Core_License_Repository $repository;

	public function __construct( Lacvo_Core_License_Repository $repository ) {
		$this->repository = $repository;
	}

	public function register(): void {
		add_action( 'woocommerce_order_status_processing', array( $this, 'fulfil_order' ), 5 );
		add_action( 'woocommerce_order_status_completed', array( $this, 'fulfil_order' ), 5 );
		add_action( 'woocommerce_order_item_meta_end', array( $this, 'render_customer_codes' ), 10, 4 );
	}

	/** Assign codes once even if WooCommerce status hooks are fired repeatedly. */
	public function fulfil_order( int $order_id ): void {
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return;
		}

		foreach ( $order->get_items( 'line_item' ) as $item_id => $item ) {
			if ( ! $item instanceof WC_Order_Item_Product || 'yes' === $item->get_meta( '_lacvo_delivered' ) ) {
				continue;
			}

			$product = $item->get_product();
			if ( ! $product ) {
				continue;
			}

			$parent_id = $product->is_type( 'variation' ) ? $product->get_parent_id() : $product->get_id();
			$parent    = wc_get_product( $parent_id );
			$source    = 'yes' === $product->get_meta( '_lacvo_digital_delivery_enabled' ) ? $product : $parent;

			if ( ! $source || 'yes' !== $source->get_meta( '_lacvo_digital_delivery_enabled' ) || 'auto_code' !== $source->get_meta( '_lacvo_delivery_mode' ) ) {
				continue;
			}

			$variation_id = $product->is_type( 'variation' ) ? $product->get_id() : 0;
			$quantity     = max( 1, (int) $item->get_quantity() );
			$codes        = $this->repository->allocate( $parent_id, $variation_id, $order_id, (int) $item_id, $quantity );

			if ( count( $codes ) < $quantity ) {
				$order->add_order_note(
					sprintf(
						__( 'Digital delivery is waiting for inventory for “%1$s” (%2$d codes required).', 'lacvo-core' ),
						$item->get_name(),
						$quantity
					)
				);
				continue;
			}

			$item->update_meta_data( '_lacvo_delivered', 'yes' );
			$item->save();
			$order->add_order_note(
				sprintf(
					_n( 'Assigned %1$d code to “%2$s”.', 'Assigned %1$d codes to “%2$s”.', count( $codes ), 'lacvo-core' ),
					count( $codes ),
					$item->get_name()
				)
			);
		}
	}

	/** Display assigned codes only to the order owner or shop managers. */
	public function render_customer_codes( int $item_id, WC_Order_Item_Product $item, WC_Order $order, bool $plain_text ): void {
		unset( $item );
		$can_view = current_user_can( 'manage_woocommerce' )
			|| ( get_current_user_id() > 0 && (int) $order->get_customer_id() === get_current_user_id() );
		if ( ! $can_view && ! doing_action( 'woocommerce_email_order_details' ) ) {
			return;
		}

		$codes = $this->repository->for_order_item( $item_id );
		if ( ! $codes ) {
			return;
		}

		if ( $plain_text ) {
			foreach ( $codes as $code ) {
				echo esc_html( $code['code'] ) . "\n";
			}
			return;
		}

		echo '<div class="lacvo-order-codes"><strong>' . esc_html__( 'License code', 'lacvo-core' ) . '</strong>';
		foreach ( $codes as $code ) {
			echo '<code>' . esc_html( $code['code'] ) . '</code>';
		}
		echo '</div>';
	}
}
