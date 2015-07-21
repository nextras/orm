<?php

/**
 * This file is part of the Nextras\Orm library.
 * @license    MIT
 * @link       https://github.com/nextras/orm
 */

namespace NextrasTests\Orm;

/** @var Model $orm */

$orm->books->getMapper()->addMethod('findBooksWithEvenId', function() use ($orm) {
	$books = [];
	foreach ($orm->books->findAll() as $book) {
		if ($book->id % 2 === 0) {
			$books[] = $book;
		}
	}
	return $books;
});
