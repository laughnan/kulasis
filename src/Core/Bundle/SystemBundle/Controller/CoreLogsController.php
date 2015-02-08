<?php

namespace Kula\Core\Bundle\SystemBundle\Controller;

use Kula\Core\Bundle\FrameworkBundle\Controller\Controller;

class CoreLogsController extends Controller {

  public function sessionAction() {
    
    $this->authorize();

    $sessions = array();

    $sessions = $this->db()->select('LOG_SESSION', 'session')
      ->fields('session', array('SESSION_ID', 'IN_TIME', 'OUT_TIME', 'IP_ADDRESS'))
      ->join('CONS_CONSTITUENT', 'constituent', 'constituent.CONSTITUENT_ID = session.USER_ID')
      ->fields('constituent', array('LAST_NAME', 'FIRST_NAME'))
      ->join('CORE_USER_ROLES', 'role', 'role.ROLE_ID = session.ROLE_ID')
      ->join('CORE_USERGROUP', 'usergroup', 'usergroup.USERGROUP_ID = role.USERGROUP_ID')
      ->fields('usergroup', array('USERGROUP_NAME'))
      ->join('CORE_ORGANIZATION', 'organization', 'organization.ORGANIZATION_ID = role.ORGANIZATION_ID')
      ->fields('organization', array('ORGANIZATION_ABBREVIATION'))
      ->leftJoin('CORE_TERM', 'term', 'term.TERM_ID = session.TERM_ID')
      ->fields('term', array('TERM_ABBREVIATION'))
      ->orderBy('IN_TIME', 'DESC', 'session')
      ->range(0, 100);
    $sessions = $sessions->execute()->fetchAll();
    
    return $this->render('KulaCoreSystemBundle:Logs:session.html.twig', array('sessions' => $sessions));
  }
  
}