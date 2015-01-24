<?php

namespace Kula\Core\Bundle\SystemBundle\Controller;

use Kula\Core\Bundle\FrameworkBundle\Controller\Controller;

class CoreUsersController extends Controller {
  
  public function indexAction() {
    $this->authorize();
    $this->processForm();
    $this->setRecordType('Core.User');
    
    $user = $this->db()->db_select('CORE_USER', 'user')
      ->fields('user')
      ->condition('USER_ID', $this->record->getSelectedRecordID())
      ->execute()->fetch();
    
    return $this->render('KulaCoreSystemBundle:Users:index.html.twig', array('user' => $user));
  }
  
  public function user_groupsAction() {
    $this->authorize();
    $this->processForm();
    $this->setRecordType('Core.User');
    
    $usergroups = $this->db()->db_select('CORE_USER_ROLES', 'usrgrps')
      ->fields('usrgrps', array('ROLE_ID', 'ORGANIZATION_ID', 'TERM_ID', 'DEFAULT_ROLE', 'ADMINISTRATOR'))
      ->join('CORE_USERGROUP', 'usrgrp', 'usrgrps.USERGROUP_ID = usrgrp.USERGROUP_ID')
      ->fields('usrgrp', array('USERGROUP_ID'))
      ->condition('USER_ID', $this->record->getSelectedRecordID())
      ->orderBy('USERGROUP_NAME', 'ASC')
      ->execute()->fetchAll();
    
    return $this->render('KulaCoreSystemBundle:Users:user_groups.html.twig', array('usergroups' => $usergroups));
  }
  
  public function addAction() {
    $this->authorize();
    $this->setSubmitMode('search');
    $constituents = array();
    
    $add = $this->request->request->get('add');
    
    if (isset($add['Core.User']['new']['Core.User.ID'])) {
      $this->processForm();
      $user_id = $this->poster->getPosterRecord('Core.User', 'new')->getField('Core.User.ID');
      return $this->forward('core_system_users', array('record_type' => 'Core.User', 'record_id' => $user_id), array('record_type' => 'Core.User', 'record_id' => $user_id));
    }
    
    if ($this->request->request->get('search')) {
      $query = $this->searcher->prepareSearch($this->request->request->get('search'), 'CONS_CONSTITUENT', 'CONSTITUENT_ID');
      $query = $query->fields('CONS_CONSTITUENT', array('CONSTITUENT_ID', 'LAST_NAME', 'FIRST_NAME', 'MIDDLE_NAME'));
      $query = $query->leftJoin('CORE_USER', 'user', 'user.USER_ID = CONS_CONSTITUENT.CONSTITUENT_ID');
      $query = $query->fields('user', array('USER_ID'));
      $query = $query->orderBy('LAST_NAME', 'ASC');
      $query = $query->orderBy('FIRST_NAME', 'ASC');
      $query = $query->range(0, 100);
      $constituents = $query->execute()->fetchAll();
    }
    
    return $this->render('KulaCoreSystemBundle:Users:add.html.twig', array('constituents' => $constituents));
  }
  
  public function add_constituentAction() {
    $this->authorize();
    $this->formAction('core_system_users_create_constituent');
    return $this->render('KulaCoreSystemBundle:Users:add_constituent.html.twig');
  }
  
  public function create_constituentAction() {
    $this->authorize();
    
    $transaction = $this->db()->db_transaction('create_user');
    
    try {
      // get constituent data
      $constituentPoster = $this->poster();
      $constituentPoster->add('Constituent.Constituent', 'new', $this->request->request->get('add')['Constituent.Constituent']['new']);
      $constituentPoster->process();
      $constituent_id = $constituentPoster->getPosterRecord('Constituent.Constituent', 'new')->getID();

      // get user data
      $user_addition = $this->request->request->get('add')['Core.User']['new'];
      $user_addition['Core.User.ID'] = $constituent_id;
      // Post data
      $userPoster = $this->poster();
      $userPoster->add('Core.User', 'new', $user_addition);
      $userPoster->process();
      // Get user ID
      $user_id = $userPoster->getPosterRecord('Core.User', 'new')->getField('Core.User.ID');
    
      return $this->forward('core_system_users', array('record_type' => 'Core.User', 'record_id' => $user_id), array('record_type' => 'Core.User', 'record_id' => $user_id));
    } catch (\Exception $e) {
      $transaction->rollback();
      throw new \Exception($e);
    }
  }
  
  public function deleteAction() {
    $this->authorize();
    $this->setRecordType('Core.User');
    
    $rows_affected = $this->db()->db_delete('CORE_USER')
        ->condition('USER_ID', $this->record->getSelectedRecordID())->execute();
    
    if ($rows_affected == 1) {
      $this->flash->add('success', 'Deleted user.');
    }
    
    return $this->forward('core_system_users');
  }
  
}