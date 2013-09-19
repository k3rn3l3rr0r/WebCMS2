<?php

namespace WebCMS;

/**
 * Description of Settings
 *
 * @author Tomáš Voslař <tomas.voslar at webcook.cz>
 */
class Settings {
		
	/* @var Array('sections' => array(0 => Setting, ...)) */
	private $settings;
	
	/* \Doctrine\ORM\EntityManager */
	private $em;
	
	/* \AdminModule\Language */
	private $language;
	
	const SECTION_BASIC = 'basic';
	
	const SECTION_IMAGE = 'image';
	
	const SECTION_EMAIL = 'email';
	
	public function __construct($em, $language){
		$this->em = $em;
		$this->language = $language;
	}
	
	/**
	 * Gets settings by key and section.
	 * @param String $key
	 * @param String $section
	 * @return String
	 * @throws Exception
	 */
	public function get($key, $section = 'basic', $type = 'text', $options = array()){
		if(array_key_exists($section, $this->settings)){
			if(array_key_exists($key, $this->settings[$section])){
				return $this->settings[$section][$key];
			}
		}
		
		return $this->save($key, $section, $type, $options);
	}
	
	/**
	 * 
	 * @param type $section
	 * @return type
	 * @throws Exception
	 */
	public function getSection($section){
		if(array_key_exists($section, $this->settings)){
			return $this->settings[$section];
		}
		
		return FALSE;
	}
	
	/**
	 * 
	 * @param String $key
	 * @param String $section
	 */
	private function save($key, $section, $type = 'text', $options = array()){
		$setting = new \AdminModule\Setting;
		$setting->setKey($key);
		$setting->setSection($section);
		$setting->setType($type);
		$setting->setValue('');
		$setting->setLanguage($this->language);
		$setting->setOptions($options);
		
		$this->em->persist($setting);
		$this->em->flush();
		
		return $setting;
	}
	
	public function getSettings() {
		return $this->settings;
	}

	public function setSettings($settings) {
		$this->settings = $settings;
	}

}