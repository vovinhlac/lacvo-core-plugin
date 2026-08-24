<?php
/**
 * Database operations for license inventory.
 *
 * @package Lacvo_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Lacvo_Core_License_Repository {
	private string $table;

	public function __construct() {
		global $wpdb;
		$this->table = $wpdb->prefix . 'lacvo_license_codes';
	}

	/**
	 * Add unique codes to inventory.
	 *
	 * @param int      $product_id   Parent or simple product ID.
	 * @param int      $variation_id Optional variation ID.
	 * @param string[] $codes        Plaintext codes.
	 * @return array{created:int,duplicates:int,invalid:int}
	 */
	public function import( int $product_id, int $variation_id, array $codes ): array {
		global $wpdb;
		$result = array( 'created' => 0, 'duplicates' => 0, 'invalid' => 0 );

		foreach ( $codes as $code ) {
			$code = trim( $code );
			if ( '' === $code || strlen( $code ) > 2048 || preg_match( '/[\x00-\x08\x0B\x0C\x0E-\x1F]/', $code ) ) {
				++$result['invalid'];
				continue;
			}

			$hash = Lacvo_Core_Crypto::fingerprint( $code );
			$exists = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$this->table} WHERE code_hash = %s LIMIT 1", $hash ) );
			if ( $exists ) {
				++$result['duplicates'];
				continue;
			}

			try {
				$encrypted = Lacvo_Core_Crypto::encrypt( $code );
			} catch ( RuntimeException $exception ) {
				++$result['invalid'];
				continue;
			}

			$inserted = $wpdb->insert(
				$this->table,
				array(
					'product_id'     => $product_id,
					'variation_id'   => $variation_id,
					'code_encrypted' => $encrypted,
					'code_hash'      => $hash,
					'status'         => 'available',
					'created_at'     => current_time( 'mysql', true ),
				),
				array( '%d', '%d', '%s', '%s', '%s', '%s' )
			);

			if ( $inserted ) {
				++$result['created'];
			} else {
				++$result['duplicates'];
			}
		}
		return $result;
	}

	/** Atomically reserve one or more codes for an order item. */
	public function allocate( int $product_id, int $variation_id, int $order_id, int $order_item_id, int $quantity ): array {
		global $wpdb;
		$lock_name = 'lacvo_item_' . $order_item_id;
		$has_lock  = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT GET_LOCK(%s, 5)', $lock_name ) );
		if ( 1 !== $has_lock ) {
			return $this->for_order_item( $order_item_id );
		}

		try {
			$existing = $this->for_order_item( $order_item_id );
			if ( count( $existing ) >= $quantity ) {
				return array_slice( $existing, 0, $quantity );
			}

			$allocated = $existing;
			$needed    = max( 0, $quantity - count( $allocated ) );
			$wpdb->query( 'START TRANSACTION' );

			for ( $index = 0; $index < $needed; $index++ ) {
				$row = $wpdb->get_row(
					$wpdb->prepare(
						"SELECT id, code_encrypted
						FROM {$this->table}
						WHERE product_id = %d
							AND variation_id IN (%d, 0)
							AND status = 'available'
						ORDER BY CASE WHEN variation_id = %d THEN 0 ELSE 1 END, id ASC
						LIMIT 1
						FOR UPDATE",
						$product_id,
						$variation_id,
						$variation_id
					),
					ARRAY_A
				);

				if ( ! $row ) {
					throw new RuntimeException( 'No available codes.' );
				}

				$updated = $wpdb->update(
					$this->table,
					array(
						'status'        => 'assigned',
						'order_id'      => $order_id,
						'order_item_id' => $order_item_id,
						'assigned_at'   => current_time( 'mysql', true ),
					),
					array( 'id' => (int) $row['id'], 'status' => 'available' ),
					array( '%s', '%d', '%d', '%s' ),
					array( '%d', '%s' )
				);

				if ( 1 !== $updated ) {
					throw new RuntimeException( 'The selected code could not be reserved.' );
				}

				$plaintext = Lacvo_Core_Crypto::decrypt( (string) $row['code_encrypted'] );
				if ( '' === $plaintext ) {
					throw new RuntimeException( 'The selected code could not be decrypted.' );
				}

				$allocated[] = array( 'id' => (int) $row['id'], 'code' => $plaintext );
			}

			$wpdb->query( 'COMMIT' );
			return $allocated;
		} catch ( Throwable $exception ) {
			$wpdb->query( 'ROLLBACK' );
			return $this->for_order_item( $order_item_id );
		} finally {
			$wpdb->get_var( $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', $lock_name ) );
		}
	}

	/** Get decrypted assignments for an order item. */
	public function for_order_item( int $order_item_id ): array {
		global $wpdb;
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id, code_encrypted FROM {$this->table} WHERE order_item_id = %d AND status = 'assigned' ORDER BY id ASC",
				$order_item_id
			),
			ARRAY_A
		);
		return $this->decrypt_rows( $rows ?: array() );
	}

	/** Get all assignments for an order grouped by item ID. */
	public function for_order( int $order_id ): array {
		global $wpdb;
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id, order_item_id, code_encrypted FROM {$this->table} WHERE order_id = %d AND status = 'assigned' ORDER BY order_item_id, id ASC",
				$order_id
			),
			ARRAY_A
		);
		$result = array();
		foreach ( $rows ?: array() as $row ) {
			$item_id = (int) $row['order_item_id'];
			$code = Lacvo_Core_Crypto::decrypt( (string) $row['code_encrypted'] );
			if ( '' !== $code ) {
				$result[ $item_id ][] = array( 'id' => (int) $row['id'], 'code' => $code );
			}
		}
		return $result;
	}

	/** Inventory totals for the admin screen. */
	public function inventory_summary(): array {
		global $wpdb;
		$rows = $wpdb->get_results(
			"SELECT product_id, variation_id,
				SUM(CASE WHEN status = 'available' THEN 1 ELSE 0 END) AS available,
				SUM(CASE WHEN status = 'assigned' THEN 1 ELSE 0 END) AS assigned,
				COUNT(*) AS total
			FROM {$this->table}
			GROUP BY product_id, variation_id
			ORDER BY product_id DESC, variation_id ASC",
			ARRAY_A
		);
		return $rows ?: array();
	}

	private function decrypt_rows( array $rows ): array {
		$result = array();
		foreach ( $rows as $row ) {
			$code = Lacvo_Core_Crypto::decrypt( (string) $row['code_encrypted'] );
			if ( '' !== $code ) {
				$result[] = array( 'id' => (int) $row['id'], 'code' => $code );
			}
		}
		return $result;
	}
}
