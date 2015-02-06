<?php

namespace Kula\HEd\Bundle\GradingBundle\Controller;

use Kula\Core\Bundle\FrameworkBundle\Controller\Controller;

class SISGPAController extends Controller {
  
  public function indexAction() {
    $this->authorize();
    $this->processForm();
    $this->setRecordType('SIS.HEd.Student');
    
    $gpa = array();
    
    if ($this->record->getSelectedRecordID()) {
    $gpa = $this->db()->db_select('STUD_STUDENT_GPA', 'stugpa')
      ->fields('stugpa', array('LEVEL', 'TERM_CREDITS_ATTEMPTED', 'TERM_CREDITS_EARNED', 'TERM_GPA_CREDITS_ATTEMPTED', 'TERM_QUALITY_POINTS', 'TERM_GPA_VALUE', 'CUM_CREDITS_ATTEMPTED', 'CUM_CREDITS_EARNED', 'CUM_GPA_CREDITS_ATTEMPTED', 'CUM_QUALITY_POINTS', 'CUM_GPA_VALUE'))
      ->join('CORE_TERM', 'term', array('TERM_ABBREVIATION'), 'term.TERM_ID = stugpa.TERM_ID')
      ->predicate('stugpa.STUDENT_ID', $this->record->getSelectedRecordID())
      ->order_by('START_DATE', 'DESC', 'term')
      ->execute()->fetchAll();    
    }

    return $this->render('KulaHEdGradingBundle:SISGPA:gpa.html.twig', array('gpa' => $gpa));  
  }
  
}