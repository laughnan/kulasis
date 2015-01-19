<?php

namespace Kula\Core\Component\Navigation;

use Kula\Core\Component\Cache\DBCacheConfig as DBConfigCache;
use Symfony\Component\HttpKernel\CacheWarmer\WarmableInterface;
use Symfony\Component\Config\FileLocator;

class NavigationStore {
  
  private $navigation;
  
  public function __construct($db, $fileName, $cacheDir, $debug, $session, $permission, $request) {
    $this->db = $db;
    $this->cacheDir = $cacheDir;
    $this->fileName = $fileName;
    $this->debug = $debug;
    $this->session = $session;
    $this->permission = $permission;
    $this->request = $request;
  }
  
  public function warmUp($cacheDir) {
    
    $cache = new DBConfigCache($cacheDir.'/'.$this->fileName.'.php', $this->debug, $this->db, array('CORE_NAVIGATION'));

    if (!$cache->isFresh()) {

      $navigation = new Navigation($this->db);
      $navigation->loadNavigation();
      $cache->write(serialize($navigation));
      
    }
    
    $this->navigation = unserialize(file_get_contents((string) $cache));
    $this->navigation->awake($this->session, $this->permission, $this->request);
  }
  
  public function getNavigation() {
    if (!$this->navigation)
      $this->warmUp($this->cacheDir);
    return $this->navigation;
  }
  
}