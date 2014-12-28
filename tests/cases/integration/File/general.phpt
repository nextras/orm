<?php

/**
 * @testCase
 */

namespace NextrasTests\Orm\Integrations;

use Mockery;
use Nette\Caching\Storages\MemoryStorage;
use Nextras\Orm\Mapper\File\FileMapper;
use Nextras\Orm\Model\SimpleModelFactory;
use Nextras\Orm\Model\StaticModel;
use NextrasTests\Orm\Author;
use NextrasTests\Orm\AuthorsRepository;
use NextrasTests\Orm\Book;
use NextrasTests\Orm\BooksRepository;
use NextrasTests\Orm\Publisher;
use NextrasTests\Orm\PublishersRepository;
use NextrasTests\Orm\Tag;
use NextrasTests\Orm\TagFollowersRepository;
use NextrasTests\Orm\TagsRepository;
use NextrasTests\Orm\TestCase;
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
		$book3 = new Book();
		$book3->author = $orm->authors->getById(1);
		$book3->title = 'The Wall III';
		$book3->publisher = 1;
		$book3->tags->set([new Tag('Tag 1'), new Tag('Tag 2'), new Tag('Tag 3')]);

		$orm->books->persistAndFlush($book3);

		$orm = $this->createOrm();
		/** @var Author $author */
		$author = $orm->authors->findAll()->fetch();
		Assert::same('The Imp', $author->name);
		Assert::same('2000-01-01 12:12:12', $author->born->format('Y-m-d H:i:s'));
		Assert::same(3, $author->books->countStored());
		Assert::same(3, $author->books->count());
		Assert::same(1, $author->translatedBooks->count());

		/** @var Book $book */
		$book = $orm->books->findBy(['title' => 'The Wall'])->fetch();
		Assert::same($author, $book->author);
		Assert::same($author, $book->translator);
		Assert::same('Valyria', $book->publisher->name);

		$book = $orm->books->findBy(['title' => 'The Wall III'])->fetch();
		Assert::same(3, $book->tags->countStored());

		$books = [];
		foreach ($orm->tags->findAll()->fetch()->books as $book) {
			$books[] = $book->title;
		}
		Assert::same(['The Wall III'], $books);
	}


	private function createOrm()
	{
		if (!$this->files) {
			$this->files = [];
			for ($i = 0; $i < 5; $i += 1) {
				@unlink(TEMP_DIR . '/' . $i . '.data');
				$this->files[] = TEMP_DIR . '/' . $i . '.data'; // FileMock::create('');
			}
		}

		$factory = new SimpleModelFactory(new MemoryStorage(), [
			'books'        => new BooksRepository(new GenericFileMapper($this->files[0])),
			'authors'      => new AuthorsRepository(new GenericFileMapper($this->files[1])),
			'publishers'   => new PublishersRepository(new GenericFileMapper($this->files[2])),
			'tags'         => new TagsRepository(new GenericFileMapper($this->files[3])),
			'tagFollowers' => new TagFollowersRepository(new GenericFileMapper($this->files[4])),
		]);
		return $factory->create();
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
