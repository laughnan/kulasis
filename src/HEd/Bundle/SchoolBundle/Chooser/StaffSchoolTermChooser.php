<?php

namespace Kula\HEd\Bundle\SchoolBundle\Chooser;

use Kula\Core\Component\Chooser\Chooser;

class StaffSchoolTermChooser extends Chooser {
  
  public function search($q) {
    
    $query_conditions = $this->db()->db_and();
    $query_conditions = $query_conditions->condition('stafforgtrm.ORGANIZATION_TERM_ID', $this->focus()->getOrganizationTermIDs());
    
    $query_conditions_or = $this->db()->db_or();
    $query_conditions_or = $query_conditions_or->condition('ABBREVIATED_NAME', $q.'%', 'LIKE');
    
    $query_conditions = $query_conditions->condition($query_conditions_or);
    
    $data = array();
    
    $search = $this->db()->db_select('STUD_STAFF_ORGANIZATION_TERMS', 'stafforgtrm')
      ->fields('stafforgtrm', array('STAFF_ORGANIZATION_TERM_ID'))
      ->join('STUD_STAFF', 'staff', 'stafforgtrm.STAFF_ID = staff.STAFF_ID')
      ->fields('staff', array('ABBREVIATED_NAME'))
      ->condition($query_conditions)
      ->orderBy('ABBREVIATED_NAME', 'ASC');
    $search = $search->execute();
    while ($row = $search->fetch()) {
      $this->addToChooserMenu($row['STAFF_ORGANIZATION_TERM_ID'], $row['ABBREVIATED_NAME']);
    }
    
  }
  
  public function choice($id) {
    $row = $this->db()->db_select('STUD_STAFF_ORGANIZATION_TERMS', 'stafforgtrm')
      ->fields('stafforgtrm', array('STAFF_ORGANIZATION_TERM_ID'))
      ->join('STUD_STAFF', 'staff', 'stafforgtrm.STAFF_ID = staff.STAFF_ID')
      ->fields('staff', array('ABBREVIATED_NAME'))
      ->condition('stafforgtrm.STAFF_ORGANIZATION_TERM_ID', $id)
      ->execute()
      ->fetch();
    return $this->currentValue($row['STAFF_ORGANIZATION_TERM_ID'], $row['ABBREVIATED_NAME']);
  }
  
  public function searchRoute() {
    return 'core_HEd_school_staff_orgterm_chooser';
  }
  
}