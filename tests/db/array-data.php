<?php declare(strict_types = 1);

namespace NextrasTests\Orm;


use Nextras\Dbal\Utils\DateTimeImmutable;


/** @var Model $orm */

$author1 = new Author();
$author1->name = 'Writer 1';
$author1->web = 'http://example.com/1';
$orm->authors->persist($author1);

$author2 = new Author();
$author2->name = 'Writer 2';
$author2->web = 'http://example.com/2';
$orm->authors->persist($author2);

$publisher1 = new Publisher();
$publisher1->name = 'Nextras publisher A';
$orm->publishers->persist($publisher1);

$publisher2 = new Publisher();
$publisher2->name = 'Nextras publisher B';
$orm->publishers->persist($publisher2);

$publisher3 = new Publisher();
$publisher3->name = 'Nextras publisher C';
$orm->publishers->persist($publisher3);

$tag1 = new Tag('Tag 1');
$tag2 = new Tag('Tag 2');
$tag3 = new Tag('Tag 3');
$tag3->setReadOnlyValue('isGlobal', false);
$orm->tags->persist($tag1);
$orm->tags->persist($tag2);
$orm->tags->persist($tag3);

$currencyCZK = new Currency('CZK', 'Ceska koruna');
$orm->persistAndFlush($currencyCZK);
$currencyEUR = new Currency('EUR', 'Euro');
$orm->persistAndFlush($currencyEUR);

$book1 = new Book();
$book1->title = 'Book 1';
$book1->author = $author1;
$book1->translator = $author1;
$book1->publisher = $publisher1;
$book1->publishedAt = new \DateTimeImmutable('2017-04-20 20:00:00');
$book1->price = new Money(50, $currencyCZK);
$book1->tags->set([$tag1, $tag2]);
$orm->books->persist($book1);

$book2 = new Book();
$book2->title = 'Book 2';
$book2->author = $author1;
$book2->publisher = $publisher2;
$book2->publishedAt = new \DateTimeImmutable('2017-04-20 18:00:00');
$book2->price = new Money(150, $currencyCZK);
$book2->tags->set([$tag2, $tag3]);
$orm->books->persist($book2);

$book3 = new Book();
$book3->title = 'Book 3';
$book3->author = $author2;
$book3->translator = $author2;
$book3->publisher = $publisher3;
$book3->publishedAt = new \DateTimeImmutable('2017-04-20 19:00:00');
$book3->price = new Money(20, $currencyCZK);
$book3->tags->set([$tag3]);
$orm->books->persist($book3);

$book4 = new Book();
$book4->title = 'Book 4';
$book4->author = $author2;
$book4->translator = $author2;
$book4->publisher = $publisher1;
$book4->nextPart = $book3;
$book4->publishedAt = new \DateTimeImmutable('2017-04-20 17:00:00');
$book4->price = new Money(220, $currencyCZK);
$orm->books->persist($book4);

$tagFollower1 = new TagFollower();
$tagFollower1->tag = $tag1;
$tagFollower1->author = $author1;
$tagFollower1->createdAt = '2014-01-01 01:10:00'; // 00:10:00 in UTC
$orm->tagFollowers->persist($tagFollower1);

$tagFollower2 = new TagFollower();
$tagFollower2->tag = $tag3;
$tagFollower2->author = $author1;
$tagFollower2->createdAt = '2014-01-02 01:10:00'; // 00:10:00 in UTC
$orm->tagFollowers->persist($tagFollower2);

$tagFollower3 = new TagFollower();
$tagFollower3->tag = $tag2;
$tagFollower3->author = $author2;
$tagFollower3->createdAt = '2014-01-03 01:10:00'; // 01:10:00 in UTC
$orm->tagFollowers->persist($tagFollower3);

$thread = new Thread();
$orm->contents->persist($thread);

$comment1 = new Comment();
$comment1->thread = $thread;
$comment1->repliedAt = new DateTimeImmutable('2020-01-01 12:00:00');
$orm->contents->persist($comment1);

$comment2 = new Comment();
$comment2->thread = $thread;
$comment2->repliedAt = new DateTimeImmutable('2020-01-02 12:00:00');
$orm->contents->persist($comment2);

$orm->flush();
$orm->clear();
