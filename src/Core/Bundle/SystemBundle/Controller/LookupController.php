<?php

namespace Kula\Core\Bundle\SystemBundle\Controller;

use Kula\Core\Bundle\FrameworkBundle\Controller\Controller;

class LookupController extends Controller {
  
  public function indexAction() {
    $this->authorize();
    $this->processForm();
    $this->setRecordType('Core.Lookup');
    
    $values = array();
    
    if ($this->record->getSelectedRecordID()) {
      // Get Fields
      $values = $this->db()->db_select('CORE_LOOKUP_VALUES')
        ->fields('CORE_LOOKUP_VALUES', array('LOOKUP_VALUE_ID', 'LOOKUP_TABLE_ID', 'CODE', 'DESCRIPTION', 'SORT', 'INACTIVE_AFTER', 'CONVERSION'))
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
    $this->setRecordType('LOOKUP', 'Y');
    return $this->render('KulaCoreSystemBundle:Lookup:values.html.twig');
  }
  
  public function createAction() {
    $this->authorize();
    $this->processForm();
    $id = $this->poster->getResultForTable('insert', 'CORE_LOOKUP')[0];
    return $this->forward('core_system_lookup', array('record_type' => 'LOOKUP', 'record_id' => $id), array('record_type' => 'LOOKUP', 'record_id' => $id));
  }
  
  public function deleteAction() {
    $this->authorize();
    $this->setRecordType('LOOKUP');
    
    $rows_affected = $this->db()->delete('CORE_LOOKUP')
        ->predicate('LOOKUP_ID', $this->record->getSelectedRecordID())->execute();
    
    if ($rows_affected == 1) {
      $this->flash->add('success', 'Deleted lookup table.');
    }
    
    return $this->forward('core_system_lookup');
  }
  
  public function chooserAction() {
    $this->authorize();
    $data = \Kula\Bundle\Core\SystemBundle\Chooser\LookupChooser::createChooserMenu($this->request->query->get('q'));
    return $this->JSONResponse($data);
  }
  
}