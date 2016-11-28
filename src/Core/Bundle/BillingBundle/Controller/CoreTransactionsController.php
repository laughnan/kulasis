<?php

namespace Kula\Core\Bundle\BillingBundle\Controller;

use Kula\Core\Bundle\FrameworkBundle\Controller\Controller;

class CoreTransactionsController extends Controller {
  
  public function transactionsAction() {
    $this->authorize();
    $this->setRecordType('Core.Constituent');
    /*
    if ($this->request->request->get('void')) {
      $constituent_billing_service = $this->get('kula.Core.Billing.constituent');
      
      $void = $this->request->request->get('void');
      $non = $this->request->request->get('non');
        
      if (isset($non['Core.Billing.Transaction']['Core.Billing.Transaction.TransactionDate']))
        $transaction_date = $non['Core.Billing.Transaction']['Core.Billing.Transaction.TransactionDate'];
      else 
        $transaction_date = null;
      
      if (isset($non['Core.Billing.Transaction']['Core.Billing.Transaction.VoidedReason']))
        $reason = $non['Core.Billing.Transaction']['Core.Billing.Transaction.VoidedReason'];
      else 
        $reason = null;
      
      foreach($void as $table => $row_info) {
        foreach($row_info as $row_id => $row) {
          $constituent_billing_service->removeTransaction($row_id, $reason, $transaction_date);
        }
      }
    }
    */
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
        ->leftJoin('STUD_STUDENT_CLASSES', 'stuclass', 'stuclass.STUDENT_CLASS_ID = transactions.STUDENT_CLASS_ID')
        ->leftJoin('STUD_SECTION', 'sec', 'sec.SECTION_ID = stuclass.SECTION_ID')
        ->fields('sec', array('SECTION_NUMBER', 'SECTION_ID'))
        ->leftJoin('STUD_COURSE', 'crs', 'crs.COURSE_ID = sec.COURSE_ID')
        ->fields('crs', array('COURSE_TITLE'))
        ->condition('transactions.CONSTITUENT_ID', $this->record->getSelectedRecordID())
        ->condition('transactions.ORGANIZATION_TERM_ID', $this->focus->getOrganizationTermIDs())
        ->orderBy('TRANSACTION_DATE', 'DESC', 'transactions')
        ->execute()->fetchAll();
      
    }
    
    return $this->render('KulaCoreBillingBundle:CoreTransactions:transactions.html.twig', array('transactions' => $transactions));
  }
  
  public function historyAction() {
    $this->authorize();
    $this->setRecordType('Core.Constituent');
    /*
    if ($this->request->request->get('void')) {
      $constituent_billing_service = $this->get('kula.Core.Billing.constituent');
      
      $void = $this->request->request->get('void');
      $non = $this->request->request->get('non');
        
      if (isset($non['Core.Billing.Transaction']['TransactionDate']))
        $transaction_date = $non['Core.Billing.Transaction']['Core.Billing.Transaction.TransactionDate'];
      else 
        $transaction_date = null;
      
      if (isset($non['Core.Billing.Transaction']['Core.Billing.Transaction.VoidedReason']))
        $reason = $non['Core.Billing.Transaction']['Core.Billing.Transaction.VoidedReason'];
      else 
        $reason = null;
      
      foreach($void as $table => $row_info) {
        foreach($row_info as $row_id => $row) {
          $constituent_billing_service->removeTransaction($row_id, $reason, $transaction_date);
        }
      }
    }
    */
    
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
    
    return $this->render('KulaCoreBillingBundle:CoreTransactions:transactions.html.twig', array('transactions' => $transactions));
  }

  public function transaction_detailAction($constituent_transaction_id) {
    $this->authorize();
    $this->setRecordType('Core.Constituent');
    $this->processForm();
/*
    $edit_post = $this->request->get('edit');
    
    if (isset($edit_post['Core.Billing.Transaction'])) {
      // set balance amount
      foreach($edit_post['Core.Billing.Transaction'] as $row_id => $row) {
        if (isset($row['Core.Billing.Transaction.Amount'])) {
          $charge_detail_poster = $this->newPoster()->edit('Core.Billing.Transaction', $row_id, array(
            'Core.Billing.Transaction.AppliedBalance' => $row['Core.Billing.Transaction.Amount']
          ))->process();
        }
      }
    }
 */   
    $transaction = array();
    
    if ($this->record->getSelectedRecordID()) {
      $transaction = $this->db()->db_select('BILL_CONSTITUENT_TRANSACTIONS', 'transactions')
        ->fields('transactions', array('CONSTITUENT_TRANSACTION_ID', 'CONSTITUENT_ID', 'TRANSACTION_DATE', 'TRANSACTION_DESCRIPTION', 'AMOUNT', 'ORIGINAL_AMOUNT', 'VOIDED', 'VOIDED_REASON', 'APPLIED_BALANCE', 'POSTED', 'CODE_ID', 'VOIDED_TIMESTAMP', 'SHOW_ON_STATEMENT', 'ORGANIZATION_TERM_ID', 'STUDENT_CLASS_ID'))
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
    }
    
    return $this->render('KulaCoreBillingBundle:CoreTransactions:transactions_detail.html.twig', array('transaction' => $transaction));
  }
  
  public function add_chargeAction() {
    $this->authorize();
    
    $request = $this->container->get('request_stack')->getCurrentRequest();
    $routeName = $request->get('_route');
    

      // For specific student
      $this->setRecordType('Core.Constituent');
      
      if ($this->record->getSelectedRecordID()) {
        
        if ($this->request->request->get('add')) {
        
          $constituent_billing_service = $this->get('kula.Core.billing.transaction');
          $add = $this->request->request->get('add');
          $constituent_billing_service->addTransaction($this->record->getSelectedRecordID(), $add['Core.Billing.Transaction']['new_num']['Core.Billing.Transaction.OrganizationTermID'], $add['Core.Billing.Transaction']['new_num']['Core.Billing.Transaction.CodeID'], $add['Core.Billing.Transaction']['new_num']['Core.Billing.Transaction.TransactionDate'], $add['Core.Billing.Transaction']['new_num']['Core.Billing.Transaction.Description'], $add['Core.Billing.Transaction']['new_num']['Core.Billing.Transaction.Amount']);
        
          return $this->forward('Core_Billing_ConstituentBilling_Transactions', array('record_type' => 'Core.Constituent', 'record_id' => $this->record->getSelectedRecordID()), array('record_type' => 'Core.Constituent', 'record_id' => $this->record->getSelectedRecordID()));
        }
      
      }
    
    return $this->render('KulaCoreBillingBundle:CoreTransactions:transactions_add.html.twig', array('code_type' => 'C', 'org_term' => $this->focus->getOrganizationTermID(), 'current_route' => $routeName));
  }
  
  public function add_paymentAction($payment_id) {
    $this->authorize();
    
    $request = $this->container->get('request_stack')->getCurrentRequest();
    $routeName = $request->get('_route');
    
      // For specific student
      $this->setRecordType('Core.Constituent');
      
      if ($this->record->getSelectedRecordID()) {
        
        if ($this->request->request->get('add')) {
        
          $constituent_billing_service = $this->get('kula.Core.billing.transaction');
          $add = $this->request->request->get('add');
          $constituent_billing_service->addTransaction(
            $this->record->getSelectedRecordID(), 
            $add['Core.Billing.Transaction']['new_num']['Core.Billing.Transaction.OrganizationTermID'], 
            $add['Core.Billing.Transaction']['new_num']['Core.Billing.Transaction.CodeID'], 
            $add['Core.Billing.Transaction']['new_num']['Core.Billing.Transaction.TransactionDate'], 
            $add['Core.Billing.Transaction']['new_num']['Core.Billing.Transaction.Description'], 
            $add['Core.Billing.Transaction']['new_num']['Core.Billing.Transaction.Amount'], 
            $payment_id
          );

          return $this->forward('Core_Billing_ConstituentBilling_PaymentDetail', array('record_type' => 'Core.Constituent', 'record_id' => $this->record->getSelectedRecordID()), array('record_type' => 'Core.Constituent', 'record_id' => $this->record->getSelectedRecordID()), array('payment_id' => $payment_id));
        }
      
      }
    
    return $this->render('KulaCoreBillingBundle:CoreTransactions:transactions_add_payment.html.twig', array('code_type' => 'P', 'org_term' => $this->focus->getOrganizationTermID(), 'current_route' => $routeName, 'payment_id' => $payment_id));
  }
  
}