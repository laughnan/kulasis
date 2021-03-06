<?php

namespace Kula\Core\Component\Cache;

class APCCache {
  
  protected $container;
  
  public function __construct($container) {
    $this->container = $container;
  }
  
  public function add($key, $value, $options = null) {
    if ($this->checkForAPC())
      return apc_store($this->container->getParameter('instance_cache_prefix').'.'.$key, $value, $options);
  }
  
  public function get($key, $options = null) {
    if ($this->checkForAPC())
      return apc_fetch($this->container->getParameter('instance_cache_prefix').'.'.$key, $options);
  }
  
  public function exists($key, $options = null) {
    if ($this->checkForAPC())
      return apc_exists($this->container->getParameter('instance_cache_prefix').'.'.$key);
  }
  
  public function delete($key) {
    if ($this->checkForAPC())
      return apc_delete($this->container->getParameter('instance_cache_prefix').'.'.$key);
  }
  
  public function checkForAPC() {
    if (extension_loaded('apc') && ini_get('apc.enabled')) {
        return true;
    }
  }
  
  public function verifyCacheLoaded($key) {
    return $this->get('cache.'.$key);
  }
  
  public function setCacheLoaded($key) {
    return $this->add('cache.'.$key, time());
  }
  
}