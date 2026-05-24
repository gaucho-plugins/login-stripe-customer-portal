<?php
/**
 * Thin wrapper around the bundled Stripe SDK for the operations LSCP needs:
 *  - Look up a customer by email.
 *  - Optionally create a new customer.
 *  - Create a Billing Portal Session and return its URL.
 *
 * Encapsulating these here lets the integration suite stub Stripe responses
 * via a single seam rather than monkey-patching the SDK in every test.
 *
 * @package LSCP
 */

declare( strict_types = 1 );

namespace LSCP;

if ( ! defined( 'ABSPATH' ) && ! defined( 'LSCP_TEST_MODE' ) ) {
	exit;
}

final class StripeGateway {

	/** @var string */
	private $api_key;

	/** @var callable|null Injected for tests; null means use the real SDK. */
	private $http_handler;

	public function __construct( string $api_key, ?callable $http_handler = null ) {
		$this->api_key      = $api_key;
		$this->http_handler = $http_handler;
	}

	public function has_api_key(): bool {
		return '' !== trim( $this->api_key );
	}

	/**
	 * Look up the first customer with the given email.
	 *
	 * @param string $email
	 * @return string|null Customer ID, or null if no match.
	 *
	 * @throws StripeGatewayException On API error.
	 */
	public function find_customer_id( string $email ): ?string {
		$this->ensure_key();
		$this->set_sdk_key();

		try {
			$customers = $this->call(
				'customer.list',
				function () use ( $email ) {
					return \LSCP\Stripe\Customer::all(
						array(
							'email' => $email,
							'limit' => 1,
						)
					);
				}
			);
		} catch ( \Throwable $e ) {
			// Exception message is never echoed — it is wrapped and surfaced via the
			// PortalController which uses esc_html__() for the user-facing wp_die.
			throw new StripeGatewayException( 'Stripe customer lookup failed: ' . $e->getMessage(), 0, $e ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
		}

		$data = $this->extract_list_data( $customers );
		if ( empty( $data ) ) {
			return null;
		}
		return isset( $data[0]->id ) ? (string) $data[0]->id : null;
	}

	/**
	 * Create a customer with the given email and return its ID.
	 *
	 * @throws StripeGatewayException
	 */
	public function create_customer( string $email ): string {
		$this->ensure_key();
		$this->set_sdk_key();

		try {
			$customer = $this->call(
				'customer.create',
				function () use ( $email ) {
					return \LSCP\Stripe\Customer::create( array( 'email' => $email ) );
				}
			);
		} catch ( \Throwable $e ) {
			// Exception message is never echoed — see find_customer_id for the
			// rendering policy.
			throw new StripeGatewayException( 'Stripe customer create failed: ' . $e->getMessage(), 0, $e ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
		}

		if ( ! isset( $customer->id ) ) {
			throw new StripeGatewayException( 'Stripe customer create returned no id' );
		}
		return (string) $customer->id;
	}

	/**
	 * Create a Billing Portal Session and return its hosted URL.
	 *
	 * @throws StripeGatewayException
	 */
	public function create_portal_session( string $customer_id, string $return_url ): string {
		$this->ensure_key();
		$this->set_sdk_key();

		if ( '' === $customer_id ) {
			throw new StripeGatewayException( 'Cannot create portal session: empty customer id' );
		}

		try {
			$session = $this->call(
				'billing_portal.session.create',
				function () use ( $customer_id, $return_url ) {
					return \LSCP\Stripe\BillingPortal\Session::create(
						array(
							'customer'   => $customer_id,
							'return_url' => $return_url,
						)
					);
				}
			);
		} catch ( \Throwable $e ) {
			// Exception message is never echoed — see find_customer_id for the
			// rendering policy.
			throw new StripeGatewayException( 'Stripe portal session failed: ' . $e->getMessage(), 0, $e ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
		}

		if ( empty( $session->url ) ) {
			throw new StripeGatewayException( 'Stripe portal session returned no url' );
		}
		return (string) $session->url;
	}

	private function ensure_key(): void {
		if ( ! $this->has_api_key() ) {
			throw new StripeGatewayException( 'Stripe API key is not configured' );
		}
	}

	private function set_sdk_key(): void {
		if ( class_exists( '\\LSCP\\Stripe\\Stripe' ) ) {
			\LSCP\Stripe\Stripe::setApiKey( $this->api_key );
		}
	}

	/**
	 * Test seam: if a handler was injected, route the call through it,
	 * otherwise execute the real SDK closure.
	 *
	 * @param string   $op
	 * @param callable $real
	 * @return mixed
	 */
	private function call( string $op, callable $real ) {
		if ( null !== $this->http_handler ) {
			return ( $this->http_handler )( $op );
		}
		return $real();
	}

	/**
	 * Normalize the data array from a Stripe collection response — supports
	 * both real SDK objects (which expose ->data as ArrayObject) and our
	 * stub fixtures (plain arrays / stdClass).
	 *
	 * @param mixed $collection
	 * @return array
	 */
	private function extract_list_data( $collection ): array {
		if ( is_array( $collection ) ) {
			return $collection;
		}
		if ( is_object( $collection ) && isset( $collection->data ) ) {
			$data = $collection->data;
			if ( is_array( $data ) ) {
				return $data;
			}
			if ( $data instanceof \Traversable ) {
				return iterator_to_array( $data, false );
			}
		}
		return array();
	}
}
