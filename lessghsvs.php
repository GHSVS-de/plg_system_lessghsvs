<?php
/**
 * @package   System Plugin - automatic Less compiler - for Joomla 2.5 and 3.x
 * @version   0.8.1 Stable
 * @author    Andreas Tasch
 * @copyright (C) 2012-2015 - Andreas Tasch and contributors
 * @license   GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 **/
 
/**
GHSVS 2015-11-08
Takeover and edit by www.ghsvs.de
Because is more compatible with BS 3.
Less compiler lessphp;
See https://github.com/oyejorge/less.php/releases (archived meanwhile)
See: https://github.com/Asenar/less.php
**/
defined('_JEXEC') or die();

use Joomla\CMS\Factory;
use Joomla\CMS\Filesystem\Path;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\Filesystem\File;

class plgSystemLessghsvs extends CMSPlugin
{
	/**
	 * @var $app
	 */
	protected $app;
	
	private $debugPrefix = '[DEBUG plg_system_lessghsvs] ';
	private $execute = false;
	private $debug = true;

	/**
	 * override constructor to load classes as soon as possible
	 * @param $subject
	 * @param $config
	 */
	public function __construct(&$subject, $config)
	{

		parent::__construct($subject, $config);
		
		// Da irgendwelche Probleme mit Debugmodus, ggf. hier deaktivieren.
		$this->debug = JDEBUG;
		
		### VORSICHT BEI DEM GANZEN SCHEISS. Das Plugin plg_system_bs3ghsvsv lädt lessc ebenfalls!!!
		### Verwendet dabei die Einstellungen dieses Plugins hier.
		### Darf also nicht verwundern, wenn class_exists hier ein true zurückliefert!
		
		if ($this->app->isClient('site'))
		{
			// check if lessc already exists but bypass autoloader
			if (class_exists('lessc', false))
			{
				if ($this->debug)
				{
					$classPath = new \ReflectionClass('lessc');
					$classPath = $classPath->getFileName();
					$this->app->enqueueMessage($this->debugPrefix . 'Class "lessc" already exists, using file '. $classPath);
				}
				$this->execute = true;
			}
			else
			{
				$name = $this->params->get('sitelessc', 'lessphp-1.7.0.9-bugfixedGhsvs');

				if (file_exists(($file = __DIR__ . '/lessc/' . $name . '.php')))
				{
					#JLoader::register('lessc', $file);
					require_once $file;
					$this->debug ? $this->app->enqueueMessage($this->debugPrefix . "Loaded $name") : null;
					$this->execute = true;
				}
				else
				{
					$this->debug ? $this->app->enqueueMessage($this->debugPrefix . "Could not load $name") : null;
				}
			}
			
			// Load lessc but don't do anything else.
			if ((int) $this->params->get('mode', -1) === -1)
			{
				$this->execute = false;
				$this->debug ? $this->app->enqueueMessage($this->debugPrefix . 'Mode = -1. Stopping work after loading lessc.') : null;
			}

			if (! is_array($this->params->get('templates')))
			{
				$this->execute = false;
			}
		}
	}

	function onBeforeRender()
	{
		if (!$this->execute)
		{
			return;
		}
		
		$templateName = $this->app->getTemplate();
		
		if (!in_array($templateName, $this->params->get('templates')))
		{
			$this->execute = false;
			return;
		}

		$templatePath = JPATH_SITE . '/templates/' . $templateName . '/';
		$lessFile     = Path::clean(
			$templatePath . $this->params->get('lessfile', 'less/template.less'),
			'/'
		);
		
		if (!is_readable($lessFile))
		{
			$this->execute = false;
			$this->debug ? $this->app->enqueueMessage($this->debugPrefix . 'Could not read less file.') : null;
			return;
		}

		$cssFile = Path::clean(
			$templatePath . $this->params->get('cssfile', 'css/template.css'),
			'/'
		);

		if (is_readable($cssFile))
		{
			$bakFile = $cssFile . '.plg_system_lessghsvs.bak';

			if (!file_exists($bakFile))
			{
				File::copy($cssFile, $bakFile);
				$this->debug ? $this->app->enqueueMessage($this->debugPrefix . 'Created bak file of CSS file.') : null;
			}
		}

		try
		{
			$this->autoCompileLess($lessFile, $cssFile);
			$this->debug ? $this->app->enqueueMessage($this->debugPrefix . 'Starting autoCompileLess') : null;
		}
		catch (Exception $e)
		{
			echo 'plg_system_lessghsvs error: ' . $e->getMessage();
		}
	}

	/**
	 * Checks if .less file has been updated and stores it in cache for quick comparison.
	 *
	 * This function is taken and modified from documentation of lessphp
	 *
	 * @param String $inputFile
	 * @param String $outputFile
	 */
	function autoCompileLess($inputFile, $outputFile)
	{
		if (!$this->execute)
		{
			return;
		}

		$tmpPath = $this->app->get('tmp_path');

		$cacheFile = $tmpPath . '/' . $this->app->getTemplate() . "_" . basename($inputFile) . ".cache";

		if (file_exists($cacheFile))
		{
			$tmpCache = unserialize(file_get_contents($cacheFile));
			if ($tmpCache['root'] === $inputFile)
			{
				// Array.
				$cache = $tmpCache;
			}
			else
			{
				// String. File Path.
				$cache = $inputFile;
				unlink($cacheFile);
			}
		}
		else
		{
			$cache = $inputFile;
		}

		//instantiate less compiler
		$less = new lessc;

		//set less options
		//option: force recompilation regardless of change
		$force = (boolean) $this->params->get('less_force', 0);

		//option: preserve comments
		if ($this->params->get('less_comments', 0))
		{
			$less->setPreserveComments(true);
		}

		// GHSVS 2015-11-09
		if ($this->params->get('less_compress', 0))
		{
			//$less->setFormatter("compressed");
			$less->setOption('compress', true);
		}
		else
		{
			//$less->setFormatter("classic");
			$less->setOption('compress', false);
		}
// GHSVS 2015-11-09
// Sihe such lessphp-1.7.0.9-bugfixedGhsvs.php
// Bspw. glyphicons.less schlägt fehl mit false,
// wenn ich das aus Plugin-Media-Folder zulade.
		if ($this->params->get('less_relativeUrls', 1))
		{
			$less->setOption('relativeUrls', true);
		}
		else
		{
			$less->setOption('relativeUrls', false);
		}		

		//compile cache file
		$newCache = $less->cachedCompile($cache, $force);

		if (!is_array($cache) || $newCache["updated"] > $cache["updated"])
		{
			file_put_contents($cacheFile, serialize($newCache));
			file_put_contents($outputFile, $newCache['compiled']);
		}
	}

}
