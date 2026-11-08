<?php declare(strict_types = 1);

$targetPhpVersion = getenv('PHPSTAN_PHP_VERSION');
$targetPhpVersion = $targetPhpVersion !== false ? (int) $targetPhpVersion : PHP_VERSION_ID;

return [
	'parameters' => [
		'phpVersion' => $targetPhpVersion,
		'excludePaths' => [
			'analyse' => $targetPhpVersion < 80400
				? [
					__DIR__ . '/tests/cases/unit/Entity/Entity.propertyHooks.phpt',
					__DIR__ . '/tests/cases/unit/Entity/fixtures/PropertyHookEntity.php',
				]
				: [],
		],
	],
];
