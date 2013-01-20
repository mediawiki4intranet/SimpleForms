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

window.sfAjax = function(el, update)
{
  var a = { action: 'render' };
  var f = $(el).closest('form');
  if (!f || !(f = f[0]))
  {
    return;
  }
  var i = f.elements;
  for (var k = 0; k < i.length; k++)
  {
    if (i[k].type == 'select-one')
    {
      if (i[k].selectedIndex !== undefined)
      {
        a[i[k].name] = i[k].options[i[k].selectedIndex].text;
      }
    }
    else if (i[k].name && i[k].value &&
      (i[k].type != 'radio' || i[k].checked) &&
      (i[k].type != 'checkbox' || i[k].checked))
    {
      a[i[k].name] = i[k].value;
    }
  }
  update = update || (f.id+'-result');
  $.ajax({
    url: f.realAction || f.action,
    type: 'POST',
    data: a,
    dataType: 'html',
    success: function(r)
    {
      var u = document.getElementById(update);
      if (!u)
      {
        u = document.createElement('div');
        u.id = update;
        f.nextSibling ? f.parentNode.insertBefore(u, f.nextSibling) : f.parentNode.appendChild(u);
      }
      u.innerHTML = r;
    }
  });
}

window.sfSaveButton = function(el)
{
  if (el.form.title.realName)
  {
    el.form.title.name = el.form.title.realName;
  }
  el.form.realAction = el.form.realAction || el.form.action;
  var a = el.form.realAction;
  a += (a.indexOf('?') >= 0 ? '&' : '?') + 'action=save';
  el.form.action = a;
}

window.sfEditButton = function(el)
{
  if (!el.form.pagename && !el.form.title)
  {
    alert(mw.msg('sf_need_pagename'));
    return;
  }
  if (el.form.pagename)
  {
    el.form.pagename.name = 'title';
    el.form.pagename.realName = 'pagename';
  }
  el.form.realAction = el.form.realAction || el.form.action;
  el.form.action = mw.config.get('wgServer')+mw.config.get('wgScript')+'?action=edit';
}
