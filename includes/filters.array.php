<?php

//$Id$

/**
 * array filter for valid modules registered in CVS.
 *
 * @param $item
 * @return
 *  Boolean
 */
function _valid_drupal_modules_filter($item) {
  return substr($item, 0, 1) != "." && strtolower($item) === $item;
}

/**
 * array filter for valid modules in current Drupal Installation.
 *
 * @param $item
 * @return
 *   Boolean
 */
function _valid_module_versions($item) {
  return substr($item, 0, 1) != "." && strtolower($item) === $item;
}