<?php

namespace Kula\Core\Component\Navigation;

class Item {
  
  private $parent;
  
  private $name;
  private $db_id;
  private $portal;
  private $sort;
  private $displayName;
  private $route;
 
  
  public function __construct($name, $parent, $db_id, $portal, $sort, $displayName, $route) {
    
    $this->name = $name;
    $this->parent = $parent;
    $this->db_id = $db_id;
    $this->portal = $portal;
    $this->sort = $sort;
    $this->displayName = $displayName;
    $this->route = $route;
    
  }
  
  public function getDisplayName() {
    return $this->displayName;
  }
  
  public function getName() {
    return $this->name;
  }
  
  public function getRoute() {
    return $this->route;
  }
  
  public function getParent() {
    return $this->parent;
  }
  
}