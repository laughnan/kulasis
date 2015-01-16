<?php

namespace Kula\Core\Bundle\LoginBundle\Controller;

use Kula\Core\Bundle\FrameworkBundle\Controller\Controller;
use Kula\Core\Component\Navigation\Navigation;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\RedirectResponse;

class LoginController extends Controller {

  public function loginAction() {

    $google = $this->get('kula.login.auth.googleapi');
    
    try { 
      $google->authenticate();
    } catch (\Google_Auth_Exception $e) {
      $this->get('kula.login')->logout();
      $this->addFlash('info', 'You have been logged out.');
      return $this->redirect('login');
    }
    
    if ($google->getEmailAddress() AND $this->get('session')->get('google_stop') !== true) {
      return $this->doLoginAction($google->getEmailAddress());
    } 
    
    $this->get('session')->set('google_stop', false);
    return $this->render('KulaCoreLoginBundle:Login:login.html.twig', array('authurl' => $google->getAuthURL()));    
  }
  
  public function logoutAction() {
    $this->get('kula.login')->logout();
    $this->addFlash('info', 'You have been successfully logged out.');
    return $this->redirect('login');
  }
  
  public function doLoginAction($username = null) {
    
    if ($this->getRequest()->get('username') && $this->getRequest()->get('password') || $username) {
      
      // perform authentication
      if ($this->get('kula.login')->login(($username !== null ) ? $username : $this->getRequest()->get('username'), $this->getRequest()->get('password'))) {
      
        // Determine first route that can be used
        //$first_route = Navigation::getFirstAvailableRouteForm($this->get('kula.session')->get('portal'));
        $first_route = 'core_system_lookup';
        if ($first_route)
          return $this->redirect($this->get('router')->generate($first_route));
        else {
          $this->addFlash('error', 'Unable to determine first route.  You have been logged out.');
          return $this->redirect('login');
        }
        
      } else {
        $this->get('session')->set('google_stop', true);
        $this->addFlash('error', 'Invalid Username/Password combination.');
        return $this->redirect('login');
      }
    } else {
      $this->addFlash('error', 'Missing username and/or password.');  
      return $this->redirect('login');
    }
  }
  
  public function change_usergroupAction() {
    $new_usergroup = $this->getRequest()->request->get('focus_usergroup');
    
    $this->get('kula.login')->changeRole($new_usergroup);
    // Determine first route that can be used
    $first_route = Navigation::getFirstAvailableRouteForm($this->get('kula.session')->get('portal'));
    if ($first_route) {
      return $this->redirect($this->get('router')->generate($first_route));
    } else {
      $this->get('kula.login')->logout();
      $this->addFlash('error', 'Unable to determine first route.  You have been logged out.');
      return $this->redirect('/login');
    }
  }
}