<?php

namespace Drupal\solo_copy_blocks\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * SoloMoveBlocsForm Class.
 */
class SoloCopyBlocksForm extends FormBase {

  /**
   * Return FormId.
   */
  public function getFormId() {
    return 'solo_copy_blocks_form';
  }

  /**
   * Build Form.
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['description'] = [
      '#markup' => $this->t('Use this form to copy blocks from the default theme to the solo theme.'),
    ];

    $form['actions']['#type'] = 'actions';
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Copy Blocks'),
      '#button_type' => 'primary',
    ];

    return $form;
  }

  /**
   * Submit the Form.
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // Get the default theme and use it as the source theme.
    $default_theme = \Drupal::config('system.theme')->get('default');
    _solo_copy_blocks_and_config_to_solo_theme($default_theme, 'solo', 'footer_menu');
  }

}
