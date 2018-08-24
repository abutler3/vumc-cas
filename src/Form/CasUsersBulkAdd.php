<?php

namespace Drupal\cas\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\user\RoleInterface;

/**
+ * Class CasSettings.
+ *
+ * @codeCoverageIgnore
+ */
class CasUsersBulkAdd extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'cas_users_bulk_add';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {

    $form['account']['cas_names'] = [
      '#type' => 'textarea',
      '#title' => $this->t('CAS username(s)'),
      '#required' => TRUE,
      '#default_value' => '',
      '#description' => $this->t('Enter a single username, or multiple usernames, one per line. Registration will proceed as if the user(s) with the specified CAS username just logged in.'),
      '#weight' => -10,
    ];

    $roles = array_map(['\Drupal\Component\Utility\Html', 'escape'], user_role_names(TRUE));
    $form['account']['roles'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Role(s)'),
      '#options' => $roles,
      '#description' => $this->t('These roles will be added to each new user.'),
      '#weight' => -9,
    ];
    $form['account']['roles'][RoleInterface::AUTHENTICATED_ID] = [
      '#default_value' => TRUE,
      '#disabled' => TRUE,
    ];

    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Create new account(s)'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $roles = array_filter($form_state->getValue('roles'));
    unset($roles[RoleInterface::AUTHENTICATED_ID]);
    array_keys($roles);

    $cas_names = trim($form_state->getValue('cas_names'));
    $cas_names = preg_split('/[\n\r|\r|\n]+/', $cas_names);

    $operations = [];
    foreach ($cas_names as $id => $cas_name) {
      $operations[] = [
        '\Drupal\cas\CasBatch::userAdd',
        [$cas_name, $roles],
      ];
    }

    $batch = [
      'title' => $this->t('Creating CAS users...'),
      'operations' => $operations,
      'finished' => '\Drupal\cas\CasBatch::userAddFinished',
      'progress_message' => $this->t('Processed @current out of @total.'),
    ];

    batch_set($batch);
  }

}