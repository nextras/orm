<?php

namespace Nextras\Orm\Tests;

use Nette\Configurator as NetteConfigurator;


class Configurator extends NetteConfigurator
{

	public function __construct()
	{
		parent::__construct();
		unset($this->defaultExtensions['nette']);
		$this->defaultExtensions['nette'] = 'Nextras\Orm\Tests\Extension';
	}

}
