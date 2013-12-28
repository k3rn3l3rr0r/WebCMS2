<?php

namespace AdminModule;

use Nette\Application\UI;

/**
 * Languages and translations presenter.
 * @author Tomáš Voslař <tomas.voslar at webcook.cz>
 * @package WebCMS2
 */
class LanguagesPresenter extends \AdminModule\BasePresenter{
	
	/* @var Language */
	private $lang;
	
        /* @var \Webcook\Translator\ServiceFactory */
        private $serviceFactory;
        
        /* @var \Webcook\Translator\ITranslator */
        private $translatorService;
        
	/* LANGUAGES */
	
	protected function beforeRender(){
		parent::beforeRender();
		
	}
	
	protected function startup(){		
		parent::startup();
	}
	
	public function renderDefault(){
		
		$this->reloadContent();
	}
	
	protected function createComponentLanguageForm(){
		
		$locales = \WebCMS\Locales::getSystemLocales();
		
		$form = $this->createForm();
		$form->addText('name', 'Name')->setAttribute('class', 'form-control');
		$form->addText('abbr', 'Abbreviation')->setAttribute('class', 'form-control');
		$form->addSelect('locale', 'Locale')->setItems($locales);
		$form->addCheckbox('defaultFrontend', 'Default fe')->setAttribute('class', 'form-control');
		$form->addCheckbox('defaultBackend', 'Default be')->setAttribute('class', 'form-control');
		$form->addUpload('import', 'Import lang');
		$form->addSubmit('save', 'Save')->setAttribute('class', 'btn btn-success');
		
		$form->onSuccess[] = callback($this, 'languageFormSubmitted');
		
		if($this->lang) 
			$form->setDefaults($this->lang->toArray());

		return $form;
	}
	
	protected function createComponentGrid($name){
		
		$grid = $this->createGrid($this, $name, "Language");
		
		$grid->addColumnText('name', 'Name')->setSortable();
		$grid->addColumnText('abbr', 'Abbreviation')->setSortable();
		$grid->addColumnText('defaultFrontend', 'Default fe')->setReplacement(array(
			'1' => 'Yes',
			NULL => 'No'
		));
		$grid->addColumnText('defaultBackend', 'Default be')->setReplacement(array(
			'1' => 'Yes',
			NULL => 'No'
		));
		
		$grid->addActionHref("exportLanguage", 'Export')->getElementPrototype()->addAttributes(array('class' => array('btn', 'btn-primary')));
		$grid->addActionHref("updateLanguage", 'Edit')->getElementPrototype()->addAttributes(array('class' => array('btn', 'btn-primary', 'ajax'), 'data-toggle' => 'modal', 'data-target' => '#myModal', 'data-remote' => 'false'));
		$grid->addActionHref("deleteLanguage", 'Delete')->getElementPrototype()->addAttributes(array('class' => array('btn', 'btn-danger'), 'data-confirm' => 'Are you sure you want to delete the item?'));

		return $grid;
	}
	
	/**
	 * Export language into JSON file and terminate response for download it.
	 * @param Int $id 
	 */
	public function actionExportLanguage($id){
		$language = $this->em->find("AdminModule\Language", $id);
		
		$export = array(
			'name' => $language->getName(),
			'abbr' => $language->getAbbr(),
			'translations' => array()
		);
		
		foreach($language->getTranslations() as $translation){
			if($translation->getBackend()){
				$export['translations'][] = array(
					'key' => $translation->getKey(),
					'translation' => $translation->getTranslation(),
					'backend' => $translation->getBackend()
				);
			}
		}
		
		$export = json_encode($export);
		$filename = $language->getAbbr() . '.json';

		$response = $this->getHttpResponse();
		$response->setHeader('Content-Description', 'File Transfer');
		$response->setContentType('text/plain', 'UTF-8');
		$response->setHeader('Content-Disposition', 'attachment; filename=' . $filename);
		$response->setHeader('Content-Transfer-Encoding', 'binary');
		$response->setHeader('Expires', 0);
		$response->setHeader('Cache-Control', 'must-revalidate, post-check=0, pre-check=0');
		$response->setHeader('Pragma', 'public');
		$response->setHeader('Content-Length', strlen($export));

		ob_clean();
		flush();
		echo $export;

		$this->terminate();
	}
	
	public function importLanguage($fileData, $language){
		$data = json_decode($fileData, TRUE);
		
		$name = $language->getName();
		if(empty($name))
			$language->setName($data['name']);
		
		$translations = array();
		foreach($data['translations'] as $translation){
			$t = new Translation();
			$t->setLanguage($language);
			$t->setKey($translation['key']);
			$t->setTranslation($translation['translation']);
			$t->setBackend($translation['backend']);
			
			$exists = $this->translationExists($t);
			if(!$exists){
				$this->em->persist($t);
				$translations[] = $t;
			}else{
				$exists->setTranslation($translation['translation']);
			}
		}

		$this->em->persist($language);	
		$this->em->flush();
	}
	
	private function translationExists($translation){
		$exists = $this->em->getRepository('AdminModule\Translation')->findOneBy(array(
			'language' => $translation->getLanguage(),
			'key' => $translation->getKey()
		));
		
		if(is_object($exists))		
			return $exists;
		else
			return FALSE;
	}
	
	public function actionUpdateLanguage($id){
		
		if($id) $this->lang = $this->em->find("AdminModule\Language", $id);
		else $this->lang = new Language();
	}
	
	public function actionDeleteLanguage($id){
		$this->lang = $this->em->find("AdminModule\Language", $id);
		$this->em->remove($this->lang);
		$this->em->flush();
		
		$this->flashMessage('Language has been removed.', 'success');
		
		if(!$this->isAjax())
			$this->redirect('Languages:default');
	}
	
	public function renderUpdateLanguage($id){
		
		$this->reloadModalContent();
		
		$this->template->language = $this->lang;
	}
	
	public function languageFormSubmitted(UI\Form $form){
		$values = $form->getValues();
		
		$this->lang->setName($values->name);
		$this->lang->setAbbr($values->abbr);
		$this->lang->setLocale($values->locale);
		$this->lang->setDefaultFrontend($values->defaultFrontend);
		$this->lang->setDefaultBackend($values->defaultBackend);
		
		$this->em->persist($this->lang);
		$this->em->flush();
		
		if($values->import->getTemporaryFile()){
			$qb = $this->em->createQueryBuilder();
			/*$qb->delete('AdminModule\Translation', 'l')
					->where('l.language = ?1')
					->setParameter(1, $this->lang)
					->getQuery()
					->execute();
			$this->em->flush();*/
			
			$content = file_get_contents($values->import->getTemporaryFile());
			$this->importLanguage($content, $this->lang);
		}
			
		// only one item can be default
		if($values->defaultFrontend){
			$qb = $this->em->createQueryBuilder();
			$qb->update('AdminModule\Language', 'l')
					->set('l.defaultFrontend', 0)
					->where('l.id <> ?1')
					->setParameter(1, $this->lang->getId())
					->getQuery()
					->execute();
			$this->em->flush();
		}
		
		if($values->defaultBackend){
			$qb = $this->em->createQueryBuilder();
			$qb->update('AdminModule\Language', 'l')
					->set('l.defaultBackend', 0)
					 ->where('l.id <> ?1')
					->setParameter(1, $this->lang->getId())
					->getQuery()
					->execute();
			$this->em->flush();
		}

		$this->flashMessage('Language has been added.', 'success');
		
		if(!$this->isAjax())
			$this->redirect('Languages:default');
		else{
			$this->invalidateControl('header');
		}
	} 
	
	/* TRANSLATIONS */
	
	public function renderTranslates(){
		$this->reloadContent();
	}
	
	private function getAllLanguages(){
		$languages = $this->em->getRepository('AdminModule\Language')->findAll();
		
		$langs = array('' => $this->translation['Pick a language']);
		foreach($languages as $l){
			$langs[$l->getId()] = $l->getName();
		}
		
		return $langs;
	}
	
	protected function createComponentTranslationGrid($name){
		
		$grid = $this->createGrid($this, $name, "Translation");
		
		$langs = $this->getAllLanguages();
		
		$backend = array(
			'' => $this->translation['Pick filter'],
			0 => $this->translation['No'],
			1 => $this->translation['Yes']
		);
		
		$grid->addColumnText('id', 'ID')->setSortable()->setFilterNumber();
		$grid->addColumnText('key', 'Key')->setSortable()->setFilterText();
		$grid->addColumnText('translation', 'Value')->setSortable()->setCustomRender(function($item){
			return '<div class="translation" contentEditable>' . $item->getTranslation() . '</div>';
		});
		$grid->addColumnText('backend', 'Backend')->setReplacement(array(
			'1' => 'Yes',
			NULL => 'No'
		))->setFilterSelect($backend);
		
		$grid->addColumnText('language', 'Language')->setCustomRender(function($item){
			return $item->getLanguage()->getName();
		})->setSortable();
		
		$grid->addFilterSelect('language', 'Language')->getControl()->setTranslator(NULL)->setItems($langs);
		
		$grid->addActionHref("deleteTranslation", 'Delete')->getElementPrototype()->addAttributes(array('class' => array('btn', 'btn-danger'), 'data-confirm' => 'Are you sure you want to delete the item?'));
		
		$grid->setFilterRenderType(\Grido\Components\Filters\Filter::RENDER_INNER);
		return $grid;
	}
	
	public function actionDeleteTranslation($id){
		$translation = $this->em->find("AdminModule\Translation", $id);
		$this->em->remove($translation);
		$this->em->flush();
		
		$this->flashMessage('Translation has been removed.', 'success');
		
		if(!$this->isAjax())
			$this->redirect('Languages:Translates');
	}
	
	public function handleUpdateTranslation($idTranslation, $value){
		
		$translation = $this->em->find('AdminModule\Translation', trim($idTranslation));
		$translation->setTranslation(trim($value));
		
		$this->em->persist($translation);
		$this->em->flush();
		
		$this->flashMessage('Translation has been added.', 'success');
		
		$this->invalidateControl('flashMessages');
		
		if(!$this->isAjax())
			$this->redirect('Languages:Translates');
	}
	
	/* TRANSLATIONS */
	
	public function renderCloning(){
		$this->reloadContent();
	}
	
	public function createComponentCloningForm(){
		$form = $this->createForm();
		
		$langs = $this->getAllLanguages();
		$packages = \WebCMS\SystemHelper::getPackages();
		
		$form->addGroup('Copy structures');
		
		$form->addSelect('languageFrom', 'Copy from', $langs)->setRequired('Please pick up language.')->setAttribute('class', 'form-control');
		$form->addSelect('languageTo', 'Copy to', $langs)->setRequired('Please pick up language.')->setAttribute('class', 'form-control');
		$form->addCheckbox('removeData', 'Remove data?');
		
		$form->addGroup('Copy data from modules');
		
		foreach($packages as $key => $package){
			
			if($package['vendor'] === 'webcms2' && $package['package'] !== 'webcms2'){
				$object = $this->createObject($package['package']);
				
				if($object->isCloneable()){
					$form->addCheckbox(str_replace('-', '_',$package['package']), $package['package']);
				}else{
					$form->addCheckbox(str_replace('-', '_',$package['package']), $package['package'] . ' not clonable.')->setDisabled(true);
				}
			}
		}
		
		$form->onSuccess[] = callback($this, 'cloningFormSubmitted');
		$form->addSubmit('send', 'Clone');
		
		return $form;
	}
	
	public function cloningFormSubmitted(UI\Form $form){
		$values = $form->getValues();
		
		$languageFrom = $this->em->getRepository('AdminModule\Language')->find($values->languageFrom);
		$languageTo = $this->em->getRepository('AdminModule\Language')->find($values->languageTo);
		$removeData = $values->removeData;
		unset($values->languageFrom);
		unset($values->languageTo);
		unset($values->removeData);
		
		// remove data first
		if($removeData){
			$pages = $this->em->getRepository('AdminModule\Page')->findBy(array(
				'language' => $languageTo,
				'parent' => NULL
			));
			
			foreach($pages as $page){
				$this->em->remove($page);
			}
		}
		
		// clone page structure
		$transformTable = array();
		
		$pages = $this->em->getRepository('AdminModule\Page')->findBy(array(
				'language' => $languageFrom
			), array('lft' => 'asc'));
		
		foreach($pages as $page){
			$new = new Page;
			$new->setLanguage($languageTo);
			$new->setTitle($page->getTitle());
			$new->setPresenter($page->getPresenter());
			$new->setPath('tmp');
			$new->setVisible($page->getVisible());
			$new->setDefault($page->getDefault());
			$new->setClass($page->getClass());
			$new->setModule($page->getModule());
			$new->setModuleName($page->getModuleName());
			
			if($page->getParent()){
				$new->setParent($transformTable[$page->getParent()->getId()]);
			}
			
			$this->em->persist($new);
			$this->em->flush();
			
			$path = $this->em->getRepository('AdminModule\Page')->getPath($new);
			$final = array();
			foreach($path as $p){
				if($p->getParent() != NULL) $final[] = $p->getSlug();
			}
		
			$new->setPath(implode('/', $final));
			$this->em->flush();
			
			$transformTable[$page->getId()] = $new;
		}
		
		foreach($pages as $page){
			// clone boxes settings
			$boxes = $this->em->getRepository('AdminModule\Box')->findBy(array(
				'pageTo' => $page
			));
			
			foreach($boxes as $box){
				$newBox = new Box();
				$newBox->setBox($box->getBox());
				$newBox->setFunction($box->getFunction());
				$newBox->setModuleName($box->getModuleName());
				$newBox->setPresenter($box->getPresenter());
				$newBox->setPageFrom($transformTable[$box->getPageFrom()->getId()]);
				$newBox->setPageTo($transformTable[$box->getPageTo()->getId()]);
				
				$this->em->persist($newBox);
			}
		}
		
		// clone all data
		foreach($values as $key => $value){
			if($value){
				$module = $this->createObject(str_replace('_', '-', $key));
				if($module->isCloneable()){
					$module->cloneData($this->em, $languageFrom, $languageTo, $transformTable);
				}
			}
		}
		
		$this->em->flush();

		$this->flashMessage('Cloning has been successfuly done.', 'success');
		if(!$this->isAjax()){
			$this->redirect('Languages:cloning');
		}
	}
	
	/* TRANSLATOR */
	
	public function renderTranslator(){
		$this->reloadContent();
	}
	
        public function actionTranslator(){
            $this->serviceFactory = new \Webcook\Translator\ServiceFactory();
            
	    $this->translatorService = $this->getTranslateService();
            if(!$this->translatorService instanceof \Webcook\Translator\ITranslator){
                $this->flashMessage('You must fill in API key.', 'danger');
            }
        }
        
	private function getTranslateService(){
	    $serviceId = $this->settings->get('Translate service', \WebCMS\Settings::SECTION_BASIC, 'select')->getValue();
	    
	    switch ($serviceId) {
		case \Webcook\Translator\ServiceFactory::YANDEX:
		    
		    $key = $this->settings->get('Yandex API key', \WebCMS\Settings::SECTION_BASIC)->getValue();
		    
		    if(empty($key)){
			return null;
		    }
		    
		    $service = \Webcook\Translator\ServiceFactory::YANDEX;
		    $params = array(
			'key' => $key
		    );
		    
		    break;
		    
		case \Webcook\Translator\ServiceFactory::GOOGLE:
		    
		    $key = $this->settings->get('Google API key', \WebCMS\Settings::SECTION_BASIC)->getValue();
		    
		    if(empty($key)){
			return null;
		    }
		    
		    $service = \Webcook\Translator\ServiceFactory::GOOGLE;
		    $params = array(
			'key' => $key
		    );
		    
		case \Webcook\Translator\ServiceFactory::BING:
		    
		    $clientId = $this->settings->get('Bing client id', \WebCMS\Settings::SECTION_BASIC)->getValue();
		    $clientSecret = $this->settings->get('Bing client secret', \WebCMS\Settings::SECTION_BASIC)->getValue();
		   
		    if(empty($clientId) || empty($clientSecret)){
			return null;
		    }
		    
		    $service = \Webcook\Translator\ServiceFactory::BING;
		    $params = array(
			'clientId' => $clientId,
			'clientSecret' => $clientSecret
		    );
		    
		default:
		    break;
	    }
	    
	    return $this->serviceFactory->build($service, $params);
	}
	
	private function getLanguages(){
	    
	    $serviceId = $this->settings->get('Translate service', \WebCMS\Settings::SECTION_BASIC, 'select')->getValue();
	    
	    $cache = new \Nette\Caching\Cache($this->getContext()->getService('cacheStorage'), 'htmlFront');
	    
	    if(!$languages = $cache->load('tl' . $serviceId)){
		$languages = $this->translatorService->getLanguages();
		
		$cache->save('tl' . $serviceId, $languages);
	    }
	    
	    return $languages;
	}
	
	public function createComponentTranslatorForm(){
		$form = $this->createForm();
		
                $packages = \WebCMS\SystemHelper::getPackages();
                
                if($this->translatorService instanceof \Webcook\Translator\ITranslator){
                
                    $langs = $this->getLanguages();
                    $langst = array();
                    foreach($langs as $yl){
                        $langst[$yl->getAbbreviation()] = $yl->getName();
                    }

                    $form->addGroup('System');
                    $form->addSelect('systemLanguage', 'System language', $this->getAllLanguages())->setAttribute('class', 'form-control');

                    $form->addGroup('Service');
                    $form->addSelect('languageFrom', 'From', $langst)->setAttribute('class', 'form-control');
                    $form->addSelect('languageTo', 'To', $langst)->setAttribute('class', 'form-control');

                    $form->addGroup('Settings');

                    foreach($packages as $key => $package){

                            if($package['vendor'] === 'webcms2' && $package['package'] !== 'webcms2'){
                                    $object = $this->createObject($package['package']);

                                    if($object->isTranslatable()){
                                            $form->addCheckbox(str_replace('-', '_',$package['package']), $package['package']);
                                    }else{
                                            $form->addCheckbox(str_replace('-', '_',$package['package']), $package['package'] . ' not translatable.')->setDisabled(true);
                                    }
                            }
                    }


                    $form->addSubmit('translate', 'Translate');
                
                }
                
                $form->onSuccess[] = callback($this, 'translatorFormSubmitted');
                
		return $form;
	}
	
	public function translatorFormSubmitted(UI\Form $form){
		$values = $form->getValues();
		$from = $values->languageFrom;
                $to = $values->languageTo;
                $language = $this->em->getRepository('AdminModule\Language')->find($values->systemLanguage);
               
                // clear values
                unset($values->languageFrom);
                unset($values->languageTo);
                unset($values->systemLanguage);
                
                $pages = $this->em->getRepository('AdminModule\Page')->findBy(array(
				'language' => $language
			), array('lft' => 'asc'));
		
                foreach($pages as $page){
                    $t = $this->translatorService->translate($page->getTitle(), $from, $to);
                    $page->setTitle($t->getTranslation());
                }
                
                // clone all data
		foreach($values as $key => $value){
			if($value){
				$module = $this->createObject(str_replace('_', '-', $key));
				if($module->isTranslatable()){
					$module->translateData($this->em, $language, $from, $to, $this->translatorService);
				}
			}
		}
                
                $this->em->flush();
                
                $this->flashMessage('Translation of language finished.', 'success');
                if(!$this->isAjax()){
                    $this->redirect('Languages:translator');
                }
	}
}