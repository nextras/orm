<?php declare(strict_types = 1);

namespace NextrasTests\Orm;


use Tester\Environment;


require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/inc/Helper.php';

define('TEMP_DIR', __DIR__ . '/tmp');
date_default_timezone_set('Europe/Prague');

Environment::setup();


if (Helper::isRunByRunner()) {
	header('Content-type: text/plain');
	putenv('ANSICON=TRUE');
}
