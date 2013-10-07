<?php

/**
 * SimpleForms extension - provides functions to make and process forms
 * Refactored in 2013 by Mediawiki4Intranet project, http://wiki.4intra.net/
 *
 * http://wiki.4intra.net/SimpleForms
 * http://www.mediawiki.org/wiki/Extension:Simple_Forms
 *
 * @package MediaWiki
 * @subpackage Extensions
 * @author Aran Dunkley [http://www.organicdesign.co.nz/nad User:Nad]
 * @copyright © 2007 Aran Dunkley
 * @copyright © 2013 Vitaliy Filippov
 * @licence GNU General Public Licence 2.0 or later
 */

define('SFEB_NAME',   0);
define('SFEB_OFFSET', 1);
define('SFEB_LENGTH', 2);
define('SFEB_DEPTH',  3);

/**
 * SimpleForms extension special page handler
 */
class SpecialSimpleForms extends SpecialPage
{
    function __construct()
    {
        parent::__construct('SimpleForms');
        $this->setListed(false);
    }

    function getText()
    {
        global $wgRequest, $wgParser, $wgUser;
        $text = trim($wgRequest->getText(SIMPLEFORMS_CONTENT));
        $text = $wgParser->preSaveTransform($text, new Title(), $wgUser, ParserOptions::newFromUser($wgUser));
        return $text;
    }

    function execute($par = null)
    {
        global $wgRequest, $wgOut;
        $action = $wgRequest->getVal('action');
        if ($action == 'raw')
        {
            $action = 'render';
        }
        if ($action == 'save' || $action == 'render')
        {
            $text = $this->getText();
            $this->$action($text);
        }
        else
        {
            $wgOut->showErrorPage('nosuchaction', 'sf_specialnottobeused');
        }
    }

    function save($content)
    {
        global $wgSimpleFormsAllowEdit, $wgSimpleFormsAllowCreate;
        global $wgParser, $wgUser, $wgOut, $wgRequest;

        $page    = $wgRequest->getText(SIMPLEFORMS_PAGENAME);
        $summary = $wgRequest->getText(SIMPLEFORMS_SUMMARY);
        $minor   = $wgRequest->getText(SIMPLEFORMS_MINOR);
        $return  = $wgRequest->getText(SIMPLEFORMS_RETURN);

        $page    = $wgParser->preprocess($page, new Title(), ParserOptions::newFromUser($wgUser));

        $title = Title::newFromText($page);
        $exists = $title && $title->exists();
        if (!$title || $title->getNamespace() == NS_SPECIAL ||
            !($exists ? $wgSimpleFormsAllowEdit : $wgSimpleFormsAllowCreate) ||
            !$title->userCan($exists ? 'edit' : 'create') ||
            !$wgUser->matchEditToken($wgRequest->getVal('wpEditToken')))
        {
            $wgOut->showErrorPage('sf_editdenied', 'sf_editdeniedtext', array($page));
            return;
        }

        $article = new Article($title);

        if ($title->exists())
        {
            // If title exists and allowed to edit, prepend/append/replace content
            $update = $this->updateTemplates($article->getContent(), $content);
            $article->doEdit($update, $summary ? $summary : wfMsg('sf_editsummary'));
        }
        else
        {
            // No such title, create new article from content if allowed to create
            $article->doEdit($content, $summary ? $summary : wfMsg('sf_editsummary', 'created'));
        }

        // If returnto is set, add a redirect header and die
        if ($return)
        {
            $return = Title::newFromText($return);
        }
        if (!$return)
        {
            $return = $title;
        }
        $wgOut->redirect($return->getFullURL());
    }

    function render($text)
    {
        global $wgOut, $wgRequest, $wgParser, $wgUser, $wgSimpleFormsEnableCaching;
        if (!$wgSimpleFormsEnableCaching)
        {
            $wgOut->enableClientCache(false);
            $wgOut->sendCacheControl();
        }
        $wgOut->disable();
        wfResetOutputBuffers();
        if ($wgRequest->getVal('action') == 'raw')
        {
            header('Content-Type: application/octet-stream');
            echo $wgParser->preprocess($text, new Title(), ParserOptions::newFromUser($wgUser));
        }
        else
        {
            echo $wgParser->parse($text, new Title(), ParserOptions::newFromUser($wgUser))->getText();
        }
    }

    /**
     * Update templates wikitext content
     * - $updates must start and end with double-braces
     * - $updates may contain multiple template updates
     * - each update must only match one template, comparison of args will reduce multiple matches
     */
    function updateTemplates($content, $updates)
    {
        global $wgRequest;
        $caction = $wgRequest->getText(SIMPLEFORMS_CACTION);
        $taction = $wgRequest->getText(SIMPLEFORMS_TACTION);
        $regexp  = $wgRequest->getText(SIMPLEFORMS_REGEXP);

        // Resort to normal content-action if $updates is not exclusively template definitions or updating templates disabled
        if ($taction === 'update' && preg_match('/^\\{\\{.+\\}\\}$/is', $updates, $match))
        {
            // pattern to extract the first name and value of the first arg from template definition
            $pattern = '/^.+?[:\\|]\\s*(\\w+)\\s*=\\s*(.*?)\\s*[\\|\\}]/s';
            $addtext = '';

            // Get the offsets and lengths of template definitions in content and updates wikitexts
            $cbraces = $this->examineBraces($content);
            $ubraces = $this->examineBraces($updates);

            // Loop through the top-level braces in $updates
            foreach ($ubraces as $ubrace)
            {
                if ($ubrace[SFEB_DEPTH] == 1)
                {
                    // Get the update text
                    $utext = substr($updates,$ubrace[SFEB_OFFSET],$ubrace[SFEB_LENGTH]);

                    // Get braces in content with the same name as this update
                    $matches = array();
                    $uname   = $ubrace[SFEB_NAME];
                    foreach ($cbraces as $ci => $cbrace)
                        if ($cbrace[SFEB_NAME] == $uname)
                            $matches[] = $ci;

                    // If more than one matches, try to reduce to one by comparing the first arg of each with the updates first arg
                    if (count($matches) > 1 && preg_match($pattern, $utext, $uarg))
                    {
                        $tmp = array();
                        foreach ($matches as $ci)
                        {
                            $cbrace = &$cbraces[$ci];
                            $cbtext = substr($content, $cbrace[SFEB_OFFSET], $cbrace[SFEB_LENGTH]);
                            if (preg_match($pattern, $cbtext, $carg) && $carg[1] == $uarg[1] && $carg[2] == $uarg[2])
                                $tmp[] = $ci;
                        }
                        $matches = &$tmp;
                    }

                    // If matches has been reduced to a single item, update the template in the content
                    if (count($matches) == 1)
                    {
                        $coffset = $cbraces[$matches[0]][SFEB_OFFSET];
                        $clength = $cbraces[$matches[0]][SFEB_LENGTH];
                        $content = substr_replace($content, $utext, $coffset, $clength);
                    }

                    // Otherwise (if no matches, or many matches) do normal content-action on the update
                    else
                        $addtext .= "$utext\n";
                }
            }
        }

        // Do normal content-action if $updates was not purely templates
        else
            $addtext = $updates;

        // Do regular expression replacement if regexp parameter set
        $addtext = trim($addtext);
        $content = trim($content);
        if ($regexp)
        {
            $content = preg_replace('#'.str_replace('#', '\\#', preg_quote($regexp)).'#', $addtext, $content, -1, $count);
            if ($count)
                $addtext = false;
        }

        // Add any prepend/append updates using the content-action
        if ($addtext)
        {
            if ($caction == 'prepend')
                $content = "$addtext\n$content";
            elseif ($caction == 'append')
                $content = "$content\n$addtext";
            elseif ($caction == 'replace')
                $content = $addtext;
        }

        return $content;
    }

    /**
     * Return a list of info about each template definition in the passed wikitext content
     * - list item format is NAME, OFFSET, LENGTH, DEPTH
     */
    function examineBraces(&$content)
    {
        $braces = array();
        $depths = array();
        $depth = 1;
        $index = 0;
        while (preg_match('/\\{\\{\\s*([#a-z0-9_]*)|\\}\\}/is', $content, $match, PREG_OFFSET_CAPTURE, $index))
        {
            $index = $match[0][1]+2;
            if ($match[0][0] == '}}')
            {
                $brace = &$braces[$depths[$depth-1]];
                $brace[SFEB_LENGTH] = $match[0][1]-$brace[SFEB_OFFSET]+2;
                $brace[SFEB_DEPTH] = --$depth;
            }
            else
            {
                $depths[$depth++] = count($braces);
                $braces[] = array(SFEB_NAME => $match[1][0], SFEB_OFFSET => $match[0][1]);
            }
        }
        return $braces;
    }
}
