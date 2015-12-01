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

/**
 * Parser function handlers for SimpleForms
 */
class SimpleForms
{
    protected $id;
    protected static $instance;

    static function getInstance()
    {
        if (!self::$instance)
        {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Renders a form beginning
     */
    function formMagic($parser)
    {
        global $wgSimpleFormsEnableCaching;
        $this->id = uniqid('sf-');
        if (!$wgSimpleFormsEnableCaching)
        {
            $parser->disableCache();
        }
        $argl = array();
        foreach (func_get_args() as $arg)
        {
            if (!is_object($arg) &&
                preg_match('/^([a-z0-9_]+?)\\s*=\\s*(.+)$/is', $arg, $m))
            {
                $argl[$m[1]] = $m[2];
            }
        }
        $form = '<form id="'.$this->id.'" method="POST" action="'.
            Title::newFromText('Special:SimpleForms')->getLocalUrl().'">';
        foreach ($argl as $k => $v)
        {
            $form .= '<input type="hidden" name="'.htmlspecialchars($k).'" value="'.htmlspecialchars($v).'" />';
        }
        return array($form, 'noparse' => true, 'isHTML' => true);
    }

    /**
     * Renders a form end
     */
    function formEndMagic($parser)
    {
        $form = '</form>';
        return array($form, 'noparse' => true, 'isHTML' => true);
    }

    /**
     * Renders a form input
     */
    function inputMagic($parser)
    {
        global $wgSimpleFormsEnableCaching, $wgUser;
        if (!$wgSimpleFormsEnableCaching)
        {
            $parser->disableCache();
        }

        $tag     = 'input';
        $input   = '';
        $content = '';
        $argv    = array();

        // Process args
        foreach (func_get_args() as $arg)
        {
            if (!is_object($arg))
            {
                if (preg_match('/^([a-z0-9_]+?)\\s*=\\s*(.+)$/is', $arg, $match))
                    $argv[strtolower(trim($match[1]))] = trim($match[2]);
                else
                    $content = trim($arg);
            }
        }

        $type = isset($argv['type']) ? $argv['type'] : 'hidden';
        if ($type == 'textarea')
        {
            // Textarea
            $tag = 'textarea';
            unset($argv['type']);
            $content = htmlspecialchars($content);
        }
        elseif ($type == 'select')
        {
            // Select list
            unset($argv['type']);
            if (isset($argv['value']))
            {
                $val = $argv['value'];
                unset($argv['value']);
            }
            else
                $val = '';
            preg_match_all('/^\s*\*\s*(.*?)\s*$/m', $content, $m, PREG_PATTERN_ORDER);
            $tag = 'select';
            $content = '';
            foreach ($m[1] as $opt)
            {
                $txt = strtok($opt, '|');
                if ($opt != $txt)
                {
                    $opt = $txt;
                    $txt = strtok('|');
                }
                $sel = $opt === $val ? ' selected="selected"' : '';
                $content .= "\n".'<option value="'.htmlspecialchars($opt).'"'.
                    $sel.'>'.htmlspecialchars($txt).'</option>';
            }
        }
        elseif ($type == 'ajax')
        {
            // Ajax render content button
            $elId = @$argv['update'];
            unset($argv['update']);
            $argv['type'] = 'button';
            $parser->mOutput->addModules('SimpleForms');
            if (!isset($argv['onclick']))
            {
                $argv['onclick'] = '';
            }
            $argv['onclick'] .= "sfAjax(this, '".addslashes($elId)."');";
        }
        else
        {
            // <input> tag
            $content = '';
            if ($type == 'save')
            {
                // Save page button
                $input .= '<input type="hidden" name="wpEditToken" value="'.$wgUser->editToken().'" />';
                $argv['type'] = 'submit';
                $argv['onclick'] = 'sfSaveButton(this);';
                $parser->mOutput->addModules('SimpleForms');
            }
            elseif ($type == 'edit')
            {
                // Open preloaded edit form button
                $argv['type'] = 'submit';
                $argv['onclick'] = 'sfEditButton(this);';
                $parser->mOutput->addModules('SimpleForms');
            }
        }
        $input .= "<$tag";
        foreach ($argv as $k => $v)
        {
            $input .= ' '.$k.'="'.htmlspecialchars($v).'"';
        }
        $input .= ($content !== false ? ">$content</$tag>" : " />");
        return array($input, 'noparse' => true, 'isHTML' => true);
    }

    /**
     * Return value from the global $_REQUEST array (containing GET/POST variables)
     */
    function requestMagic($parser, $param)
    {
        global $wgRequest;
        $parser->disableCache();
        return $wgRequest->getText($param);
    }
}
