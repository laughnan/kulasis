<?php

namespace Kula\Core\Bundle\SystemBundle\Controller;

use Kula\Core\Bundle\FrameworkBundle\Controller\Controller;

class CoreAPILogsController extends Controller {
  
  public function sessionAction() {
    $this->authorize();

    $sessions = array();

    $sessions = $this->db()->db_select('LOG_SESSION', 'session', array('target' => 'additional'))
      ->fields('session', array('SESSION_ID', 'USER_ID', 'IN_TIME', 'OUT_TIME', 'IP_ADDRESS'))
      ->leftJoin('CORE_INTG_API_APPS', 'app', 'app.INTG_API_APP_ID = session.API_APPLICATION_ID', null, array('target' => 'default'))
      ->fields('app', array('APPLICATION'))
      ->leftJoin('CONS_CONSTITUENT', 'constituent', 'constituent.CONSTITUENT_ID = session.USER_ID', null, array('target' => 'default'))
      ->fields('constituent', array('LAST_NAME', 'FIRST_NAME'))
      ->condition('session.AUTH_METHOD', 'API')
      ->orderBy('IN_TIME', 'DESC', 'session')
      ->range(0, 100);
    $sessions = $sessions->execute()->fetchAll();
    
    return $this->render('KulaCoreSystemBundle:APILogs:session.html.twig', array('sessions' => $sessions));
  }  

  public function requestsForSessionAction($session_id) {
    $this->authorize();

    $requests = array();

    $i = 0;
    $requests_result = $this->db()->db_select('LOG_API', 'api', array('target' => 'additional'))
      ->fields('api', array('API_CALL_ID', 'TIMESTAMP', 'REQUEST_METHOD', 'REQUEST_URI', 'RESPONSE_CODE', 'REQUEST', 'RESPONSE', 'ERROR'))
      ->condition('api.LOG_SESSION_ID', $session_id)
      ->orderBy('TIMESTAMP', 'DESC', 'api')
      ->orderBy('API_CALL_ID', 'DESC', 'api')
      ->range(0, 100)->execute();
    while ($requests_row = $requests_result->fetch()) {
      $requests[$i] = $requests_row;
      $requests[$i]['REQUEST'] = print_r(unserialize($requests_row['REQUEST']), true);
      $requests[$i]['RESPONSE'] = print_r(unserialize($requests_row['RESPONSE']), true);
      $requests[$i]['ERROR'] = print_r(unserialize($requests_row['ERROR']), true);
    $i++;
    }
    
    return $this->render('KulaCoreSystemBundle:APILogs:requests.html.twig', array('requests' => $requests));
  }

  public function requestAction($request_id) {

  }

}