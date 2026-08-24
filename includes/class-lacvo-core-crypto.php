<?php
/**
 * Encryption for license codes at rest.
 *
 * @package Lacvo_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Lacvo_Core_Crypto {
	private const CIPHER = 'aes-256-gcm';

	/** Encrypt a code using the site's authentication salt. */
	public static function encrypt( string $value ): string {
		if ( ! function_exists( 'openssl_encrypt' ) ) {
			throw new RuntimeException( 'OpenSSL is required to encrypt license codes.' );
		}

		$key        = hash( 'sha256', wp_salt( 'auth' ), true );
		$iv_length  = openssl_cipher_iv_length( self::CIPHER );
		$iv         = random_bytes( $iv_length );
		$tag        = '';
		$ciphertext = openssl_encrypt( $value, self::CIPHER, $key, OPENSSL_RAW_DATA, $iv, $tag );

		if ( false === $ciphertext ) {
			throw new RuntimeException( 'Unable to encrypt the license code.' );
		}

		return 'v1:' . base64_encode( $iv . $tag . $ciphertext );
	}

	/** Decrypt a stored code. */
	public static function decrypt( string $payload ): string {
		if ( 0 !== strpos( $payload, 'v1:' ) || ! function_exists( 'openssl_decrypt' ) ) {
			return '';
		}

		$decoded = base64_decode( substr( $payload, 3 ), true );
		if ( false === $decoded ) {
			return '';
		}

		$iv_length = openssl_cipher_iv_length( self::CIPHER );
		$tag_length = 16;
		if ( strlen( $decoded ) <= $iv_length + $tag_length ) {
			return '';
		}

		$iv         = substr( $decoded, 0, $iv_length );
		$tag        = substr( $decoded, $iv_length, $tag_length );
		$ciphertext = substr( $decoded, $iv_length + $tag_length );
		$key        = hash( 'sha256', wp_salt( 'auth' ), true );
		$plaintext  = openssl_decrypt( $ciphertext, self::CIPHER, $key, OPENSSL_RAW_DATA, $iv, $tag );

		return false === $plaintext ? '' : $plaintext;
	}

	/** Build a non-reversible duplicate-detection hash. */
	public static function fingerprint( string $value ): string {
		return hash_hmac( 'sha256', $value, wp_salt( 'secure_auth' ) );
	}
}
