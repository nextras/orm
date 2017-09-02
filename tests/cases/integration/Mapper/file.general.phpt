<?php declare(strict_types = 1);

/**
 * @testCase
 */

namespace NextrasTests\Orm\Integration\Mapper;

use Nette\Caching\Cache;
use Nette\Caching\Storages\MemoryStorage;
use Nextras\Orm\Mapper\Memory\ArrayMapper;
use Nextras\Orm\Model\SimpleModelFactory;
use NextrasTests\Orm\Author;
use NextrasTests\Orm\AuthorsRepository;
use NextrasTests\Orm\Book;
use NextrasTests\Orm\BooksRepository;
use NextrasTests\Orm\EansRepository;
use NextrasTests\Orm\Publisher;
use NextrasTests\Orm\PublishersRepository;
use NextrasTests\Orm\Tag;
use NextrasTests\Orm\TagFollowersRepository;
use NextrasTests\Orm\TagsRepository;
use NextrasTests\Orm\TestCase;
use Tester\Assert;


$dic = require_once __DIR__ . '/../../../bootstrap.php';


class FileMapperTest extends TestCase
{
	public function testGeneral()
	{
		$orm = $this->createOrm();

		$author = new Author();
		$author->name = 'The Imp';
		$author->web = 'localhost';
		$author->born = '2000-01-01';

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
		Assert::same('2000-01-01', $author->born->format('Y-m-d'));
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
		$fileName = function ($name) {
			return TEMP_DIR . "/$name.data"; // FileMock::create('');
		};

		$factory = new SimpleModelFactory(new Cache(new MemoryStorage()), [
			'books'        => new BooksRepository(new TestFileMapper($fileName('books'))),
			'authors'      => new AuthorsRepository(new TestFileMapper($fileName('authors'))),
			'publishers'   => new PublishersRepository(new TestFileMapper($fileName('publishers'))),
			'tags'         => new TagsRepository(new TestFileMapper($fileName('tags'))),
			'tagFollowers' => new TagFollowersRepository(new TestFileMapper($fileName('tags'))),
			'eans'         => new EansRepository(new TestFileMapper($fileName('eans'))),
		]);
		return $factory->create();
	}
}


class TestFileMapper extends ArrayMapper
{
	/** @var string */
	private $fileName;


	public function __construct($fileName)
	{
		$this->fileName = $fileName;
	}


	protected function saveData(array $data)
	{
		file_put_contents($this->fileName, serialize($data));
	}


	protected function readData(): array
	{
		$fileName = $this->fileName;
		if (!file_exists($fileName)) {
			return [];
		}
		return unserialize(file_get_contents($fileName));
	}
}


$test = new FileMapperTest($dic);
$test->run();
