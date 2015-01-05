<?php

/**
 * This file is part of the Nextras\ORM library.
 *
 * @license    MIT
 * @link       https://github.com/nextras/orm
 * @author     Jan Skrasek
 */

namespace NextrasTests\Orm;

/** @var Model $orm */

$author1 = new Author();
$author1->name = 'Writer 1';
$author1->web = 'http://example.com/1';
$orm->authors->persist($author1);

$author2 = new Author();
$author2->name = 'Writer 2';
$author2->name = 'http://example.com/2';
$orm->authors->persist($author2);

$publisher = new Publisher();
$publisher->name = 'Nextras publisher';
$orm->publishers->persist($publisher);

$tag1 = new Tag('Tag 1');
$tag2 = new Tag('Tag 2');
$tag3 = new Tag('Tag 3');
$orm->tags->persist($tag1);
$orm->tags->persist($tag2);
$orm->tags->persist($tag3);

$book1 = new Book();
$book1->title = 'Book 1';
$book1->author = $author1;
$book1->translator = $author1;
$book1->publisher = $publisher;
$book1->tags->set([$tag1, $tag2]);
$orm->books->persist($book1);

$book2 = new Book();
$book2->title = 'Book 2';
$book2->author = $author1;
$book2->publisher = $publisher;
$book2->tags->set([$tag2, $tag3]);
$orm->books->persist($book2);

$book3 = new Book();
$book3->title = 'Book 3';
$book3->author = $author2;
$book3->translator = $author2;
$book3->publisher = $publisher;
$book3->tags->set([$tag3]);
$orm->books->persist($book3);

$book4 = new Book();
$book4->title = 'Book 4';
$book4->author = $author2;
$book4->translator = $author2;
$book4->publisher = $publisher;
$book4->previousPart = $book3;
$orm->books->persist($book4);

$tagFollower1 = new TagFollower();
$tagFollower1->tag = $tag1;
$tagFollower1->author = $author1;
$tagFollower1->createdAt = '2014-01-01 00:10:00';
$orm->tagFollowers->persist($tagFollower1);

$tagFollower2 = new TagFollower();
$tagFollower2->tag = $tag3;
$tagFollower2->author = $author1;
$tagFollower2->createdAt = '2014-01-01 00:10:00';
$orm->tagFollowers->persist($tagFollower2);

$tagFollower3 = new TagFollower();
$tagFollower3->tag = $tag2;
$tagFollower3->author = $author2;
$tagFollower3->createdAt = '2014-01-01 00:10:00';
$orm->tagFollowers->persist($tagFollower3);

$orm->flush();
