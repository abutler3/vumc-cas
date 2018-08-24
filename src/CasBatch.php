<?php

namespace Drupal\cas;

use Drupal\user\Entity\User;

/**
 * Class CasBatch.
 *
 * Provides CAS batch functions.
 */
class CasBatch {

  /**
   * Perform a single CAS user creation batch operation.
   *
   * Callback for batch_set().
   *
   * @param string $cas_name
   *   The name of the CAS user.
   * @param array $roles
   *   An array of roles to provision for the created CAS user.
   * @param array $context
   *   The batch context array, passed by reference.
   */
  public static function userAdd($cas_name, array $roles, array &$context) {
    $casUserManager = \Drupal::service('cas.user_manager');
    $logger = \Drupal::logger('cas');

    // Remove any whitespace around usernames.
    $cas_name = trim($cas_name);

    // Check if the account already exists.
    if ($existing_uid = $casUserManager->getUidForCasUsername($cas_name)) {
      $existing_account = User::load($existing_uid);
      $url = $existing_account->toUrl()->toString();
      $context['results']['messages']['already_exist'][] = $cas_name;
      drupal_set_message(t(
        'CAS username <a href=":url">%name</a> already in use on the site.',
        [
          ':url' => $url,
          '%name' => $cas_name,
        ]
      ), 'warning');
      return;
    }

    try {
      $account = $casUserManager->register($cas_name);
      if (!$account) 
     	throw new Exception('CasLoginException');
	} catch (Exception $e) {
	   echo $e->getMessage();
	}
    // $account = $casUserManager->register($cas_name);

    // Display error if user creation fails.
    if (!$account) {
      $context['results']['messages']['error'][] = $cas_name;
      drupal_set_message(t(
        'Error occurred during account creation of CAS username %name.',
        ['%name' => $cas_name]
      ), 'error');
    }
    else {
      $url = $account->toUrl()->toString();
      $context['results']['messages']['newly_created'][] = $cas_name;

      if (!empty($roles)) {
        foreach ($roles as $role) {
          $account->addRole($role);
        }
        $account->save();
        drupal_set_message(t(
          'CAS user <a href=":url">%name</a> created with role(s) %roles.',
          [
            ':url' => $url,
            '%name' => $cas_name,
            '%roles' => implode(', ', $roles),
          ]
        ));
        $logger->notice(
          'Created CAS user: %name with role(s): %roles.',
          [
            '%name' => $cas_name,
            '%roles' => implode(', ', $roles),
          ]
        );
      }
      else {
        drupal_set_message(t(
          'CAS user <a href=":url">%name</a> created.',
          [
            ':url' => $url,
            '%name' => $cas_name,
          ]
        ));
        $logger->notice(
          'Created CAS user: %name.',
          ['%name' => $cas_name]
        );
      }
    }
  }

  /**
   * Complete CAS user creation batch process.
   *
   * Callback for batch_set().
   *
   * Consolidates message output.
   */
  public static function userAddFinished($success, $results, $operations) {
    if ($success) {
      if (!empty($results['messages']['error'])) {
        drupal_set_message(t(
          'Errors occurred during account creation of %count CAS users.',
          ['%count' => count($results['messages']['error'])]
        ), 'error');
      }
      if (!empty($results['messages']['already_exist'])) {
        drupal_set_message(t(
          '%count CAS username(s) are already in use on this site',
          ['%count' => count($results['messages']['already_exist'])]
        ), 'warning');
      }
      if (!empty($results['messages']['newly_created'])) {
        drupal_set_message(t(
          '%count CAS user(s) were created.',
          ['%count' => count($results['messages']['newly_created'])]
        ));
      }
    }
    else {
      // An error occurred.
      // $operations contains the operations that remained unprocessed.
      $error_operation = reset($operations);
      $message = t('An error occurred while processing %error_operation with arguments: @arguments', [
        '%error_operation' => $error_operation[0],
        '@arguments' => print_r($error_operation[1], TRUE),
      ]);
      drupal_set_message($message, 'error');
    }
  }

}