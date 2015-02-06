<?php

namespace Kula\HEd\Bundle\GradingBundle\Controller;

use Kula\Core\Bundle\FrameworkBundle\Controller\Controller;

class SISCourseHistoryController extends Controller {
  
  public function coursehistoryAction() {
    $this->authorize();
    //$this->processForm();
    $this->setRecordType('SIS.HEd.Student');

    $non = $this->request->request->get('non');
    
    // Add new course history records
    if ($this->request->request->get('add')) {
      $course_history_service = $this->get('kula.HEd.grading.coursehistory');
      $new_ch = $this->request->request->get('add')['STUD_STUDENT_COURSE_HISTORY'];
      unset($new_ch['new_num']);
      foreach($new_ch as $ch_id => $ch_data) {
        $new_ch = $ch_data;
        if (isset($non['STUD_STUDENT_COURSE_HISTORY']['ORGANIZATION_ID']['value']))
          $new_ch[$ch_id]['ORGANIZATION_ID'] = $non['STUD_STUDENT_COURSE_HISTORY']['ORGANIZATION_ID']['value'];
        elseif (isset($non['STUD_STUDENT_COURSE_HISTORY']['NON_ORGANIZATION_ID']['value']))
          $new_ch[$ch_id]['NON_ORGANIZATION_ID'] = $non['STUD_STUDENT_COURSE_HISTORY']['NON_ORGANIZATION_ID']['value'];
        
        $student_course_history_id = $course_history_service->insertCourseHistoryForCH($new_ch);
      }
    }
    
    // Edit course history records
    if ($this->request->request->get('edit')) {
      $course_history_service = $this->get('kula.HEd.grading.coursehistory');
      $edit_ch = $this->request->request->get('edit')['STUD_STUDENT_COURSE_HISTORY'];
      foreach($edit_ch as $edit_id => $edit_data) {
        $edit_ch = $edit_data;
        if (isset($non['STUD_STUDENT_COURSE_HISTORY']['ORGANIZATION_ID']['value']))
          $edit_ch[$edit_id]['ORGANIZATION_ID'] = $non['STUD_STUDENT_COURSE_HISTORY']['ORGANIZATION_ID']['value'];
        elseif (isset($non['STUD_STUDENT_COURSE_HISTORY']['NON_ORGANIZATION_ID']['value']))
          $edit_ch[$edit_id]['NON_ORGANIZATION_ID'] = $non['STUD_STUDENT_COURSE_HISTORY']['NON_ORGANIZATION_ID']['value'];
        $student_course_history_id = $course_history_service->updateCourseHistoryForCH($edit_id, $edit_ch);
      }
    }
    
    // Delete course history records
    if ($this->request->request->get('delete')) {
      $delete_course_history_poster = new \Kula\Component\Database\PosterFactory();
      $student_course_history_poster = $delete_course_history_poster->newPoster(null, null, $this->request->request->get('delete'));
    }
    
    $course_history = array();
    
    if ($this->record->getSelectedRecordID()) {
    
      $course_history = $this->db()->db_select('STUD_STUDENT_COURSE_HISTORY', 'ch')
        ->fields('ch', array('COURSE_HISTORY_ID', 'CALENDAR_MONTH', 'CALENDAR_YEAR', 'TERM', 'COURSE_ID', 'COURSE_NUMBER', 'COURSE_TITLE', 'LEVEL', 'CREDITS_ATTEMPTED', 'CREDITS_EARNED', 'MARK_SCALE_ID', 'MARK'))
        ->condition('STUDENT_ID', $this->record->getSelectedRecordID())
        ->orderBy('ch.CALENDAR_YEAR', 'ASC')
        ->orderBy('ch.CALENDAR_MONTH', 'ASC')
        ->execute()->fetchAll();
          
    }

    return $this->render('KulaHEdGradingBundle:SISCourseHistory:coursehistory.html.twig', array('course_history' => $course_history));
  }
  
  public function detailAction($id, $sub_id) {
    $this->authorize();
    $this->processForm();
    $this->setRecordType('SIS.HEd.Student');
      
    $course_history = array();
    
    $course_history = $this->db()->db_select('STUD_STUDENT_COURSE_HISTORY', 'ch')
      ->fields('ch', array('COURSE_HISTORY_ID', 'COURSE_ID', 'ORGANIZATION_ID', 'NON_ORGANIZATION_ID', 'START_DATE', 'COMPLETED_DATE', 'INSTRUCTOR_ID', 'INSTRUCTOR', 'MARK_SCALE_ID', 'GPA_VALUE', 'QUALITY_POINTS', 'STUDENT_CLASS_ID', 'DEGREE_REQ_GRP_ID'))
      ->condition('STUDENT_ID', $this->record->getSelectedRecordID())
      ->condition('COURSE_HISTORY_ID', $sub_id)
      ->execute()->fetch();

    return $this->render('KulaHEdGradingBundle:SISCourseHistory:coursehistory_detail.html.twig', array('course_history' => $course_history));  
  }
  
}