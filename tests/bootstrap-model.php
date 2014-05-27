<?php

namespace Nextras\Orm\Tests;

use Nette\Database\Connection;
use Nette\Database\Context;
use Nette\Database\Conventions\DiscoveredConventions;
use Nette\Database\Structure;
use Model\AuthorsMapper;
use Model\AuthorsRepository;
use Model\BooksMapper;
use Model\BooksRepository;
use Model\TagsMapper;
use Model\TagsRepository;
use Nextras\Orm\Model\StaticModel;

require __DIR__ . '/bootstrap.php';


$connection   = new Connection('mysql:host=localhost;dbname=nextras_orm_test', 'root', '', ['lazy' => TRUE]);
$structure    = new Structure($connection, $cacheStorage);
$conventions  = new DiscoveredConventions($structure);
$context      = new Context($connection, $structure, $conventions, $cacheStorage);


$model = new StaticModel([
	'authors' => new AuthorsRepository(new AuthorsMapper($context)),
	'books'   => new BooksRepository(new BooksMapper($context)),
	'tags'    => new TagsRepository(new TagsMapper($context)),
], $cacheStorage);

return $model;
