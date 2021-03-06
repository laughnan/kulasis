<?php

namespace Kula\Core\Bundle\BillingBundle\Report;

use Kula\Core\Bundle\FrameworkBundle\Report\Report;

class BillingStatementReport extends Report {
  
  private $width = array(16, 25, 20, 85, 20, 20);
  
  private $data;
  public $balance;
  private $before_holds_y;
  
  public $due_date;
  public $address;

  public function __construct($orientation='P', $unit='mm', $size='Letter') {
    parent::__construct($orientation, $unit, $size);

    $this->SetMargins(15, 15);
    $this->SetAutoPageBreak(true, 10);
    $this->include_footer_info = false;
  }

  public function setData($data) {
    $this->data = $data;
  }
  
  public function Header()
  {
    // Page number
    $this->Cell(0,0,'Page '.$this->GroupPageNo().' of '.$this->PageGroupAlias(),0,0,'L');
    // Report Title
    $this->SetX(15);
    $this->Cell(0,0, 'BILLING STATEMENT', 0, 0,'C');
    // Date Generated
    $this->Cell(0,0, date("m/d/y h:i A"),0,0,'R');
    // Next Line
    $this->Ln(5);
  
    if ($this->GroupPageNo() == 1)
      $this->first_header();
    
    // Column headings
    $this->SetDrawColor(0,0,0);
    $this->SetFillColor(200,200,200);
    $this->SetLineWidth(.1);
      $header = array('Date', 'Org', 'Term', 'Description', 'Amount', 'Balance');
      for($i=0;$i<count($header);$i++)
          $this->Cell($this->width[$i],6,$header[$i],$header[$i] == null ? 0 : 1,0,'C', true);
    $this->Ln();
    $this->SetFillColor(245,245,245);
  }
  
  public function first_header() {
    
    // School Logo
    $image1 = KULA_ROOT . $this->reportLogo;
    $this->Cell(1,0, $this->Image($image1, 15, 20), 0, 0, 'L');
    
    $original_y = $this->GetY();
    $this->SetY(23);
    // College Information
    $this->Cell(0,5, $this->reportInstitutionName, '', 0,'C');
    $this->Ln(4);
    $this->Cell(0,5, $this->reportAddressLine1, '', 0,'C');
    $this->Ln(4);
    $this->Cell(0,5, $this->reportAddressLine2, '', 0,'C');
    $this->Ln(4);
    $this->Cell(0,5, $this->reportPhoneLine1, '', 0,'C');
    $this->Ln(25);
    
    $this->SetY($original_y + 7);
    // Student Name
    $y_pos = $this->GetY();
    $this->SetLeftMargin(20);
    $this->SetFont('Arial', '', 10);
    $middle_initial = substr($this->data['student']['MIDDLE_NAME'], 0, 1);
    if ($middle_initial) $middle_initial = $middle_initial.'.';
  
    if (isset($this->address['type']) AND $this->address['type'] == 'bill') {
    
      if ($this->address['recipient'] != '') {
        $this->Cell(0,5, $this->address['recipient'], '', 0,'L');
        $this->Ln(4);
      }
    
    }
  
    $name_line = '';
    if (isset($this->address['recipient']) AND $this->address['type'] == 'bill') $name_line = 'Re: ';
    $name_line = $name_line . $this->data['student']['LAST_NAME'].', '.$this->data['student']['FIRST_NAME'].' '.$middle_initial;
    $this->Cell(0,5, $name_line, '', 0,'L');
    $this->Ln(4);

    // Address
    if (isset($this->address['address'])) {
      $this->Cell(0,5, $this->address['address'], '', 0,'L');
      $this->Ln(4);
      $this->Cell(0,5, $this->address['city'].', '.$this->address['state'].' '.$this->address['zipcode'], '', 0,'L');
      $this->Ln(4);
    }
  
    $this->SetFont('Arial', '', 8);
    // Student Info
    $this->SetLeftMargin(120);
    $this->SetY($y_pos);
    
    $this->Cell(30,5, 'Student ID:', '', 0,'R');
    $this->Cell(30,5, $this->data['student']['PERMANENT_NUMBER'], '', 0, 'L');
    $this->Ln(4);
    $this->Cell(30,5, 'Phone:', '', 0,'R');
    $this->Cell(30,5, $this->data['student']['PHONE_NUMBER'], '', 0, 'L');
    $this->Ln(4);
    if (isset($this->data['status']['GRADE'])) {
    $this->Cell(30,5, 'Grade:', '', 0,'R');
    $this->Cell(30,5, $this->data['status']['GRADE'].' / '.$this->data['status']['ENTER_CODE'], '', 0, 'L');
    $this->Ln(4);
    }
    if (isset($this->data['status']['DEGREE_NAME'])) {
    $this->Cell(30,5, 'Degree Program:', '', 0,'R');
    $this->Cell(30,5, $this->data['status']['DEGREE_NAME'], '', 0, 'L');
    $this->Ln(4);
    }
    $this->Cell(30,5, 'Payment Plan:', '', 0,'R');
    $this->Cell(30,5, $this->data['status']['PAYMENT_PLAN'] == '1' ? 'Yes' : 'No', '', 0, 'L');
    $this->Ln(4);
    $this->SetLeftMargin(15);
    $this->Ln(10);
  }
  
  public function previous_balances($balance) {
    $this->SetFont('Arial', 'B', 8);
    $this->table_row($balance, 'Y');
    $this->SetFont('Arial', '', 8);
  }
  
  public function total_balance($balance, $due_date) {
    
    $balance_desc = 'Balance Due';
    
    if ($due_date AND $balance > 0) {
      $balance_desc .= ' by '.$due_date;
    }
    
    $this->SetFont('Arial', 'B', 8);
    $this->SetFillColor(200,200,200);
    $this->Cell($this->width[0],6,'',1,0,'L', true);
    $this->Cell($this->width[1],6,'',1,0,'L', true);
    $this->Cell($this->width[2],6,'',1,0,'L', true);
    $this->Cell($this->width[3],6,$balance_desc,1,0,'R', true);
    $this->Cell($this->width[4],6,'',1,0,'R', true);
    $this->Cell($this->width[5],6,'$ '.$balance,1,0,'R', true);
    $this->Ln();
    $this->SetFillColor(245,245,245);
    $this->SetFont('Arial', '', 8);
  }
  
  public function table_row($row, $previous_balances = null) {

    if ($previous_balances == 'Y')
      $this->Cell($this->width[0],6,'',1,0,'L', $this->fill);
    else
      $this->Cell($this->width[0],6, date("m/d/Y", strtotime($row['TRANSACTION_DATE'])),1,0,'L', $this->fill);
    $this->Cell($this->width[1],6,$row['ORGANIZATION_ABBREVIATION'],1,0,'L',$this->fill);
    $this->Cell($this->width[2],6,$row['TERM_ABBREVIATION'],1,0,'L',$this->fill);
    $this->Cell($this->width[3],6,substr($row['TRANSACTION_DESCRIPTION'], 0, 65),1,0,'L',$this->fill);
    $this->Cell($this->width[4],6,'$ '.$row['AMOUNT'],1,0,'R',$this->fill);
    $this->Cell($this->width[5],6,'$ '.$row['balance'],1,0,'R',$this->fill);
    
    $this->Ln();
    $this->fill = !$this->fill;
  }

  public function fa_table_row($row) {
    $this->Cell($this->width[0],6, 'Pending',1,0,'L', $this->fill);
    $this->Cell($this->width[1],6,$row['ORGANIZATION_ABBREVIATION'],1,0,'L',$this->fill);
    $this->Cell($this->width[2],6,$row['TERM_ABBREVIATION'],1,0,'L',$this->fill);
    $this->Cell($this->width[3],6,substr($row['AWARD_DESCRIPTION'], 0, 65),1,0,'L',$this->fill);
    $this->Cell($this->width[4],6,'$ '.$row['amount'],1,0,'R',$this->fill);
    $this->Cell($this->width[5],6,'$ '.$row['balance'],1,0,'R',$this->fill);
    
    $this->Ln();
    $this->fill = !$this->fill;
  }

  public function holds_header() {
    if ($this->GetY() > 250) {
      $this->Ln($this->GetY() - 250);
      $this->Cell(0, 5, ' ');
    }
    
    $this->before_holds_y = $this->GetY();
    $this->Ln(10);
    $this->Cell(40, 6, 'Hold Category', 1, 0, 'C');
    $this->Cell(60, 6, 'Description', 1, 0, 'C');
    $this->Ln(6);
  }
  
  public function hold_row($hold_info) {
    
    
    // http://stackoverflow.com/questions/1748158/fpdf-multicell-issue
    // Get the number of lines this multi-line content will generate. I subtracted 1
    // from column width as a kind of cell padding
    $total_string_width = $this->GetStringWidth($hold_info['COMMENTS'] == '' ? ' ' : $hold_info['COMMENTS']);
    $number_of_lines = ceil( $total_string_width / (60 - 1) );

    // Determine the height of the resulting multi-line cell.
    $height_of_cell = ceil( $number_of_lines * 4 ); 
    
    $this->Cell(40, $height_of_cell, $hold_info['HOLD_NAME'], 1, 0, 'L');
    $this->MultiCell(60, 4, $hold_info['COMMENTS'], 1, 'L');
    $this->Ln(6);  
  }
  
  public function remit_payment() {
    if ($this->before_holds_y AND $this->GetY() > 50) {
      $this->SetY($this->before_holds_y);
      $this->SetLeftMargin(140);
    }
    
    if ($this->GetY() > 220) {
      $this->Ln($this->GetY() - 220);
      $this->Cell(0, 5, ' ');
    }
    $this->Ln(10);
    $this->SetFont('Arial', 'U', 8);
    $this->Cell(0,5, 'Please Remit Payment To:', '', 0,'L');
    $this->SetFont('Arial', '', 8);
    $this->Ln(4);
    $this->Cell(0,5, $this->reportInstitutionName, '', 0,'L');
    $this->Ln(4);
    $this->Cell(0,5, 'Attn: BURSAR', '', 0,'L');
    $this->Ln(4);
    $this->Cell(0,5, $this->reportAddressLine1, '', 0,'L');
    $this->Ln(4);
    $this->Cell(0,5, $this->reportAddressLine2, '', 0,'L');
    $this->Ln(25);
    if ($this->before_holds_y) {
      $this->SetLeftMargin(20);
      $this->SetX(20);
      $this->before_holds_y = null;
    }
  }
  
  public function Footer()
  {
    parent::Footer();
  }
  
}