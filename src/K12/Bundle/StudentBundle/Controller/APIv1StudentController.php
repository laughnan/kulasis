<?php

namespace Kula\K12\Bundle\StudentBundle\Controller;

use Kula\Core\Bundle\FrameworkBundle\Controller\APIController;
use Symfony\Component\HttpFoundation\Response;

use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

class APIv1StudentController extends APIController {

  public function relatedChildrenAction() {

    $currentUser = $this->authorizeUser();

    $data = array();

    $data = $this->db()->db_select('CONS_RELATIONSHIP', 'rel')
      ->fields('rel', array('CONSTITUENT_ID'))
      ->condition('rel.RELATED_CONSTITUENT_ID', $currentUser)
      ->join('CONS_CONSTITUENT', 'cons', 'cons.CONSTITUENT_ID = rel.CONSTITUENT_ID')
      ->fields('cons', array('LAST_NAME', 'FIRST_NAME', 'PERMANENT_NUMBER'))
      ->execute()->fetchAll();

    return $this->JSONResponse($data);
  }

  public function createChildAction() {
    $currentUser = $this->authorizeUser();

    $transaction = $this->db()->db_transaction('create_user');

    // create constituent
    $constituent_service = $this->get('Kula.Core.Constituent');
    $constituent_data = $this->form('add', 'Core.Constituent', 0);
    $relationship_data = $this->form('add', 'Core.Constituent.Relationship', 0);
    $constituent_id = $constituent_service->createConstituent($constituent_data);

    // create constituent relationship
    $this->newPoster()->add('Core.Constituent.Relationship', 0, array(
      'Core.Constituent.Relationship.ConstituentID' => $constituent_id,
      'Core.Constituent.Relationship.RelatedConstituentID' => $currentUser,
      'Core.Constituent.Relationship.Relationship' => isset($relationship_data['Core.Constituent.Relationship.Relationship']) ? $relationship_data['Core.Constituent.Relationship.Relationship'] : null
    ))->process(array('VERIFY_PERMISSIONS' => false, 'AUDIT_LOG' => false));

    if ($constituent_id) {
      $transaction->commit();
      return $this->JSONResponse($constituent_id);
    } else {
      $transaction->rollback();
    }

  }

  public function updateChild($student_id, $org_term) {

    // Check for authorized access to constituent
    $this->authorizeConstituent($student_id);

    $constituent_data = $this->form('edit', 'Core.Constituent', 0);
    $status_data = $this->form('edit', 'K12.Student.Status', 0);

    // Get student status
    $student_status_id = $this->db()->db_select('STUD_STUDENT_STATUS', 'stustatus')
      ->fields('stustatus', array('STUDENT_STATUS_ID'))
      ->condition('stustatus.ORGANIZATION_TERM_ID', $org_term)
      ->condition('stustatus.STUDENT_ID', $student_id)
      ->execute()->fetch()['STUDENT_STATUS_ID'];

    $transaction = $this->db()->db_transaction('update_child');

    if ($student_status_id) {
      $this->newPoster()->edit('Core.Constituent', $student_id, $constituent_data))->process(array('VERIFY_PERMISSIONS' => false, 'AUDIT_LOG' => false));
      $this->newPoster()->edit('K12.Student.Status', $student_status_id, $status_data))->process(array('VERIFY_PERMISSIONS' => false, 'AUDIT_LOG' => false));
    }
    
    if ($constituent_id) {
      $transaction->commit();
      return $this->JSONResponse($constituent_id);
    } else {
      $transaction->rollback();
    }

  }

}