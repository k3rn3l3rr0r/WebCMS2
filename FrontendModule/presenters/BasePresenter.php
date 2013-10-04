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
	
	/* @var string */
	public $abbr;
	
	/* @var Array */
	public $languages;
	
	/* @var Array */
	private $breadcrumbs = array();
	
	/* Method is executed before render. */
	protected function beforeRender(){
		
		$this->setLayout("layout");
		
		if($this->isAjax()){
			$this->invalidateControl('flashMessages');
		}
		
		$this->template->registerHelperLoader('\WebCMS\SystemHelper::loader');
		
		// get top page for sidebar menu
		$top = $this->actualPage;
		while($top->getParent() != NULL && $top->getLevel() > 1){
			$top = $top->getParent();
		}
		
		// set up boxes
		$this->setUpBoxes();
		
		$this->template->breadcrumb = $this->getBreadcrumbs();
		$this->template->abbr = $this->abbr;
		$this->template->settings = $this->settings;
		// !params load from settings
		$this->template->structures = $this->getStructures(FALSE, 'nav navbar-nav', TRUE);
		$this->template->sidebar = $this->getStructure($top, $this->em->getRepository('AdminModule\Page'), FALSE, 'nav');
		$this->template->setTranslator($this->translator);
		$this->template->actualPage = $this->actualPage;
		$this->template->user = $this->getUser();
		$this->template->activePresenter = $this->getPresenter()->getName();
		$this->template->languages = $this->em->getRepository('AdminModule\Language')->findAll();
	}
	
	/* Startup method. */
	protected function startup(){
		parent::startup();
		
		// set language
		if(is_numeric($this->getParam('language'))) $this->language = $this->em->find('AdminModule\Language', $this->getParam('language'));
		else $this->language = $this->em->getRepository('AdminModule\Language')->findOneBy(array(
			'defaultFrontend' => TRUE
		));
		
		$this->abbr = $this->language->getDefaultFrontend() ? '' : $this->language->getAbbr() . '/';
		
		// load languages
		$this->languages = $this->em->getRepository('AdminModule\Language')->findAll();
		
		\WebCMS\PriceFormatter::setLocale($this->language->getLocale());
		
		// translations
		$translation = new \WebCMS\Translation($this->em, $this->language , 0);
		$this->translation = $translation->getTranslations();
		$this->translator = new \WebCMS\Translator($this->translation);
		
		// system settings
		$this->settings = new \WebCMS\Settings($this->em, $this->language);
		$this->settings->setSettings($this->getSettings());
		
		$id = $this->getParam('id');
		if($id) $this->actualPage = $this->em->find('AdminModule\Page', $id);
	}
	
	private function getSettings(){
		$query = $this->em->createQuery('SELECT s FROM AdminModule\Setting s WHERE s.language >= ' . $this->language->getId() . ' OR s.language IS NULL');
		$tmp = $query->getResult();	
		
		$settings = array();
		foreach($tmp as $s){
				$settings[$s->getSection()][$s->getKey()] = $s;
		}
		
		return $settings;
	}
	
	public function createForm(){
		$form = new UI\Form();
		
		$form->setTranslator($this->translator);
		
		return $form;
	}
	
	public function createComponentLanguagesForm(){
		$form = $this->createForm();
		
		$form->getElementPrototype()->action = $this->link('this', array(
			'id' => $this->actualPage->getId(),
			'path' => $this->actualPage->getPath(),
			'abbr' => $this->abbr,
			'do' => 'languagesForm-submit'
		));
		
		$items = array();
		foreach($this->languages as $lang){
			$items[$lang->getId()] = $lang->getName(); 	
		}
		
		$form->addSelect('language', 'Change language')->setItems($items)->setDefaultValue($this->language->getId());
		$form->addSubmit('submit', 'Change');
		$form->onSuccess[] = callback($this, 'languagesFormSubmitted', array('abbr' => '', 'path' => $this->actualPage->getPath()));
		
		return $form;
	}
	
	public function languagesFormSubmitted($form){
		$values = $form->getValues();
		
		$home = $this->em->getRepository('AdminModule\Page')->findOneBy(array(
			'language' => $values->language,
			'default' => TRUE
		));
		
		if(is_object($home)){
		
			$abbr = $home->getLanguage()->getDefaultFrontend() ? '' : $home->getLanguage()->getAbbr() . '/';

			$this->session->destroy();
			
			$this->redirectUrl($this->link('this', array(
				'id' => $home->getId(),
				'path' => $home->getPath(),
				'abbr' => $abbr,
			)));
			
		}else{
			$this->flashMessage($this->translation['No default page for selected language.'], 'error');
		}
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
	
	/**
	 * Load all system structures.
	 * @return type
	 */
	private function getStructures($direct = TRUE, $rootClass = 'nav navbar-nav', $dropDown = FALSE){
		$repo = $this->em->getRepository('AdminModule\Page');
		
		$structs = $repo->findBy(array(
			'language' => $this->language,
			'parent' => NULL
		));
		
		$structures = array();
		foreach($structs as $s){
			$structures[$s->getTitle()] = $this->getStructure($s, $repo, $direct, $rootClass, $dropDown);
		}
		
		return $structures;
	}
	
	/**
	 * Set up boxes (call box function and save it into array) and give them to the tempalte.
	 */
	private function setUpBoxes(){
		$parameters = $this->context->getParameters();
		$boxes = $parameters['boxes'];
		
		$finalBoxes = array();
		foreach($boxes as $key => $box){
			$finalBoxes[$key] = NULL;
		}
		
		$assocBoxes = $this->em->getRepository('AdminModule\Box')->findBy(array(
			'pageTo' => $this->actualPage
		));

		foreach($assocBoxes as $box){
			$presenter = 'FrontendModule\\' . $box->getPresenter() . 'Module\\' . $box->getPresenter() . 'Presenter';
			$object = new $presenter;
			
			if(method_exists($object, $box->getFunction())) 
					$function = $box->getFunction();
					$pageFrom = $box->getPageFrom();
					$finalBoxes[$box->getBox()] = call_user_func(array($object, $function), $this, $pageFrom);
		}

		$this->template->boxes = $finalBoxes;
	}
	
	/**
	 * Get structure by node. In node is set to null whole tree is returned.
	 * @param type $node
	 * @param Repository $repo
	 * @param type $direct
	 * @param type $rootClass
	 * @param type $dropDown
	 * @return type
	 */
	protected function getStructure($node = NULL, $repo, $direct = TRUE, $rootClass = 'nav navbar-nav', $dropDown = FALSE, $system = TRUE){
		
		return $repo->childrenHierarchy($node, $direct, array(
				'decorate' => true,
				'html' => true,
				'rootOpen' => function($nodes) use($rootClass, $dropDown){

					$drop = $nodes[0]['level'] == 2 ? TRUE : FALSE;
					$class = $nodes[0]['level'] < 2 ? $rootClass : '';

					if($drop && $dropDown)
						$class .= ' dropdown-menu';

					return '<ul class="' . $class . '">';
				},
				'rootClose' => '</ul>',
				'childOpen' => function($node) use($dropDown){
					$hasChildrens = count($node['__children']) > 0 ? TRUE : FALSE;
					$active = $this->getParam('id') == $node['id'] ? TRUE : FALSE;
					$class = '';

					if($this->getParam('lft') > $node['lft'] && $this->getParam('lft') < $node['rgt'] && $this->getParam('root') == $node['root']){
						$class .= ' active';
					}

					if($hasChildrens && $dropDown)
						$class .= ' dropdown';

					if($active)
						$class .= ' active';

					return '<li class="' . $class . '">';
				},
				'childClose' => '</li>',
				'nodeDecorator' => function($node) use($dropDown, $system) {
					$hasChildrens = count($node['__children']) > 0 ? TRUE : FALSE;
					$params = '';
					$class = '';
					
					$moduleName = array_key_exists('moduleName', $node) ? $node['moduleName'] : 'Eshop';
					$presenter = array_key_exists('presenter', $node) ? $node['presenter'] : 'Categories';
					$path = $moduleName === 'Eshop' && !$system ? $path = $this->actualPage->getPath() . '/' . $node['path'] : $node['path'];
							
					$link = $this->link(':Frontend:' . $moduleName . ':' . $presenter . ':default', array('id' => $node['id'], 'path' => $path, 'abbr' => $this->abbr));

					if($hasChildrens && $node['level'] == 1 && $dropDown){
						$params = ' data-toggle="dropdown"';
						$class .= ' dropdown-toggle';
						$link = '#';
					}

					return '<a ' . $params .' class="' . $class . '" href="' . $link . '">'.$node['title'].'</a>';
				}
			));
	}

	public function getBreadcrumbs(){
		// bredcrumb
		$default = $this->em->getRepository('AdminModule\Page')->findOneBy(array(
			'default' => TRUE,
			'language' => $this->language
		));
		
		if($this->actualPage->getDefault())
			$default = array();
		else
			$default = array($default);
		
		// system breadcrumbs
		$system = $default + $this->em->getRepository('AdminModule\Page')->getPath($this->actualPage);
		$finalSystem = array();
		foreach($system as $item){
			if($item->getParent()){
				$finalSystem[] = new \WebCMS\BreadcrumbsItem($item->getId(),
						$item->getModuleName(), 
						$item->getPresenter(), 
						$item->getTitle(), 
						$item->getPath()
					);
			}
		}
		
		foreach($this->breadcrumbs as $b){
			array_push($finalSystem, $b);
		}
		
		return $finalSystem;
	}
	
	/**
	 * 
	 * @param Array $item
	 */
	public function addToBreadcrumbs($id, $moduleName, $presenter, $title, $path){
		
		$this->breadcrumbs[] = new \WebCMS\BreadcrumbsItem($id, $moduleName, $presenter, $title, $path);
	}

}