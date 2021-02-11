<?php
/**
 * @file
 * Contains \Drupal\locations_shp_importer\Form\ImportForm.
 */

namespace Drupal\locations_shp_importer\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Implements a form for importing from SHP file.
 */
class ImportForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'locations_shp_importer_import_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form = [];
    iform_load_helpers([]);
    global $indicia_templates;
    $msg = $this->t('To use this tool, you need a set of files in SHP format, including at least a file called *.shp and a file called *.dbf.');
    $msg .= ' ' . $this->t('The SHP file attributes in the *.dbf file must include an attribute which provides a name and an optional code for each location.');
    $msg .= ' ' . $this->t('Select your files and add them to a zip file, not in a sub-folder then upload the zip file below.');
    $instruct = str_replace(
      '{message}',
      $msg,
      $indicia_templates['messageBox']
    );
    $form['instruct'] = [
      '#markup' => $instruct,
    ];
    $form['file'] = [
      '#title' => $this->t('Upload a Zipped set of SHP files'),
      '#type' => 'file',
    ];
    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Import'),
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // Add validator for your file type etc.
    $validators = ['file_validate_extensions' => ['zip']];
    $file = file_save_upload('file', $validators, FALSE, 0);
    if (!$file) {
      return;
    }
    $archiver = \Drupal::service('plugin.manager.archiver')->getInstance(['filepath' => $file->getFileUri()]);
    if (!$archiver) {
      $this->messenger()->addError($this->t('Cannot extract %file, not a valid archive.', ['%file' => $file->getFilename()]));
      return;
    }
    $files = $archiver->listContents();
    $firstFilename = '';
    $exts = [];
    foreach ($files as $file) {
      if (!preg_match('#^[^/]++$#', $file)) {
        $this->messenger()->addError($this->t('The files must be in the root of the zip file, not in a sub-folder.'));
        return;
      }
      $tokens = explode('.', $file);
      $ext = array_pop($tokens);
      $filename = implode('.', $tokens);
      if (!$firstFilename) {
        $firstFilename = $filename;
      }
      else {
        if ($filename !== $firstFilename) {
          $this->messenger()->addError($this->t('SHP file upload problem - ZIP file contains files with different file names.'));
          return;
        }
      }
      $exts[] = strtolower($ext);
    }
    if (!in_array('dbf', $exts) || !in_array('shp', $exts)) {
      $this->messenger()->addError($this->t('SHP file upload problem - ZIP file must contain at least a *.shp and *.dbf file.'));
      return;
    }
    $directory = uniqid('', TRUE);
    $archiver->extract("public://locations_shp_importer/$directory");
    $form_state->setRedirect('locations_shp_importer.import_options', [
      'path' => $directory,
      'file' => $firstFilename,
    ]);
  }

}
