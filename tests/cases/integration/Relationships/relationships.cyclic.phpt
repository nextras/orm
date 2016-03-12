<?php

/**
 * @testCase
 * @dataProvider ../../../sections.ini
 */

namespace NextrasTests\Orm\Integration\Relationships;

use Mockery;
use Nextras\Orm\InvalidStateException;
use NextrasTests\Orm\DataTestCase;
use NextrasTests\Orm\Photo;
use NextrasTests\Orm\PhotoAlbum;
use Tester\Assert;

$dic = require_once __DIR__ . '/../../../bootstrap.php';


class RelationshipCyclicTest extends DataTestCase
{
	public function testCycleCheck()
	{
		$album = new PhotoAlbum();
		$album->title = 'album 1';
		$photo1 = new Photo();
		$photo1->title = 'photo 1';
		$photo1->album = $album;
		$photo2 = new Photo();
		$photo2->title = 'photo 2';
		$photo2->album = $album;
		$photo3 = new Photo();
		$photo3->title = 'photo 3';
		$photo3->album = $album;
		$album->preview = $photo2;

		Assert::throws(function () use ($album) {
			$this->orm->persist($album);
		}, InvalidStateException::class, 'Persist cycle detected in NextrasTests\Orm\Photo::$album - NextrasTests\Orm\PhotoAlbum::$preview. Use manual two phase persist.');

		Assert::throws(function () use ($photo2) {
			$this->orm->persist($photo2);
		}, InvalidStateException::class, 'Persist cycle detected in NextrasTests\Orm\PhotoAlbum::$preview - NextrasTests\Orm\Photo::$album. Use manual two phase persist.');
	}


	public function testCycleManualPersist()
	{
		$album = new PhotoAlbum();
		$album->title = 'album 1';
		$photo1 = new Photo();
		$photo1->title = 'photo 1';
		$photo1->album = $album;
		$photo2 = new Photo();
		$photo2->title = 'photo 2';
		$photo2->album = $album;
		$photo3 = new Photo();
		$photo3->title = 'photo 3';
		$photo3->album = $album;

		$this->orm->persist($album);
		$album->preview = $photo2;
		$this->orm->persist($album);

		Assert::true($album->isPersisted());
		Assert::true($photo1->isPersisted());
		Assert::true($photo2->isPersisted());
		Assert::true($photo3->isPersisted());
	}
}


$test = new RelationshipCyclicTest($dic);
$test->run();
