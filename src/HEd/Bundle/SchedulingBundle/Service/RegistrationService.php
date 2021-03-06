<?php

namespace Kula\HEd\Bundle\SchedulingBundle\Service;

class RegistrationService {
  
  public function __construct(\Kula\Core\Component\DB\DB $db, 
                              \Kula\Core\Component\DB\PosterFactory $poster_factory,
                              $record = null, 
                              $session = null,
                              $student_service = null) {
    $this->db = $db;
    $this->record = $record;
    $this->posterFactory = $poster_factory;
    $this->session = $session;
    $this->student_service = $student_service;
  }
  
  public function getAvailableRegistration($student_id) {
    $registrations = array();
    $student_regs = array();
    
    // Get current student status info
    $current_student_status = array();
    $current_student_status_result = $this->db->db_select('STUD_STUDENT_STATUS', 'stustatus')
      ->fields('stustatus', array('STATUS', 'GRADE', 'LEVEL', 'STUDENT_ID'))
      ->join('CORE_ORGANIZATION_TERMS', 'orgterms', 'orgterms.ORGANIZATION_TERM_ID = stustatus.ORGANIZATION_TERM_ID')
      ->fields('orgterms', array('ORGANIZATION_ID'))
      ->join('CORE_ORGANIZATION', 'org', 'org.ORGANIZATION_ID = orgterms.ORGANIZATION_ID')
      ->join('CORE_TERM', 'term', 'term.TERM_ID = orgterms.TERM_ID')
      ->condition('stustatus.STUDENT_ID', $student_id)
      ->condition('term.START_DATE', date('Y-m-d'), '<=')
      ->orderBy('START_DATE', 'desc', 'term')
      ->execute();
    while ($current_student_status_row = $current_student_status_result->fetch()) {
      if (!isset($current_student_status[$current_student_status_row['ORGANIZATION_ID']]))
        $current_student_status[$current_student_status_row['ORGANIZATION_ID']] = $current_student_status_row; 
    }
    
    // Get student registrations
    $stu_reg_info = $this->db->db_select('STUD_STUDENT_REGISTRATION', 'stureg')
      ->fields('stureg')
      ->join('CORE_ORGANIZATION_TERMS', 'orgterms', 'orgterms.ORGANIZATION_TERM_ID = stureg.ORGANIZATION_TERM_ID')
      ->join('CORE_ORGANIZATION', 'org', 'org.ORGANIZATION_ID = orgterms.ORGANIZATION_ID')
      ->fields('org', array('ORGANIZATION_NAME', 'ORGANIZATION_ID'))
      ->join('CORE_TERM', 'term', 'term.TERM_ID = orgterms.TERM_ID')
      ->fields('term', array('TERM_ABBREVIATION'))
      ->leftJoin('STUD_STUDENT_STATUS', 'stustatus', 'stustatus.ORGANIZATION_TERM_ID = orgterms.ORGANIZATION_TERM_ID AND stustatus.STUDENT_ID = '.$student_id)
      ->fields('stustatus', array('STUDENT_STATUS_ID'))
      ->condition('stureg.STUDENT_ID', $student_id)
      ->condition('term.START_DATE', date('Y-m-d'), '>=')
      ->execute();
    while ($stu_reg_row = $stu_reg_info->fetch()) {
      $student_regs[$stu_reg_row['ORGANIZATION_ID']] = $stu_reg_row;
    }
    
    return $student_regs;
    
  }
  
  public function enroll($registration_id) {

    $transaction = $this->db->db_transaction();
    
    // Get student registration info
    $stu_reg_info = $this->db->db_select('STUD_STUDENT_REGISTRATION', 'stureg')
      ->fields('stureg')
      ->join('CORE_ORGANIZATION_TERMS', 'orgterms', 'orgterms.ORGANIZATION_TERM_ID = stureg.ORGANIZATION_TERM_ID')
      ->join('CORE_ORGANIZATION', 'org', 'org.ORGANIZATION_ID = orgterms.ORGANIZATION_ID')
      ->fields('org', array('ORGANIZATION_NAME', 'ORGANIZATION_ID'))
      ->join('CORE_TERM', 'term', 'term.TERM_ID = orgterms.TERM_ID')
      ->fields('term', array('TERM_ABBREVIATION', 'START_DATE'))
      ->condition('stureg.REGISTRATION_ID', $registration_id)
      ->execute()->fetch();
    
    // Get current student status
    $current_student_status = $this->db->db_select('STUD_STUDENT_STATUS', 'stustatus')
      ->fields('stustatus', array('STATUS', 'GRADE', 'LEVEL', 'STUDENT_ID', 'RESIDENT', 'SEEKING_DEGREE_1_ID', 'ENTER_TERM_ID', 'ADMISSIONS_COUNSELOR_ID', 'COHORT', 'ADVISOR_ID'))
      ->join('CORE_ORGANIZATION_TERMS', 'orgterms', 'orgterms.ORGANIZATION_TERM_ID = stustatus.ORGANIZATION_TERM_ID')
      ->fields('orgterms', array('ORGANIZATION_ID'))
      ->join('CORE_ORGANIZATION', 'org', 'org.ORGANIZATION_ID = orgterms.ORGANIZATION_ID')
      ->join('CORE_TERM', 'term', 'term.TERM_ID = orgterms.TERM_ID')
      ->leftJoin('STUD_STUDENT_COURSE_HISTORY_TERMS', 'crshisterms', 'crshisterms.STUDENT_STATUS_ID = stustatus.STUDENT_STATUS_ID')
      ->fields('crshisterms', array('CUM_HOURS'))
      ->condition('stustatus.STUDENT_ID', $stu_reg_info['STUDENT_ID'])
      ->condition('term.START_DATE', date('Y-m-d'), '<=')
      ->condition('org.ORGANIZATION_ID', $stu_reg_info['ORGANIZATION_ID'])
      ->orderBy('START_DATE', 'desc', 'term')
      ->execute()->fetch();
  
    
    $enrollmentInfo = array('StudentID' => $stu_reg_info['STUDENT_ID'], 
                            'OrganizationTermID' => $stu_reg_info['ORGANIZATION_TERM_ID'],
                            'HEd.Student.Status.Grade' => $stu_reg_info['GRADE'],
                            'HEd.Student.Status.Level' => $stu_reg_info['LEVEL'],
                            'HEd.Student.Status.Resident' => $current_student_status['RESIDENT'],
                            'HEd.Student.Status.EnterDate' => $stu_reg_info['START_DATE'],
                            'HEd.Student.Status.EnterCode' => $stu_reg_info['ENTER_CODE'],
                            'SeekingDegree1ID' => $current_student_status['SEEKING_DEGREE_1_ID'],
                            'EnterTermID' => $current_student_status['ENTER_TERM_ID'],
                            'AdmissionsCounselorID' => $current_student_status['ADMISSIONS_COUNSELOR_ID'],
                            'Cohort' => $current_student_status['COHORT'],
                            'AdvisorID' => $current_student_status['ADVISOR_ID'],
                            'SeekingDegree1ID' => $current_student_status['SEEKING_DEGREE_1_ID'],
    );

    $enrollment = $this->student_service->enrollStudent($enrollmentInfo, array('VERIFY_PERMISSIONS' => false));

    if ($enrollment) {
      $transaction->commit();
      return $enrollment;
    } else {
      $transaction->rollback();
    }
    
  }
}