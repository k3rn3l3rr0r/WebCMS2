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
		
		$form = $this->createForm();
		$form->addText('title', 'Name')->setAttribute('class', 'form-control');
		$form->addSelect('parent', 'Parent')->setTranslator(NULL)->setItems($hierarchy)->setAttribute('class', 'form-control');
		$form->addCheckbox('default', 'Default')->setAttribute('class', 'form-control');
		$form->addCheckbox('visible', 'Show')->setAttribute('class', 'form-control')->setDefaultValue(1);
		
		$form->addSubmit('save', 'Save')->setAttribute('class', 'btn btn-success');
		
		$form->onSuccess[] = callback($this, 'pageFormSubmitted');
		
		if($this->page) 
			$form->setDefaults($this->page->toArray());

		return $form;
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
		
		$grid->addColumn('title', 'Name')->setCustomRender(function($item){
			return str_repeat("-", $item->getLevel()) . $item->getTitle();
		});
		
		$grid->addColumnText('root', 'Structure')->setCustomRender(function($item){
			return '-';
		});
		
		$grid->addFilterSelect('root', 'Structure')->getControl()->setTranslator(NULL)->setItems($prnts);
		
		$grid->addColumn('visible', 'Visible')->setReplacement(array(
			'1' => 'Yes',
			NULL => 'No'
		));
		
		$grid->addAction("moveUp", "Move up");
		$grid->addAction("moveDown", "Move down");
		$grid->addAction("updatePage", 'Edit')->getElementPrototype()->addAttributes(array('class' => 'btn btn-primary ajax', 'data-toggle' => 'modal', 'data-target' => '#myModal', 'data-remote' => 'false'));
		$grid->addAction("deletePage", 'Delete')->getElementPrototype()->addAttributes(array('class' => 'btn btn-danger', 'data-confirm' => 'Are you sure you want to delete this item?'));

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
		
		$this->flashMessage($this->translation['Page has been removed.'], 'success');
		
		if(!$this->isAjax())
			$this->redirect('Pages:default');
	}
	
	public function actionMoveUp($id){
		$this->page = $this->em->find("AdminModule\Page", $id);
		
		if($this->page->getParent()){
			$repository = $this->em->getRepository('AdminModule\Page');
			$repository->moveUp($this->page);
			
			$this->flashMessage($this->translation['Page has been moved up.'], 'success');
		}else{
			$this->flashMessage($this->translation['Page has not been moved up, because it is root page.'], 'warning');
		}
		
		if(!$this->isAjax())
			$this->redirect('Pages:default');
	}
	
	public function actionMoveDown($id){
		$this->page = $this->em->find("AdminModule\Page", $id);
		
		if($this->page->getParent()){
			$repository = $this->em->getRepository('AdminModule\Page');
			$repository->moveDown($this->page);
			
			$this->flashMessage($this->translation['Page has been moved down.'], 'success');
		}else{
			$this->flashMessage($this->translation['Page has not been moved up, because it is root page.'], 'warning');
		}
		
		if(!$this->isAjax())
			$this->redirect('Pages:default');
	}
	
	public function pageFormSubmitted(UI\Form $form){
		$values = $form->getValues();
		
		if($values->parent)
			$parent = $this->em->find("AdminModule\Page", $values->parent);
		else
			$parent = NULL;
		
		$this->page->setTitle($values->title);
		$this->page->setVisible($values->visible);
		$this->page->setDefault($values->default);
		$this->page->setParent($parent);
		$this->page->setLanguage($this->state->language);

		$this->em->persist($this->page); // FIXME only if is new we have to persist entity, otherway it can be just flushed
		$this->em->flush();

		$this->flashMessage($this->translation['Page has been added.'], 'success');
		
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