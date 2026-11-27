<?php declare(strict_types = 1);

namespace NextrasBench;


use Nextras\Dbal\Connection;


/** Seeds N books/authors/tags (+3 tags per book, favorite authors) into the integration-test schema. */
function seedAsyncDatabase(Connection $c, int $n, bool $isPg): void
{
	if (
		(int) $c->query('SELECT COUNT(*) FROM books')->fetchField() === $n
		&& (int) $c->query('SELECT COUNT(*) FROM tags')->fetchField() === $n
		&& (int) $c->query('SELECT COUNT(*) FROM authors')->fetchField() === $n
		&& (int) $c->query('SELECT COUNT(*) FROM books_x_tags')->fetchField() === 3 * $n
	) {
		return;
	}
	$tables = [
		'books_x_tags', 'tag_followers', 'publishers_x_tags', 'contents',
		'book_collections', 'books', 'eans', 'tags', 'authors', 'publishers',
	];
	if ($isPg) {
		$c->query('TRUNCATE %table[] CASCADE', $tables);
	} else {
		$c->query('SET FOREIGN_KEY_CHECKS = 0');
		foreach ($tables as $t) $c->query('DELETE FROM %table', $t);
		$c->query('SET FOREIGN_KEY_CHECKS = 1');
	}

	$genres = ['horror', 'fantasy', 'sciFi'];
	$authors = $tags = $books = [];
	for ($i = 1; $i <= $n; $i++) {
		$authors[] = [
			'id' => $i,
			'name' => "Author $i",
			'web' => "https://example.com/$i",
			'born_on' => new \DateTimeImmutable('1970-01-01 +' . $i . ' days'),
		];
		$tags[] = ['id' => $i, 'name' => "Tag $i", 'is_global' => $i % 2 ? 'y' : 'n'];
	}
	$c->query('INSERT INTO publishers %values[]', [['publisher_id' => 1, 'name' => 'Publisher']]);
	foreach (array_chunk($authors, 500) as $chunk) $c->query('INSERT INTO authors %values[]', $chunk);
	foreach (array_chunk($tags, 500) as $chunk) $c->query('INSERT INTO tags %values[]', $chunk);
	for ($i = 1; $i <= $n; $i++) {
		$books[] = [
			'id' => $i,
			'author_id' => $i,
			'translator_id' => ($i % 3 === 0) ? (($i % $n) + 1) : null,
			'title' => "Book $i",
			'publisher_id' => 1,
			'genre' => $genres[$i % 3],
			'published_at' => new \DateTimeImmutable('2020-01-01 +' . $i . ' minutes'),
			'price' => 100 + $i,
			'price_currency' => 'CZK',
		];
	}
	foreach (array_chunk($books, 500) as $chunk) $c->query('INSERT INTO books %values[]', $chunk);
	$c->query('UPDATE authors SET favorite_author_id = MOD(id * 7, %i) + 1', $n);
	$pairs = [];
	for ($i = 1; $i <= $n; $i++) {
		foreach ([0, 1, 2] as $k) $pairs[] = ['book_id' => $i, 'tag_id' => (($i * 3 + $k * 101) % $n) + 1];
	}
	foreach (array_chunk($pairs, 500) as $chunk) $c->query('INSERT INTO books_x_tags %values[]', $chunk);
	if ($isPg) {
		foreach (['authors', 'tags', 'books'] as $t) {
			$c->query("SELECT setval(pg_get_serial_sequence('$t', 'id'), %i)", $n);
		}
	}
}
