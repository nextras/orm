<?php declare(strict_types = 1);

namespace NextrasTests\Orm\PHPStan;


use PhpParser\Node\Expr\StaticCall;
use PHPStan\Analyser\Scope;
use PHPStan\Analyser\SpecifiedTypes;
use PHPStan\Analyser\TypeSpecifier;
use PHPStan\Analyser\TypeSpecifierAwareExtension;
use PHPStan\Analyser\TypeSpecifierContext;
use PHPStan\Reflection\MethodReflection;
use PHPStan\ShouldNotHappenException;
use PHPStan\Type\StaticMethodTypeSpecifyingExtension;
use Tester\Assert;
use function count;


class AssertTypeSpecifyingExtension implements StaticMethodTypeSpecifyingExtension, TypeSpecifierAwareExtension
{
	/** @var TypeSpecifier */
	private $typeSpecifier;


	public function setTypeSpecifier(TypeSpecifier $typeSpecifier): void
	{
		$this->typeSpecifier = $typeSpecifier;
	}


	public function getClass(): string
	{
		return Assert::class;
	}


	public function isStaticMethodSupported(
		MethodReflection $staticMethodReflection,
		StaticCall $node,
		TypeSpecifierContext $context
	): bool
	{
		static $methods = [
			'type',
			'notnull',
		];
		return in_array(strtolower($staticMethodReflection->getName()), $methods, true);
	}


	public function specifyTypes(
		MethodReflection $staticMethodReflection,
		StaticCall $node,
		Scope $scope,
		TypeSpecifierContext $context
	): SpecifiedTypes
	{
		$name = strtolower($staticMethodReflection->getName());
		if ($name === 'notnull') {
			$expression = new \PhpParser\Node\Expr\BinaryOp\NotIdentical(
				$node->getArgs()[0]->value,
				new \PhpParser\Node\Expr\ConstFetch(new \PhpParser\Node\Name('null'))
			);
		} elseif ($name === 'type') {
			$expr = $node->getArgs()[1];
			$class = $node->getArgs()[0];

			$classType = $scope->getType($class->value);
			$value = $classType->getConstantStrings();
			if (count($value) !== 1) {
				return new \PHPStan\Analyser\SpecifiedTypes();
			}

			$expression = new \PhpParser\Node\Expr\Instanceof_(
				$expr->value,
				new \PhpParser\Node\Name($value[0]->getValue())
			);
		} else {
			throw new ShouldNotHappenException();
		}

		$specifiedTypes = $this->typeSpecifier->specifyTypesInCondition(
			$scope,
			$expression,
			TypeSpecifierContext::createTrue()
		);

		return $specifiedTypes;
	}
}
