<?php
/**
 * @version    SVN: <svn_id>
 * @package    Com_Hierarchy
 * @author     Techjoomla <extensions@techjoomla.com>
 * @copyright  Copyright (c) 2009-2017 TechJoomla. All rights reserved.
 * @license    GNU General Public License version 2 or later.
 */

defined('_JEXEC') or die;

jimport('joomla.application.component.modellist');

/**
 * Methods supporting a list of Hierarchy records.
 *
 * @since  1.6
 */
class HierarchyModelHierarchys extends JModelList
{
	/**
	 * Constructor.
	 *
	 * @param   array  $config  An optional associative array of configuration settings.
	 *
	 * @since   1.6
	 * @see     JController
	 */
	public function __construct($config = array())
	{
		if (empty($config['filter_fields']))
		{
			$config['filter_fields'] = array(
				'id', 'a.id',
				'user_id', 'a.user_id',
				'subuser_id', 'a.subuser_id',
				'created_by', 'a.created_by',
			);
		}

		parent::__construct($config);
	}

	/**
	 * Method to auto-populate the model state.
	 *
	 * Note. Calling getState in this method will result in recursion.
	 *
	 * @param   string  $ordering   An optional ordering field.
	 * @param   string  $direction  An optional direction (asc|desc).
	 *
	 * @return  void
	 *
	 * @since   1.6
	 */
	protected function populateState($ordering = null, $direction = null)
	{
		// Initialise variables.
		$app = JFactory::getApplication();

		// List state information
		$limit = $app->getUserStateFromRequest('global.list.limit', 'limit', $app->getCfg('list_limit'));
		$this->setState('list.limit', $limit);

		$limitstart = $app->input->getInt('limitstart', 0);
		$this->setState('list.start', $limitstart);

		// Load the filter state.
		$search = $app->getUserStateFromRequest($this->context . 'filter_search', 'filter_search');
		$this->setState('filter_search', $search);

		$contextName = $app->getUserStateFromRequest($this->context . 'filter_context', 'filter_context', '', 'string');
		$this->setState('filter_context', $contextName);

		// Receive & set filters
		if ($filters = $app->getUserStateFromRequest($this->context . '.filter', 'filter', array(), 'array'))
		{
			foreach ($filters as $name => $value)
			{
				$this->setState('filter.' . $name, $value);
			}
		}

		$ordering = $app->input->get('filter_order');

		if (!empty($ordering))
		{
			$list             = $app->getUserState($this->context . '.list');
			$list['ordering'] = $app->input->get('filter_order');
			$app->setUserState($this->context . '.list', $list);
		}

		$orderingDirection = $app->input->get('filter_order_Dir');

		if (!empty($orderingDirection))
		{
			$list              = $app->getUserState($this->context . '.list');
			$list['direction'] = $app->input->get('filter_order_Dir');
			$app->setUserState($this->context . '.list', $list);
		}

		$list = $app->getUserState($this->context . '.list');
		$this->setState('list.ordering', $list['ordering']);
		$this->setState('list.direction', $list['direction']);
	}

	/**
	 * Build an SQL query to load the list data.
	 *
	 * @return  JDatabaseQuery
	 *
	 * @since   1.6
	 */
	protected function getListQuery()
	{
		$user       = JFactory::getUser();

		// Create a new query object.
		$db    = $this->getDbo();
		$query = $db->getQuery(true);

		/*$subQquery = $db->getQuery(true);
		$subQquery->select(
				$db->quoteName(
					array('hu.id', 'hu.user_id', 'hu.reports_to', 'hu.context', 'hu.context_id', 'hu.state', 'hu.note')
							)
				);

		$subQquery->from($db->quoteName('#__hierarchy_users', 'hu'));
		$subQquery->where('hu.user_id = ' . (int) $user->id);*/

		// Select the required fields from the table.
		$query->select(
				$this->getState('list.select',
				'DISTINCT' . $db->quoteName('a.id', 'subuserId') . ',' . $db->quoteName('a.name') . ',' . $db->quoteName('a.username')
				)
				);
		$query->from($db->quoteName('#__users', 'a'));

		// Join over the user field 'user_id'
		$query->select(
				$db->quoteName(
					array('hu.id', 'hu.user_id', 'hu.reports_to', 'hu.context', 'hu.context_id', 'hu.state', 'hu.note')
							)
				);
		$query->join('LEFT', $db->quoteName('#__hierarchy_users', 'hu') . ' ON (' . $db->quoteName('hu.user_id') . ' = ' . $db->quoteName('a.id') . ')');

		// Filter by search in title
		$search = $this->getState('filter_search');

		$contextName = $this->getState('filter_context');

		if ($user->id)
		{
			$query->where('hu.reports_to = ' . (int) $user->id);
		}

		if (!empty($search))
		{
			if (stripos($search, 'id:') === 0)
			{
				$query->where('a.id = ' . (int) substr($search, 3));
			}
			else
			{
				$search = $db->Quote('%' . $db->escape($search, true) . '%');
				$query->where('( a.name LIKE ' . $search . ' )');
			}
		}

		// Filter by user name
		if (!empty($UserNames))
		{
			$UserNames = $db->Quote('%' . $db->escape($UserNames, true) . '%');
			$query->where('( a.id LIKE ' . $UserNames . ' )');
		}

		// Filter by context
		if (!empty($contextName))
		{
			$contextName = $db->Quote('%' . $db->escape($contextName, true) . '%');
			$query->where('( hu.context LIKE ' . $contextName . ' )');
		}

		$query->where('a.block=0');

		// Add the list ordering clause.
		$orderCol = $this->state->get('list.ordering');
		$orderDirn = $this->state->get('list.direction');

		if ($orderCol && $orderDirn)
		{
			$query->order($db->escape($orderCol . ' ' . $orderDirn));
		}

		// Filter the items over the group id if set.
		$groupId = $this->getState('usergroup');

		if ($groupId)
		{
			$query->join('LEFT', '#__user_usergroup_map AS map2 ON map2.user_id = a.id');

			if ($groupId)
			{
				$query->where('map2.group_id = ' . (int) $groupId);
			}
		}

		return $query;
	}

	/**
	 * Method to get reports_to
	 *
	 * @param   INT  $reportsTo  reportsTo
	 *
	 * @return  Array of data
	 *
	 * @since   1.0
	 */
	public function getReportsTo($reportsTo)
	{
		$db = JFactory::getDbo();
		$query = $db->getQuery(true);
		$query->select('*');
		$query->from($db->quoteName('#__hierarchy_users'));
		$query->where($db->quoteName('reports_to') . ' = ' . $db->quote($reportsTo));
		$db->setQuery($query);
		$results = $db->loadObjectList();

		return $results;
	}

	/**
	 * Method to get reporting to users
	 *
	 * @param   INT  $userID  userID
	 *
	 * @return  Array of data
	 *
	 * @since   1.0
	 */
	public function getReportingTo($userID)
	{
		$db = JFactory::getDbo();
		$query = $db->getQuery(true);
		$query->select('*');
		$query->from($db->quoteName('#__hierarchy_users'));
		$query->where($db->quoteName('user_id') . ' < ' . $db->quote($userID));
		$db->setQuery($query);
		$results = $db->loadObjectList();

		foreach ($results as $res)
		{
			$user = JFactory::getUser($res->user_id);
			$res->reportingTo = $user->name;
		}

		return $results;
	}

	/**
	 * Method to get reports_to
	 *
	 * @param   INT  $reportsTo  reportsTo
	 *
	 * @return  Array of data
	 *
	 * @since   1.0
	 */
	public function getAlsoReportsTo($reportsTo)
	{
		$db = JFactory::getDbo();
		$query = $db->getQuery(true);
		$query->select('*');
		$query->from($db->quoteName('#__hierarchy_users'));
		$query->where($db->quoteName('reports_to') . ' = ' . $db->quote($reportsTo));
		$db->setQuery($query);
		$results = $db->loadObjectList();

		foreach ($results as $res)
		{
			$user = JFactory::getUser($res->user_id);
			$res->reportsToName = $user->name;
		}

		return $results;
	}
}
