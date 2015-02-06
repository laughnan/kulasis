<?php

namespace Kula\HEd\Bundle\BillingBundle\Controller;

use Kula\Core\Bundle\FrameworkBundle\Controller\Controller;

class SISTransactionsController extends Controller {
  
  public function transactionsAction() {
    $this->authorize();
    $this->setRecordType('SIS.HEd.Student');
    
    if ($this->request->request->get('void')) {
      $constituent_billing_service = $this->get('kula.HEd.billing.student');
      
      $void = $this->request->request->get('void');
      $non = $this->request->request->get('non');
        
      if (isset($non['BILL_CONSTITUENT_TRANSACTIONS']['TRANSACTION_DATE']))
        $transaction_date = $non['BILL_CONSTITUENT_TRANSACTIONS']['TRANSACTION_DATE'];
      else 
        $transaction_date = null;
      
      if (isset($non['BILL_CONSTITUENT_TRANSACTIONS']['VOIDED_REASON']))
        $reason = $non['BILL_CONSTITUENT_TRANSACTIONS']['VOIDED_REASON'];
      else 
        $reason = null;
      
      foreach($void as $table => $row_info) {
        foreach($row_info as $row_id => $row) {
          $constituent_billing_service->removeTransaction($row_id, $reason, $transaction_date);
        }
      }
    }
    
    $transactions = array();
    
    if ($this->record->getSelectedRecordID()) {
      
      $transactions = $this->db()->db_select('BILL_CONSTITUENT_TRANSACTIONS', 'transactions')
        ->fields('transactions', array('CONSTITUENT_TRANSACTION_ID', 'TRANSACTION_DATE', 'TRANSACTION_DESCRIPTION', 'AMOUNT', 'POSTED', 'VOIDED', 'APPLIED_BALANCE', ))
        ->join('BILL_CODE', 'code', 'code.CODE_ID = transactions.CODE_ID')
        ->fields('code', array('CODE_TYPE', 'CODE'))
        ->leftJoin('CORE_ORGANIZATION_TERMS', 'orgterms', 'orgterms.ORGANIZATION_TERM_ID = transactions.ORGANIZATION_TERM_ID')
        ->leftJoin('CORE_ORGANIZATION', 'org', 'org.ORGANIZATION_ID = orgterms.ORGANIZATION_ID')
        ->fields('org', array('ORGANIZATION_ABBREVIATION'))
        ->leftJoin('CORE_TERM', 'term', 'term.TERM_ID = orgterms.TERM_ID')
        ->fields('term', array('TERM_ABBREVIATION'))
        ->condition('transactions.CONSTITUENT_ID', $this->record->getSelectedRecordID())
        ->condition('transactions.ORGANIZATION_TERM_ID', $this->focus->getOrganizationTermIDs())
        ->orderBy('TRANSACTION_DATE', 'DESC', 'transactions')
        ->execute()->fetchAll();
      
    }
    
    return $this->render('KulaHEdBillingBundle:SISTransactions:transactions.html.twig', array('transactions' => $transactions));
  }
  
  public function historyAction() {
    $this->authorize();
    $this->setRecordType('SIS.HEd.Student');
    
    if ($this->request->request->get('void')) {
      $constituent_billing_service = $this->get('kula.HEd.billing.student');
      
      $void = $this->request->request->get('void');
      $non = $this->request->request->get('non');
        
      if (isset($non['BILL_CONSTITUENT_TRANSACTIONS']['TRANSACTION_DATE']))
        $transaction_date = $non['BILL_CONSTITUENT_TRANSACTIONS']['TRANSACTION_DATE'];
      else 
        $transaction_date = null;
      
      if (isset($non['BILL_CONSTITUENT_TRANSACTIONS']['VOIDED_REASON']))
        $reason = $non['BILL_CONSTITUENT_TRANSACTIONS']['VOIDED_REASON'];
      else 
        $reason = null;
      
      foreach($void as $table => $row_info) {
        foreach($row_info as $row_id => $row) {
          $constituent_billing_service->removeTransaction($row_id, $reason, $transaction_date);
        }
      }
    }
    
    $transactions = array();
    
    if ($this->record->getSelectedRecordID()) {
      
      $transactions = $this->db()->db_select('BILL_CONSTITUENT_TRANSACTIONS', 'transactions')
        ->fields('transactions', array('CONSTITUENT_TRANSACTION_ID', 'TRANSACTION_DATE', 'TRANSACTION_DESCRIPTION', 'AMOUNT', 'POSTED', 'VOIDED', 'APPLIED_BALANCE'))
        ->join('BILL_CODE', 'code', 'code.CODE_ID = transactions.CODE_ID')
        ->fields('code', array('CODE_TYPE', 'CODE'))
        ->leftJoin('CORE_ORGANIZATION_TERMS', 'orgterms', 'orgterms.ORGANIZATION_TERM_ID = transactions.ORGANIZATION_TERM_ID')
        ->leftJoin('CORE_ORGANIZATION', 'org', 'org.ORGANIZATION_ID = orgterms.ORGANIZATION_ID')
        ->fields('org', array('ORGANIZATION_ABBREVIATION'))
        ->leftJoin('CORE_TERM', 'term', 'term.TERM_ID = orgterms.TERM_ID')
        ->fields('term', array('TERM_ABBREVIATION'))
        ->condition('transactions.CONSTITUENT_ID', $this->record->getSelectedRecordID())
        ->orderBy('term.START_DATE', 'DESC')
        ->orderBy('transactions.TRANSACTION_DATE', 'DESC')
        ->execute()->fetchAll();
      
    }
    
    return $this->render('KulaHEdBillingBundle:SISTransactions:transactions.html.twig', array('transactions' => $transactions));
  }

  public function transaction_detailAction($constituent_transaction_id) {
    $this->authorize();
    $this->setRecordType('SIS.HEd.Student');
    
    $edit_post = $this->request->get('edit');
    
    if (isset($edit_post['BILL_CONSTITUENT_TRANSACTIONS'])) {
      
      // set balance amount
      foreach($edit_post['BILL_CONSTITUENT_TRANSACTIONS'] as $row_id => $row) {
        if (isset($row['AMOUNT']))
          $edit_post['BILL_CONSTITUENT_TRANSACTIONS'][$row_id]['APPLIED_BALANCE'] = $row['AMOUNT'];
      }
      
      $charge_detail_poster = new \Kula\Component\Database\PosterFactory;
      $return_charge_poster = $charge_detail_poster->newPoster(null, array('BILL_CONSTITUENT_TRANSACTIONS' => $edit_post['BILL_CONSTITUENT_TRANSACTIONS']));
    }
    
    $transaction = array();
    $applied_transactions = array();
    $applied_transactions_total = 0;
    
    if ($this->record->getSelectedRecordID()) {
      $transaction = $this->db()->db_select('BILL_CONSTITUENT_TRANSACTIONS', 'transactions')
        ->fields('transactions', array('CONSTITUENT_TRANSACTION_ID', 'CONSTITUENT_ID', 'TRANSACTION_DATE', 'TRANSACTION_DESCRIPTION', 'AMOUNT', 'ORIGINAL_AMOUNT', 'VOIDED', 'VOIDED_REASON', 'APPLIED_BALANCE', 'POSTED', 'CODE_ID', 'VOIDED_TIMESTAMP', 'SHOW_ON_STATEMENT', 'ORGANIZATION_TERM_ID'))
        ->join('BILL_CODE', 'code', 'code.CODE_ID = transactions.CODE_ID')
        ->fields('code', array('CODE_TYPE'))
        ->leftJoin('CORE_ORGANIZATION_TERMS', 'orgterms', 'transactions.ORGANIZATION_TERM_ID = orgterms.ORGANIZATION_TERM_ID')
        ->leftJoin('CORE_ORGANIZATION', 'organization', 'orgterms.ORGANIZATION_ID = organization.ORGANIZATION_ID')
        ->fields('organization', array('ORGANIZATION_NAME'))
        ->leftJoin('CORE_TERM', 'term', 'orgterms.TERM_ID = term.TERM_ID')
        ->fields('term', array('TERM_ABBREVIATION'))
        ->leftJoin('CORE_USER', 'user', 'user.USER_ID = transactions.VOIDED_USERSTAMP')
        ->fields('user', array('USERNAME'))
        ->condition('transactions.CONSTITUENT_TRANSACTION_ID', $constituent_transaction_id)
        ->execute()->fetch();
      
      $query_conditions_or = $this->db()->db_or();
      $query_conditions_or = $query_conditions_or->condition('CHARGE_TRANSACTION_ID', $constituent_transaction_id);
      $query_conditions_or = $query_conditions_or->condition('PAYMENT_TRANSACTION_ID', $constituent_transaction_id);
      
      $applied_transactions = $this->db()->db_select('BILL_CONSTITUENT_TRANSACTIONS_APPLIED')
        ->condition($query_conditions_or)
        ->execute()->fetchAll();
      
      foreach($applied_transactions as $index => $row) {
        $applied_transactions_total += $row['AMOUNT'];
      }
    }
    
    return $this->render('KulaHEdBillingBundle:SISTransactions:transactions_detail.html.twig', array('transaction' => $transaction, 'applied_transactions' => $applied_transactions, 'applied_transactions_total' => $applied_transactions_total));
  }
  
  public function add_chargeAction() {
    return $this->add('C');
  }
  
  public function add_paymentAction() {
    return $this->add('P');
  }
  
  public function add($code_type) {
    $this->authorize();
    $this->setRecordType('SIS.HEd.Student');
    
    if ($this->record->getSelectedRecordID()) {
        
      if ($this->request->request->get('add')) {
        
        $constituent_billing_service = $this->get('kula.HEd.billing.student');
        $add = $this->request->request->get('add');
        $constituent_billing_service->addTransaction($this->record->getSelectedRecord()['STUDENT_ID'], $add['BILL_CONSTITUENT_TRANSACTIONS']['new_num']['ORGANIZATION_TERM_ID'], $add['BILL_CONSTITUENT_TRANSACTIONS']['new_num']['CODE_ID'], $add['BILL_CONSTITUENT_TRANSACTIONS']['new_num']['TRANSACTION_DATE'], $add['BILL_CONSTITUENT_TRANSACTIONS']['new_num']['TRANSACTION_DESCRIPTION'], $add['BILL_CONSTITUENT_TRANSACTIONS']['new_num']['AMOUNT']);
        
        return $this->forward('sis_student_billing_transactions', array('record_type' => 'SIS.HEd.Student', 'record_id' => $this->record->getSelectedRecordID()), array('record_type' => 'SIS.HEd.Student', 'record_id' => $this->record->getSelectedRecordID()));
      }
        
    }
    
    return $this->render('KulaHEdBillingBundle:SISTransactions:transactions_add.html.twig', array('code_type' => $code_type));
  }
  
}