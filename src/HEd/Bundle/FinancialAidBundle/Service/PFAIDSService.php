<?php

namespace Kula\HEd\Bundle\FinancialAidBundle\Service;

class PFAIDSService {
  
  protected $db;
  
  protected $poster_factory;
  
  protected $record;

  public function __construct(\Kula\Core\Component\DB\DB $db, 
                              \Kula\Core\Component\DB\PosterFactory $poster_factory,
                              $record = null,
                              $session = null,
                              $focus = null,
                              $ssn_key = null) {
    $this->db = $db;
    $this->record = $record;
    $this->posterFactory = $poster_factory;
    $this->session = $session;
    $this->focus = $focus;
    $this->ssn_key = $ssn_key;
  }
  
  private function pfaids_connect($id) {
    
    // Get database connection parameters
    $intgDB = $this->db->db_select('CORE_INTG_DATABASE')
      ->fields('CORE_INTG_DATABASE')
      ->condition('APPLICATION', 'PFAIDEU')
      ->condition('INTG_DATABASE_ID', $id)
      ->execute()->fetch();
    
    $connection = mssql_connect($intgDB['HOST'], $intgDB['USERNAME'], $intgDB['PASSWORD']);
    mssql_select_db($intgDB['DATABASE_NAME'], $connection);
    
    return $connection;
  }
  
  public function pfaids_deleteRecords($intgDBID, $awardYearToken = null, $ssn = null) {
    
    $connection = $this->pfaids_connect($intgDBID);
    
    $query = "DELETE FROM external_data";
    
    if ($awardYearToken)
      $query .= " WHERE award_year_token = '".str_replace("'", "''", $awardYearToken)."'";
    if ($awardYearToken AND $ssn)
      $query .= " AND ssn = '".str_replace("'", "''", $ssn)."'";
    
    mssql_query($query, $connection);
    return mssql_rows_affected($connection);
  }
  
  public function pfaids_addRecords($intgDBID, $awardYearToken, $id = null) {
    
    $connection = $this->pfaids_connect($intgDBID);
    
    $insert_count = 0;
    $update_count = 0;
    
    $students_result = $this->db->db_select('CONS_CONSTITUENT', 'cons')
      ->fields('cons')
      ->expression("AES_DECRYPT(cons.SOCIAL_SECURITY_NUMBER, '".$this->ssn_key."')", 'encrypted_ssn')
      ->join('STUD_STUDENT', 'stu', 'cons.CONSTITUENT_ID = stu.STUDENT_ID')
      ->fields('stu')
      ->join('STUD_STUDENT_STATUS', 'stustatus', 'stustatus.STUDENT_ID = stu.STUDENT_ID')
      ->fields('stustatus')
      ->leftJoin('CONS_PHONE', 'phone', 'phone.PHONE_NUMBER_ID = cons.PRIMARY_PHONE_ID')
      ->fields('phone', array('PHONE_NUMBER'))
      ->leftJoin('CONS_EMAIL_ADDRESS', 'email', 'email.EMAIL_ADDRESS_ID = cons.PRIMARY_EMAIL_ID')
      ->fields('email', array('EMAIL_ADDRESS'))
      ->condition('stustatus.ORGANIZATION_TERM_ID', $this->focus->getOrganizationTermIDs())
      ->execute();
    while ($stu_row = $students_result->fetch()) {
  
      $cleaned_ssn = str_ireplace('-', '', $stu_row['encrypted_ssn']);
  
      if (strlen($cleaned_ssn) == 9) {
    
        $pf_data = array(
          '[ssn]' => "'".$cleaned_ssn."'",
          '[award_year_token]' => $awardYearToken,
          '[ALTERnate_id]' => "'".$stu_row['PERMANENT_NUMBER']."'",
          '[last_name]' => "'".str_replace("'", "''", $stu_row['LAST_NAME'])."'",
          '[first_name]' => "'".str_replace("'", "''", $stu_row['FIRST_NAME'])."'",
          '[middle_init]' => strlen(substr($stu_row['MIDDLE_NAME'], 0, 1)) > 0 ? "'".substr($stu_row['MIDDLE_NAME'], 0, 1)."'" : 'null', 
          '[name_mid]' => $stu_row['MIDDLE_NAME'] ? "'".str_replace("'", "''", $stu_row['MIDDLE_NAME'])."'" : 'null', 
          '[birth_date]' => $stu_row['BIRTH_DATE'] ? "'".date('mdY', strtotime($stu_row['BIRTH_DATE']))."'" : 'null',
        );
        
        // Get address
        if ($stu_row['HOME_ADDRESS_ID']) {
          $address_id = $stu_row['HOME_ADDRESS_ID'];
        } elseif ($stu_row['MAILING_ADDRESS_ID']) {
          $address_id = $stu_row['MAILING_ADDRESS_ID'];
        } elseif ($stu_row['RESIDENCE_ADDRESS_ID']) {
          $address_id = $stu_row['RESIDENCE_ADDRESS_ID'];
        }
        
        $primary_address = $this->db->db_select('CONS_ADDRESS', 'addr')
          ->fields('addr')
          ->condition('ADDRESS_ID', $address_id)
          ->execute()->fetch();
        
        $pf_data['primary_street1'] = "'".str_replace("'", "''", $primary_address['THOROUGHFARE'])."'";
        $pf_data['primary_city'] = "'".str_replace("'", "''", $primary_address['LOCALITY'])."'";
        $pf_data['primary_state'] = "'".str_replace("'", "''", $primary_address['ADMINISTRATIVE_AREA'])."'";
        $pf_data['primary_zip'] = "'".str_replace("'", "''", $primary_address['POSTAL_CODE'])."'";
        $pf_data['primary_telephone'] = "'".str_replace("'", "''", $stu_row['PHONE_NUMBER'])."'"; 
        $pf_data['email_address'] = "'".str_replace("'", "''", $stu_row['EMAIL_ADDRESS'])."'";
        
        $pf_data['legal_residence'] = "'".str_replace("'", "''", $primary_address['ADMINISTRATIVE_AREA'])."'";
        
        // Get degree information
        $degree_info = $this->db->db_select('STUD_STUDENT_DEGREES', 'studegree')
          ->fields('studegree')
          ->condition('studegree.STUDENT_DEGREE_ID', $stu_row['SEEKING_DEGREE_1_ID'])
          ->execute()->fetch();
        
        $pf_data['grad_date'] = (isset($degree_info['EXPECTED_GRADUATION_DATE']) AND $degree_info['EXPECTED_GRADUATION_DATE']) ? "'".date('mdY', strtotime($degree_info['EXPECTED_GRADUATION_DATE']))."'" : 'null';
        $pf_data['admission_status'] = "'A'";
        $pf_data['us_citizen'] = ($stu_row['CITIZENSHIP_COUNTRY'] == 'US') ? '1' : 'null';
        $pf_data['gender'] = $stu_row['GENDER'] != '' ? "'".$stu_row['GENDER']."'" : 'null';
        $pf_data['veteran_status'] = ($stu_row['VETERAN'] == '1') ? "'Y'" : 'null';
        $pf_data['academic_division'] = "'20'";
        $pf_data['citizen_country'] = $stu_row['CITIZENSHIP_COUNTRY'] ? "'".$stu_row['CITIZENSHIP_COUNTRY']."'" : 'null';
        $pf_data['transfer_flag'] = ($stu_row['ENTER_CODE'] == '04') ? "'Y'" : 'null';
        
        // check if already exists
        $already_exists = mssql_fetch_array(mssql_query("SELECT ssn, award_year_token FROM external_data WHERE ssn = '".$cleaned_ssn."' AND award_year_token = '".$awardYearToken."'"));
        
        if ($already_exists) {
          $inner_sql = array();
          
          foreach($pf_data as $key => $value) {
            $inner_sql[] = $key.' = '.$value;
          }
          
          $sql = "UPDATE external_data SET ".implode(', ', $inner_sql)." WHERE [ssn] = '".$cleaned_ssn."' AND [award_year_token] = '".$awardYearToken."'";
          $update_count++;
        } else {
          $sql = "INSERT INTO external_data (".implode(', ', array_keys($pf_data)).") VALUES (".implode(", ", array_values($pf_data)).")";
          $insert_count++;
        }
        //echo $sql.'<br />';
        mssql_query($sql);
      }
      
      unset($cleaned_ssn, $pf_data);
  
    }
    
    $results['update_count'] = $update_count;
    $results['insert_count'] = $insert_count;
    
    return $results;
  }

  

}