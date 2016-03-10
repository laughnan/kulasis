<?php

namespace Kula\K12\Bundle\StudentBundle\Controller;

use Kula\Core\Bundle\FrameworkBundle\Controller\Controller;

class CoreEnrollmentController extends Controller {
  
  public function indexAction() {
    $this->authorize();
    $this->processForm();
    $this->setRecordType('Core.K12.Student');
    
    $statuses = array();
    
    if ($this->record->getSelectedRecordID()) {
      // Get Statuses
      $statuses = $this->db()->db_select('STUD_STUDENT_STATUS', 'stustatus')
        ->fields('stustatus', array('STUDENT_STATUS_ID', 'GRADE', 'LEVEL', 'THESIS_STATUS', 'RESIDENT', 'ENTER_DATE', 'ENTER_CODE', 'LEAVE_DATE', 'LEAVE_CODE', 'FTE'))
        ->join('CORE_ORGANIZATION_TERMS', 'orgterm', 'stustatus.ORGANIZATION_TERM_ID = orgterm.ORGANIZATION_TERM_ID')
        ->join('CORE_ORGANIZATION', 'org', 'orgterm.ORGANIZATION_ID = org.ORGANIZATION_ID')
        ->fields('org', array('ORGANIZATION_NAME'))
        ->join('CORE_TERM', 'term', 'orgterm.TERM_ID = term.TERM_ID')
        ->fields('term', array('TERM_ABBREVIATION'))
        ->condition('STUDENT_ID', $this->record->getSelectedRecord()['STUDENT_ID'])
        ->orderBy('START_DATE', 'ASC', 'term')
        ->orderBy('ENTER_DATE', 'ASC', 'STUD_STUDENT_STATUS')
        ->execute()->fetchAll();
    }
    
    return $this->render('KulaK12StudentBundle:CoreEnrollment:statuses.html.twig', array('statuses' => $statuses));
  }
  
  public function enrollmentsAction($student_status_id) {
    $this->authorize();
    $this->processForm();
    $this->setRecordType('Core.K12.Student');
    
    $enrollments = array();
    
    if ($this->record->getSelectedRecordID()) {
      // Get Status
      $status = $this->db()->db_select('STUD_STUDENT_STATUS', 'stustatus')
        ->fields('stustatus', array('STUDENT_STATUS_ID', 'GRADE', 'LEVEL', 'THESIS_STATUS', 'RESIDENT', 'ENTER_DATE', 'ENTER_CODE', 'LEAVE_DATE', 'LEAVE_CODE', 'FTE', 'SEEKING_DEGREE_1_ID', 'SEEKING_DEGREE_2_ID', 'ENTER_TERM_ID'))
        ->join('CORE_ORGANIZATION_TERMS', 'orgterm', 'stustatus.ORGANIZATION_TERM_ID = orgterm.ORGANIZATION_TERM_ID')
        ->join('CORE_ORGANIZATION', 'org', 'orgterm.ORGANIZATION_ID = org.ORGANIZATION_ID')
        ->fields('org', array('ORGANIZATION_NAME'))
        ->join('CORE_TERM', 'term', 'orgterm.TERM_ID = term.TERM_ID')
        ->fields('term', array('TERM_ABBREVIATION'))
        ->condition('STUDENT_ID', $this->record->getSelectedRecord()['STUDENT_ID'])
        ->condition('STUDENT_STATUS_ID', $student_status_id)
        ->orderBy('term.START_DATE', 'ASC')
        ->orderBy('stustatus.ENTER_DATE', 'ASC')
        ->execute()->fetch();
      
      // Get Enrollments
      $enrollments = $this->db()->db_select('STUD_STUDENT_ENROLLMENT', 'STUD_STUDENT_ENROLLMENT')
        ->fields('STUD_STUDENT_ENROLLMENT', array('ENROLLMENT_ID', 'ENTER_DATE', 'ENTER_CODE', 'LEAVE_DATE', 'LEAVE_CODE', 'CREATED_TIMESTAMP', 'UPDATED_TIMESTAMP'))
        ->join('STUD_STUDENT_STATUS', 'status', 'STUD_STUDENT_ENROLLMENT.STUDENT_STATUS_ID = status.STUDENT_STATUS_ID')
        ->join('CORE_ORGANIZATION_TERMS', 'orgterm', 'status.ORGANIZATION_TERM_ID = orgterm.ORGANIZATION_TERM_ID')
        ->join('CORE_ORGANIZATION', 'org', 'orgterm.ORGANIZATION_ID = org.ORGANIZATION_ID')
        ->fields('org', array('ORGANIZATION_NAME'))
        ->join('CORE_TERM', 'term', 'orgterm.TERM_ID = term.TERM_ID')
        ->fields('term', array('TERM_ABBREVIATION'))
        ->condition('STUDENT_ID', $this->record->getSelectedRecord()['STUDENT_ID'])
        ->condition('STUD_STUDENT_ENROLLMENT.STUDENT_STATUS_ID', $student_status_id)
        ->orderBy('term.START_DATE', 'ASC')
        ->orderBy('STUD_STUDENT_ENROLLMENT.ENTER_DATE', 'ASC')
        ->execute()->fetchAll();
    }
    
    return $this->render('KulaK12StudentBundle:CoreEnrollment:enrollments.html.twig', array('enrollments' => $enrollments, 'status' => $status));
  }
  
  public function activityAction($student_enrollment_id) {
    $this->authorize();
    $this->setRecordType('Core.K12.Student');
    
    $activity = array();
    
    if ($this->record->getSelectedRecordID()) {
      
      // Get Enrollment
      $enrollment = $this->db()->db_select('STUD_STUDENT_ENROLLMENT', 'STUD_STUDENT_ENROLLMENT')
        ->fields('STUD_STUDENT_ENROLLMENT', array('STUDENT_STATUS_ID', 'ENROLLMENT_ID', 'ENTER_DATE', 'ENTER_CODE', 'LEAVE_DATE', 'LEAVE_CODE', 'CREATED_TIMESTAMP', 'UPDATED_TIMESTAMP'))
        ->join('STUD_STUDENT_STATUS', 'status', 'STUD_STUDENT_ENROLLMENT.STUDENT_STATUS_ID = status.STUDENT_STATUS_ID')
        ->join('CORE_ORGANIZATION_TERMS', 'orgterm', 'status.ORGANIZATION_TERM_ID = orgterm.ORGANIZATION_TERM_ID')
        ->join('CORE_ORGANIZATION', 'org', 'orgterm.ORGANIZATION_ID = org.ORGANIZATION_ID')
        ->fields('org', array('ORGANIZATION_NAME'))
        ->join('CORE_TERM', 'term', 'orgterm.TERM_ID = term.TERM_ID')
        ->fields('term', array('TERM_ABBREVIATION'))
        ->leftJoin('CONS_CONSTITUENT', 'created_user', 'created_user.CONSTITUENT_ID = STUD_STUDENT_ENROLLMENT.CREATED_USERSTAMP')
        ->fields('created_user', array('LAST_NAME' => 'createduser_LAST_NAME', 'FIRST_NAME' => 'createduser_FIRST_NAME'))
        ->leftJoin('CONS_CONSTITUENT', 'updated_user', 'updated_user.CONSTITUENT_ID = STUD_STUDENT_ENROLLMENT.UPDATED_USERSTAMP')
        ->fields('updated_user', array('LAST_NAME' => 'updateduser_LAST_NAME', 'FIRST_NAME' => 'updateduser_FIRST_NAME'))
        ->condition('STUDENT_ID', $this->record->getSelectedRecord()['STUDENT_ID'])
        ->condition('STUD_STUDENT_ENROLLMENT.ENROLLMENT_ID', $student_enrollment_id)
        ->orderBy('term.START_DATE', 'ASC')
        ->orderBy('STUD_STUDENT_ENROLLMENT.ENTER_DATE', 'ASC')
        ->execute()->fetch();

      // Get Status
      $status = $this->db()->db_select('STUD_STUDENT_STATUS', 'STUD_STUDENT_STATUS')
        ->fields('STUD_STUDENT_STATUS', array('STUDENT_STATUS_ID', 'GRADE', 'RESIDENT', 'LEVEL', 'THESIS_STATUS', 'ENTER_DATE', 'ENTER_CODE', 'LEAVE_DATE', 'LEAVE_CODE', 'FTE'))
        ->join('CORE_ORGANIZATION_TERMS', 'orgterm', 'STUD_STUDENT_STATUS.ORGANIZATION_TERM_ID = orgterm.ORGANIZATION_TERM_ID')
        ->join('CORE_ORGANIZATION', 'org', 'orgterm.ORGANIZATION_ID = org.ORGANIZATION_ID')
        ->fields('org', array('ORGANIZATION_NAME'))
        ->join('CORE_TERM', 'term', 'orgterm.TERM_ID = term.TERM_ID')
        ->fields('term', array('TERM_ABBREVIATION'))
        ->condition('STUDENT_ID', $this->record->getSelectedRecord()['STUDENT_ID'])
        ->condition('STUDENT_STATUS_ID', $enrollment['STUDENT_STATUS_ID'])
        ->orderBy('term.START_DATE', 'ASC')
        ->orderBy('STUD_STUDENT_STATUS.ENTER_DATE', 'ASC')
        ->execute()->fetch();
      
      // Get Activity
      $activity = $this->db()->db_select('STUD_STUDENT_ENROLLMENT_ACTIVITY', 'STUD_STUDENT_ENROLLMENT_ACTIVITY')
        ->fields('STUD_STUDENT_ENROLLMENT_ACTIVITY', array('ENROLLMENT_ACTIVITY_ID', 'ENROLLMENT_ID', 'EFFECTIVE_DATE', 'GRADE', 'LEVEL', 'THESIS_STATUS', 'RESIDENT', 'FTE', 'SEEKING_DEGREE_1_ID', 'SEEKING_DEGREE_2_ID'))
        ->join('STUD_STUDENT_ENROLLMENT', 'enrollment', 'enrollment.ENROLLMENT_ID = STUD_STUDENT_ENROLLMENT_ACTIVITY.ENROLLMENT_ID')
        ->join('STUD_STUDENT_STATUS', 'status', 'enrollment.STUDENT_STATUS_ID = status.STUDENT_STATUS_ID')
        ->join('CORE_ORGANIZATION_TERMS', 'orgterm', 'status.ORGANIZATION_TERM_ID = orgterm.ORGANIZATION_TERM_ID')
        ->join('CORE_ORGANIZATION', 'org', 'orgterm.ORGANIZATION_ID = org.ORGANIZATION_ID')
        ->fields('org', array('ORGANIZATION_NAME'))
        ->join('CORE_TERM', 'term', 'orgterm.TERM_ID = term.TERM_ID')
        ->fields('term', array('TERM_ABBREVIATION'))
        ->condition('STUDENT_ID', $this->record->getSelectedRecord()['STUDENT_ID'])
        ->condition('STUD_STUDENT_ENROLLMENT_ACTIVITY.ENROLLMENT_ID', $student_enrollment_id)
        ->orderBy('term.START_DATE', 'ASC')
        ->orderBy('enrollment.ENTER_DATE', 'ASC')
        ->orderBy('STUD_STUDENT_ENROLLMENT_ACTIVITY.EFFECTIVE_DATE', 'ASC')
        ->execute()->fetchAll();
      
    }
    
    return $this->render('KulaK12StudentBundle:CoreEnrollment:activity.html.twig', array('activity' => $activity, 'enrollment' => $enrollment, 'status' => $status));
  }
  
  public function activateinactivateAction() {
    $this->authorize();
    $this->setRecordType('Core.K12.Student.Status');
    
    if ($this->record->getSelectedRecordID()) {
      
      // Get selected status information
      $status = $this->db()->db_select('STUD_STUDENT_STATUS', 'stustatus')
        ->fields('stustatus', array('STUDENT_STATUS_ID', 'STUDENT_ID', 'STATUS', 'GRADE', 'RESIDENT', 'ENTER_DATE', 'ENTER_CODE', 'LEAVE_DATE', 'LEAVE_CODE', 'FTE'))
        ->join('CORE_ORGANIZATION_TERMS', 'orgterm', 'stustatus.ORGANIZATION_TERM_ID = orgterm.ORGANIZATION_TERM_ID')
        ->join('CORE_ORGANIZATION', 'org', 'orgterm.ORGANIZATION_ID = org.ORGANIZATION_ID')
        ->fields('org', array('ORGANIZATION_NAME'))
        ->join('CORE_TERM', 'term', 'orgterm.TERM_ID = term.TERM_ID')
        ->fields('term', array('TERM_ABBREVIATION'))
        ->condition('STUDENT_STATUS_ID', $this->record->getSelectedRecordID())
        ->orderBy('term.START_DATE', 'ASC')
        ->orderBy('stustatus.ENTER_DATE', 'ASC')
        ->execute()->fetch();
      
      if ($status['STATUS'] == '') {
        
        if ($enrollmentInfo = $this->form('edit', 'K12.Student.Enrollment')) {

          $transaction = $this->db()->db_transaction();
          // update enrollment record
          $enrollmentPoster = $this->newPoster()->edit('K12.Student.Enrollment', key($enrollmentInfo), current($enrollmentInfo))->process()->getResult();
          
          // update status record
          $statusPoster = $this->newPoster()->edit('K12.Student.Status', $status['STUDENT_STATUS_ID'], array(
            'K12.Student.Status.LeaveDate' => $enrollmentInfo[key($enrollmentInfo)]['K12.Student.Enrollment.LeaveDate'],
            'K12.Student.Status.LeaveCode' => $enrollmentInfo[key($enrollmentInfo)]['K12.Student.Enrollment.LeaveCode'],
            'K12.Student.Status.Status' => 'I'
          ))->process()->getResult();
          
          // Drop all classes
          $schedule_service = $this->get('kula.K12.scheduling.schedule');
          $schedule_service->dropAllClassesForStudentStatus($status['STUDENT_STATUS_ID'], $enrollmentInfo[key($enrollmentInfo)]['K12.Student.Enrollment.LeaveDate']);
          
          // Process billing
          $student_billing_service = $this->get('kula.K12.billing.student');
          $student_billing_service->processBilling($status['STUDENT_STATUS_ID'], 'Student Inactivated and Classes Dropped');
          
          // redirect
          if ($enrollmentPoster AND $statusPoster) {
            $transaction->commit();
            $this->addFlash('success', 'Inactivated student.');
            return $this->forward('Core_K12_Student_Enrollment_Statuses', array('record_type' => 'Core.K12.Student', 'record_id' => $status['STUDENT_ID']), array('record_type' => 'Core.K12.Student', 'record_id' => $status['STUDENT_ID']));
          } else {
            $transaction->rollback();
            throw new \Kula\Core\Component\DB\PosterException('Inactivation failed.');
          }
          
        }
        
        // Need to get enrollment
        $enrollment = $this->db()->db_select('STUD_STUDENT_ENROLLMENT')
          ->fields('STUD_STUDENT_ENROLLMENT', array('ENROLLMENT_ID', 'ENTER_DATE', 'ENTER_CODE', 'LEAVE_DATE', 'LEAVE_CODE'))
          ->condition('STUDENT_STATUS_ID', $this->record->getSelectedRecordID())
          ->condition('LEAVE_DATE', null)
          ->execute()->fetch();
        
        // Want to inactivate student
        return $this->render('KulaK12StudentBundle:CoreEnrollment:inactivate.html.twig', array('status' => $status, 'enrollment' => $enrollment));
        
      } elseif ($status['STATUS'] == 'I') {
        
        if ($enrollmentInfo = $this->form('add', 'K12.Student.Status', 'new')) {
          
          $transaction = $this->db()->db_transaction();
          
          // Update status info
          $enrollmentInfo['K12.Student.Status.Status'] = null;
          $statusPoster = $this->newPoster()->edit('K12.Student.Status', $status['STUDENT_STATUS_ID'], $enrollmentInfo)->process()->getResult();
          
          // insert enrollment record
          $enrollmentPoster = $this->newPoster()->add('K12.Student.Enrollment', 'new', array(
            'K12.Student.Enrollment.StatusID' => $status['STUDENT_STATUS_ID'],
            'K12.Student.Enrollment.EnterDate' => $enrollmentInfo['K12.Student.Status.EnterDate'],
            'K12.Student.Enrollment.EnterCode' => $enrollmentInfo['K12.Student.Status.EnterCode']
          ))->process()->getResult();

          // insert enrollment activity record
          $activityPoster = $this->newPoster()->add('K12.Student.Enrollment.Activity', 'new', array(
            'K12.Student.Enrollment.Activity.EnrollmentID' => $enrollmentPoster,
            'K12.Student.Enrollment.Activity.Grade' => (isset($enrollmentInfo['K12.Student.Status.Grade'])) ? $enrollmentInfo['K12.Student.Status.Grade'] : null,
            'K12.Student.Enrollment.Activity.Resident' => (isset($enrollmentInfo['K12.Student.Status.Resident'])) ? $enrollmentInfo['K12.Student.Status.Resident'] : null,
            'K12.Student.Enrollment.Activity.FTE' => (isset($enrollmentInfo['K12.Student.Status.FTE'])) ? $enrollmentInfo['K12.Student.Status.FTE'] : null,
            'K12.Student.Enrollment.Activity.Level' => (isset($enrollmentInfo['K12.Student.Status.Level'])) ? $enrollmentInfo['K12.Student.Status.Level'] : null,
            'K12.Student.Enrollment.Activity.EffectiveDate' => date('m/d/Y')
          ))->process()->getResult();
          
          // determine tuition rate
          $constituent_billing_service = $this->get('kula.K12.billing.constituent');
          $constituent_billing_service->determineTuitionRate($this->record->getSelectedRecordID());
          
          // mandatory transactions
          $constituent_billing_service->checkMandatoryTransactions($this->record->getSelectedRecordID());
          
          if ($statusPoster AND $enrollmentPoster AND $activityPoster) {
            $transaction->commit();
            $this->addFlash('success', 'Activated student.');
            return $this->forward('Core_K12_Student_Enrollment_Statuses', array('record_type' => 'Core.K12.Student', 'record_id' => $status['STUDENT_ID']), array('record_type' => 'Core.K12.Student', 'record_id' => $status['STUDENT_ID']));
          } else {
            $transaction->rollback();
            throw new \Kula\Core\Component\DB\PosterException('Activation failed.');
          }
          
        }
        
        // want to activate student
        return $this->render('KulaK12StudentBundle:CoreEnrollment:activate.html.twig', array('status' => $status));
      }
      
    } 
  }
  
  public function editAction() {
    
    $this->authorize();
    $this->setRecordType('Core.K12.Student.Status');
    
    $status = array();
    $effective_date = null;
    
    if ($this->record->getSelectedRecordID()) {
      
      // Add enrollment activity
      if ($activity_post = $this->form('add', 'K12.Student.Enrollment.Activity', 'new')) {
        
        // posted data
        $transaction = $this->db()->db_transaction();
        
        if ($activity_post['K12.Student.Enrollment.Activity.Grade']) {
          $activity_data['K12.Student.Enrollment.Activity.Grade'] = $activity_post['K12.Student.Enrollment.Activity.Grade'];
        }
        if ($activity_post['K12.Student.Enrollment.Activity.Resident']) {
          $activity_data['K12.Student.Enrollment.Activity.Resident'] = $activity_post['K12.Student.Enrollment.Activity.Resident'];
        }
        if ($activity_post['K12.Student.Enrollment.Activity.FTE']) {
          $activity_data['K12.Student.Enrollment.Activity.FTE'] = $activity_post['K12.Student.Enrollment.Activity.FTE'];
        }
        if ($activity_post['K12.Student.Enrollment.Activity.Level']) {
          $activity_data['K12.Student.Enrollment.Activity.Level'] = $activity_post['K12.Student.Enrollment.Activity.Level'];
        }
        
        // Post data to status
        if ($activity_post['K12.Student.Enrollment.Activity.Grade']) {
          $status_data['K12.Student.Status.Grade'] = $activity_post['K12.Student.Enrollment.Activity.Grade'];
        }
        if ($activity_post['K12.Student.Enrollment.Activity.Resident']) {
          $status_data['K12.Student.Status.Resident'] = $activity_post['K12.Student.Enrollment.Activity.Resident'];
        }
        if ($activity_post['K12.Student.Enrollment.Activity.FTE']) {
          $status_data['K12.Student.Status.FTE'] = $activity_post['K12.Student.Enrollment.Activity.FTE'];
        }
        if ($activity_post['K12.Student.Enrollment.Activity.Level']) {
          $status_data['K12.Student.Status.Level'] = $activity_post['K12.Student.Enrollment.Activity.Level'];
        }
        
        // Get latest enrollment ID
        $enrollment = $this->db()->db_select('STUD_STUDENT_ENROLLMENT')
          ->fields('STUD_STUDENT_ENROLLMENT', array('ENROLLMENT_ID', 'ENTER_DATE'))
          ->condition('STUDENT_STATUS_ID', $this->record->getSelectedRecordID())
          ->orderBy('ENTER_DATE', 'DESC')
          ->execute()->fetch();
        
        // Determine if date already exists
        $activity_exists = $this->db()->db_select('STUD_STUDENT_ENROLLMENT_ACTIVITY')
          ->fields('STUD_STUDENT_ENROLLMENT_ACTIVITY', array('EFFECTIVE_DATE', 'ENROLLMENT_ACTIVITY_ID'))
          ->condition('ENROLLMENT_ID', $enrollment['ENROLLMENT_ID'])
          ->orderBy('EFFECTIVE_DATE', 'DESC')
          ->execute()->fetch();
        if ($activity_exists['EFFECTIVE_DATE'] == date('Y-m-d', strtotime($activity_post['K12.Student.Enrollment.Activity.EffectiveDate']))) {
          // update existing record
          // Post data to activity
          $activity_poster = $this->newPoster()->edit('K12.Student.Enrollment.Activity', $activity_exists['ENROLLMENT_ACTIVITY_ID'], $activity_data)->process()->getResult();
        } else {
          // insert new record
          // Post data to activity
          $activity_data['K12.Student.Enrollment.Activity.EffectiveDate'] = $activity_post['K12.Student.Enrollment.Activity.EffectiveDate'];
          $activity_data['K12.Student.Enrollment.Activity.EnrollmentID'] = $enrollment['ENROLLMENT_ID'];
          $activity_poster = $this->newPoster()->add('K12.Student.Enrollment.Activity', 'new', $activity_data)->process()->getResult();
        }
       
        if ($activity_exists['EFFECTIVE_DATE'] <= date('Y-m-d', strtotime($activity_post['K12.Student.Enrollment.Activity.EffectiveDate']))) {
          // Post data to status
          $status_poster = $this->newPoster()->edit('K12.Student.Status', $this->record->getSelectedRecordID(), $status_data)->process()->getResult();
        }
        
        if ($activity_poster) {
          $transaction->commit();
          return $this->forward('Core_K12_Student_Enrollment_Statuses', array('record_type' => 'Core.K12.Student.Status', 'record_id' => $this->record->getSelectedRecordID()), array('record_type' => 'Core.K12.Student.Status', 'record_id' => $this->record->getSelectedRecordID()));
        } else {
          $transaction->rollback();
          throw new \Kula\Core\Component\DB\PosterException('Changes not saved.');
        }
      }
      
      $status = $this->db()->db_select('STUD_STUDENT_STATUS')
        ->fields('STUD_STUDENT_STATUS')
        ->condition('STUDENT_STATUS_ID', $this->record->getSelectedRecordID())
        ->execute()->fetch();
      
      $effective_date = date('Y-m-d');
    
    } // end if selected record
        
    return $this->render('KulaK12StudentBundle:CoreEnrollment:edit.html.twig', array('status' => $status, 'effective_date' => $effective_date));
    
  }
  
}
