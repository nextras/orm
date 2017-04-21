<?php declare(strict_types = 1);

namespace NextrasTests\Orm;

use Nextras\Orm\Entity\Entity;
use Nextras\Orm\Relationships\ManyHasMany as MHM;
use Nextras\Orm\Relationships\OneHasMany as OHM;


/**
 * @property int               $id           {primary}
 * @property string            $name
 * @property MHM|Book[]        $books        {m:m Book::$tags}
 * @property OHM|TagFollower[] $tagFollowers {1:m TagFollower::$tag, cascade=[persist, remove]}
 * @property bool              $isGlobal     {default true}
 */
final class Tag extends Entity
{
	public function __construct($name = NULL)
	{
		parent::__construct();
		if ($name) {
			$this->name = $name;
		}
	}
}
