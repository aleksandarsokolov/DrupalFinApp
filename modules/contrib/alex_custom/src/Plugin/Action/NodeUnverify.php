<?php

namespace Drupal\alex_custom\Plugin\Action;

use Drupal\Core\Action\ActionBase;
use Drupal\Core\Session\AccountInterface;

/**
 * Node Unverify Action.
 *
 * @Action(
 *   id = "node_unverify_action",
 *   label = @Translation("Node Unverify Action"),
 *   type = "node"
 * )
 */
class NodeUnverify extends ActionBase {

  /**
   * {@inheritdoc}
   */
  public function execute($entity = NULL) {
    /** @var \Drupal\taxonomy\TermInterface $entity */
    if ($entity->hasField('field_field_billing_verified')) {
      $entity->field_field_billing_verified->value = 0;
      $entity->save();
    }

  }

  /**
   * {@inheritdoc}
   */
  public function access($object, AccountInterface $account = NULL, $return_as_object = FALSE) {
    /** @var \Drupal\taxonomy\TermInterface $object */
    $result = $object->access('update', $account, TRUE)
      ->andIf($object->field_field_billing_verified->access('edit', $account, TRUE));

    return $return_as_object ? $result : $result->isAllowed();
  }

}