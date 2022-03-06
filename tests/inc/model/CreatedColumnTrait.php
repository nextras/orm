<?php

namespace NextrasTests\Orm;


use DateTimeImmutable;

/**
 * @property DateTimeImmutable    $createdAt             {default "2021-12-02 20:21:00"}
 * @property-read string          $createdAtFormatted    {virtual}
 */
trait CreatedColumnTrait
{

	/**
	 * Getter for column createdAtFormatted
	 * @return string
	 */
	protected function getterCreatedAtFormatted(): string
	{
		return $this->createdAt->format('d.m.Y H:i:s');
	}
}
