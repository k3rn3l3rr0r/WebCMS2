<?php

namespace AdminModule;

/**
 * Description of UpdatePresenter
 *
 * @author Tomáš Voslař <tomas.voslar at webcook.cz>
 */
class UpdatePresenter extends \AdminModule\BasePresenter{
	protected function beforeRender(){
		parent::beforeRender();
		
		$this->reloadContent();
	}
	
	protected function startup(){		
		parent::startup();
	}
	
	public function handleUpdateSystem(){
		
		putenv("COMPOSER_HOME=/usr/bin/.composer");
		
		system("../install/install.sh 4 > ../log/install.log 2> ../log/install-error.log");
		$res = file_get_contents('../log/install.log');
		
		$this->flashMessage($res, 'success');
		if(file_exists('../log/install-error.log')){
			$resError = file_get_contents('../log/install-error.log');
			
			if(!empty($resError)) 
				$this->flashMessage($resError, 'danger');
			
			unlink('../log/install-error.log');
		}
				
		unlink('../log/install.log');
		
		if(!$this->isAjax())
			$this->redirect('Update:');
		else{
			$this->invalidateControl('footer');
		}
	}
	
	public function actionClearCache(){
		// pomoci skriptu jen hned po skriptu ukoncit pomoc terminate, ajaxove
		$this->context->cacheStorage->clean(array(\Nette\Caching\Cache::ALL => TRUE));
		
		$this->flashMessage('Mezipaměť byla smazána.', 'success');
		$this->redirect("Update:");
	}
}