<?php
defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Form\FormHelper;

FormHelper::loadFieldClass('list');

class JFormFieldTemplatesghsvs extends JFormFieldList
{
	public $type = 'Templatesghsvs';

	public function getOptions()
	{
		$options = static::getTemplateOptions();
		return array_merge(parent::getOptions(), $options);
	}

	public static function getTemplateOptions($clientId = '0')
	{
		// Build the filter options.
		$db = Factory::getDbo();
		$query = $db->getQuery(true);

		$query->select($db->quoteName('element', 'value'))
			->select($db->quoteName('name', 'text'))
			->select($db->quoteName('extension_id', 'e_id'))
			->from($db->quoteName('#__extensions'))
			->where($db->quoteName('type') . ' = ' . $db->quote('template'))
			->where($db->quoteName('enabled') . ' = 1')
			->order($db->quoteName('client_id') . ' ASC')
			->order($db->quoteName('name') . ' ASC');
		$query->where($db->quoteName('client_id') . ' = ' . (int) $clientId);

		$db->setQuery($query);
		$options = $db->loadObjectList();

		return $options;
	}
}
