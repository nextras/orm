<?php

/**
 * @testCase
 */

namespace Nextras\Orm\Tests\Integrations;

use Mockery;
use Nette\Caching\Storages\MemoryStorage;
use Nextras\Orm\Mapper\File\FileMapper;
use Nextras\Orm\Model\StaticModel;
use Nextras\Orm\Tests\Author;
use Nextras\Orm\Tests\AuthorsRepository;
use Nextras\Orm\Tests\Book;
use Nextras\Orm\Tests\BooksRepository;
use Nextras\Orm\Tests\Publisher;
use Nextras\Orm\Tests\PublishersRepository;
use Nextras\Orm\Tests\TagFollowersRepository;
use Nextras\Orm\Tests\TagsRepository;
use Nextras\Orm\Tests\TestCase;
use Tester\Assert;

$dic = require_once __DIR__ . '/../../../bootstrap.php';


class GeneralFileTest extends TestCase
{
	private $files;


	public function testGeneral()
	{
		$orm = $this->createOrm();

		$author = new Author();
		$author->name = 'The Imp';
		$author->web = 'localhost';
		$author->born = '2000-01-01 12:12:12';

		$orm->authors->attach($author);

		$publisher = new Publisher();
		$publisher->name = 'Valyria';

		$book = new Book();
		$book->author = $author;
		$book->title = 'The Wall';
		$book->publisher = $publisher;
		$book->translator = $author;

		$book2 = new Book();
		$book2->author = $author;
		$book2->title = 'The Wall II';
		$book2->publisher = $publisher;

		$orm->authors->persistAndFlush($author);

		$orm = $this->createOrm();
		/** @var Author $author */
		$author = $orm->authors->findAll()->fetch();
		Assert::same('The Imp', $author->name);
		Assert::same('2000-01-01 12:12:12', $author->born->format('Y-m-d H:i:s'));
		Assert::same(2, $author->books->countStored());
		Assert::same(2, $author->books->count());
		Assert::same(1, $author->translatedBooks->count());

		/** @var Book $book */
		$book = $orm->books->findBy(['title' => 'The Wall'])->fetch();
		Assert::same($author, $book->author);
		Assert::same($author, $book->translator);
		Assert::same('Valyria', $book->publisher->name);
	}


	private function createOrm()
	{
		if (!$this->files) {
			$this->files = [];
			for ($i = 0; $i < 5; $i += 1) {
				$this->files[] = TEMP_DIR . '/' . $i . '.data'; // FileMock::create('');
			}
		}

		return new StaticModel([
			'books'        => new BooksRepository(new GenericFileMapper($this->files[0])),
			'authors'      => new AuthorsRepository(new GenericFileMapper($this->files[1])),
			'publishers'   => new PublishersRepository(new GenericFileMapper($this->files[2])),
			'tags'         => new TagsRepository(new GenericFileMapper($this->files[3])),
			'tagFollowers' => new TagFollowersRepository(new GenericFileMapper($this->files[4])),
		], new MemoryStorage());
	}

}


class GenericFileMapper extends FileMapper
{
	/** @var string */
	private $file;
	public function __construct($file) { $this->file = $file; }
	protected function getFileName() { return $this->file; }
}


$test = new GeneralFileTest($dic);
$test->run();
