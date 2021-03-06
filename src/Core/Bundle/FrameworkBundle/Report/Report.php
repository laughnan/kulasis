<?php

namespace Kula\Core\Bundle\FrameworkBundle\Report;

class Report extends \FPDF {
  
  protected $report_title;
  protected $school_name;
  protected $term_name;
  protected $show_logo = true;
  
  public $row_page_count;
  public $row_total_count;
  protected $fill = false;
  public $include_footer_info;
  public $AutoPageCellFill;   // flag set when resetting cell fill
  
  public $NewPageGroup;   // variable indicating whether a new group was requested
  public $PageGroups;     // variable containing the number of pages of the groups
  public $CurrPageGroup;  // variable containing the alias of the current page group
  
  protected $session;
  
  public function __construct($orientation='P', $unit='mm', $size='Letter')
  {
    $container = $GLOBALS['kernel']->getContainer();
    $this->session = $container->get('kula.core.session');
    
    $this->reportInstitutionName = $container->getParameter('report_institution_name');
    $this->reportAddressLine1 = $container->getParameter('report_institution_address_line1');
    $this->reportAddressLine2 = $container->getParameter('report_institution_address_line2');
    $this->reportPhoneLine1 = $container->getParameter('report_institution_phone_line1');
    $this->reportLogo = $container->getParameter('report_logo_path');
    
    parent::__construct($orientation, $unit, $size);
    
    $this->include_footer_info = true;
    $this->SetAutoPageBreak(true, 10);
    $this->setAutoFillForForPageBreak(true);
    $this->SetFont('Arial','',8);
    if ($this->DefOrientation == 'L' AND $this->show_logo) {
      $this->SetMargins(10, 25);
    } elseif ($this->DefOrientation == 'L') {
      $this->SetMargins(15, 15);
    } else {
      $this->SetMargins(19.05, 19.05);
    }
    $this->SetAutoPageBreak(true, 25);
  }
  
  // Page header
  public function Header()
  {
      $this->SetFont('Arial','',8);
      if ($this->show_logo) {
        // School Logo
        $image1 = KULA_ROOT . $this->reportLogo;
        if ($this->DefOrientation == 'L') {
          $this->Cell(1,0, $this->Image($image1, 10, 7), 0, 0, 'L');
        } else {
          $this->Cell(1,0, $this->Image($image1, 15, 15), 0, 0, 'L');
        }
      }
      // Report Title
      $this->Cell(0,0, $this->report_title, 0, 0,'C');
      // Date Generated
      $this->Cell(0,0, $this->school_name,0,0,'R');
      // Next Line
      $this->Ln(4);
      // School Year
      //$this->Cell(10,0,,0,0,'L');
      // Time Generated
      $this->Cell(0,0, $this->term_name,0,0,'R');
      // Next Line
      $this->Ln(5);
  }
  
  // Page footer
  public function Footer()
  { 
      $this->SetFont('Arial','',8);
      $this->AliasNbPages();
      // Position at 1.5 cm from bottom
      $this->SetY(-18);
      
      if ($this->include_footer_info) {
      // Row Count
      $this->Cell(10,10,"Count: ".$this->row_page_count,0,0,'L'); // of {totrows}
      // Page number
      if ($this->GroupPageNo())
        $this->Cell(0,10,'Page '.$this->GroupPageNo().' of '.$this->PageGroupAlias(),0,0,'C');
      else
        $this->Cell(0,10,'Page '.$this->PageNo().' of {nb}',0,0,'C');
      // User Generated
      $this->Cell(0,10,$this->session->get('username') . ' on ' . date("m/d/y H:i"),0,0,'R');
      }
      // Reset Values
      $this->row_page_count = 0;
      $this->fill = false;
  }
  
  public function getHeightOfCell($text, $height_of_line)
  {
    // http://stackoverflow.com/questions/1748158/fpdf-multicell-issue
    // Get the number of lines this multi-line content will generate. I subtracted 1
    // from column width as a kind of cell padding
    $total_string_width = $this->GetStringWidth(trim($text) == '' ? ' ' : trim($text));
    $number_of_lines = ceil( $total_string_width / (60 - 1) );

    // Determine the height of the resulting multi-line cell.
    return ceil( $number_of_lines * $height_of_line ); 
  }
  
  // set report title
  public function setReportTitle($title)
  {
    $this->report_title = $title;
  }
  
  // set include footer info
  public function setFooterInfo($include_footer_info)
  {
    $this->include_footer_info = $include_footer_info;
  }
  
  public function setAutoFillForForPageBreak($fill_cell)
  {
    $this->AutoPageCellFill = $fill_cell;
  }
  
  // Place total record count
  public function _putpages()
  {
    $nb = $this->page;
    
    if (!empty($this->PageGroups))
    {
        // do page number replacement
        foreach ($this->PageGroups as $k => $v)
        {
            for ($n = 1; $n <= $nb; $n++)
            {
                $this->pages[$n]=str_replace($k, $v, $this->pages[$n]);
            }
        }
    } else {
      for($n=1;$n<=$nb;$n++)
        $this->pages[$n] = str_replace("{totrows}",$this->row_total_count,$this->pages[$n]);
    }
    parent::_putpages();
  }
  
  
  // create a new page group; call this before calling AddPage()
  public function StartPageGroup()
  {
      $this->NewPageGroup=true;
  }

  // current page in the group
  public function GroupPageNo()
  {
      return $this->PageGroups[$this->CurrPageGroup];
  }

  // alias of the current page group -- will be replaced by the total number of pages in this group
  public function PageGroupAlias()
  {
      return $this->CurrPageGroup;
  }

  public function _beginpage($orientation, $size, $rotation)
  {
    parent::_beginpage($orientation, $size, $rotation);
    if ($this->NewPageGroup) {
      // start a new group
      $n = sizeof($this->PageGroups)+1;
      $alias = "{nb$n}";
      $this->PageGroups[$alias] = 1;
      $this->CurrPageGroup = $alias;
      $this->NewPageGroup=false;
    } elseif ($this->CurrPageGroup)
      $this->PageGroups[$this->CurrPageGroup]++;
  }

  public function Image($file, $x=null, $y=null, $w=0, $h=0, $type='', $link='') {
    if (file_exists($file)) {
      parent::Image($file, $x=null, $y=null, $w=0, $h=0, $type='', $link='');
    } else {
      return '';
    }
  }
  
}