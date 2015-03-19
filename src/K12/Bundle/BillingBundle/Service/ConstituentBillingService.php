<?php

namespace Kula\K12\Bundle\BillingBundle\Service;

class ConstituentBillingService {
  
  protected $database;
  
  protected $poster_factory;
  
  protected $record;
  
  protected $session;
  
  public function __construct(\Kula\Core\Component\DB\DB $db, 
                              \Kula\Core\Component\DB\PosterFactory $poster_factory,
                              $record = null, 
                              $session = null) {
    $this->database = $db;
    $this->record = $record;
    $this->posterFactory = $poster_factory;
    $this->session = $session;
  }
  
  public function addTransaction($constituent_id, $organization_term_id, $transaction_code_id, $transaction_date, $transaction_description, $amount) {
    
    // Get transaction code
    $transaction_code = $this->database->db_select('BILL_CODE', 'code')
      ->fields('code', array('CODE_TYPE', 'CODE_DESCRIPTION'))
      ->condition('code.CODE_ID', $transaction_code_id)
      ->execute()->fetch();
    if ($transaction_description) {
      $formatted_transaction_description = $transaction_code['CODE_DESCRIPTION'].' - '.$transaction_description;
    } else {
      $formatted_transaction_description = $transaction_code['CODE_DESCRIPTION'];
    }
    
    if ($transaction_code['CODE_TYPE'] == 'P')
      $amount = $amount * -1;
    
    // Prepare & post payment data    
    return $this->posterFactory->newPoster()->add('K12.Billing.Transaction', 'new', array(
      'K12.Billing.Transaction.ConstituentID' => $constituent_id,
      'K12.Billing.Transaction.OrganizationTermID' => $organization_term_id,
      'K12.Billing.Transaction.CodeID' => $transaction_code_id,
      'K12.Billing.Transaction.TransactionDate' => $transaction_date,
      'K12.Billing.Transaction.Description' => $formatted_transaction_description,
      'K12.Billing.Transaction.Amount' => $amount, 
      'K12.Billing.Transaction.OriginalAmount' => $amount,
      'K12.Billing.Transaction.AppliedBalance' => $amount,
      'K12.Billing.Transaction.Posted' => 0
    ))->process()->getResult();
  }
  
  public function addCourseFees($student_class_id) {
    
    // Get Class Info
    $class_fees = $this->database->db_select('STUD_STUDENT_CLASSES', 'class')
      ->fields('class', array('STUDENT_STATUS_ID'))
      ->join('STUD_STUDENT_STATUS', 'status', 'class.STUDENT_STATUS_ID = status.STUDENT_STATUS_ID')
      ->fields('status', array('STUDENT_ID'))
      ->join('STUD_SECTION', 'section', 'section.SECTION_ID = class.SECTION_ID')
      ->fields('section', array('ORGANIZATION_TERM_ID'))
      ->join('BILL_COURSE_FEE', 'crsfee', 'crsfee.COURSE_ID = section.COURSE_ID AND section.ORGANIZATION_TERM_ID = crsfee.ORGANIZATION_TERM_ID')
      ->fields('crsfee', array('CODE_ID', 'AMOUNT'))
      ->join('BILL_CODE', 'code', 'code.CODE_ID = crsfee.CODE_ID')
      ->fields('code', array('CODE_DESCRIPTION'))
      ->condition('class.STUDENT_CLASS_ID', $student_class_id)
      ->execute();
    while ($class_fee_row = $class_fees->fetch()) {
      // Prepare & post payment data
      $this->posterFactory->newPoster()->add('K12.Billing.Transaction', 'new', array(
        'K12.Billing.Transaction.ConstituentID' => $class_fee_row['STUDENT_ID'],
        'K12.Billing.Transaction.OrganizationTermID' => $class_fee_row['ORGANIZATION_TERM_ID'],
        'K12.Billing.Transaction.CodeID' => $class_fee_row['CODE_ID'],
        'K12.Billing.Transaction.TransactionDate' => date('Y-m-d'),
        'K12.Billing.Transaction.Description' => $class_fee_row['CODE_DESCRIPTION'],
        'K12.Billing.Transaction.Amount' => $class_fee_row['AMOUNT'], 
        'K12.Billing.Transaction.OriginalAmount' => $class_fee_row['AMOUNT'],
        'K12.Billing.Transaction.AppliedBalance' => $class_fee_row['AMOUNT'],
        'K12.Billing.Transaction.Posted' => 1,
        'K12.Billing.Transaction.ShowOnStatement' => 1,
        'K12.Billing.Transaction.StudentClassID' => $student_class_id
      ))->process();
    }
    // Add section fees
    // Get Class Info
    $class_fees = $this->database->db_select('STUD_STUDENT_CLASSES', 'class')
      ->fields('class', array('STUDENT_STATUS_ID'))
      ->join('STUD_STUDENT_STATUS', 'status', 'class.STUDENT_STATUS_ID = status.STUDENT_STATUS_ID')
      ->fields('status', array('STUDENT_ID'))
      ->join('STUD_SECTION', 'section', 'section.SECTION_ID = class.SECTION_ID')
      ->fields('section', array('ORGANIZATION_TERM_ID'))
      ->join('BILL_SECTION_FEE', 'crsfee', 'crsfee.SECTION_ID = section.SECTION_ID')
      ->fields('crsfee', array('CODE_ID', 'AMOUNT'))
      ->join('BILL_CODE', 'code', 'code.CODE_ID = crsfee.CODE_ID')
      ->fields('code', array('CODE_DESCRIPTION'))
      ->condition('class.STUDENT_CLASS_ID', $student_class_id)
      ->execute();
    while ($class_fee_row = $class_fees->fetch()) {
      
      // Prepare & post payment data
      $this->posterFactory->newPoster()->add('K12.Billing.Transaction', 'new', array(
        'K12.Billing.Transaction.ConstituentID' => $class_fee_row['STUDENT_ID'],
        'K12.Billing.Transaction.OrganizationTermID' => $class_fee_row['ORGANIZATION_TERM_ID'],
        'K12.Billing.Transaction.CodeID' => $class_fee_row['CODE_ID'],
        'K12.Billing.Transaction.TransactionDate' => date('Y-m-d'),
        'K12.Billing.Transaction.Description' => $class_fee_row['CODE_DESCRIPTION'],
        'K12.Billing.Transaction.Amount' => $class_fee_row['AMOUNT'], 
        'K12.Billing.Transaction.OriginalAmount' => $class_fee_row['AMOUNT'],
        'K12.Billing.Transaction.AppliedBalance' => $class_fee_row['AMOUNT'],
        'K12.Billing.Transaction.Posted' => 1,
        'K12.Billing.Transaction.ShowOnStatement' => 1,
        'K12.Billing.Transaction.StudentClassID' => $student_class_id
      ))->process();
    }
    
  }
  
  public function removeCourseFees($student_class_id) {
    $course_fees_transactions = $this->database->db_select('BILL_CONSTITUENT_TRANSACTIONS', 'constrans')
      ->fields('constrans', array('CONSTITUENT_TRANSACTION_ID'))
      ->condition('constrans.REFUND_TRANSACTION_ID', null)
      ->condition('constrans.STUDENT_CLASS_ID', $student_class_id)
      ->execute();
    while ($course_fees_transaction = $course_fees_transactions->fetch()) {
      $this->removeTransaction($course_fees_transaction['CONSTITUENT_TRANSACTION_ID'], 'Schedule change (auto)', date('Y-m-d'));
    }
  }
  
  public function determineTuitionRate($student_status_id) {

    // Get Student Status Info
    $student_status = $this->database->db_select('STUD_STUDENT_STATUS', 'status')
      ->fields('status', array('STUDENT_STATUS_ID', 'ORGANIZATION_TERM_ID', 'ENTER_CODE', 'LEVEL'))
      ->condition('status.STUDENT_STATUS_ID', $student_status_id)
      ->execute()->fetch();
    
    // Get Tuition Rate
    $tuition_rate = $this->database->db_select('BILL_TUITION_RATE_STUDENTS', 'trstu')
      ->fields('trstu')
      ->join('BILL_TUITION_RATE', 'tr', 'tr.TUITION_RATE_ID = trstu.TUITION_RATE_ID')
      ->fields('tr', array('TUITION_RATE_ID'))
      ->condition('trstu.LEVEL', $student_status['LEVEL'])
      ->condition('trstu.ENTER_CODE', $student_status['ENTER_CODE'])
      ->condition('tr.ORGANIZATION_TERM_ID', $student_status['ORGANIZATION_TERM_ID'])
      ->execute()->fetch();
    
    if ($tuition_rate['TUITION_RATE_ID']) {
      // post tuition rate
      return $this->posterFactory->newPoster()->edit('K12.Student.Status', $student_status_id, array(
        'K12.Student.Status.TuitionRateID' => $tuition_rate['TUITION_RATE_ID']
      ))->process()->getResult();
    }
    
  }
  
  public function postFinancialAidAward($award_id) {
    
    $transaction = $this->database->db_transaction();
    
    // Get FA Info
    $award_info = $this->database->db_select('FAID_STUDENT_AWARDS', 'award')
      ->fields('award', array('AWARD_ID', 'NET_AMOUNT', 'DISBURSEMENT_DATE'))
      ->join('FAID_AWARD_CODE', 'awardcode', 'awardcode.AWARD_CODE_ID = award.AWARD_CODE_ID')
      ->join('BILL_CODE', 'code', 'awardcode.TRANSACTION_CODE_ID = code.CODE_ID')
      ->fields('code', array('CODE_ID', 'CODE_DESCRIPTION'))
      ->join('FAID_STUDENT_AWARD_YEAR_TERMS', 'awardterms', 'awardterms.AWARD_YEAR_TERM_ID = award.AWARD_YEAR_TERM_ID')
      ->fields('awardterms', array('ORGANIZATION_TERM_ID'))
      ->join('FAID_STUDENT_AWARD_YEAR', 'stuawardyr', 'stuawardyr.AWARD_YEAR_ID = awardterms.AWARD_YEAR_ID')
      ->fields('stuawardyr', array('STUDENT_ID'))
      ->condition('award.AWARD_ID', $award_id)
      ->execute()->fetch();
    
    $payment_poster = $this->posterFactory->newPoster()->add('K12.Billing.Transaction', 'new', array(
      'K12.Billing.Transaction.ConstituentID' => $award_info['STUDENT_ID'],
      'K12.Billing.Transaction.OrganizationTermID' => $award_info['ORGANIZATION_TERM_ID'],
      'K12.Billing.Transaction.CodeID' => $award_info['CODE_ID'],
      'K12.Billing.Transaction.TransactionDate' => $award_info['DISBURSEMENT_DATE'] != '' ? $award_info['DISBURSEMENT_DATE'] : date('Y-m-d'),
      'K12.Billing.Transaction.Description' => $award_info['CODE_DESCRIPTION'],
      'K12.Billing.Transaction.Amount' => $award_info['NET_AMOUNT'] * -1, 
      'K12.Billing.Transaction.OriginalAmount' => $award_info['NET_AMOUNT'] * -1,
      'K12.Billing.Transaction.AppliedBalance' => $award_info['NET_AMOUNT'] * -1,
      'K12.Billing.Transaction.Posted' => 1,
      'K12.Billing.Transaction.ShowOnStatement' => 1,
      'K12.Billing.Transaction.AwardID' => $award_id,
    ))->process()->getResult();
    
    $award_poster = $this->posterFactory->newPoster()->edit('K12.FAID.Student.Award', $award_id, array(
      'K12.FAID.Student.Award.AwardStatus' => 'AWAR'
    ))->process()->getResult();
    
    $transaction->commit();
    
  }
  
  public function adjustFinancialAidAward(Array $award_ids) {
    
    // Query for financial aid award totals
    $award_id_totals = $this->database->db_select('BILL_CONSTITUENT_TRANSACTIONS', 'constrans')
      ->fields('constrans', array('AWARD_ID'))
      ->expression('SUM(AMOUNT)', 'award_total')
      ->condition('AWARD_ID', array_keys($award_ids))
      ->groupBy('AWARD_ID')
      ->execute();
    while ($award_id_total = $award_id_totals->fetch()) {
      if ($award_id_total['award_total'] != ($award_ids[$award_id_total['AWARD_ID']] * -1)) {
        
        // Change award to negative number and determine difference
        $adjustment_amt = -1 * ($award_id_total['award_total'] - (-1 * $award_ids[$award_id_total['AWARD_ID']]));
        
        // Get FA Info
        $award_info = $this->database->db_select('FAID_STUDENT_AWARDS', 'award')
          ->fields('award', array('AWARD_ID', 'NET_AMOUNT'))
          ->join('FAID_AWARD_CODE', 'awardcode', 'awardcode.AWARD_CODE_ID = award.AWARD_CODE_ID')
          ->join('BILL_CODE', 'code', 'awardcode.TRANSACTION_CODE_ID = code.CODE_ID')
          ->fields('code', array('CODE_ID', 'CODE_DESCRIPTION'))
          ->join('FAID_STUDENT_AWARD_YEAR_TERMS', 'awardterms', 'awardterms.AWARD_YEAR_TERM_ID = award.AWARD_YEAR_TERM_ID')
          ->fields('awardterms', array('ORGANIZATION_TERM_ID'))
          ->join('FAID_STUDENT_AWARD_YEAR', 'stuawardyr', 'stuawardyr.AWARD_YEAR_ID = awardterms.AWARD_YEAR_ID')
          ->fields('stuawardyr', array('STUDENT_ID'))
          ->condition('award.AWARD_ID', $award_id_total['AWARD_ID'])
          ->execute()->fetch();
        
        $payment_poster = $this->posterFactory->newPoster()->add('K12.Billing.Transaction', 'new', array(
          'K12.Billing.Transaction.ConstituentID' => $award_info['STUDENT_ID'],
          'K12.Billing.Transaction.OrganizationTermID' => $award_info['ORGANIZATION_TERM_ID'],
          'K12.Billing.Transaction.CodeID' => $award_info['CODE_ID'],
          'K12.Billing.Transaction.TransactionDate' => date('Y-m-d'),
          'K12.Billing.Transaction.Description' => $award_info['CODE_DESCRIPTION'],
          'K12.Billing.Transaction.Amount' => $adjustment_amt, 
          'K12.Billing.Transaction.OriginalAmount' => $adjustment_amt,
          'K12.Billing.Transaction.AppliedBalance' => $adjustment_amt,
          'K12.Billing.Transaction.Posted' => 0,
          'K12.Billing.Transaction.ShowOnStatement' => 1,
          'K12.Billing.Transaction.AwardID' => $award_id_total['AWARD_ID'],
        ))->process()->getResult();
      }
    }
    
  }
  
  public function postTransaction($constituent_transaction_id) {
    return $this->posterFactory->newPoster()->edit('K12.Billing.Transaction', $constituent_transaction_id, array(
      'K12.Billing.Transaction.Posted' => 1,
      'K12.Billing.Transaction.ShowOnStatement' => 1
    ))->process()->getResult();
  }
  
  public function removeTransaction($constituent_transaction_id, $voided_reason, $transaction_date) {
    
    $transaction = $this->database->db_transaction();

    // Get transaction info
    $transaction_row = $this->database->db_select('BILL_CONSTITUENT_TRANSACTIONS', 'transactions')
      ->fields('transactions', array('POSTED', 'CODE_ID', 'CONSTITUENT_ID', 'ORGANIZATION_TERM_ID', 'TRANSACTION_DATE', 'TRANSACTION_DESCRIPTION', 'AMOUNT', 'VOIDED_REASON', 'STUDENT_CLASS_ID', 'AWARD_ID'))
      ->condition('CONSTITUENT_TRANSACTION_ID', $constituent_transaction_id)
      ->execute()->fetch();
    
    if ($transaction_row['POSTED'] == '1') {
      $new_amount = $transaction_row['AMOUNT'] * -1;
      
      if ($new_amount < 0)
        $transaction_description = 'REFUND '.$transaction_row['TRANSACTION_DESCRIPTION'];
      else
        $transaction_description = 'PAYBACK '.$transaction_row['TRANSACTION_DESCRIPTION'];
      
      // create return payment
      $return_payment_data = array(
        'K12.Billing.Transaction.ConstituentID' => $transaction_row['CONSTITUENT_ID'],
        'K12.Billing.Transaction.OrganizationTermID' => $transaction_row['ORGANIZATION_TERM_ID'],
        'K12.Billing.Transaction.CodeID' => $transaction_row['CODE_ID'],
        'K12.Billing.Transaction.TransactionDate' => $transaction_date,
        'K12.Billing.Transaction.Description' => $transaction_description,
        'K12.Billing.Transaction.Amount' => $new_amount, 
        'K12.Billing.Transaction.OriginalAmount' => $new_amount,
        'K12.Billing.Transaction.AppliedBalance' => 0,
        'K12.Billing.Transaction.RefundTransactionID' => $constituent_transaction_id,
        'K12.Billing.Transaction.VoidedReason' => $voided_reason,
        'K12.Billing.Transaction.Posted' => 1,
        'K12.Billing.Transaction.ShowOnStatement' => 0
      );
      
      if ($transaction_row['STUDENT_CLASS_ID']) $return_payment_data['K12.Billing.Transaction.StudentClassID'] = $transaction_row['STUDENT_CLASS_ID'];
      if ($transaction_row['AWARD_ID']) $return_payment_data['K12.Billing.Transaction.AwardID'] = $transaction_row['AWARD_ID'];
      
      $return_payment_affected = $this->posterFactory->newPoster()->add('K12.Billing.Transaction', 'new', $return_payment_data)->process()->getResult();

      // set as returned for existing transaction
      $original_transaction_poster = $this->posterFactory->newPoster()->edit('K12.Billing.Transaction', $constituent_transaction_id, array(
        'K12.Billing.Transaction.RefundTransactionID' => $return_payment_affected,
        'K12.Billing.Transaction.AppliedBalance' => 0,
        'K12.Billing.Transaction.ShowOnStatement' => 0
      ))->process()->getResult();
        
        // Has an FA award.  Need to set back to pending
        if ($transaction_row['AWARD_ID']) {
          $fa_poster = $this->posterFactory->newPoster()->add('K12.FAID.Student.Award', $transaction_row['AWARD_ID'], array(
            'K12.FAID.Student.Award.AwardStatus' => 'PEND'
          ))->process()->getResult();
        }
        
      $transaction->commit();
      
      return $return_payment_affected;
      
    } else {
      // Void payment
      if ($transaction_row['VOIDED_REASON'] != '')
        $voided_reason = $transaction_row['VOIDED_REASON']. ' | '.$voided_reason;
      
      $voided_transaction_result = $this->posterFactory->newPoster()->edit('K12.Billing.Transaction', $constituent_transaction_id, array(
        'K12.Billing.Transaction.Amount' => 0.00, 
        'K12.Billing.Transaction.AppliedBalance' => 0.00, 
        'K12.Billing.Transaction.Posted' => 1, 
        'K12.Billing.Transaction.ShowOnStatement' => 0, 
        'K12.Billing.Transaction.Voided' => 1, 
        'K12.Billing.Transaction.VoidedReason' => $voided_reason, 
        'K12.Billing.Transaction.VoidedUserstamp' => $this->session->get('user_id'), 
        'K12.Billing.Transaction.VoidedTimestamp' => date('Y-m-d H:i:s')
      ))->process()->getResult();

      $transaction->commit();
      
      return $voided_transaction_result;
    }
    
  }
  
}