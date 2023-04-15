<?php declare(strict_types = 1);

namespace NextrasTests\Orm;


use Nextras\Orm\Entity\Embeddable\Embeddable;


/**
 * @property-read int $cents
 * @property-read Currency $currency
 */
class Money extends Embeddable
{
	public function __construct(int $cents, Currency $currency)
	{
		parent::__construct([
			'cents' => $cents,
			'currency' => $currency,
		]);
	}
}
