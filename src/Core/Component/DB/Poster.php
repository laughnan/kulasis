<?php

namespace Kula\Core\Component\DB;

use Kula\Core\Component\DB\DB as DB;
use Kula\Core\Component\Schema\Schema as Schema;
use Symfony\Component\HttpFoundation\RequestStack as RequestStack;
use Kula\Core\Component\DB\PosterRecord as PosterRecord;

class Poster {
  
  protected $container;
  
  private $db;
  private $requestStack;
  private $schema;
  
  private $records = array();
  
  private $result;
  private $primary_keys;
  
  private $original_data;
  
  private $hasViolations = false;
  private $isPosted = false;
  
  private $noLog;

  public function __construct($container) {
    
    $this->container = $container;
    $this->db = $this->container->get('kula.core.db');
    $this->requestStack = $this->container->get('request_stack');
    $this->schema = $this->container->get('kula.core.schema');
    $this->session = $this->container->get('kula.core.session');
    $this->permission = $this->container->get('kula.core.permission');
    
    $this->result = null;
    $this->hasViolations = false;
    $this->isPosted = false;
  }
  
  public function isPosted() {
    return $this->isPosted;
  }
  
  public function noLog() {
    $this->noLog = true;
    return $this;
  }
  
  public function add($table, $id, $fields) {
    $this->records[$table][$id.'_add'] = new PosterRecord($this->container, PosterRecord::ADD, $table, $id, $fields);
    $this->records[$table][$id.'_add']->setNoLog($this->noLog);
    return $this;
  }
  
  public function addMultiple(array $post) {
    foreach($post as $table => $tableRow) {
      foreach($tableRow as $id => $row) {
        if ($id !== 'new_num') { 
          $this->add($table, $id, $row);
        }
      }
    }
  }
  
  public function edit($table, $id, $fields) {
    $this->records[$table][$id] = new PosterRecord($this->container, PosterRecord::EDIT, $table, $id, $fields);
    $this->records[$table][$id]->setNoLog($this->noLog);
    return $this;
  }
  
  public function editMultiple(array $post) {
    ksort($post);
    foreach($post as $table => $tableRow) {
      foreach($tableRow as $id => $row) {
        if (count($row) > 0) {
          $this->edit($table, $id, $row);
        }
      }
    }
  }
  
  public function delete($table, $id) {
    $this->records[$table][$id] = new PosterRecord($this->container, PosterRecord::DELETE, $table, $id);
    $this->records[$table][$id]->setNoLog($this->noLog);
    return $this;
  }
  
  public function deleteMultiple(array $post) {
    ksort($post);
    foreach($post as $table => $tableRow) {
      foreach($tableRow as $id => $row) {
        $this->delete($table, $id);
      }
    }
  }
  
  public function process($options = array()) {
    if (count($this->records) > 0) {
      $transaction = $this->db->db_transaction();
      foreach($this->records as $table => $tableRow) {
        foreach($tableRow as $id => $record) {
          $record->process($options);
        }
      }
      $transaction->commit();
      $this->isPosted = true;
    }
    return $this;
  }
  
  public function getResult() {
    foreach($this->records as $table => $record) {
      foreach($this->records[$table] as $record_id => $posterRecord) {
        return $this->records[$table][$record_id]->getResult();
      }
    }
  }
  
  public function getPosterRecord($table, $id) {
    if (isset($this->records[$table][$id]))
      return $this->records[$table][$id];
    elseif (isset($this->records[$table][$id.'_add']))
      return $this->records[$table][$id.'_add'];
  }
  
  public function getID() {
    foreach($this->records as $table => $record) {
      foreach($this->records[$table] as $record_id => $posterRecord) {
        return $this->records[$table][$record_id]->getID();
      }
    } 
  }
  
  public function getAddedIDs($table) {
    $ids = array();
    if (isset($this->records[$table])) {
    foreach($this->records[$table] as $id => $record) {
      
      if ($record->getCRUD() == PosterRecord::ADD AND $record->isPosted()) {
        $ids[] = $record->getID();
      }
      
    }
    }
    
    return $ids;
  }

  public function getEditedIDs($table) {
    $ids = array();
    if (isset($this->records[$table])) {
    foreach($this->records[$table] as $id => $record) {
      
      if ($record->getCRUD() == PosterRecord::EDIT AND $record->isPosted()) {
        $ids[] = $record->getID();
      }
      
    }
    }
    
    return $ids;
  }

  public function getDeletedIDs($table) {
    $ids = array();
    if (isset($this->records[$table])) {
    foreach($this->records[$table] as $id => $record) {
      
      if ($record->getCRUD() == PosterRecord::DELETE AND $record->isPosted()) {
        $ids[] = $record->getID();
      }
      
    }
    }
    
    return $ids;
  }
  
}

