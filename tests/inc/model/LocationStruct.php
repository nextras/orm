<?php

namespace NextrasTests\Orm;


class LocationStruct implements IModifiable
{

	/** @var string */
	protected $street;

	/** @var string */
	protected $city;

	/** @var callable[] */
	private $onModifiedListeners = [];


	public function __construct($street, $city)
	{
		$this->street = $street;
		$this->city = $city;
	}


	/**
	 * @param callable $callable
	 */
	public function addOnModifiedListener($callable)
	{
		$this->onModifiedListeners[] = $callable;
	}


	protected function onModified()
	{
		foreach ($this->onModifiedListeners as $listener) {
			$listener();
		}
	}


	/**
	 * @return string
	 */
	public function getStreet()
	{
		return $this->street;
	}


	/**
	 * @param string $street
	 */
	public function setStreet($street)
	{
		if ($this->street !== $street) {
			$this->street = $street;
			$this->onModified();
		}
	}


	/**
	 * @return string
	 */
	public function getCity()
	{
		return $this->city;
	}


	/**
	 * @param string $city
	 */
	public function setCity($city)
	{
		if ($this->city !== $city) {
			$this->city = $city;
			$this->onModified();
		}
	}

}
