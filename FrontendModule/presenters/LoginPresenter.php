<?php

namespace FrontendModule;

use Nette\Application\UI;
use Kdyby\BootstrapFormRenderer\BootstrapRenderer;
use Nette\Security as NS;

class LoginPresenter extends \FrontendModule\BasePresenter
{
    public function beforeRender()
    {
        parent::beforeRender();

        if ($this->getUser()->isLoggedIn()) {
            $this->forward('Homepage:');
        }
    }

    protected function startup()
    {
        parent::startup();
    }

    /**
     * Sign in form component factory.
     * @return UI\Form
     */
    protected function createComponentSignInForm($name)
    {
        $form = new UI\Form();

        $form->getElementPrototype()->action = $this->link('default', array(
            'path' => 'loginfe',
            'abbr' => $this->abbr,
            'do' => 'signInForm-submit',
        ));

        $form->setRenderer(new BootstrapRenderer());

        $form->addText('username', 'Přihlašovací jméno')
            ->setRequired('Please provide a username.')
            ->setAttribute('placeholder', $this->translation['Username']);

        $form->addPassword('password', 'Heslo')
            ->setRequired('Please provide a password.')
            ->setAttribute('placeholder', $this->translation['Password']);

        $form->addCheckbox('remember', 'Permanent login?');

        $form->addSubmit('send', 'Přihlásit');

        $form->onSuccess[] = callback($this, 'signInFormSubmitted');

        return $form;
    }

    public function renderDefault()
    {
        $this->setLayout('login');
    }

    public function signInFormSubmitted($form)
    {

        try {
            $values = $form->getValues();
            if ($values->remember) {
                $this->getUser()->setExpiration('+ 14 days', false);
            } else {
                $this->getUser()->setExpiration('+ 20 minutes', true);
            }
            $this->getUser()->login($values->username, $values->password);
            $this->flashMessage('Přihlášení bylo úspěšné', 'success');
            $this->forward('Homepage:');
        } catch (NS\AuthenticationException $e) {
            $this->flashMessage($this->translation[$e->getMessage()], 'danger');
            $form->addError($e->getMessage());
        }
    }

}
