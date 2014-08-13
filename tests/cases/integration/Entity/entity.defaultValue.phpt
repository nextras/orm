<?php

namespace Nextras\Orm\Tests\Integrations;

use Mockery;
use Nextras\Orm\Tests\Author;
use Nextras\Orm\Tests\TestCase;
use Tester\Assert;

$dic = require_once __DIR__ . '/../../../bootstrap.php';


/**
 * @testCase
 */
class EntityDefaultValueTest extends TestCase
{

	public function testGetValue()
	{
		/** @var Author $author */
		$author = $this->e('Nextras\Orm\Tests\Author');
		Assert::same('http://www.example.com', $author->web);

		$author->web = 'http://www.nextras.cz';
		Assert::same('http://www.nextras.cz', $author->web);
	}


	public function testSetValue()
	{
		/** @var Author $author */
		$author = $this->e('Nextras\Orm\Tests\Author');
		$author->web = 'http://www.nextras.cz';

		Assert::same('http://www.nextras.cz', $author->web);
	}


	public function testSetNULLValue()
	{
		/** @var Author $author */
		$author = $this->e('Nextras\Orm\Tests\Author');
		Assert::type('Nette\Utils\DateTime', $author->born);

		$author->born = NULL;
		Assert::null($author->born);
	}


	public function testGetRawValue()
	{
		/** @var Author $author */
		$author = $this->e('Nextras\Orm\Tests\Author');
		Assert::same('http://www.example.com', $author->getRawValue('web'));
	}


	public function testGetProperty()
	{
		/** @var Author $author */
		$author = $this->e('Nextras\Orm\Tests\Author');
		Assert::same('http://www.example.com', $author->getProperty('web'));
	}

}


$test = new EntityDefaultValueTest($dic);
$test->run();
