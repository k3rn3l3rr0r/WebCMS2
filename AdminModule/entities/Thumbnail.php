<?php

namespace AdminModule;

use Doctrine\ORM\Mapping as orm;

/**
 * Description of Thumbnail
 * @orm\Entity
 * @author Tomáš Voslař <tomas.voslar at webcook.cz>
 */
class Thumbnail extends \AdminModule\Doctrine\Entity{
	/**
	 * @ORM\Column(name="`key`")
	 * @var String
	 */
	private $key;
	
	/**
	 * @ORM\Column(type="integer")
	 * @var Int
	 */
	private $x;
	
	/**
	 * @ORM\Column(type="integer")
	 * @var Int 
	 */
	private $y;
	
	/**
	 * @ORM\Column(type="boolean")
	 * @var Boolean 
	 */
	private $watermark;
	
	/**
	 * @ORM\Column(type="boolean")
	 * @var Boolean 
	 */
	private $system;
	
	public function getKey() {
		return $this->key;
	}

	public function setKey($key) {
		$this->key = $key;
	}

	public function getX() {
		return $this->x == 0 ? NULL : $this->x;
	}

	public function setX($x) {
		$this->x = $x;
	}

	public function getY() {
		return $this->y == 0 ? NULL : $this->y;
	}

	public function setY($y) {
		$this->y = $y;
	}

	public function getWatermark() {
		return $this->watermark;
	}

	public function setWatermark($watermark) {
		$this->watermark = $watermark;
	}

	public function getSystem() {
		return $this->system;
	}

	public function setSystem($system) {
		$this->system = $system;
	}
}
