<?php

namespace AdminModule;

/**
 * Settings presenter.
 * @author Tomáš Voslař <tomas.voslar at webcook.cz>
 * @package WebCMS2
 */
class SettingsPresenter extends \AdminModule\BasePresenter{
	
	/* @var Thumbnail */
	private $thumbnail;
	
	protected function beforeRender(){
		parent::beforeRender();
	}
	
	protected function startup(){		
		parent::startup();
	}
	
	/* BASIC */
	
	public function createComponentBasicSettingsForm(){
		
		$settings = array();
		$settings[] = $this->settings->get('Navbar dropdown', \WebCMS\Settings::SECTION_BASIC, 'checkbox');
		$settings[] = $this->settings->get('Info email', \WebCMS\Settings::SECTION_BASIC, 'text');
		$settings[] = $this->settings->get('Navbar class', \WebCMS\Settings::SECTION_BASIC, 'text');
		$settings[] = $this->settings->get('Sidebar class', \WebCMS\Settings::SECTION_BASIC, 'text');
		
		return $this->createSettingsForm($settings);
	}
	
	public function renderDefault(){
		$this->reloadContent();
	}
	
	/* PICTURES */
	
	public function createComponentPicturesSettingsForm(){
		
		$settings = array();
		
		// global settings for all languages
		$this->settings->setLanguage(NULL);
		
		$settings[] = $this->settings->get('Apply watermark', \WebCMS\Settings::SECTION_IMAGE, 'radio', array(
			0 => 'Do not apply watermark',
			1 => 'Use picture as watermark',
			2 => 'Use text as watermark'
		));
		
		$settings[] = $this->settings->get('Watermark text', \WebCMS\Settings::SECTION_IMAGE, 'text');
		$settings[] = $this->settings->get('Watermark text size', \WebCMS\Settings::SECTION_IMAGE, 'text');
		$settings[] = $this->settings->get('Watermark text font', \WebCMS\Settings::SECTION_IMAGE, 'select', array(
			0 => 'Comic sans',
			1 => 'Arial',
			2 => 'Times new roman'
		));
		
		$settings[] = $this->settings->get('Watermark text color', \WebCMS\Settings::SECTION_IMAGE, 'text');
		
		$settings[] = $this->settings->get('Watermark position', \WebCMS\Settings::SECTION_IMAGE, 'radio', array(
			0 => 'Top left',
			1 => 'Top right',
			2 => 'Center',
			3 => 'Bottom left',
			4 => 'Bottom right'
		));
		
		// set back language for further settings in app
		$this->settings->setLanguage($this->state->language);
		
		return $this->createSettingsForm($settings);
	}
	
	public function renderPictures($panel){
		$this->reloadContent();
		
		$this->template->panel = $panel;
	}
	
	public function actionAddThumbnail($id){
		if($id) $this->thumbnail = $this->em->find("AdminModule\Thumbnail", $id);
		else $this->thumbnail = new Thumbnail();
	}
	
	public function actionDeleteThumbnail($id){
		$this->thumbnail = $this->em->find("AdminModule\Thumbnail", $id);
		$this->em->remove($this->thumbnail);
		$this->em->flush();
		
		$this->flashMessage('Thumbnail has been removed.', 'success');
		
		if(!$this->isAjax())
			$this->redirect('Settings:pictures', array('panel' => 'thumbnails'));
	}
	
	public function renderAddThumbnail($id){
		
		$this->reloadModalContent();
		
		$this->template->thumbnail = $this->thumbnail;
	}
	
	public function createComponentThumbnailForm(){
		
		$form = $this->createForm();
		
		$form->addText('key', 'Key');
		$form->addText('x', 'Width');
		$form->addText('y', 'Height');
		$form->addCheckbox('watermark', 'Watermark?');
		
		if(\WebCMS\SystemHelper::isSuperAdmin($this->user))
			$form->addCheckbox('system', 'System?');
		else
			$form->addHidden('system', 'System?');
		
		$form->addSubmit('submit', 'Save');
		
		$form->onSuccess[] = callback($this, 'thumbnailFormSubmitted');
		$form->setDefaults($this->thumbnail->toArray());
		
		return $form;
	}
	
	public function thumbnailFormSubmitted(\Nette\Forms\Form $form){
		$values = $form->getValues();
		
		if(!$this->thumbnail->getId())
			$thumb = new Thumbnail;
		else 
			$thumb = $this->thumbnail;
		
		if(!\WebCMS\SystemHelper::isSuperAdmin($this->user) && $thumb->getSystem()){
			$this->flashMessage('You do not have a permission to do this operation.', 'danger');
			$this->redirect('Settings:pictures', array('panel' => 'thumbnails'));
		}
		
		$thumb->setKey($values->key);
		$thumb->setX($values->x);
		$thumb->setY($values->y);
		$thumb->setWatermark($values->watermark);
		$thumb->setSystem($values->system);
		
		$this->em->persist($thumb);
		$this->em->flush();
		
		$this->flashMessage('Thumbnail setting was added.', 'success');
		
		if(!$this->isAjax())
			$this->redirect('Settings:pictures', array('panel' => 'thumbnails'));
	}
	
	protected function createComponentThumbnailGrid($name){
		
		$grid = $this->createGrid($this, $name, "Thumbnail");
		
		$grid->addColumnText('key', 'Key');
		$grid->addColumnText('x', 'Width');
		$grid->addColumnText('y', 'Height');
		
		$grid->addColumnText('watermark', 'Watermark')->setReplacement(array(
			1 => 'Yes',
			NULL => 'No'
		));
		
		$grid->addColumnText('system', 'System')->setReplacement(array(
			1 => 'Yes',
			NULL => 'No'
		));
		
		$grid->addActionHref("addThumbnail", 'Edit')->getElementPrototype()->addAttributes(array('class' => array('btn', 'btn-primary', 'ajax'), 'data-toggle' => 'modal', 'data-target' => '#myModal', 'data-remote' => 'false'));
		$grid->addActionHref("deleteThumbnail", 'Delete')->getElementPrototype()->addAttributes(array('class' => array('btn', 'btn-danger'), 'data-confirm' => 'Are you sure you want to delete this item?'));

		return $grid;
	}
	
	/* EMAILS */
	
	public function createComponentEmailsSettingsForm(){
		
		$settings = array();
		$settings[] = $this->settings->get('User new password', \WebCMS\Settings::SECTION_EMAIL, 'textarea');
		
		return $this->createSettingsForm($settings);
	}
	
	public function renderEmails(){
		$this->reloadContent();
	}
	
	/* BOXES SETTINGS - BATCH */
	
	public function renderBoxesSettings(){
		$this->reloadContent();
		
		// fetch all boxes
		$parameters = $this->getContext()->container->getParameters();
		
		// fetch all pages
		$pages = $this->em->getRepository('AdminModule\Page')->findBy(array(
			'language' => $this->state->language
		));
		
		$boxesAssoc = array();
		foreach($pages as $page){
			if($page->getParent() != NULL){
				$module = $this->createObject($page->getModuleName());

				foreach($module->getBoxes() as $box){
					$boxesAssoc[$page->getId() . '-' . $box['module'] . '-' . $box['presenter'] . '-' . $box['function']] = $page->getTitle() . ' - ' . $this->translation[$box['name']];
				}
			}
		}
		
		$boxesAssoc = array(
			0 => $this->translation['Box is not linked.']
		) + $boxesAssoc;
		
		$this->template->boxes = $parameters['boxes'];
		$this->template->pages = $pages;
		$this->template->boxesAssoc = $boxesAssoc;
	}
	
	public function handleUpdateBox($name, $value){
		$this->reloadContent();
		
		$parsed = explode('-', $name);
		$pageTo = $this->em->getRepository('AdminModule\Page')->find($parsed[0]);
		$box = $parsed[1];
		
		$parsed = explode('-', $value);
		$pageFrom = $this->em->getRepository('AdminModule\Page')->find($parsed[0]);
		$moduleName = $parsed[1];
		$presenter = $parsed[2];
		$function = $parsed[3];
		
		$exists = $this->em->getRepository('AdminModule\Box')->findOneBy(array(
			'pageTo' => $pageTo,
			'box' => $box
		));
		
		if(is_object($exists)){
			$boxAssign = $exists;
		}else{
			$boxAssign = new Box();
		}
		
		$boxAssign->setBox($box);
		$boxAssign->setFunction($function);
		$boxAssign->setModuleName($moduleName);
		$boxAssign->setPresenter($presenter);
		$boxAssign->setPageFrom($pageFrom);
		$boxAssign->setPageTo($pageTo);
		
		if(!$boxAssign->getId()){
			$this->em->persist($boxAssign);
		}
		$this->em->persist($boxAssign);
		$this->em->flush();
		
		$this->flashMessage('Box settings changed.', 'success');
	}
	
	/* SEO SETTINGS - BATCH*/
	public function renderSeoSettings(){
		$this->reloadContent();
				
		// fetch all pages
		$pages = $this->em->getRepository('AdminModule\Page')->findBy(array(
			'language' => $this->state->language
		));

		$this->template->pages = $pages;
	}
	
	public function createComponentSeoBasicForm() {
		
		$settings = array();
		
		$settings[] = $this->settings->get('Seo keywords', \WebCMS\Settings::SECTION_BASIC, 'text');
		$settings[] = $this->settings->get('Seo title', \WebCMS\Settings::SECTION_BASIC, 'text');
		$settings[] = $this->settings->get('Seo description', \WebCMS\Settings::SECTION_BASIC, 'text');
		$settings[] = $this->settings->get('Seo title before', \WebCMS\Settings::SECTION_BASIC, 'checkbox');
		
		return $this->createSettingsForm($settings);
	}
	
	public function actionUpdateMeta($idPage, $type, $value){
		$page = $this->em->getRepository('AdminModule\Page')->find($idPage);
		
		if($type === 'title'){
			$page->setMetaTitle($value);
		}elseif($type === 'keywords'){
			$page->setMetaKeywords($value);
		}elseif($type === 'description'){
			$page->setMetaDescription($value);
		}elseif($type === 'slug'){
			$page->setSlug($value);
			
			$path = $this->em->getRepository('AdminModule\Page')->getPath($page);
			$final = array();
			foreach($path as $p){
				if($p->getParent() != NULL) $final[] = $p->getSlug();
			}
		
			$page->setPath(implode('/', $final));
		}
		
		$this->em->flush();
		
		$this->flashMessage('Seo has been updated.', 'success');
		$this->invalidateControl('flashMessages');
	}
	
	/* PROJECT SPECIFIC SETTINGS */
	public function createComponentProjectSettingsForm(){
		
		$parameters = $this->getContext()->container->getParameters();
		$settings = array();
		
		if(array_key_exists('settings', $parameters)){
			
			$projectSettings = $parameters['settings'];

			foreach($projectSettings as $key => $value){
				$settings[] = $this->settings->get($key, \WebCMS\Settings::SECTION_BASIC, 'checkbox');
			}
		}else{
			$this->flashMessage('There are no settings in config file.', 'info');
		}
			
		return $this->createSettingsForm($settings);
	}
	
	public function renderProject(){
		$this->reloadContent();
	}
        
        /* API third party */
	
        public function renderApi(){
		$this->reloadContent();
	}
        
	public function createComponentApiSettingsForm(){
		
		$settings = array();
		$settings[] = $this->settings->get('Yandex API key', \WebCMS\Settings::SECTION_BASIC, 'text');
		
		$settings[] = $this->settings->get('Google API key', \WebCMS\Settings::SECTION_BASIC, 'text');
		
		$settings[] = $this->settings->get('Bing client id', \WebCMS\Settings::SECTION_BASIC, 'text');
		$settings[] = $this->settings->get('Bing client secret', \WebCMS\Settings::SECTION_BASIC, 'text');
		
		return $this->createSettingsForm($settings);
	}
}