<?php
/**
 * LexiconHelper
 * Copyright 2011 Bob Ray
 *
 * LexiconHelper is free software; you can redistribute it and/or modify it
 * under the terms of the GNU General Public License as published by the Free
 * Software Foundation; either version 2 of the License, or (at your option) any
 * later version.
 *
 * LexiconHelper is distributed in the hope that it will be useful, but WITHOUT ANY
 * WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR
 * A PARTICULAR PURPOSE. See the GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along with
 * LexiconHelper; if not, write to the Free Software Foundation, Inc., 59 Temple Place,
 * Suite 330, Boston, MA 02111-1307 USA
 *
 * @package lexiconhelper
 * @author Bob Ray <http://bobsguides.com>
 
 *
 * Description: The LexiconHelper snippet identifies lexicon strings
 * in code and checks them against strings in a language file.
 * In the code, the references must be in this form:
 * $modx->lexicon('language_string_key')
 *
 * You must use single quotes and no spaces.
 *
 * Output can be pasted into language file for editing.
 * /

/*
  @version Version 1.0.0-beta1
  Modified: November 30, 2011

   
  Properties:
    @property code_path  - (required) Path to directory with code
         file. Should end in a slash.
         {core_path} and {assets_path} will be translated.

    @property code_file - (required) name of code file to be analyzed.

    @property language_path - (required) Path to directory with code
         file. Should end in a slash.
         {core_path} and {assets_path} will be translated.

    @property language_file - (optional) Path to language file.
         Default: default.inc.php

    @property language - (optional) Two-letter language code identifying
         language file to process.
         Default: en
    @property manager_language - (optional) Two-letter language code
         to use in error messages and reports. Use only to override manager
         language.
         Default: manager_language System Setting
*/

/**
 * @package = lexiconhelper
 *
 */

/* Important: All language keys in the code file must be in this form:
 *
 *      $modx->lexicon('language_string_key');
 *
 * Use singe quotes and no spaces
*/

if (!defined('MODX_CORE_PATH')) {
    $outsideModx = true;
    /* put the path to your core in the next line to run outside of MODx */
    define(MODX_CORE_PATH, 'c:/xampp/htdocs/addons/core/');
    require_once MODX_CORE_PATH . '/model/modx/modx.class.php';
    $modx = new modX();
    if (!$modx) {
        $modx->log(modX::LOG_LEVEL_ERROR, 'Could not create MODX class');
    }
    $modx->initialize('mgr');
} else {
    $outsideModx = false;
}

$props =& $scriptProperties;

/* language to use for error messages and reports */
$snippetLanguage = $modx->getOption('manager_language',$props,null);
$snippetLanguage = !empty ($snippetLanguage) ? $snippetLanguage . ':' : 'en:';
$modx->lexicon->load($snippetLanguage . 'newspublisher:default');

$orphans = array();
$empty = array();
$matches = array();


/* Set log stuff */
$modx->setLogLevel(modX::LOG_LEVEL_INFO);
$modx->setLogTarget(XPDO_CLI_MODE ? 'ECHO' : 'HTML');

$path = $modx->getOption('code_path', $props, null);
$path = str_replace('{core_path}', MODX_CORE_PATH, $path);
$path = str_replace('{assets_path}', MODX_ASSETS_PATH, $path);

$code_file = $modx->getOption('code_file', $props, null);

$codeFiles = explode(',', $code_file);

$code = '';
foreach($codeFiles as $file) {
    /* full path to code file - set manually to run outside of MODX */
    $codeFile = $path . $file;

    /* make sure code file exists */
    if (! file_exists($codeFile)) {
        $modx->log(modX::LOG_LEVEL_ERROR, $modx->lexicon('lh.could_not_find_code_file') . ': ' . $codeFile);
        return '';
    } else {
       $code .= file_get_contents($codeFile);
    }
}
$path = $modx->getOption('language_path', $props, null);
$path = str_replace('{core_path}', MODX_CORE_PATH, $path);
$path = str_replace('{assets_path}', MODX_ASSETS_PATH, $path);
$file = $modx->getOption('language_file', $props, null);
$file = empty ($file)? 'default.inc.php' : $file;
$language = $modx->getOption('language', $props, null);
$language = $language ? $language  : 'en';

/* full path to language file - set manually to run outside of MODX */
$languageFile = $path . $language . '/' . $file;

if (! file_exists($languageFile)) {
    $modx->log(modX::LOG_LEVEL_ERROR,$modx->lexicon('lh.could_not_find_language_file') . ': ' . $languageFile);
    return '';
} else {
   include $languageFile;
}

/* find the code strings */
preg_match_all("/lexicon\(\'([^\\']+)\'\)/", $code, $matches);

$codeStrings = array_values($matches[1]);

/* see if codestrings are in language file */
if (!empty($codeStrings)) {
    foreach($codeStrings as $key => $codeString) {
        if (! isset($_lang[$codeString]) ) {
            $untranslated[] = $codeString;
        }
        if (isset($_lang[$codeString]) && empty($_lang[$codeString])) {
            $empty[] = $codeString;
        }

    }
} else {
    $output .= "\n\n   *** " . $modx->lexicon('lh.no_language_strings_in_code') . ' ***';
}

/* look for unused strings in language file */
if (isset($_lang)) {
    foreach($_lang as $key => $value) {
        if (! in_array($key, $codeStrings)) {
            $orphans[] = $key;
        }
    }
}
$output = '';

if (!empty($codeStrings) && !empty($_lang) && empty($untranslated) && empty($empty)) {
    /* no untranslated code strings */
    $output .= "\n\n\n   *** " . $modx->lexicon('lh.code_all_present_in_language_file') . ' ***';
} else {
    if (!empty($untranslated)) {
        /* report untranslated code strings */
        $output .= "\n\n\n   *** " . $modx->lexicon('lh.missing_from_language_file')  . " ***\n";
        foreach ($untranslated as $item) {
            $output .= "\n" . '$_lang[' . "'" . $item . "'] = '';";
        }
    }
    if (!empty($empty)) {
        /* report empty strings in language file */
        $output .= "\n\n\n   *** " . $modx->lexicon('lh.in_lexicon_but_empty') . " ***\n";
        foreach ($empty as $item) {
            $output .= "\n" . '$_lang[' . "'" . $item . "'] = '';";
        }
    }
}

$output .= "\n";

if (empty ($orphans) && !empty($_lang)) {
    /* Report no orphans */
    $output .= "\n\n\n   *** " . $modx->lexicon('lh.no_orphans') . " ***\n";
} elseif (!empty($orphans)) {
    /* list orphans */
    $output .= "\n\n\n   *** " . $modx->lexicon('lh.orphans') . ' ***' . "\n";
    foreach($orphans as $key => $value) {
        $output .= "\n" . '$_lang[' . "'" . $value . "'] = " . "'" . $_lang[$value] . "'";
    }
}

if ($outsideModx) {
    echo $output;
} else {
    return '<pre>' . $output . '</pre>';
}