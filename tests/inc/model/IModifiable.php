<?php

namespace NextrasTests\Orm;


interface IModifiable
{

	/**
	 * @param callable $callable
	 */
	public function addOnModifiedListener($callable);

}
