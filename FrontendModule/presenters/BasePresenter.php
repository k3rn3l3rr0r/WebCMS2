<?php

namespace FrontendModule;

use Nette;
use Kdyby\BootstrapFormRenderer\BootstrapRenderer;
use Nette\Application\UI;

/**
 * Base class for all application presenters.
 *
 * @author     Tomáš Voslař <tomas.voslar at webcook.cz>
 * @package    WebCMS2
 */
abstract class BasePresenter extends Nette\Application\UI\Presenter{
	/** @var Doctrine\ORM\EntityManager */
	protected $em;
	
	/* @var \WebCMS\Translation */
	public $translation;
	
	/* @var \WebCMS\Translator */
	public $translator;
	
	/* @var Nette\Http\SessionSection */
	public $language;
	
	/* @var User */
	public $systemUser;
	
	/* @var \WebCMS\Settings */
	public $settings;
	
	/* @var Page */
	public $actualPage;
	
	/* Method is executed before render. */
	protected function beforeRender(){
		
		$this->setLayout("layout");
		
		if($this->isAjax()){
			$this->invalidateControl('flashMessages');
		}
		
		$this->template->registerHelperLoader('\WebCMS\SystemHelper::loader');
		
		$this->template->structures = $this->getStructures();
		$this->template->setTranslator($this->translator);
		$this->template->actualPage = $this->actualPage;
		$this->template->user = $this->getUser();
		$this->template->activePresenter = $this->getPresenter()->getName();
		$this->template->languages = $this->em->getRepository('AdminModule\Language')->findAll();
	}
	
	/* Startup method. */
	protected function startup(){
		parent::startup();
		
		$this->language = $this->em->find('AdminModule\Language', $this->getParam('language'));
		
		// translations
		$translation = new \WebCMS\Translation($this->em, $this->language , 0);
		$this->translation = $translation->getTranslations();
		$this->translator = new \WebCMS\Translator($this->translation);
		
		$id = $this->getParam('id');
		if($id) $this->actualPage = $this->em->find('AdminModule\Page', $id);
	}

	/**
	 * Injects entity manager.
	 * @param \Doctrine\ORM\EntityManager $em
	 * @return \Backend\BasePresenter
	 * @throws \Nette\InvalidStateException
	 */
	public function injectEntityManager(\Doctrine\ORM\EntityManager $em){
		if ($this->em) {
			throw new \Nette\InvalidStateException('Entity manager has been already set.');
		}
		
		$this->em = $em;
		return $this;
	}
	
	private function getStructures(){
		$qb = $this->em->createQueryBuilder();
		
		$qb = $this->em->createQueryBuilder();
		
		$qb->addOrderBy('l.root', 'ASC');
		$qb->andWhere('l.parent IS NULL');
		$qb->andWhere('l.language = ' . $this->language->getId());
		
		return $qb->select('l')->from("AdminModule\\Page", 'l')->getQuery()->getResult();
	}
}