<?php

namespace AdminModule;

use Nette\Application\UI;

/**
 * Admin presenter.
 * @author Tomáš Voslař <tomas.voslar at webcook.cz>
 * @package WebCMS2
 */
class PagesPresenter extends \AdminModule\BasePresenter{
	
	/* @var Page */
	private $page;
	
	protected function beforeRender(){
		parent::beforeRender();
	}
	
	protected function startup(){		
		parent::startup();
	}
	
	protected function createComponentPageForm(){

		$repository = $this->em->getRepository('AdminModule\Page');
		$hierarchy = $repository->getTreeForSelect(array(
			array('by' => 'root', 'dir' => 'ASC'), 
			array('by' => 'lft', 'dir' => 'ASC')
			),
			array(
				'language = ' . $this->state->language->getId()
		));
		
		$hierarchy = array(0 => $this->translation['Pick parent']) + $hierarchy;
		
		// loads modules
		$modules = $this->em->getRepository('AdminModule\Module')->findAll();
		$modulesToSelect = array();
		foreach($modules as $module){
			$objectModule = $this->createObject($module->getName());
			
			foreach($objectModule->getPresenters() as $presenter){
				if($presenter['frontend']) $modulesToSelect[$module->getId() . '-' . $presenter['name']] = $module->getName() . ' ' . $presenter['name'];
			}
		}
		
		$form = $this->createForm();
		$form->addText('title', 'Name')->setAttribute('class', 'form-control');
		$form->addText('redirect', 'Redirect')->setAttribute('class', 'form-control');
		$form->addText('class', 'Menu item class')->setAttribute('class', 'form-control');
		$form->addSelect('module', 'Module')->setTranslator(NULL)->setItems($modulesToSelect)->setAttribute('class', 'form-control');
		$form->addSelect('parent', 'Parent')->setTranslator(NULL)->setItems($hierarchy)->setAttribute('class', 'form-control');
		$form->addCheckbox('default', 'Default')->setAttribute('class', 'form-control');
		$form->addCheckbox('visible', 'Show')->setAttribute('class', 'form-control')->setDefaultValue(1);
		
		$form->addSubmit('save', 'Save')->setAttribute('class', 'btn btn-success');
		
		$form->onSuccess[] = callback($this, 'pageFormSubmitted');
		
		if($this->page){
			$form->setDefaults($this->page->toArray());
			if(is_object($this->page->getModule())) $form['module']->setDefaultValue($this->page->getModule()->getId() . '-' . $this->page->getPresenter());
		}
			
		
		return $form;
	}
	
	public function pageFormSubmitted(UI\Form $form){
		$values = $form->getValues();
		
		$repo = $this->em->getRepository('AdminModule\Page');
		
		$tmpBoxes = array();
		if($values->parent){
			$parent = $this->em->find("AdminModule\Page", $values->parent);
			
			// copy boxes
			$tmpBoxes = $parent->getBoxes();
		}
		else{
			$parent = NULL;
		}
		
		if($values->module){
			$parse = explode('-', $values->module);
			$module = $this->em->find("AdminModule\Module", $parse[0]);
			$presenter = $parse[1];
		}
		else{
			$module = NULL;
			$presenter = '';
		}
		
		$this->page->setTitle($values->title);
		
		if(!empty($values->redirect)){
			$this->page->setRedirect($values->redirect);
		}else{
			$this->page->setRedirect(NULL);
		}
		
		$this->page->setVisible($values->visible);
		$this->page->setDefault($values->default);
		$this->page->setParent($parent);
		$this->page->setLanguage($this->state->language);
		$this->page->setModule($module);	
		$this->page->setPresenter($presenter);
		$this->page->setPath('tmp value');
		$this->page->setClass($values->class);
		
		if($module){
			$this->page->setModuleName($module->getName());
		}
		
		if(!$this->page->getId()){
			$this->em->persist($this->page); 
			
			// create boxes from parent
			foreach($tmpBoxes as $box){
				$tmp = new Box();
				$tmp->setBox($box->getBox());
				$tmp->setFunction($box->getFunction());
				$tmp->setModuleName($box->getModuleName());
				$tmp->setPresenter($box->getPresenter());
				$tmp->setPageFrom($box->getPageFrom());
				$tmp->setPageTo($this->page);
				
				$this->em->persist($tmp);
			}
			
			$this->em->flush();
			die();
			// sets permissions for users roles
			$roles = $this->em->getRepository('AdminModule\Role')->findBy(array(
				'automaticEnable' => true
			));

			foreach($roles as $r){
				$module = $this->createObject($this->page->getModuleName());
				foreach($module->getPresenters() as $presenter){
					$permission = new Permission;

					$resource = 'admin:' . $this->page->getModuleName() . $presenter['name'] . $this->page->getId();
					$permission->setResource($resource);
					$permission->setRead(true);

					$r->addPermission($permission);
				}
			}
		}
		$this->em->flush();
		// creates path
		$path = $repo->getPath($this->page);
		$final = array();
		foreach($path as $p){
			if($p->getParent() != NULL) $final[] = $p->getSlug();
		}
		
		$this->page->setPath(implode('/', $final));
		
		$this->em->flush();

		$this->flashMessage('Page has been added.', 'success');
		
		if(!$this->isAjax())
			$this->redirect('Pages:default');
	} 
	
	protected function createComponentPagesGrid($name){
		
		$parents = $this->em->getRepository('AdminModule\Page')->findBy(array(
			'parent' => NULL,
			'language' => $this->state->language->getId()
		));
		
		$prnts = array('' => $this->translation['Pick structure']);
		foreach($parents as $p){
			$prnts[$p->getId()] = $p->getTitle();
		}
		
		$grid = $this->createGrid($this, $name, 'Page', array(
			array('by' => 'root', 'dir' => 'ASC'), 
			array('by' => 'lft', 'dir' => 'ASC')
			),
			array(
				'language = ' . $this->state->language->getId()
			)
		);
		
		$grid->addColumnText('title', 'Name')->setCustomRender(function($item){
			return str_repeat("-", $item->getLevel()) . $item->getTitle();
		});
		
		$grid->addColumnText('root', 'Structure')->setCustomRender(function($item){
			return '-';
		});
		
		$grid->addFilterSelect('root', 'Structure')->getControl()->setTranslator(NULL)->setItems($prnts);
		
		$grid->addColumnText('visible', 'Visible')->setReplacement(array(
			'1' => 'Yes',
			NULL => 'No'
		));
		
		$grid->addActionHref("moveUp", "Move up");
		$grid->addActionHref("moveDown", "Move down");
		$grid->addActionHref("updatePage", 'Edit')->getElementPrototype()->addAttributes(array('class' => array('btn', 'btn-primary', 'ajax'), 'data-toggle' => 'modal', 'data-target' => '#myModal', 'data-remote' => 'false'));
		$grid->addActionHref("deletePage", 'Delete')->getElementPrototype()->addAttributes(array('class' => array('btn', 'btn-danger'), 'data-confirm' => 'Are you sure you want to delete this item?'));

		return $grid;
	}
	
	public function actionUpdatePage($id){
		if($id) $this->page = $this->em->find("AdminModule\Page", $id);
		else $this->page = new Page();
	}
	
	public function actionDeletePage($id){
		$this->page = $this->em->find("AdminModule\Page", $id);
		$this->em->remove($this->page);
		$this->em->flush();
		
		$this->flashMessage('Page has been removed.', 'success');
		
		if(!$this->isAjax())
			$this->redirect('Pages:default');
	}
	
	public function actionMoveUp($id){
		$this->page = $this->em->find("AdminModule\Page", $id);
		
		if($this->page->getParent()){
			$repository = $this->em->getRepository('AdminModule\Page');
			$repository->moveUp($this->page);
			
			$this->flashMessage('Page has been moved up.', 'success');
		}else{
			$this->flashMessage('Page has not been moved up, because it is root page.', 'warning');
		}
		
		if(!$this->isAjax())
			$this->redirect('Pages:default');
	}
	
	public function actionMoveDown($id){
		$this->page = $this->em->find("AdminModule\Page", $id);
		
		if($this->page->getParent()){
			$repository = $this->em->getRepository('AdminModule\Page');
			$repository->moveDown($this->page);
			
			$this->flashMessage('Page has been moved down.', 'success');
		}else{
			$this->flashMessage('Page has not been moved up, because it is root page.', 'warning');
		}
		
		if(!$this->isAjax())
			$this->redirect('Pages:default');
	}
	
	public function renderUpdatePage($id){
		$this->reloadModalContent();
		
		$this->template->page = $this->page;
	}
	
	public function renderDefault(){
		$this->reloadContent();
	}
	
}