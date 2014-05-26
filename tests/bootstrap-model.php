<?php

use Model\AuthorsMapper;
use Model\AuthorsRepository;
use Model\BooksMapper;
use Model\BooksRepository;
use Model\TagsMapper;
use Model\TagsRepository;
use Nextras\Orm\Model\StaticModel;

require __DIR__ . '/bootstrap.php';


$model = new StaticModel([
	'authors' => new AuthorsRepository(new AuthorsMapper($context)),
	'books'   => new BooksRepository(new BooksMapper($context)),
	'tags'    => new TagsRepository(new TagsMapper($context)),
], $cacheStorage);

return $model;
