<?php

namespace NextrasTests\Orm;

use Nextras\Orm\Entity\Embeddable\Embeddable;


/**
 * @property-read int    $cents
 * @property-read string $currency
 */
class Money extends Embeddable
{
	const CZK = 'CZK';
	const EUR = 'EUR';
	const GBP = 'GBP';
	const USD = 'USD';
	const CURRENCIES = [self::EUR, self::CZK, self::GBP, self::USD];


	public function __construct(int $cents, string $currency)
	{
		parent::__construct([
			'cents' => $cents,
			'currency' => $currency,
		]);
	}


	protected function setterCurrency(string $currency)
	{
		assert(in_array($currency, self::CURRENCIES, TRUE));
		return $currency;
	}
}
