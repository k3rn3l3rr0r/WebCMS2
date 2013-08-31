<?php

namespace AdminModule;

/**
 * Filesystem presenter.
 * @author Tomáš Voslař <tomas.voslar at webcook.cz>
 * @package WebCMS2
 */
class FilesystemPresenter extends \AdminModule\BasePresenter{

	protected function beforeRender(){
		parent::beforeRender();
		
		$this->reloadContent();
	}
	
	protected function startup(){		
		parent::startup();
		
	}
	
}