<?php
namespace Frontend\Login\Controller;

use Auth\Entity\Benutzer;
use Frontend\Application\Controller\AbstractFrontendController;
use Frontend\Login\Form\Login;
use Auth\Acl\Acl;
use Frontend\Login\Form\Register;
use Zend\View\Model\ViewModel;

class LoginController extends AbstractFrontendController
{

    public function registerAction()
    {
        $registerForm = new Register();
        $registerForm->setInputFilter(new \Frontend\Login\Form\Filter\Register(
            $this->getEntityManager()
        ));

        $loginForm = new Login();
        $loginForm->init();

        if( $this->request->isPost() ) {

            $registerForm->setData(
                $this->getRequest()->getPost()
            );

            if( $registerForm->isValid() )
            {
                $data = $registerForm->getData();

                $benutzer = new Benutzer();
                $benutzer->setUsername( $data['username'] );
                $benutzer->setPassword( md5($data['password']) );
                $benutzer->setEmail( $data['email'] );
                $benutzer->setDisabled( false );
                $benutzer->setRegisterDate( date('c') );

                $gruppe = $this->getEntityManager()->getReference('Auth\Entity\Role', 1);
                $benutzer->setGruppe($gruppe);

                $this->getEntityManager()->persist( $benutzer );
                $this->getEntityManager()->flush();

                // login
                /** @var Acl $authService */
                $authService = $this->getServiceLocator()->get('AuthService');
                $authService->instantLogin( $benutzer );

                return $this->redirect()->toRoute('frontend/user/home');

            } else {
                $registerForm->populateValues(
                    $this->getRequest()->getPost()
                );
            }
        }

        $viewModel = new ViewModel;
        $viewModel->setVariable('loginForm', $loginForm);
        $viewModel->setVariable('registerForm', $registerForm);
        $viewModel->setTemplate('/login/login.phtml');
        return $viewModel;
    }


    /**
     * Administrationsbereich Login
     *
     * @return ViewModel
     */
    public function loginAction() {

    	// wenn access vorhanden direkt weiter
    	if( $this->hasAccess('frontend/dashboard/access') ) {
    		return $this->redirect()->toRoute('frontend/user/home');
    	}

    	// form
        $registerForm = new Register();
    	$loginForm = new Login();
    	$loginForm->init();

    	if( $this->request->isPost() ) {
    	
    		$username = $this->request->getPost('username');
    		$password = $this->request->getPost('password');
    		
    		$authService = $this->getServiceLocator()->get('AuthService');
            $loggedIn = $authService->login( $username, $password);

    		if( $loggedIn == Acl::LOGIN_WRONG ) {

    			$this->Message()->addErrorMessage('Username/Password combination wrong!');

    		} elseif( $loggedIn == Acl::LOGIN_DISABLED ) {

    			$this->Message()->addInfoMessage('Your Account was banned!');
    		
    		} elseif ( $loggedIn == Acl::LOGIN_SUCCESS ) {

    			// last login
                /** @var Benutzer $benutzer */
    			$benutzer = $this->identity();
    			$benutzer->setLastLogin(date('c'));

    			$this->getEntityManager()->merge( $benutzer );
    			$this->getEntityManager()->flush();
    			
    			return $this->redirect()->toRoute('frontend/user/home');
    		}
    	}
    	 
    	$viewModel = new ViewModel;
        $viewModel->setVariable('loginForm', $loginForm);
        $viewModel->setVariable('registerForm', $registerForm);
        $viewModel->setTemplate('/login/login.phtml');
    	return $viewModel;
    }

    /**
     * Administrationsbereich Logout
     *
     * @return mixed
     */
    public function logoutAction(){

        $authService = $this->getServiceLocator()->get('AuthService');

        if( $authService->hasIdentity() ) {

            $authService->clearIdentity();
            $this->Message()->addSuccessMessage('LOGIN_TEXT_LOGOUT_SUCCESS');
        }

    	return $this->redirect()->toRoute('frontend');
    }
}
