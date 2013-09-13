<?php
/**
 * plg System Template Selector
 * @version	 	1.8.0
 * @package		Template Selector
 * @copyright		Copyright (C) 2007 - 2012 Yoshiki Kozaki All rights reserved.
 * @license		http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 *
 * @author		Yoshiki Kozaki info@joomler.net
 * @link 			http://www.joomler.net/
 */

/**
* @package		Joomla
* @copyright		Copyright (C) 2005 - 2008 Open Source Matters. All rights reserved.
* @license		GNU/GPL
*/

// no direct access
defined('_JEXEC') or die('Restricted access');

jimport( 'joomla.plugin.plugin' );

class  plgSystemTemplateSelector extends JPlugin
{
	protected static $_styleID;
	protected static $_cookieJS;
	
	public function onAfterInitialise()	{
		$app = JFactory::getApplication();
		self::$_cookieJS = '';
		if($app->isAdmin()){
			return;
		}
		$userAgent = $_SERVER['HTTP_USER_AGENT'];
		$uri = $_SERVER['REQUEST_URI'];
		$userIP = $_SERVER['REMOTE_ADDR'];
		$defaultStyle = '';
		if (preg_match('/bot|spider|google/i',$userAgent)) {
			$sLog = '"'.JHtml::_('date','now','d-m-Y H:i:s').'";"URI: '.$uri.'";"IP: '.$userIP.'";"UA: '.$userAgent.'"'."\n";
			$logfile = JPATH_ROOT.DS.'logs'.DS.'bots-'.date('md').'.csv';
			@file_put_contents($logfile, $sLog, FILE_APPEND);
			$defaultStyle = 15;
		}
		if (stripos($uri,'shop/') !== false) {//vitana bezoekers
			$app->redirect('/producten');
		}
		$cookieValue = JRequest::getVar('jTemplateSelector', $defaultStyle, 'cookie', 'int');
		$template_style_id = $app->input->getInt('bst', $cookieValue);
// echo $cookieValue.'--'.$template_style_id; 

		//via content achterhalen
		$content_style_id = 0;
		if (stripos($uri,'producten') !== false) { //crude
			require_once (JPATH_ROOT.DS.'components'.DS.'com_virtuemart'.DS.'router.php');
			$segments = explode('/',str_replace('/producten','',$uri));
			$vars = virtuemartParseRoute($segments);
			if (isset($vars['virtuemart_product_id'])) {
				$db = JFactory::getDBO();
				$query = "SELECT cf.custom_value FROM `#__virtuemart_products` AS p "
					." INNER JOIN `#__virtuemart_product_customfields` AS cf ON p.`virtuemart_product_id` = cf.`virtuemart_product_id`"
					." AND cf.`virtuemart_custom_id` = 3"
					." WHERE p.`virtuemart_product_id` = ".$vars['virtuemart_product_id'];
				$db->setQuery($query);
				$styleName = $db->loadResult();
				if ($styleName) {
					$styleXref = array(
						'green'=>15,
						'sports'=>14,
						'lifestyle'=>16
					);
					if (isset($styleXref[$styleName])) $content_style_id = $styleXref[$styleName];
				}
			}
		}
		if (stripos($uri,'blog') !== false) { //crude
			if (stripos($uri,'bastiaan') !== false) {
				$content_style_id = 15;
			}
			if (stripos($uri,'eva') !== false) {
				$content_style_id = 14;
			}
			if (stripos($uri,'wilma') !== false) {
				$content_style_id = 16;
			}
		}
		if ($content_style_id) {
			$template_style_id = $content_style_id;
		}
// echo 'BR:'.$content_style_id.'-'.$template_style_id.'<br/>';
		
		
		if ($template_style_id < 1) { //niets doen, naar standaard template
			$sLog = '"'.JHtml::_('date','now','d-m-Y H:i:s').'";"URI: '.$uri.'";"IP: '.$userIP.'";"UA: '.$userAgent.'"'."\n";
			$logfile = JPATH_ROOT.DS.'logs'.DS.'koekieloos-'.date('md').'.csv';
			@file_put_contents($logfile, $sLog, FILE_APPEND);
			return;
		} 
		if($cookieValue != $template_style_id){
			$this->setCookieScript($template_style_id);
		}

		$row = $this->getTemplateRow($template_style_id);
		if(!$row || empty($row->template)){
			return;
		}
// echo '<pre>row';
// print_r($row);
// echo '</pre>';
		
		$current = self::getCurrentTemplate();
// echo '-'.$row->template;
		if($current != $template_style_id && is_dir(JPATH_THEMES. DS. $row->template)){
// echo $template_style_id.'-'.$current.'-'.$row->template;
			self::$_styleID = $template_style_id;
			$app->setTemplate($row->template, (new JRegistry($row->params)));
		}/**/
	}
	public function onAfterRoute() {
		$app = JFactory::getApplication();

		if($app->isAdmin()){
			return;
		}
		$option = JRequest::getCmd('option');
		$view = JRequest::getCmd('view');
		$layout = JRequest::getCmd('layout');
		$template_style_id = self::getMenuTemplate();
		$current = self::getCurrentTemplate();
		if ($template_style_id > 0 && $current != $template_style_id) {
			$row = $this->getTemplateRow($template_style_id);
			if(!$row || empty($row->template)){
				return;
			}
			if ($current != $template_style_id && is_dir(JPATH_THEMES. DS. $row->template)) {
				define('TEMPL_CHANGED',$template_style_id);
				self::$_styleID = $template_style_id;
				$this->setCookieScript($template_style_id);
				$app->setTemplate($row->template, (new JRegistry($row->params)));
			}
		}
		JFactory::getDocument()->addScriptDeclaration(self::$_cookieJS);
		//filter in request
		$styleID = plgSystemTemplateSelector::getCurrentTemplate();
		$sAfixJs="
			window.addEvent('domready', function() {
				$$('a').each(function(el) {
					var href = el.href;
					var match = href.match(/xivox/);
					var matchNot = href.match(/mailto|javascript|bst/);
	//console.log(match,matchNot);
					if (match && !matchNot) {
						var op = href.match(/\?/)?'&':'?';
						el.set('href',href+op+'bst=".$styleID."');
					}
				});
			});
		";
// echo '<pre>aroute'.$styleID;
// echo 'AR:'.$current.'-'.$template_style_id.'<br/>';
// print_r(self::$_styleID);
// echo	self::$_cookieJS;
// echo '</pre>';
		JFactory::getDocument()->addScriptDeclaration($sAfixJs);
		$app = JFactory::getApplication() ;
		$jinput=$app->input;
		$tplXref = array(
			15=>'677265656e',//'green',
			14=>'73706f727473',//'sports',
			16=>'6c6966657374796c65'//'lifestyle'
		);
		if (isset($tplXref[$styleID])) {
			//cf filter
			$customid = 3;
			$jinput->set('custom_f_'.$customid,array($tplXref[$styleID]));
			//blog
			if ($option == 'com_content' && $view == 'category' && $layout == 'blog') {
				$blogXref = array(
					15=>13,//'green' bastiaan,
					14=>11,//'sports  eva',
					16=>12 //'lifestyle wilma'
				);
				JRequest::setVar('id',$blogXref[$styleID]);
		// echo JRequest::getVar('id','id');
			}
		}
	}
	protected function setCookieScript($template_style_id) {
		self::$_cookieJS = "if (Browser.Features.localstorage) {localStorage['jTemplateSelector'] = $template_style_id;
		} else {Cookie.write('jTemplateSelector', $template_style_id);}
		";
	}
	protected function getTemplateRow($template_style_id) {
		$db = JFactory::getDbo();
		$query = $db->getQuery(true);
		$query->select($db->qn('template'));
		$query->select($db->qn('params'));
		$query->from($db->qn('#__template_styles'));
		$query->where($db->qn('client_id'). ' = 0');
		$query->where($db->qn('id'). ' = '. (int)$template_style_id);
		$query->order($db->qn('id'));
		$db->setQuery( $query );
		$row = $db->loadObject();
		return $row;
	}
	public static function getMenuTemplate() {
		$app = JFactory::getApplication();
		$menus = $app->getMenu('site');
		$menu = $menus->getActive();
		if($menu){
			$template_style_id = (int)$menu->template_style_id;
			if($template_style_id > 0){
				return $template_style_id;
			}
		}
		return 0;
	}
	public static function getCurrentTemplate() {
		$app = JFactory::getApplication();
		$template = $app->getTemplate(true);
		if (!isset(self::$_styleID) && isset($template->id) && $template->id > 0) {
			return $template->id;
		}
		return self::$_styleID;
	}
}
