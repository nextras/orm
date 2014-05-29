<?php

namespace Nextras\Orm\Tests;

use Nette\Database\Connection;
use Nette\Database\Context;
use Nette\Database\Conventions\DiscoveredConventions;
use Nette\Database\Structure;
use Nextras\Orm\Tests\AuthorsMapper;
use Nextras\Orm\Tests\AuthorsRepository;
use Nextras\Orm\Tests\BooksMapper;
use Nextras\Orm\Tests\BooksRepository;
use Nextras\Orm\Tests\TagsMapper;
use Nextras\Orm\Tests\TagsRepository;
use Nextras\Orm\Model\StaticModel;

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/model/author/Person.php';
require_once __DIR__ . '/model/author/Author.php';
require_once __DIR__ . '/model/author/AuthorsMapper.php';
require_once __DIR__ . '/model/author/AuthorsRepository.php';
require_once __DIR__ . '/model/book/Book.php';
require_once __DIR__ . '/model/book/BooksMapper.php';
require_once __DIR__ . '/model/book/BooksRepository.php';
require_once __DIR__ . '/model/tag/Tag.php';
require_once __DIR__ . '/model/tag/TagsMapper.php';
require_once __DIR__ . '/model/tag/TagsRepository.php';


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
