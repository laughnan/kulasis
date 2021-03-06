<?php

namespace Kula\Core\Bundle\SystemBundle\Controller;

use Kula\Core\Bundle\FrameworkBundle\Controller\Controller;

class CoreLookupController extends Controller {
  
  public function indexAction() {
    $this->authorize();
    $this->processForm();
    if ($this->session->get('portal') == 'sis') {
      $this->setRecordType('SIS.Lookup');
    } else {
      $this->setRecordType('Core.Lookup');
    }
    
    $values = array();
    
    if ($this->record->getSelectedRecordID()) {
      // Get Fields
      $values = $this->db()->db_select('CORE_LOOKUP_VALUES')
        ->fields('CORE_LOOKUP_VALUES')
        ->condition('LOOKUP_TABLE_ID', $this->record->getSelectedRecordID())
        ->orderBy('SORT', 'ASC')
        ->orderBy('CODE', 'ASC')
        ->execute()->fetchAll();
    }
    
    return $this->render('KulaCoreSystemBundle:Lookup:values.html.twig', array('values' => $values));
  }
  
  public function addAction() {
    $this->authorize();
    $this->formAction('core_system_lookup_create');
    $this->setRecordType('Core.Lookup', 'Y');
    return $this->render('KulaCoreSystemBundle:Lookup:values.html.twig');
  }
  
  public function createAction() {
    $this->authorize();
    $this->processForm();
    $id = $this->poster->getPosterRecord('Core.Lookup.Table', 0)->getID();
    return $this->forward('core_system_lookup', array('record_type' => 'Core.Lookup', 'record_id' => $id), array('record_type' => 'Core.Lookup', 'record_id' => $id));
  }
  
  public function deleteAction() {
    $this->authorize();
    $this->setRecordType('Core.Lookup');
    
    $rows_affected = $this->db()->db_delete('CORE_LOOKUP_TABLES')
        ->condition('LOOKUP_TABLE_ID', $this->record->getSelectedRecordID())->execute();
    
    if ($rows_affected == 1) {
      $this->flash->add('success', 'Deleted lookup table.');
    }
    
    return $this->forward('core_system_lookup');
  }
  
  public function chooserAction() {
    $this->authorize();
    $data = $this->chooser('Core.Lookup')->createChooserMenu($this->request->query->get('q'));
    return $this->JSONResponse($data);
  }
  
}