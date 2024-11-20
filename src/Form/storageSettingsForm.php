<?php

declare(strict_types=1);

// see
// https://www.drupal.org/docs/8/api/configuration-api/working-with-configuration-forms
//

namespace Drupal\storage\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Configure example settings for this site.
 */
class StorageSettingsForm extends ConfigFormBase {
  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'storage_admin_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'storage.settings',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('storage.settings');

    $form['title'] = array(
      '#type' => 'textfield',
      '#title' => $this->t('Title'),
      '#default_value' => $config->get('title'),
    );

    $form['subtitle'] = array(
      '#type' => 'textarea',
      '#title' => $this->t('Subtitle'),
      '#default_value' => $config->get('subtitle'),
    );

    $form['question_header'] = array(
      '#type' => 'textarea',
      '#title' => $this->t('Question Header'),
      '#default_value' => $config->get('question_header'),
    );

    $form['service_header'] = array(
      '#type' => 'textarea',
      '#title' => $this->t('Service Header'),
      '#default_value' => $config->get('service_header'),
    );

    $form['chart_header'] = array(
      '#type' => 'textarea',
      '#title' => $this->t('Chart Header'),
      '#default_value' => $config->get('chart_header'),
    );

    $form['email_form_header'] = array(
      '#type' => 'textarea',
      '#title' => $this->t('Email Header'),
      '#default_value' => $config->get('email_form_header'),
    );

    $form['email_address'] = array(
      '#type' => 'textfield',
      '#title' => $this->t('Email Address'),
      '#default_value' => $config->get('email_address'),
    );

    $form['email_name'] = array(
      '#type' => 'textfield',
      '#title' => $this->t('Email Name'),
      '#default_value' => $config->get('email_name'),
    );

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
      // Retrieve the configuration
      \Drupal::configFactory()->getEditable('storage.settings')
      // Set the submitted configuration setting
      // You can set multiple configurations at once by making
      // multiple calls to set()
      ->set('title', $form_state->getValue('title'))
      ->set('subtitle', $form_state->getValue('subtitle'))
      ->set('question_header', $form_state->getValue('question_header'))
      ->set('service_header', $form_state->getValue('service_header'))
      ->set('chart_header', $form_state->getValue('chart_header'))
      ->set('email_form_header', $form_state->getValue('email_form_header'))
      ->set('email_address', $form_state->getValue('email_address'))
      ->set('email_name', $form_state->getValue('email_name'))
      ->save();

    parent::submitForm($form, $form_state);
  }
}
