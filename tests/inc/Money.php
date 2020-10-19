<?php declare(strict_types = 1);

namespace NextrasTests\Orm;


use Nextras\Orm\Entity\Embeddable\Embeddable;
use function assert;
use function is_string;


/**
 * @property-read int $cents
 * @property-read Currency $currency {m:1 Currency, oneSided=true}
 */
class Money extends Embeddable
{
	/**
	 * @param Currency|string $currency
	 */
	public function __construct(int $cents, $currency)
	{
		assert(is_string($currency) || $currency instanceof Currency);
		parent::__construct([
			'cents' => $cents,
			'currency' => $currency,
		]);
	}
}
