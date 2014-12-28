<?php

namespace NextrasTests\Orm;

use Nette\Configurator as NetteConfigurator;


class Configurator extends NetteConfigurator
{

	public function __construct()
	{
		parent::__construct();
		$this->defaultExtensions['nette'] = 'NextrasTests\Orm\Extension';
	}

}
