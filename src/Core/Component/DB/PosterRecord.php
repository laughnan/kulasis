<?php

namespace Kula\Core\Component\DB;

use Kula\Core\Component\DB\DB as DB;
use Kula\Core\Component\Schema\Schema as Schema;
use Symfony\Component\HttpFoundation\RequestStack as RequestStack;

use Kula\Core\Component\Permission\Permission;

class PosterRecord {
  
  private $db;
  private $schema;
  
  private $crud;
  private $table;
  private $id;
  private $fields;
  
  private $originalRecord;
  
  private $violations;
  private $hasViolations;
  
  const ADD = 'C';
  const EDIT = 'U';
  const DELETE = 'D';
  
  public function __construct(DB $db, Schema $schema, $session, $permission, $crud, $table, $id, $fields) {
    $this->db = $db;
    $this->schema = $schema;
    $this->session = $session;
    $this->permission = $permission;
    $this->crud = $crud;
    $this->table = $table;
    $this->id = $id;
    $this->fields = $fields;
    $this->hasViolations = false;
  }
  
  public function process() {
    $this->getOriginalRecord();
    $this->processConfirmation();
    $this->processSynthetic();
    $this->processCheckboxFields();
    $this->processDateFields();
    $this->processTimeFields();
    $this->processChoosers();
    
    $this->processBlankValues();
    if ($this->crud == self::EDIT) {
      $this->processSameValues();
    }
    if (count($this->fields) > 0) {
      $this->verifyPermissions();
      $this->validate();
    
      if (!$this->hasViolations) {
        $this->execute();
      }
    }
  }
  
  private function getOriginalRecord() {
    if ($this->crud == self::ADD)
      return false;
    $this->originalRecord = $this->db->db_select($this->schema->getTable($this->table)->getDBName(), 'originalTable')
      ->fields('originalTable')
      ->condition($this->schema->getDBPrimaryColumnForTable($this->table), $this->id)
      ->execute()->fetch();
  }
  
  private function processConfirmation() {
    foreach($this->fields as $fieldName => $field) {
      $confirmKey = $fieldName . '_confirmation';
      if (isset($this->fields[$confirmKey])) {
        if ($this->fields[$fieldName] != $this->fields[$confirmKey]) {
          throw new \Exception($key . ' and confirmation fields do not match.');
        }
        unset($this->fields[$confirmKey]);
      }
    }
  }
  
  private function processSynthetic() {
    foreach($this->fields as $fieldName => $field) {
      if ($this->schema->getClass($fieldName)) {
      $class = '\\'.$this->schema->getClass($fieldName);
      if (method_exists($class, 'save')) {
        $returnedValue = call_user_func_array($class.'::save', array($field, $this->id));
        if ($returnedValue == 'remove_field')
          unset($this->fields[$fieldName]);
        else
          $this->fields[$fieldName] = $returnedValue;
      }
      }
    }
  }
  
  private function processBlankValues() {
    foreach($this->fields as $fieldName => $field) {
      if (!is_array($field) AND trim($field) == '') {
        $this->fields[$fieldName] = null;
      }
    }
  }
  
  private function processSameValues() {
    foreach($this->fields as $fieldName => $field) {
      if (array_key_exists($this->schema->getField($fieldName)->getDBName(), $this->originalRecord) AND $this->originalRecord[$this->schema->getField($fieldName)->getDBName()] == $this->fields[$fieldName]) {
        unset($this->fields[$fieldName]);
      }
    }
  }
  
  private function processCheckboxFields() {
    foreach($this->fields as $fieldName => $field) {
      if ($this->schema->getFieldType($fieldName) == 'checkbox') {
        if (isset($field['checkbox_hidden']) OR isset($field['checkbox'])) {
          // Checkbox originally unchecked, now checked.
          if ($field['checkbox_hidden'] == '' AND $field['checkbox'] == 1) {
            $this->fields[$fieldName] = 1;
          }
          // Checkbox originally checked, now unchecked.
          if ($field['checkbox_hidden'] == '1' AND !isset($field['checkbox'])) {
            $this->fields[$fieldName] = 0;
          }
        }
      }
    }
  }
  
  private function processDateFields() {
    foreach($this->fields as $fieldName => $field) {
      if ($this->schema->getFieldType($fieldName) == 'date') {
        if ($field != '') {
          // Check if slashes or dashes in place
          if (strpos($field, '/') === false AND strpos($field, '-') === false) {
            // split string and use mktime to determine date
            $newDate = mktime(0, 0, 0, substr($field, 0, 2), substr($field, 2, 2), substr($field, 4, strlen($field)-4));
          } else {
            $newDate = strtotime($field);
          }
          $this->fields[$fieldName] = date('Y-m-d', $newDate);
        }
      }
    }
  }
  
  private function processTimeFields() {
    foreach($this->fields as $fieldName => $field) {
      if ($this->schema->getFieldType($fieldName) == 'time') {
        if ($field != '') {
          $this->fields[$fieldName] = date('H:i:s', strtotime($field));
        }
      }
    }
  }
  
  private function processDateTimeFields() {
    foreach($this->fields as $fieldName => $field) {
      if ($this->schema->getFieldType($fieldName) == 'datetime') {
        if ($field != '') {
          $this->fields[$fieldName] = date('Y-m-d H:i:s', strtotime($field));
        }
      }
    }
  }
  
  private function processChoosers() {
    foreach($this->fields as $fieldName => $field) {
      if ($this->schema->getFieldType($fieldName) == 'chooser') {
        $this->fields[$fieldName] = $field['value'];
      }
    }
  }
  
  private function validate() {
    // begin validation
    // merge new data with existing row
    $row_to_validate = array_merge($this->originalRecord, $this->fields);
    $schema = $this->schema->getTable($this->table);
    if ($validator = $schema->getDBClass()) {
      $validator_obj = new \Kula\Core\Bundle\FrameworkBundle\Service\Validator($validator, $row_to_validate);
      $violations = $validator_obj->getViolations();
      $this->violations->addAll($violations);
      if (count($violations) > 0) {
        $this->hasViolations = true;
      }
    }
  }
  
  private function verifyPermissions() {

    $permission_violations = array();
    if ($this->crud == self::ADD) {
      // Check for permission to insert into table
      if (!$this->permission->getPermissionForSchemaObject($this->table, null, Permission::ADD)) {
        $permission_violations[] = new \Symfony\Component\Validator\ConstraintViolation('Attempted to insert into ' . $this->table  . ' with no permission to insert into table.', 'Attempted to insert into {table} with no permission to insert into table.', array('table' => $this->table), 'Array', $this->table, null);
      } 
    }
    
    if ($this->crud == self::ADD || $this->crud == self::EDIT) {
      // Check for permission
      foreach($this->fields as $attribute => $value) {
        if (!$this->permission->getPermissionForSchemaObject($this->table, $attribute, Permission::WRITE)) {
          $permission_violations[] = new \Symfony\Component\Validator\ConstraintViolation('Attempted to {crud}' . $table . '.' . $attribute . ' with no write permission.', 'Attempted to {crud} {field} with no permission.', array('field' => $attribute, 'crud' => $this->crud), 'Array', $attribute, $value);
          unset($this->fields[$attribute]);
        }
      }
    }
    
    if ($this->crud == self::DELETE) {
      // Check for permission to delete from table
      if (!$this->permission->getPermissionForSchemaObject($this->table, null, Permission::DELETE)) {
        $permission_violations[] = new \Symfony\Component\Validator\ConstraintViolation('Attempted to delete from ' . $this->table  . ' with no permission to delete.', 'Attempted to delete from {table} with no permission to delete.', array('table' => $this->table), 'Array', $this->table, null);
      }
      
    }
    
    if (count($permission_violations) > 0) {
      $this->hasViolations = true;
      $this->violations = new \Symfony\Component\Validator\ConstraintViolationList($permission_violations);
    }
  }
  
  private function appendStamps() {
    if ($this->schema->getTable($this->table)->getDBTimestamps()) {
      if ($this->crud == self::ADD) {
        $this->fields['CREATED_TIMESTAMP'] = date('Y-m-d H:i:s');
        $this->fields['CREATED_USERSTAMP'] = $this->session->get('user_id');
      }
      if ($this->crud == self::EDIT) {
        $this->fields['UPDATED_TIMESTAMP'] = date('Y-m-d H:i:s');
        $this->fields['UPDATED_USERSTAMP'] = $this->session->get('user_id');
      }
    }
  }
  
  private function auditLog($fields) {
    $audit = $this->db->db_connection(array('target' => 'write'))->prepare('INSERT INTO '.$this->schema->getTable('Log.Audit.Changes')->getDBName().' (USER_ID, LOG_SESSION, CRUD_OPERATION, TABLE_NAME, RECORD_ID, OLD_RECORD, NEW_RECORD, CREATED_USERSTAMP, CREATED_TIMESTAMP)
        VALUES (:user_id, :session_id, :crud, :table_name, :record_id, :old_record, :new_record, :created_userstamp, :created_timestamp)');
    $audit->execute(array(
        'user_id' => $this->session->get('user_id'), 
        'session_id' => $this->session->get('session_id'), 
        'crud' => $this->crud,
        'table_name' => $this->schema->getTable($this->table)->getDBName(), 
        'record_id' => $this->id, 
        'old_record' => isset($this->originalRecord) ? serialize($this->originalRecord) : null, 
        'new_record' => count($fields) > 0 ? serialize($fields) : null, 
        'created_userstamp' => $this->session->get('user_id'), 
        'created_timestamp' => date('Y-m-d H:i:s')));
  }
  
  private function execute() {
    
    if ($this->crud == self::ADD OR $this->crud == self::EDIT) {
      $fields = array();
      foreach($this->fields as $fieldName => $field) {
        $fields[$this->schema->getField($fieldName)->getDBName()] = $field;
      }
      $this->appendStamps();
    }

    if ($this->crud == self::ADD) {
      $this->id = $this->db->db_insert($this->schema->getTable($this->table)->getDBName())
        ->fields($fields)
        ->execute();
      $this->auditLog($fields);
      return $this->id;
    }
    if ($this->crud == self::EDIT) {
      $affectedRows = $this->db->db_update($this->schema->getTable($this->table)->getDBName())
        ->fields($fields)
        ->condition($this->schema->getDBPrimaryColumnForTable($this->table), $this->id)
        ->execute();
      $this->auditLog($fields);
      return $affectedRows;
    }
    if ($this->crud == self::DELETE) {
      $affectedRows = $this->db->db_delete($this->schema->getTable($this->table)->getDBName())
        ->condition($this->schema->getDBPrimaryColumnForTable($this->table), $this->id)
        ->execute();
      $this->auditLog();
      return $affectedRows;
    }
  }
}