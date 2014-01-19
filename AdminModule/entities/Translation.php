<?php

namespace AdminModule;

use Doctrine\ORM\Mapping as orm;

/**
 * Entity class Translation.
 * @orm\Entity
 * @author Tomáš Voslař <tomas.voslar at webcook.cz>
 */
class Translation extends \AdminModule\Doctrine\Entity {
	/**
	 * @orm\Column(name="`key`", type="text")
	 * @var String 
	 */
	private $key;
	/**
	 * @orm\Column(type="text")
	 * @var String 
	 */
	private $translation;
	/**
	 * @orm\ManyToOne(targetEntity="Language")
	 * @orm\JoinColumn(name="language_id", referencedColumnName="id", onDelete="CASCADE")
	 * @var Int 
	 */
	private $language;
	/**
	 * @orm\Column(type="boolean")
	 * @var Boolean 
	 */
	private $backend;
	
	/**
	 * TODO unique=true
	 * @orm\Column()
	 * @var 
	 */
	private $hash;
	
	public function getHash() {
	    return $this->hash;
	}

	public function setHash() {
	    $this->hash = sha1($this->getKey() . $this->getLanguage()->getId() . $this->getBackend());
	}
	
	public function getKey() {
		return $this->key;
	}

	public function getTranslation() {
		return $this->translation;
	}

	public function getLanguage() {
		return $this->language;
	}

	public function getBackend() {
		return $this->backend;
	}

	public function setKey($key) {
		$this->key = $key;
	}

	public function setTranslation($translation) {
		$this->translation = $translation;
	}

	public function setLanguage($language) {
		$this->language = $language;
	}

	public function setBackend($backend) {
		$this->backend = $backend;
	}
}