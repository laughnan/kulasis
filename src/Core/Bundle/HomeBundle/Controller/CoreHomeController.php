<?php

namespace Kula\Core\Bundle\HomeBundle\Controller;

use Kula\Core\Bundle\FrameworkBundle\Controller\Controller;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\RedirectResponse;

class CoreHomeController extends Controller {
  
  public function homeAction() {
    $this->authorize();
        
    $enrolled_data = array();
    $totals_enrolled_data = array();
    $levels = array();
    $enrolled_list = array();
    $credits = array();
    $totals_credit_data = array();
    $fte_data = array();
    $totals_fte_data = array();
    
    if (count($this->focus->getOrganizationTermIDs()) > 0 AND count($this->focus->getOrganizationTermIDs()) < 5) {
      
    $enrolled = $this->db()->db_select('STUD_STUDENT_STATUS')
      ->fields('STUD_STUDENT_STATUS', array('LEVEL', 'GRADE' => 'GRADE_code'))
      ->expression('COUNT(*)', 'count')
      ->leftJoin('CORE_LOOKUP_VALUES', 'value', "value.CODE = STUD_STUDENT_STATUS.GRADE AND value.LOOKUP_TABLE_ID = (SELECT LOOKUP_TABLE_ID FROM CORE_LOOKUP_TABLES WHERE LOOKUP_TABLE_NAME = '".$this->focus->getOrganizationTarget().".Student.Enrollment.Grade')")
      ->fields('value', array('DESCRIPTION' => 'GRADE'))
      ->condition('ORGANIZATION_TERM_ID', $this->focus->getOrganizationTermIDs())
      ->condition('STATUS', null)
      ->groupBy('LEVEL')
      ->groupBy('GRADE')
      ->groupBy('GRADE_code')
      ->groupBy('DESCRIPTION');
    $enrolled = $enrolled->execute();
    while ($enrolled_row = $enrolled->fetch()) {
      
      if ($enrolled_row['GRADE'] == '') $enrolled_row['GRADE'] = $enrolled_row['GRADE_code'];
      
      $levels[$enrolled_row['LEVEL']] = $enrolled_row['LEVEL'];
      $enrolled_data[$enrolled_row['GRADE']][$enrolled_row['LEVEL']] = $enrolled_row['count'];
      if (!isset($totals_enrolled_data[$enrolled_row['LEVEL']]))
        $totals_enrolled_data[$enrolled_row['LEVEL']] = 0;
      $totals_enrolled_data[$enrolled_row['LEVEL']] += $enrolled_row['count'];
    }
    
    $total_credits = $this->db()->db_select('STUD_STUDENT_CLASSES', 'classes')
      ->expression('SUM(classes.CREDITS_ATTEMPTED)', 'total_credits')
      ->join('STUD_STUDENT_STATUS', 'stustatus', 'stustatus.STUDENT_STATUS_ID = classes.STUDENT_STATUS_ID')
      ->fields('stustatus', array('LEVEL', 'GRADE' => 'GRADE_code'))
      ->join('STUD_MARK_SCALE', 'markscale', 'markscale.MARK_SCALE_ID = classes.MARK_SCALE_ID')
      ->leftJoin('CORE_LOOKUP_VALUES', 'value', "value.CODE = stustatus.GRADE AND value.LOOKUP_TABLE_ID = (SELECT LOOKUP_TABLE_ID FROM CORE_LOOKUP_TABLES WHERE LOOKUP_TABLE_NAME = '".$this->focus->getOrganizationTarget().".Student.Enrollment.Grade')")
      ->fields('value', array('DESCRIPTION' => 'GRADE'))
      ->condition('ORGANIZATION_TERM_ID', $this->focus->getOrganizationTermIDs())
      ->condition('stustatus.STATUS', null)
      ->condition('markscale.AUDIT', '0')
      ->groupBy('stustatus.LEVEL')
      ->groupBy('GRADE')
      ->groupBy('GRADE_code')
      ->groupBy('DESCRIPTION');
    $total_credits = $total_credits->execute();
    while ($total_credits_row = $total_credits->fetch()) {
      if ($total_credits_row['GRADE'] == '') $total_credits_row['GRADE'] = $total_credits_row['GRADE_code'];
      
      $credits[$total_credits_row['GRADE']][$total_credits_row['LEVEL']] = $total_credits_row['total_credits'];
      if (!isset($totals_credit_data[$total_credits_row['LEVEL']]))
        $totals_credit_data[$total_credits_row['LEVEL']] = 0;
      $totals_credit_data[$total_credits_row['LEVEL']] += $total_credits_row['total_credits'];
    }
    
    // Get FTE
    $full_fte = $this->db()->db_select('STUD_STUDENT_CLASSES', 'classes')
      ->expression('SUM(classes.CREDITS_ATTEMPTED)', 'total_credits')
      ->join('STUD_STUDENT_STATUS', 'stustatus', 'stustatus.STUDENT_STATUS_ID = classes.STUDENT_STATUS_ID')
      ->fields('stustatus', array('LEVEL', 'FTE', 'GRADE' => 'GRADE_code'))
      ->join('STUD_SCHOOL_TERM_LEVEL', 'schoolterm', 'schoolterm.LEVEL = stustatus.LEVEL AND schoolterm.ORGANIZATION_TERM_ID = stustatus.ORGANIZATION_TERM_ID')
      ->fields('schoolterm', array('FTE_FULL_TIME_HOURS' => 'FULL_TIME_CREDITS'))
      ->join('STUD_MARK_SCALE', 'markscale', 'markscale.MARK_SCALE_ID = classes.MARK_SCALE_ID')
      ->leftJoin('CORE_LOOKUP_VALUES', 'value', "value.CODE = stustatus.GRADE AND value.LOOKUP_TABLE_ID = (SELECT LOOKUP_TABLE_ID FROM CORE_LOOKUP_TABLES WHERE LOOKUP_TABLE_NAME = '".$this->focus->getOrganizationTarget().".Student.Enrollment.Grade')")
      ->fields('value', array('DESCRIPTION' => 'GRADE'))
      ->condition('stustatus.ORGANIZATION_TERM_ID', $this->focus->getOrganizationTermIDs())
      ->condition('stustatus.STATUS', null)
      ->condition('markscale.AUDIT', '0')
      ->groupBy('stustatus.LEVEL')
      ->groupBy('GRADE')
      ->groupBy('GRADE_code')
      ->groupBy('DESCRIPTION')
      ->groupBy('FTE_FULL_TIME_HOURS')
      ->groupBy('classes.STUDENT_STATUS_ID');
    $full_fte = $full_fte->execute();
    while ($full_fte_row = $full_fte->fetch()) {
      
      
      if ($full_fte_row['GRADE'] == '') $full_fte_row['GRADE'] = $full_fte_row['GRADE_code'];
      
      if (!isset($fte_data[$full_fte_row['GRADE']][$full_fte_row['LEVEL']]))
        $fte_data[$full_fte_row['GRADE']][$full_fte_row['LEVEL']] = 0;
      
      // if FTE is 1, add 1 to total
      if ($full_fte_row['FTE'] == 1) {
        $fte_amount = $full_fte_row['FTE'];
        $fte_data[$full_fte_row['GRADE']][$full_fte_row['LEVEL']] += $fte_amount;  
      } else {
        // not full FTE
        $fte_amount = 0;
        if ($full_fte_row['FULL_TIME_CREDITS']) {
          $fte_amount = bcdiv($full_fte_row['total_credits'], $full_fte_row['FULL_TIME_CREDITS'], 3);
          $fte_data[$full_fte_row['GRADE']][$full_fte_row['LEVEL']] += $fte_amount;
        }
      }
      
      if (!isset($totals_fte_data[$full_fte_row['LEVEL']]))
        $totals_fte_data[$full_fte_row['LEVEL']] = 0;
      $totals_fte_data[$full_fte_row['LEVEL']] += $fte_amount;
      unset($fte_amount);
    }
    
    
    $enrolled_list = $this->db()->db_select('STUD_STUDENT_STATUS', 'status')
      ->fields('status', array('LEVEL', 'ENTER_DATE', 'STATUS', 'LEAVE_DATE', 'GRADE' => 'GRADE_code'))
      ->join('CONS_CONSTITUENT', 'constituent', 'constituent.CONSTITUENT_ID = status.STUDENT_ID')
      ->fields('constituent', array('PERMANENT_NUMBER', 'LAST_NAME', 'FIRST_NAME', 'MIDDLE_NAME', 'GENDER'))
      ->leftJoin('CORE_LOOKUP_VALUES', 'value', "value.CODE = status.GRADE AND value.LOOKUP_TABLE_ID = (SELECT LOOKUP_TABLE_ID FROM CORE_LOOKUP_TABLES WHERE LOOKUP_TABLE_NAME = '".$this->focus->getOrganizationTarget().".Student.Enrollment.Grade')")
      ->fields('value', array('DESCRIPTION' => 'GRADE'))
      ->leftJoin('CORE_LOOKUP_VALUES', 'entercode_value', "entercode_value.CODE = status.ENTER_CODE AND entercode_value.LOOKUP_TABLE_ID = (SELECT LOOKUP_TABLE_ID FROM CORE_LOOKUP_TABLES WHERE LOOKUP_TABLE_NAME = '".$this->focus->getOrganizationTarget().".Student.Enrollment.EnterCode')")
      ->fields('entercode_value', array('DESCRIPTION' => 'ENTER_CODE'))
      ->leftJoin('CORE_LOOKUP_VALUES', 'leavecode_value', "leavecode_value.CODE = status.LEAVE_CODE AND leavecode_value.LOOKUP_TABLE_ID = (SELECT LOOKUP_TABLE_ID FROM CORE_LOOKUP_TABLES WHERE LOOKUP_TABLE_NAME = '".$this->focus->getOrganizationTarget().".Student.Enrollment.LeaveCode')")
      ->fields('leavecode_value', array('DESCRIPTION' => 'LEAVE_CODE'))
      ->leftJoin('BILL_TUITION_RATE', 'tuitionrate', 'tuitionrate.TUITION_RATE_ID = status.TUITION_RATE_ID')
      ->fields('tuitionrate', array('TUITION_RATE_NAME'))
      ->condition('status.ORGANIZATION_TERM_ID', $this->focus->getOrganizationTermIDs())
      ->orderBy('LEVEL')->orderBy('GRADE')->orderBy('LAST_NAME')->orderBy('FIRST_NAME');
    $enrolled_list = $enrolled_list->execute()->fetchAll();
    
    }
    
    asort($levels);
    
    return $this->render('KulaCoreHomeBundle:CoreHome:index.html.twig', array('enrolled' => $enrolled_data, 'levels' => $levels, 'credits' => $credits, 'totals_credit_data' => $totals_credit_data, 'totals_enrolled_data' => $totals_enrolled_data, 'enrolled_list' => $enrolled_list, 'fte_data' => $fte_data, 'totals_fte_data' => $totals_fte_data)); 
  }

  public function homeScheduleAction() {
    $this->authorize();
        
    $enrolled_data = array();
    $totals_enrolled_data = array();
    $levels = array();
    $enrolled_list = array();
    $credits = array();
    $totals_credit_data = array();
    $fte_data = array();
    $totals_fte_data = array();
    
    if (count($this->focus->getOrganizationTermIDs()) > 0 AND count($this->focus->getOrganizationTermIDs()) < 5) {

    // Get students with schedules for terms
    $student_schedules = array('0');
    $student_schedules_result = $this->db()->db_select('STUD_STUDENT_CLASSES', 'class')
      ->distinct()
      ->fields('class', array('STUDENT_STATUS_ID'))
      ->join('STUD_STUDENT_STATUS', 'stustatus', 'stustatus.STUDENT_STATUS_ID = class.STUDENT_STATUS_ID')
      ->condition('stustatus.ORGANIZATION_TERM_ID', $this->focus->getOrganizationTermIDs())
      ->condition('class.DROPPED', 0)
      ->groupBy('STUDENT_STATUS_ID')
      ->execute();
    while ($student_schedules_row = $student_schedules_result->fetch()) {
      $student_schedules[] = $student_schedules_row['STUDENT_STATUS_ID'];
    }
      
    $enrolled = $this->db()->db_select('STUD_STUDENT_STATUS')
      ->fields('STUD_STUDENT_STATUS', array('LEVEL', 'GRADE' => 'GRADE_code'))
      ->expression('COUNT(*)', 'count')
      ->leftJoin('CORE_LOOKUP_VALUES', 'value', "value.CODE = STUD_STUDENT_STATUS.GRADE AND value.LOOKUP_TABLE_ID = (SELECT LOOKUP_TABLE_ID FROM CORE_LOOKUP_TABLES WHERE LOOKUP_TABLE_NAME = '".$this->focus->getOrganizationTarget().".Student.Enrollment.Grade')")
      ->fields('value', array('DESCRIPTION' => 'GRADE'))
      ->condition('ORGANIZATION_TERM_ID', $this->focus->getOrganizationTermIDs())
      ->condition('STATUS', null)
      ->condition('STUDENT_STATUS_ID', $student_schedules, 'IN')
      ->groupBy('LEVEL')
      ->groupBy('GRADE')
      ->groupBy('GRADE_code')
      ->groupBy('DESCRIPTION');
    $enrolled = $enrolled->execute();
    while ($enrolled_row = $enrolled->fetch()) {
      
      if ($enrolled_row['GRADE'] == '') $enrolled_row['GRADE'] = $enrolled_row['GRADE_code'];
      
      $levels[$enrolled_row['LEVEL']] = $enrolled_row['LEVEL'];
      $enrolled_data[$enrolled_row['GRADE']][$enrolled_row['LEVEL']] = $enrolled_row['count'];
      if (!isset($totals_enrolled_data[$enrolled_row['LEVEL']]))
        $totals_enrolled_data[$enrolled_row['LEVEL']] = 0;
      $totals_enrolled_data[$enrolled_row['LEVEL']] += $enrolled_row['count'];
    }
    
    $total_credits = $this->db()->db_select('STUD_STUDENT_CLASSES', 'classes')
      ->expression('SUM(classes.CREDITS_ATTEMPTED)', 'total_credits')
      ->join('STUD_STUDENT_STATUS', 'stustatus', 'stustatus.STUDENT_STATUS_ID = classes.STUDENT_STATUS_ID')
      ->fields('stustatus', array('LEVEL', 'GRADE' => 'GRADE_code'))
      ->join('STUD_MARK_SCALE', 'markscale', 'markscale.MARK_SCALE_ID = classes.MARK_SCALE_ID')
      ->leftJoin('CORE_LOOKUP_VALUES', 'value', "value.CODE = stustatus.GRADE AND value.LOOKUP_TABLE_ID = (SELECT LOOKUP_TABLE_ID FROM CORE_LOOKUP_TABLES WHERE LOOKUP_TABLE_NAME = '".$this->focus->getOrganizationTarget().".Student.Enrollment.Grade')")
      ->fields('value', array('DESCRIPTION' => 'GRADE'))
      ->condition('ORGANIZATION_TERM_ID', $this->focus->getOrganizationTermIDs())
      ->condition('stustatus.STATUS', null)
      ->condition('markscale.AUDIT', '0')
      ->condition('classes.STUDENT_STATUS_ID', $student_schedules)
      ->groupBy('stustatus.LEVEL')
      ->groupBy('GRADE')
      ->groupBy('GRADE_code')
      ->groupBy('DESCRIPTION');
    $total_credits = $total_credits->execute();
    while ($total_credits_row = $total_credits->fetch()) {
      if ($total_credits_row['GRADE'] == '') $total_credits_row['GRADE'] = $total_credits_row['GRADE_code'];
      
      $credits[$total_credits_row['GRADE']][$total_credits_row['LEVEL']] = $total_credits_row['total_credits'];
      if (!isset($totals_credit_data[$total_credits_row['LEVEL']]))
        $totals_credit_data[$total_credits_row['LEVEL']] = 0;
      $totals_credit_data[$total_credits_row['LEVEL']] += $total_credits_row['total_credits'];
    }
    
    // Get FTE
    $full_fte = $this->db()->db_select('STUD_STUDENT_CLASSES', 'classes')
      ->expression('SUM(classes.CREDITS_ATTEMPTED)', 'total_credits')
      ->join('STUD_STUDENT_STATUS', 'stustatus', 'stustatus.STUDENT_STATUS_ID = classes.STUDENT_STATUS_ID')
      ->fields('stustatus', array('LEVEL', 'FTE', 'GRADE' => 'GRADE_code'))
      ->join('STUD_SCHOOL_TERM_LEVEL', 'schoolterm', 'schoolterm.LEVEL = stustatus.LEVEL AND schoolterm.ORGANIZATION_TERM_ID = stustatus.ORGANIZATION_TERM_ID')
      ->fields('schoolterm', array('FTE_FULL_TIME_HOURS' => 'FULL_TIME_CREDITS'))
      ->join('STUD_MARK_SCALE', 'markscale', 'markscale.MARK_SCALE_ID = classes.MARK_SCALE_ID')
      ->leftJoin('CORE_LOOKUP_VALUES', 'value', "value.CODE = stustatus.GRADE AND value.LOOKUP_TABLE_ID = (SELECT LOOKUP_TABLE_ID FROM CORE_LOOKUP_TABLES WHERE LOOKUP_TABLE_NAME = '".$this->focus->getOrganizationTarget().".Student.Enrollment.Grade')")
      ->fields('value', array('DESCRIPTION' => 'GRADE'))
      ->condition('stustatus.ORGANIZATION_TERM_ID', $this->focus->getOrganizationTermIDs())
      ->condition('stustatus.STATUS', null)
      ->condition('markscale.AUDIT', '0')
      ->condition('stustatus.STUDENT_STATUS_ID', $student_schedules)
      ->groupBy('stustatus.LEVEL')
      ->groupBy('GRADE')
      ->groupBy('GRADE_code')
      ->groupBy('DESCRIPTION')
      ->groupBy('FTE_FULL_TIME_HOURS')
      ->groupBy('classes.STUDENT_STATUS_ID');
    $full_fte = $full_fte->execute();
    while ($full_fte_row = $full_fte->fetch()) {
      
      
      if ($full_fte_row['GRADE'] == '') $full_fte_row['GRADE'] = $full_fte_row['GRADE_code'];
      
      if (!isset($fte_data[$full_fte_row['GRADE']][$full_fte_row['LEVEL']]))
        $fte_data[$full_fte_row['GRADE']][$full_fte_row['LEVEL']] = 0;
      
      // if FTE is 1, add 1 to total
      if ($full_fte_row['FTE'] == 1) {
        $fte_amount = $full_fte_row['FTE'];
        $fte_data[$full_fte_row['GRADE']][$full_fte_row['LEVEL']] += $fte_amount;  
      } else {
        // not full FTE
        $fte_amount = 0;
        if ($full_fte_row['FULL_TIME_CREDITS']) {
          $fte_amount = bcdiv($full_fte_row['total_credits'], $full_fte_row['FULL_TIME_CREDITS'], 3);
          $fte_data[$full_fte_row['GRADE']][$full_fte_row['LEVEL']] += $fte_amount;
        }
      }
      
      if (!isset($totals_fte_data[$full_fte_row['LEVEL']]))
        $totals_fte_data[$full_fte_row['LEVEL']] = 0;
      $totals_fte_data[$full_fte_row['LEVEL']] += $fte_amount;
      unset($fte_amount);
    }
    
    
    $enrolled_list = $this->db()->db_select('STUD_STUDENT_STATUS', 'status')
      ->fields('status', array('LEVEL', 'ENTER_DATE', 'STATUS', 'LEAVE_DATE', 'GRADE' => 'GRADE_code'))
      ->join('CONS_CONSTITUENT', 'constituent', 'constituent.CONSTITUENT_ID = status.STUDENT_ID')
      ->fields('constituent', array('PERMANENT_NUMBER', 'LAST_NAME', 'FIRST_NAME', 'MIDDLE_NAME', 'GENDER'))
      ->leftJoin('CORE_LOOKUP_VALUES', 'value', "value.CODE = status.GRADE AND value.LOOKUP_TABLE_ID = (SELECT LOOKUP_TABLE_ID FROM CORE_LOOKUP_TABLES WHERE LOOKUP_TABLE_NAME = '".$this->focus->getOrganizationTarget().".Student.Enrollment.Grade')")
      ->fields('value', array('DESCRIPTION' => 'GRADE'))
      ->leftJoin('CORE_LOOKUP_VALUES', 'entercode_value', "entercode_value.CODE = status.ENTER_CODE AND entercode_value.LOOKUP_TABLE_ID = (SELECT LOOKUP_TABLE_ID FROM CORE_LOOKUP_TABLES WHERE LOOKUP_TABLE_NAME = '".$this->focus->getOrganizationTarget().".Student.Enrollment.EnterCode')")
      ->fields('entercode_value', array('DESCRIPTION' => 'ENTER_CODE'))
      ->leftJoin('CORE_LOOKUP_VALUES', 'leavecode_value', "leavecode_value.CODE = status.LEAVE_CODE AND leavecode_value.LOOKUP_TABLE_ID = (SELECT LOOKUP_TABLE_ID FROM CORE_LOOKUP_TABLES WHERE LOOKUP_TABLE_NAME = '".$this->focus->getOrganizationTarget().".Student.Enrollment.LeaveCode')")
      ->fields('leavecode_value', array('DESCRIPTION' => 'LEAVE_CODE'))
      ->leftJoin('BILL_TUITION_RATE', 'tuitionrate', 'tuitionrate.TUITION_RATE_ID = status.TUITION_RATE_ID')
      ->fields('tuitionrate', array('TUITION_RATE_NAME'))
      ->condition('status.ORGANIZATION_TERM_ID', $this->focus->getOrganizationTermIDs())
      ->condition('status.STUDENT_STATUS_ID', $student_schedules)
      ->orderBy('LEVEL')->orderBy('GRADE')->orderBy('LAST_NAME')->orderBy('FIRST_NAME');
    $enrolled_list = $enrolled_list->execute()->fetchAll();
    
    }
    
    asort($levels);
    
    return $this->render('KulaCoreHomeBundle:CoreHome:index.html.twig', array('enrolled' => $enrolled_data, 'levels' => $levels, 'credits' => $credits, 'totals_credit_data' => $totals_credit_data, 'totals_enrolled_data' => $totals_enrolled_data, 'enrolled_list' => $enrolled_list, 'fte_data' => $fte_data, 'totals_fte_data' => $totals_fte_data)); 
  }
  
}