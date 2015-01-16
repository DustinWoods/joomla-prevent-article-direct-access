<?php
// no direct access
defined( '_JEXEC' ) or die( 'Restricted access' );

class PlgSystemPreventDirectArticleAccess extends JPlugin
{
	//To Do: make these adjustable in plugin settings
	private $_allowIDAccess = false;
	private $_allowIDAliasAccess = false;
	private $_allowIDAliasCategoryAccess = false;

	/**
	* Constructor
	*
	* @access      protected
	* @param       object  $subject The object to observe
	* @param       array   $config  An array that holds the plugin configuration
	*/
	public function __construct(& $subject, $config)
	{
		parent::__construct($subject, $config);
		$this->loadLanguage();
	}
	/**
	* Plugin method with the same name as the event will be called automatically.
	*/
	function onAfterInitialise() {
		$app = JFactory::getApplication();

		// Get the router
		$router = $app->getRouter();

		if($router->getMode() == JROUTER_MODE_SEF) {
			// Create a callback array to call the augmentRoute method of this object
			$augmentParseCallback = array($this, 'augmentParse');

			// Attach the callback to the router
			$router->attachParseRule($augmentParseCallback);
		}
	}

	/**
	 * Here's where we check the route and throw a 404 if we detect direct access to an article
	 */
	function augmentParse(&$router, $uri) {
		$fail = false;

		$app = JFactory::getApplication();

		$route = $uri->getPath();
		// Remove the suffix
		if ($app->get('sef_suffix'))
		{
			if ($suffix = pathinfo($route, PATHINFO_EXTENSION))
			{
				$route = str_replace('.' . $suffix, '', $route);
			}
		}

		if (empty($route))
		{
			return;
		}

		// Parse the application route
		$segments = explode('/', $route);

		//Check Joomla version
		$jversion = new JVersion();

		if( version_compare( $jversion->getShortVersion(), "3.0", 'lt' ) ) {
			$fail = $this->_25isDirectRoute($segments);
		} else {
			$fail = $this->_30isDirectRoute($segments);			
		}

		if($fail) {
			JError::raiseError(404, JTxt::_('JGLOBAL_RESOURCE_NOT_FOUND'));
		}

		return;
	}

	private function _30isDirectRoute($segments) {
		$total = count($segments);

		for ($i = 0; $i < $total; $i++)
		{
			$segments[$i] = preg_replace('/-/', ':', $segments[$i], 1);
		}
		// Count route segments
		$count = count($segments);

		if($count != 1)
			return false;

		// Get the active menu item.
		$app = JFactory::getApplication();
		$menu = $app->getMenu();
		$item = $menu->getActive();
		if (!isset($item))
			return false;

		$params = JComponentHelper::getParams('com_content');
		$advanced = $params->get('sef_advanced_link', 0);
		$db = JFactory::getDbo();


		// We check to see if an alias is given.  If not, we assume it is an article
		if (strpos($segments[0], ':') === false && !$_allowIDAccess) {
			return true;
		} else if($_allowIDAccess) {
			return false;
		}

		list($id, $alias) = explode(':', $segments[0], 2);
		// First we check if it is a category
		$category = JCategories::getInstance('Content')->get($id);


		if ($category && $category->alias == $alias && $_allowIDAliasCategoryAccess) {
			return false;
		} else if($_allowIDAliasAccess) {
			$query = $db->getQuery(true)
				->select($db->quoteName(array('alias', 'catid')))
				->from($db->quoteName('#__content'))
				->where($db->quoteName('id') . ' = ' . (int) $id);
			$db->setQuery($query);
			$article = $db->loadObject();
			if ($article) {
				if ($article->alias == $alias) {
					return false;
				}
			}
		}
		//If nothing found and count is still 1, then we have a 404
		return true;

	}



	private function _25isDirectRoute($segments) {

		// Count route segments
		$count = count($segments);

		if($count != 1)
			return false;

		//Get the active menu item.
		$app	= JFactory::getApplication();
		$menu	= $app->getMenu();
		$item	= $menu->getActive();
		if (!isset($item))
			return false;

		$params = JComponentHelper::getParams('com_content');
		$advanced = $params->get('sef_advanced_link', 0);
		$db = JFactory::getDBO();


		// if only 1 segment exists with no separator, then it's assumed an id, spit out 404
		if (strpos($segments[0], ':') === false && !$_allowIDAccess) {
			return true;
		} else if($_allowIDAccess) {
			return false;
		}

		//Let's try to see if the segment with a separator pulls up a category/article combo
		list($id, $alias) = explode(':', $segments[0], 2);
		// first we check if it is a category
		$category = JCategories::getInstance('Content')->get($id);
		if ($category && $category->alias == $alias && $_allowIDAliasCategoryAccess) {
			return false;
		} else if($_allowIDAliasAccess) {
			$query = 'SELECT alias, catid FROM #__content WHERE id = '.(int)$id;
			$db->setQuery($query);
			$article = $db->loadObject();
			if ($article) {
				if ($article->alias == $alias) {
					return false;
				}
			}
		}

		//If nothing found and count is still 1, then we have a 404
		return true;
	}

}
?>