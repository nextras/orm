<?php declare(strict_types = 1);

/**
 * @testCase
 */

namespace NextrasTests\Orm\Integration\Mapper;


use Nette\Caching\Cache;
use Nette\Caching\Storages\MemoryStorage;
use Nextras\Dbal\Utils\DateTimeImmutable;
use Nextras\Orm\Mapper\Memory\ArrayMapper;
use Nextras\Orm\Model\SimpleModelFactory;
use NextrasTests\Orm\Author;
use NextrasTests\Orm\AuthorsRepository;
use NextrasTests\Orm\Book;
use NextrasTests\Orm\BooksRepository;
use NextrasTests\Orm\CurrenciesRepository;
use NextrasTests\Orm\EansRepository;
use NextrasTests\Orm\Model;
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
	public function testGeneral(): void
	{
		/** @var Model $orm */
		$orm = $this->createOrm();

		$author = new Author();
		$author->name = 'The Imp';
		$author->web = 'localhost';
		$author->born = new DateTimeImmutable('2000-01-01');

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

		/** @var Model $orm */
		$orm = $this->createOrm();
		$book3 = new Book();
		$book3->author = $orm->authors->getByIdChecked(1);
		$book3->title = 'The Wall III';
		$book3->publisher = 1;
		$book3->tags->set([new Tag('Tag 1'), new Tag('Tag 2'), new Tag('Tag 3')]);

		$orm->books->persistAndFlush($book3);

		/** @var Model $orm */
		$orm = $this->createOrm();
		/** @var Author $author */
		$author = $orm->authors->findAll()->fetch();
		Assert::same('The Imp', $author->name);
		Assert::notNull($author->born);
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
		Assert::notNull($book);
		Assert::same(3, $book->tags->countStored());

		$books = [];
		$tag = $orm->tags->findAll()->fetch();
		Assert::notNull($tag);
		foreach ($tag->books as $innerBook) {
			$books[] = $innerBook->title;
		}
		Assert::same(['The Wall III'], $books);
	}


	/**
	 * @return \Nextras\Orm\Model\Model
	 */
	private function createOrm()
	{
		$fileName = function ($name): string {
			return TEMP_DIR . "/$name.data"; // FileMock::create('');
		};

		// @phpstan-ignore-next-line
		$factory = new SimpleModelFactory(
			new Cache(new MemoryStorage()),
			[
				// @phpstan-ignore-next-line
				'authors' => new AuthorsRepository(new TestFileMapper($fileName('authors'))),
				// @phpstan-ignore-next-line
				'books' => new BooksRepository(new TestFileMapper($fileName('books'))),
				// @phpstan-ignore-next-line
				'currencies' => new CurrenciesRepository(new TestFileMapper($fileName('currencies'))),
				// @phpstan-ignore-next-line
				'publishers' => new PublishersRepository(new TestFileMapper($fileName('publishers'))),
				// @phpstan-ignore-next-line
				'tags' => new TagsRepository(new TestFileMapper($fileName('tags'))),
				// @phpstan-ignore-next-line
				'tagFollowers' => new TagFollowersRepository(new TestFileMapper($fileName('tags'))),
				// @phpstan-ignore-next-line
				'eans' => new EansRepository(new TestFileMapper($fileName('eans'))),
			]
		);
		return $factory->create();
	}
}


/**
 * @template E of \Nextras\Orm\Entity\IEntity
 * @extends ArrayMapper<E>
 */
class TestFileMapper extends ArrayMapper
{
	/** @var string */
	private $fileName;


	public function __construct(string $fileName)
	{
		$this->fileName = $fileName;
	}


	protected function saveData(array $data): void
	{
		file_put_contents($this->fileName, serialize($data));
	}


	protected function readData(): array
	{
		$fileName = $this->fileName;
		if (!file_exists($fileName)) {
			return [];
		}
		$contents = file_get_contents($fileName);
		return unserialize($contents !== false ? $contents : '');
	}
}


$test = new FileMapperTest($dic);
$test->run();
