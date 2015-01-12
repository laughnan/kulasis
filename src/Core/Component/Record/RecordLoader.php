<?php

namespace Kula\Core\Component\Record;

use Kula\Core\Component\DefinitionLoader\DefinitionLoader;


class RecordLoader {
  
  private $records = array();
  
  public function getRecordsFromBundles(array $bundles) {
    
    $records = DefinitionLoader::loadDefinitionsFromBundles($bundles, 'record');
    
    if ($records) {
      foreach($records as $path => $record) {
        $this->loadRecord($record, $path);
      }
    }
    
  }
  
  public function loadRecord($records, $path) {
    
    foreach($records as $recordName => $record) {
      
      $this->records[$recordName] = $record;
      
    }
  }
  
  public function synchronizeDatabaseCatalog(\Kula\Core\Component\DB\DB $db) {
    
    foreach($this->records as $recordName => $record) {
      
      // Check table exists in database
      $catalogRecordTable = $db->db_select('CORE_RECORD_TYPES', 'record_types')
        ->fields('record_types')
        ->condition('RECORD_NAME', $recordName)
        ->execute()->fetch();
      
      $recordFields = array();
      
      if ($catalogRecordTable['RECORD_TYPE_ID']) {
        
        if ($catalogRecordTable['CLASS'] != $record['class']) 
          $recordFields['CLASS'] = $record['class'];
        if (count($recordFields) > 0)
          $db->db_update('CORE_RECORD_TYPES')->fields($recordFields)->condition('RECORD_NAME', $recordName)->execute();
        
      } else {
        
        $recordFields['RECORD_NAME'] = $recordName;
        $recordFields['PORTAL'] = strtolower(substr($recordName, 0, strrpos($recordName, '.')));
        $recordFields['CLASS'] = (isset($record['class'])) ? $record['class'] : null;
        $recordID = $db->db_insert('CORE_RECORD_TYPES')->fields($recordFields)->execute();
        
      }
      
      
    }
  
  }
  
}