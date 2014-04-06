<?php

use Nette\Diagnostics\Debugger,
    Nette\Application as NA;

/**
 * Error presenter.
 *
 * @author     John Doe
 * @package    MyApplication
 */
class ErrorPresenter extends \FrontendModule\BasePresenter {

    /**
     * @param  Exception
     * @return void
     */
    public function renderDefault($exception) {

	if ($this->isAjax()) { // AJAX request? Just note this error in payload.
	    $this->payload->error = TRUE;
	    $this->terminate();
	} elseif ($exception instanceof NA\BadRequestException) {
	    $code = $exception->getCode();
	    // load template 403.latte or 404.latte or ... 4xx.latte
	    $this->setView(in_array($code, array(403, 404, 405, 410, 500)) ? $code : '4xx');

	    $errorPage = new \WebCMS\Entity\Page;
	    $errorPage->setTitle('404');
	
	$this->actualPage = $errorPage;

	    $this->template->actualPage = $errorPage;
	    $this->template->errorCode = $code;
	    $this->template->seoTitle = '404';
	    $this->template->seoDescription = '';
	    $this->template->seoKeywords = '';
	    $this->template->breadcrumb = NULL;
	    
	    $this->setLayout('layout');
	    // log to access.log
	    Debugger::log("HTTP code $code: {$exception->getMessage()} in {$exception->getFile()}:{$exception->getLine()}", 'access');
	} else {
	    $this->setView('500'); // load template 500.latte
	    Debugger::log($exception, Debugger::ERROR); // and log exception
	}
    }

}
