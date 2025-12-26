<?php

namespace Diprotec\ERP\Interfaces;

interface ClientInterface {
	/**
	 * Get products from ERP.
	 *
	 * @param string|null $modified_after Timestamp to filter products.
	 * @return array
	 */
	public function getProducts( ?string $modified_after = null ): array;

	/**
	 * Get stock for a specific SKU.
	 *
	 * @param string $sku Product SKU.
	 * @return array
	 */
	public function getStock( string $sku ): array;

	/**
	 * Create an order in ERP.
	 *
	 * @param array $order_payload Order data.
	 * @return array
	 */
	public function createOrder( array $order_payload ): array;
}
