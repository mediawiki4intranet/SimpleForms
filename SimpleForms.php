<?php
/**
 * SimpleForms extension - Provides functions to make and process forms
 * (OK)      IDEA 1: Create/edit an article using a form and a template
 * (LESS OK) IDEA 2: Use ajax to query and render some dynamic content from wiki (DPL?)
 * (WICKED)  IDEA 3: Make any random form using this extension
 *
 * See http://www.mediawiki.org/wiki/Extension:Simple_Forms for installation and usage details
 *
 * Started: 2007-04-25
 * Refactored by Vitaliy Filippov and Mediawiki4Intranet project http://wiki.4intra.net/
 *
 * @package MediaWiki
 * @subpackage Extensions
 * @author Aran Dunkley [http://www.organicdesign.co.nz/nad User:Nad]
 * @copyright © 2007 Aran Dunkley
 * @copyright © 2013 Vitaliy Filippov
 * @licence GNU General Public Licence 2.0 or later
 */

/**
 * Usage example:
 *
 * {{#form: content = <nowiki>{{#dpl:category={{subst:#request:cat}}}}</nowiki> }}
 *
 * {{#input: type = select | name = cat |
 *   * Select category
 *   * Category 1
 *   * Category 2
 * }}
 * {{#input: type = ajax | value = Preview list}}
 *
 * Title: {{#input: type = text | name = pagename}}
 * {{#input: type = save | value = Save page}}
 * {{#input: type = edit | value = Preload edit form}}
 *
 * {{#formend:}}
 */

if (!defined('MEDIAWIKI'))
    die('Not an entry point.');
define('SIMPLEFORMS_VERSION', '0.4.15, 2012-03-14'); /* User:Alexandre Porto */

// Request parameter names
define('SIMPLEFORMS_CONTENT',  'content');   // used for parsing wikitext content
define('SIMPLEFORMS_CACTION',  'caction');   // specify whether to prepend, append or replace existing content
define('SIMPLEFORMS_SUMMARY',  'summary');   // specify an edit summary when updating or creating content
define('SIMPLEFORMS_PAGENAME', 'pagename');  // specify a page heading to use when rendering content with no title
define('SIMPLEFORMS_MINOR',    'minor');     // specify that the edit/create be flagged as a minor edit
define('SIMPLEFORMS_TACTION',  'templates'); // specify that the edit/create be flagged as a minor edit
define('SIMPLEFORMS_RETURN',   'returnto');  // specify a page to return to after processing the request
define('SIMPLEFORMS_REGEXP',   'regexp');    // if the content-action is replace, a perl regular expression can be used

// Parser function names
$wgSimpleFormsFormMagic       = "form";     // the parser-function name for form containers
$wgSimpleFormsFormEndMagic    = "formend";  // the parser-function name for form end
$wgSimpleFormsInputMagic      = "input";    // the parser-function name for form inputs
$wgSimpleFormsRequestMagic    = "request";  // the parser-function name for accessing the post/get variables

// Configuration
$wgSimpleFormsServerUser      = "";         // Set this to an existing username so server IP doesn't show up in changes
$wgSimpleFormsAllowCreate     = true;       // Allow creating new articles from content query item
$wgSimpleFormsAllowEdit       = true;       // Allow appending, prepending or replacing of content in existing articles from query item
$wgSimpleFormsEnableCaching   = true;       // Enable caching of parsed forms

$dir = dirname(__FILE__);

$wgHooks['ParserFirstCallInit'][] = 'wfSimpleFormsParserFirstCallInit';
$wgHooks['EditPage::importFormData'][] = 'wfSimpleFormsPreload';
$wgAutoloadClasses['SimpleForms'] = $dir.'/SimpleForms.body.php';
$wgAutoloadClasses['SpecialSimpleForms'] = $dir.'/SimpleForms.special.php';
$wgExtensionMessagesFiles['SimpleForms'] = $dir.'/SimpleForms.i18n.php';
$wgSpecialPages['SimpleForms'] = 'SpecialSimpleForms';

$wgResourceModules['SimpleForms'] = array(
    'scripts'       => 'SimpleForms.js',
    'localBasePath' => $dir,
    'remoteExtPath' => 'SimpleForms',
    'messages'      => array('sf_need_pagename'),
);

$wgExtensionCredits['parserhook'][] = array(
    'name'        => 'Simple Forms',
    'author'      => '[http://www.organicdesign.co.nz/nad User:Nad] and [http://www.mediawiki.org/wiki/User:Bilardi Alessandra Bilardi]',
    'description' => 'Functions to make and process forms.',
    'url'         => 'http://www.mediawiki.org/wiki/Extension:Simple_Forms',
    'version'     => SIMPLEFORMS_VERSION,
);

/**
 * Register parser functions
 */
function wfSimpleFormsParserFirstCallInit(&$parser)
{
    $parser->setFunctionHook('form',      'wfSimpleForms_form');
    $parser->setFunctionHook('formend',   'wfSimpleForms_formend');
    $parser->setFunctionHook('input',     'wfSimpleForms_input');
    $parser->setFunctionHook('request',   'wfSimpleForms_request');
    return true;
}

function wfSimpleForms_form($parser)
{
    $a = func_get_args();
    return call_user_func_array(array(SimpleForms::getInstance(), 'formMagic'), $a);
}

function wfSimpleForms_formend($parser)
{
    $a = func_get_args();
    return call_user_func_array(array(SimpleForms::getInstance(), 'formEndMagic'), $a);
}

function wfSimpleForms_input($parser)
{
    $a = func_get_args();
    return call_user_func_array(array(SimpleForms::getInstance(), 'inputMagic'), $a);
}

function wfSimpleForms_request($parser)
{
    $a = func_get_args();
    return call_user_func_array(array(SimpleForms::getInstance(), 'requestMagic'), $a);
}

/**
 * Allows to open page edit form pre-filled with content made by a form
 */
function wfSimpleFormsPreload($editpage, $request)
{
    global $wgParser, $wgUser;
    $text = trim($request->getVal(SIMPLEFORMS_CONTENT));
    if ($text)
    {
        // PreSaveTransform will replace {{subst:{{#request:PARAMETER}}}}
        $text = $wgParser->preSaveTransform($text, $editpage->mTitle, $wgUser, ParserOptions::newFromUser($wgUser));
        $editpage->textbox1 = $text;
    }
    return true;
}
