<?php

namespace Kula\K12\Bundle\SchedulingBundle\Service;

class ScheduleService {
  
  protected $database;
  
  protected $poster_factory;
  
  protected $record;
  
  public function __construct(\Kula\Core\Component\DB\DB $db, 
                              \Kula\Core\Component\DB\PosterFactory $poster_factory,
                              $record = null, 
                              $session = null,
                              $billing = null) {
    $this->database = $db;
    $this->record = $record;
    $this->posterFactory = $poster_factory;
    $this->session = $session;
    $this->billing = $billing;
  }
  
  public function addClassForStudentStatus($student_status_id, $section_id, $start_date) {
    
    // Get Seciton Info
    $section_info = $this->database->db_select('STUD_SECTION')
      ->fields('STUD_SECTION', array('START_DATE', 'CREDITS', 'MARK_SCALE_ID'))
      ->condition('SECTION_ID', $section_id)
      ->execute()->fetch();
    // Get Student Status
    $student_status_info = $this->database->db_select('STUD_STUDENT_STATUS')
      ->fields('STUD_STUDENT_STATUS', array('LEVEL'))
      ->leftJoin('BILL_TUITION_RATE', 'tuitionrate', 'tuitionrate.TUITION_RATE_ID = STUD_STUDENT_STATUS.TUITION_RATE_ID')
      ->fields('tuitionrate', array('BILLING_MODE'))
      ->condition('STUDENT_STATUS_ID', $student_status_id)
      ->execute()->fetch();
      
    $class_info['K12.Student.Class.StudentStatusID'] = $student_status_id;  
    $class_info['K12.Student.Class.SectionID'] = $section_id;
    $class_info['K12.Student.Class.CreditsAttempted'] = $section_info['CREDITS'];
    $class_info['K12.Student.Class.Level'] = $student_status_info['LEVEL'];
  
    if ($section_info['START_DATE'] < $start_date)
      $class_info['K12.Student.Class.StartDate'] = $start_date;
    else
      $class_info['K12.Student.Class.StartDate'] = $section_info['START_DATE'];
    
    $transaction = $this->database->db_transaction();
    
    $student_class_id = $this->posterFactory->newPoster()->add('K12.Student.Class', 'new', $class_info)->process()->getResult();
    
    // check if exists in wait list
    $waitlist_info = $this->database->db_select('STUD_STUDENT_WAIT_LIST')
      ->fields('STUD_STUDENT_WAIT_LIST', array('STUDENT_WAIT_LIST_ID'))
      ->condition('STUDENT_STATUS_ID', $student_status_id)
      ->condition('SECTION_ID', $section_id)
      ->execute()->fetch();
    if ($waitlist_info['STUDENT_WAIT_LIST_ID']) {
      $waitlist_poster = $this->posterFactory->newPoster()->delete('K12.Student.WaitList', $waitlist_info['STUDENT_WAIT_LIST_ID'])->process();
    }
    
    // process course fees
    if ($student_status_info['BILLING_MODE'] == 'FEES') {
      $this->billing->addCourseFees($student_class_id);
    }
    
    if ($student_class_id) {
      
      // Update section totals
      $section_row = $this->database->db_select('STUD_SECTION')
        ->fields('STUD_SECTION', array('ENROLLED_TOTAL'))
        ->condition('SECTION_ID', $section_id)
        ->execute()->fetch();
      
      $section_poster = $this->posterFactory->newPoster()->edit('K12.Section', $section_id, array(
        'K12.Section.EnrolledTotal' => $section_row['ENROLLED_TOTAL'] + 1
      ))->process()->getResult();

      if ($section_poster) {
        $transaction->commit();
      } else {
        $transaction->rollback();
      }
      
      return $student_class_id;
      
    } else {
      $transaction->rollback();
      return false;
    }
    
  }
  
  public function addWaitListClassForStudentStatus($student_status_id, $section_id) {
    
    $transaction = $this->database->db_transaction();
    
    $waitlist_id = $this->posterFactory->newPoster()->add('K12.Student.WaitList', 'new', array(
      'K12.Student.WaitList.StudentStatusID' => $student_status_id,
      'K12.Student.WaitList.SectionID' => $section_id,
      'K12.Student.WaitList.AddedTimestamp' => date('Y-m-d H:i:s')
    ))->process()->getResult();
    
    if ($waitlist_id) {
    
      // Update section totals
      $section_row = $this->database->db_select('STUD_SECTION')
      ->fields('STUD_SECTION', array('WAIT_LISTED_TOTAL'))
      ->condition('SECTION_ID', $section_id)
      ->execute()->fetch();
      
      $section_poster = $this->posterFactory->newPoster()->edit('K12.Section', $section_id, array(
        'K12.Section.WaitListedTotal' => $section_row['WAIT_LISTED_TOTAL'] + 1
      ))->process()->getResult();
      
      if ($section_poster) {
        $transaction->commit();
      } else {
        $transaction->rollback();
      }
    
    } else {
      $transaction->rollback();
      return false;
    }
    
  }
  
  public function dropAllClassesForStudentStatus($student_status_id, $drop_date) {
    
    $transaction = $this->database->db_transaction();
    
    $classes_result = $this->database->db_select('STUD_STUDENT_CLASSES', 'classes')
      ->fields('classes', array('STUDENT_CLASS_ID'))
      ->condition('STUDENT_STATUS_ID', $student_status_id)
      ->condition('DROPPED', 0)
      ->execute();
    while ($classes_row = $classes_result->fetch()) {
      $this->dropClassForStudentStatus($classes_row['STUDENT_CLASS_ID'], $drop_date);
    }
    
    $transaction->commit();
  }
  
  public function dropClassForStudentStatus($class_id, $drop_date) {
    
    // set start date
    $term_info = $this->database->db_select('CORE_TERM')
      ->fields('CORE_TERM', array('START_DATE', 'END_DATE'))
      ->join('CORE_ORGANIZATION_TERMS', 'CORE_ORGANIZATION_TERMS', 'CORE_TERM.TERM_ID = CORE_ORGANIZATION_TERMS.TERM_ID')
      ->condition('ORGANIZATION_TERM_ID', $this->record->getSelectedRecord()['ORGANIZATION_TERM_ID'])
      ->execute()->fetch();
    
    if ($term_info['START_DATE'] < date('Y-m-d'))
      $end_date = date('Y-m-d');
    else
      $end_date = null;
        
    $transaction = $this->database->db_transaction();

    $class_row = $this->database->db_select('STUD_STUDENT_CLASSES')
      ->fields('STUD_STUDENT_CLASSES')
      ->condition('STUDENT_CLASS_ID', $class_id)
      ->join('STUD_STUDENT_STATUS', 'stustatus', 'stustatus.STUDENT_STATUS_ID = STUD_STUDENT_CLASSES.STUDENT_STATUS_ID')
      ->leftJoin('BILL_TUITION_RATE', 'tuitionrate', 'tuitionrate.TUITION_RATE_ID = stustatus.TUITION_RATE_ID')
      ->fields('tuitionrate', array('BILLING_MODE'))
      ->execute()->fetch();
    
    $class_data = array(
      'K12.Student.Class.Dropped' => 1,
      'K12.Student.Class.DropDate' => $drop_date,
      'K12.Student.Class.EndDate' => ($drop_date >= $class_row['START_DATE']) ? $drop_date : $end_date
    );
    
    if ($drop_date < $class_row['START_DATE']) $class_data['K12.Student.Class.StartDate'] = null;
    
    $class_poster = $this->posterFactory->newPoster()->edit('K12.Student.Class', $class_id, $class_data)->process()->getResult();
        
    if ($class_poster) {
      
      // process course fees
      if ($class_row['BILLING_MODE'] == 'FEES' AND $drop_date < $class_row['START_DATE']) {
        $this->billing->removeCourseFees($student_class_id);
      }
      
      // Update section totals
      $section_row = $this->database->db_select('STUD_SECTION')
        ->fields('STUD_SECTION', array('ENROLLED_TOTAL'))
        ->condition('SECTION_ID', $class_row['SECTION_ID'])
        ->execute()->fetch();
      
      $section_poster = $this->posterFactory->newPoster()->edit('K12.Section', $class_row['SECTION_ID'], array(
        'K12.Section.EnrolledTotal' => $section_row['ENROLLED_TOTAL'] + 1
      ))->process()->getResult();
      
      if ($section_poster) {
        $transaction->commit();
      } else {
        $transaction->rollback();
      }
    } else {
      $transaction->rollback();
    }  
  }
  
  public function dropWaitListClassForStudentStatus($waitlist_id) {

    $class_row = $this->database->db_select('STUD_STUDENT_WAIT_LIST')
          ->fields('STUD_STUDENT_WAIT_LIST')
          ->condition('STUDENT_WAIT_LIST_ID', $waitlist_id)
          ->execute()->fetch();
    
    $transaction = $this->database->db_transaction();
    
    $waitlist_poster = $this->posterFactory->newPoster()->delete('K12.Student.WaitList', $waitlist_id)->process()->getResult();

    if ($waitlist_poster) {
      // Update section totals
      $section_row = $this->database->db_select('STUD_SECTION')
        ->fields('STUD_SECTION', array('WAIT_LISTED_TOTAL'))
        ->condition('SECTION_ID', $class_row['SECTION_ID'])
        ->execute()->fetch();
      
      $section_poster = $this->posterFactory->newPoster()->edit('K12.Section', $class_row['SECTION_ID'], array(
        'K12.Section.WaitListedTotal' => $section_row['WAIT_LISTED_TOTAL'] - 1
      ))->process()->getResult();
      
      if ($section_poster) {
        $transaction->commit();
      } else {
        $transaction->rollback();
      }
    } else {
      $transaction->rollback();
      return false;
    }
  }
  
  public function calculateFTE($student_status_id) {
    
    // Get total credits
    $student_status = $this->database->db_select('STUD_STUDENT_STATUS', 'status')
      ->fields('status', array('TOTAL_CREDITS_ATTEMPTED', 'ORGANIZATION_TERM_ID', 'LEVEL', 'FTE', 'STATUS'))
      ->condition('status.STUDENT_STATUS_ID', $student_status_id)
      ->execute()->fetch();
    
    // Determine FTE
    $fte = $this->database->db_select('STUD_SCHOOL_TERM_LEVEL_FTE', 'schooltermlevelFTE')
      ->fields('schooltermlevelFTE', array('FTE'))
      ->join('STUD_SCHOOL_TERM_LEVEL', 'schooltermlevel', 'schooltermlevel.SCHOOL_TERM_LEVEL_ID = schooltermlevelFTE.SCHOOL_TERM_LEVEL_ID')
      ->condition('ORGANIZATION_TERM_ID', $student_status['ORGANIZATION_TERM_ID'])
      ->condition('LEVEL', $student_status['LEVEL'])
      ->condition('CREDIT_TOTAL', $student_status['TOTAL_CREDITS_ATTEMPTED'], '<=')
      ->orderBy('CREDIT_TOTAL', 'DESC')
      ->execute()->fetch()['FTE'];
    
    if ($fte != $student_status['FTE'] AND $student_status['STATUS'] == '') {
      // Need to change FTE
      
      // Need to see if activity record already exists
      $student_activity_record = $this->database->db_select('STUD_STUDENT_ENROLLMENT_ACTIVITY', 'enrollment_activity')
        ->fields('enrollment_activity', array('ENROLLMENT_ACTIVITY_ID', 'ENROLLMENT_ID', 'EFFECTIVE_DATE'))
        ->join('STUD_STUDENT_ENROLLMENT', 'enrollment', 'enrollment.ENROLLMENT_ID = enrollment_activity.ENROLLMENT_ID')
        ->join('STUD_STUDENT_STATUS', 'status', 'status.STUDENT_STATUS_ID = enrollment.STUDENT_STATUS_ID')
        ->condition('status.STUDENT_STATUS_ID', $student_status_id)
        ->condition('enrollment.LEAVE_DATE', null)
        ->orderBy('EFFECTIVE_DATE')
        ->execute()->fetch();
      
      // if effective date same as today
      if ($student_activity_record['EFFECTIVE_DATE'] == date('Y-m-d')) {
        
        // update existing record
        $enrollment_activity_poster = $this->posterFactory->newPoster()->edit('K12.Student.Enrollment.Activity', $student_activity_record['ENROLLMENT_ACTIVITY_ID'], array(
          'K12.Student.Enrollment.Activity.FTE' => $fte
        ))->process()->getResult();
      } else {
        // create new record
        $enrollment_activity_poster = $this->posterFactory->newPoster()->add('K12.Student.Enrollment.Activity', 'new', array(
          'K12.Student.Enrollment.Activity.EffectiveDate' => date('Y-m-d'),
          'K12.Student.Enrollment.Activity.EnrollmentID' => $student_activity_record['ENROLLMENT_ID'],
          'K12.Student.Enrollment.Activity.FTE' => $fte
        ))->process()->getResult();
      }
      
      // update existing status
      $status_poster = $this->posterFactory->newPoster()->edit('K12.Student.Status', $student_status_id, array(
        'K12.Student.Status.FTE' => $fte
      ))->process()->getResult();
      return $status_poster;
      
    }
    
  }
  
  public function calculateTotalAttemptedCredits($student_status_id) {
    
    $classes = $this->database->db_select('STUD_STUDENT_CLASSES', 'classes')
      ->expression('SUM(CREDITS_ATTEMPTED)', 'total_credits_attempted')
      ->join('STUD_MARK_SCALE', 'markscale', "markscale.MARK_SCALE_ID = classes.MARK_SCALE_ID AND markscale.AUDIT = 'N'")
      ->condition('STUDENT_STATUS_ID', $student_status_id)
      ->condition('DROPPED', '0')
      ->execute()->fetch();
    
    return $this->posterFactory->newPoster()->edit('K12.Student.Status', $student_status_id, array(
      'K12.Student.Status.TotalCreditsAttempted' => $classes['total_credits_attempted']
    ))->process()->getResult();
  }
  
  public function calculateLatestDropDate($student_status_id) {
    
    $classes = $this->database->db_select('STUD_STUDENT_CLASSES', 'classes')
      ->fields('classes', array('DROP_DATE'))
      ->condition('STUDENT_STATUS_ID', $student_status_id)
      ->condition('DROPPED', '1')
      ->orderBy('DROP_DATE', 'DESC')
      ->execute()->fetch();
    
    return $classes['DROP_DATE'];
  }
  
}