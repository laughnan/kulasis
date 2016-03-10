<?php
  
namespace Kula\K12\Bundle\SchedulingBundle\Controller;

use Kula\Core\Bundle\FrameworkBundle\Controller\ReportController;

class CoreClassRosterDirectoryReportController extends ReportController {
  
  public function indexAction() {
    $this->authorize();
    if ($this->request->query->get('record_type') == 'Core.K12.Section' AND $this->request->query->get('record_id') != '')
      $this->setRecordType('Core.K12.Section');
    //$this->assign("grade_levels", Kula_Records_GradeLevel::getGradeLevelsForSchoolForMenu($_SESSION['kula']['school']['id'], "Y"));
    return $this->render('KulaK12SchedulingBundle:CoreClassRosterReport:reports_classroster.html.twig');
  }
  
  public function generateAction()
  {  
    $this->authorize();
    
    $pdf = new \Kula\K12\Bundle\SchedulingBundle\Report\ClassRosterDirectoryReport("P");
    $pdf->SetFillColor(245,245,245);
    $pdf->row_count = 0;
    
    $meetings = array();
    // Get meeting data
    $meeting_result = $this->db()->db_select('STUD_SECTION', 'section')
      ->fields('section', array('SECTION_ID', 'SECTION_NUMBER'))
      ->join('STUD_SECTION_MEETINGS', 'meetings', 'meetings.SECTION_ID = section.SECTION_ID')
      ->fields('meetings', array('MON', 'TUE', 'WED', 'THU', 'FRI', 'SAT', 'SUN', 'START_TIME', 'END_TIME'))
      ->leftJoin('STUD_ROOM', 'rooms', 'rooms.ROOM_ID = meetings.ROOM_ID')
      ->fields('rooms', array('ROOM_NUMBER'))
      ->condition('section.STATUS', null);
    
    $org_term_ids = $this->focus->getOrganizationTermIDs();
    if (isset($org_term_ids) AND count($org_term_ids) > 0)
      $meeting_result = $meeting_result->condition('section.ORGANIZATION_TERM_ID', $org_term_ids);
    $record_id = $this->request->request->get('record_id');
    if (isset($record_id) AND $record_id != '')
      $meeting_result = $meeting_result->condition('section.SECTION_ID', $record_id);
    $meeting_result = $meeting_result
      ->orderBy('SECTION_ID');
    $meeting_result = $meeting_result->execute();
    $i = 0;
    $section_id = 0;
    while ($meeting_row = $meeting_result->fetch()) {
      if ($section_id != $meeting_row['SECTION_ID']) $i = 0;
      $meetings[$meeting_row['SECTION_ID']]['meetings'][$i]['meets'] = '';
      if ($meeting_row['MON'] == '1') $meetings[$meeting_row['SECTION_ID']]['meetings'][$i]['meets'] .= 'M';
      if ($meeting_row['TUE'] == '1') $meetings[$meeting_row['SECTION_ID']]['meetings'][$i]['meets'] .= 'T';
      if ($meeting_row['WED'] == '1') $meetings[$meeting_row['SECTION_ID']]['meetings'][$i]['meets'] .= 'W';
      if ($meeting_row['THU'] == '1') $meetings[$meeting_row['SECTION_ID']]['meetings'][$i]['meets'] .= 'R';
      if ($meeting_row['FRI'] == '1') $meetings[$meeting_row['SECTION_ID']]['meetings'][$i]['meets'] .= 'F';
      if ($meeting_row['SAT'] == '1') $meetings[$meeting_row['SECTION_ID']]['meetings'][$i]['meets'] .= 'S';
      if ($meeting_row['SUN'] == '1') $meetings[$meeting_row['SECTION_ID']]['meetings'][$i]['meets'] .= 'U';
      $meetings[$meeting_row['SECTION_ID']]['meetings'][$i]['START_TIME'] = date('g:i A', strtotime($meeting_row['START_TIME']));
      $meetings[$meeting_row['SECTION_ID']]['meetings'][$i]['END_TIME'] = date('g:i A', strtotime($meeting_row['END_TIME']));
      $meetings[$meeting_row['SECTION_ID']]['meetings'][$i]['ROOM'] = $meeting_row['ROOM_NUMBER'];
      $i++;
      $section_id = $meeting_row['SECTION_ID'];
    }
    
    // Get Data and Load
    $result = $this->db()->db_select('STUD_SECTION', 'section')
      ->fields('section', array('SECTION_ID', 'SECTION_NUMBER'))
      ->join('STUD_COURSE', 'course', 'course.COURSE_ID = section.COURSE_ID')
      ->fields('course', array('COURSE_TITLE'))
      ->join('CORE_ORGANIZATION_TERMS', 'orgterms', 'orgterms.ORGANIZATION_TERM_ID = section.ORGANIZATION_TERM_ID')
      ->join('CORE_ORGANIZATION', 'org', 'orgterms.ORGANIZATION_ID = org.ORGANIZATION_ID')
      ->fields('org', array('ORGANIZATION_NAME'))
      ->join('CORE_TERM', 'term', 'term.TERM_ID = orgterms.TERM_ID')
      ->fields('term', array('TERM_ABBREVIATION'))
      ->leftJoin('STUD_STAFF_ORGANIZATION_TERMS', 'stafforgterm', 'stafforgterm.STAFF_ORGANIZATION_TERM_ID = section.STAFF_ORGANIZATION_TERM_ID')
      ->leftJoin('STUD_STAFF', 'staff', 'staff.STAFF_ID = stafforgterm.STAFF_ID')
      ->fields('staff', array('ABBREVIATED_NAME'))
      ->join('STUD_STUDENT_CLASSES', 'class', 'class.SECTION_ID = section.SECTION_ID')
      ->fields('class', array('STUDENT_CLASS_ID', 'START_DATE', 'END_DATE'))
      ->join('STUD_STUDENT_STATUS', 'status', 'status.STUDENT_STATUS_ID = class.STUDENT_STATUS_ID')
      ->fields('status', array('STUDENT_ID', 'STUDENT_STATUS_ID', 'SEEKING_DEGREE_1_ID'))
      ->leftJoin('CORE_LOOKUP_VALUES', 'grvalue', "grvalue.CODE = status.GRADE AND grvalue.LOOKUP_TABLE_ID = (SELECT LOOKUP_TABLE_ID FROM CORE_LOOKUP_TABLES WHERE LOOKUP_TABLE_NAME = 'K12.Student.Enrollment.Grade')")
      ->fields('grvalue', array('DESCRIPTION' => 'GRADE'))
      ->leftJoin('CORE_LOOKUP_VALUES', 'entercodevalue', "entercodevalue.CODE = status.ENTER_CODE AND entercodevalue.LOOKUP_TABLE_ID = (SELECT LOOKUP_TABLE_ID FROM CORE_LOOKUP_TABLES WHERE LOOKUP_TABLE_NAME = 'K12.Student.Enrollment.EnterCode')")
      ->fields('entercodevalue', array('DESCRIPTION' => 'ENTER_CODE'))
      ->join('STUD_STUDENT', 'student', 'status.STUDENT_ID = student.STUDENT_ID')
      ->join('CONS_CONSTITUENT', 'stucon', 'student.STUDENT_ID = stucon.CONSTITUENT_ID')
      ->fields('stucon', array('PERMANENT_NUMBER', 'LAST_NAME', 'FIRST_NAME', 'MIDDLE_NAME', 'GENDER', 'PREFERRED_NAME'))
      ->condition('DROPPED', '0');
    $org_term_ids = $this->focus->getOrganizationTermIDs();
    if (isset($org_term_ids) AND count($org_term_ids) > 0)
      $result = $result->condition('section.ORGANIZATION_TERM_ID', $org_term_ids);
    
    // Add on selected record
    $record_id = $this->request->request->get('record_id');
    if (isset($record_id) AND $record_id != '')
      $result = $result->condition('section.SECTION_ID', $record_id);
    
    $result = $result
      ->orderBy('term.START_DATE', 'ASC')
      ->orderBy('SECTION_NUMBER', 'ASC')
      ->orderBy('SECTION_ID', 'ASC')
      ->orderBy('stucon.LAST_NAME', 'ASC')
      ->orderBy('stucon.FIRST_NAME', 'ASC')
      ->execute();
    
    $last_section_id = 0;
    
    while ($row = $result->fetch()) {
      
      if (isset($meetings[$row['SECTION_ID']]))  {
      $pdf->setData(array_merge($row, $meetings[$row['SECTION_ID']]));
      } else {
        $pdf->setData($row);  
      }
      if ($last_section_id != $row['SECTION_ID']) {
        $pdf->row_count = 1;
        $pdf->StartPageGroup();
        $pdf->AddPage();
      }
      
      // Get Address
      $row['address'] = $this->db()->db_select('CONS_ADDRESS', 'address')
        ->fields('address', array('THOROUGHFARE', 'LOCALITY', 'ADMINISTRATIVE_AREA', 'POSTAL_CODE'))
        ->join('CONS_CONSTITUENT', 'constituent', 'constituent.RESIDENCE_ADDRESS_ID = address.ADDRESS_ID OR constituent.MAILING_ADDRESS_ID = address.ADDRESS_ID')
        ->condition('constituent.CONSTITUENT_ID', $row['STUDENT_ID'])->execute()->fetch();
      
      // Get phone
      $row['phone'] = $this->db()->db_select('CONS_PHONE', 'phone')
        ->fields('phone', array('PHONE_NUMBER', 'PHONE_TYPE'))
        ->join('CONS_CONSTITUENT', 'constituent', 'constituent.PRIMARY_PHONE_ID = phone.PHONE_NUMBER_ID')
        ->condition('constituent.CONSTITUENT_ID', $row['STUDENT_ID'])->execute()->fetch();
      
      // Get email
      $row['email'] = $this->db()->db_select('CONS_EMAIL_ADDRESS', 'email')
        ->fields('email', array('EMAIL_ADDRESS', 'EMAIL_ADDRESS_TYPE'))
        ->join('CONS_CONSTITUENT', 'constituent', 'constituent.PRIMARY_EMAIL_ID = email.EMAIL_ADDRESS_ID')
        ->condition('constituent.CONSTITUENT_ID', $row['STUDENT_ID'])->execute()->fetch();
      
      // Check how far from bottom
      $current_y = $pdf->GetY();
      if (270 - $current_y < 30) {
        $pdf->Ln(270 - $current_y);
      }
      
      $pdf->table_row($row);
      $last_section_id = $row['SECTION_ID'];
      $pdf->row_count++;
      $pdf->row_page_count++;
      $pdf->row_total_count++;
    }
    // Closing line
    return $this->pdfResponse($pdf->Output('','S'));
  
  }
}