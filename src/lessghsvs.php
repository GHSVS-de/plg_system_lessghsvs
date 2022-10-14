<?php
/**
 * @package   System Plugin - automatic Less compiler - for Joomla 2.5 and 3.x
 * @version   0.8.1 Stable
 * @author    Andreas Tasch
 * @copyright (C) 2012-2015 - Andreas Tasch and contributors
 * @license   GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 **/

/*
GHSVS 2015-11-08
Takeover and edit by www.ghsvs.de
Because is more compatible with BS 3.
Less compiler lessphp; see https://github.com/oyejorge/less.php/releases
**/
defined('_JEXEC') or die();

use Joomla\CMS\Factory;
use Joomla\CMS\Filesystem\Path;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Filesystem\File;
use MatthiasMullie\Minify;

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
				$this->debug ? $this->app->enqueueMessage($this->debugPrefix . 'Info: Mode = -1. Stopping work after loading lessc.') : null;
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

		require __DIR__ . '/vendor/autoload.php';

		$templateName = $this->app->getTemplate();

		if (!in_array($templateName, $this->params->get('templates', [])))
		{
			$this->execute = false;
			return;
		}

		if (!($lessFiles = trim($this->params->get('lessfile', ''))))
		{
			$this->execute = false;
			$this->debug ? $this->app->enqueueMessage($this->debugPrefix . 'Error: No LESS file(s) entered.') : null;
			return;
		}

		if (!($cssFiles = trim($this->params->get('cssfile', ''))))
		{
			$this->execute = false;
			$this->debug ? $this->app->enqueueMessage($this->debugPrefix . 'Error: No CSS file(s) entered.') : null;
			return;
		}

		$lessFiles = array_map("trim", explode("\n", str_replace("\r", '', $lessFiles)));
		$cssFiles  = array_map("trim", explode("\n", str_replace("\r", '', $cssFiles)));

		if (count($lessFiles) !== count($cssFiles))
		{
			$this->execute = false;
			$this->debug ? $this->app->enqueueMessage($this->debugPrefix . 'Error: Number of entered LESS files differ from number of entered CSS files.') : null;
			return;
		}

		if(count(array_filter($lessFiles)) !== count($lessFiles)
			|| count(array_filter($cssFiles)) !== count($cssFiles))
		{
			$this->execute = false;
			$this->debug ? $this->app->enqueueMessage($this->debugPrefix . 'Error: Empty lines in LESS or CSS field found. Not allowed.') : null;
			return;
		}

		$templatePath = JPATH_SITE . '/templates/' . $templateName . '/';

		foreach ($lessFiles as $key => $lessFile)
		{
			$lessFiles[$key] = Path::clean($templatePath . $lessFile, '/');
			$cssFiles[$key] = Path::clean($templatePath . $cssFiles[$key], '/');

			if (!is_readable($lessFiles[$key]))
			{
				$this->execute = false;
				$this->debug ? $this->app->enqueueMessage($this->debugPrefix . 'Error: Could not read LESS file ' . $lessFile) : null;
				return;
			}
		}

		foreach ($lessFiles as $key => $lessFile)
		{
			$cssFile = $cssFiles[$key];

			if (is_readable($cssFile))
			{
				$bakFile = $cssFile . '.plg_system_lessghsvs.bak';

				if (!file_exists($bakFile))
				{
					File::copy($cssFile, $bakFile);
					$this->debug ? $this->app->enqueueMessage($this->debugPrefix . 'Info: Created bak file of CSS file: ' . $bakFile) : null;
				}
			}

			try
			{
				$this->debug ? $this->app->enqueueMessage($this->debugPrefix . 'Info: Starting autoCompileLess with LESS file to CSS file: ' . $lessFile . ' to '. $cssFile) : null;
				$this->autoCompileLess($lessFile, $cssFile);
			}
			catch (Exception $e)
			{
				echo 'plg_system_lessghsvs error: ' . $e->getMessage();
				$this->execute = false;
				$this->app->enqueueMessage($this->debugPrefix . 'plg_system_lessghsvs error: ' . $e->getMessage());
				return;
			}
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

		// noch nicht richtig implementiert. Siehe compress-Einstellung.
		$ouputFileMin = explode('.', $outputFile);
		$extension = 'min.' . $ouputFileMin[count($ouputFileMin) - 1];
		array_pop($ouputFileMin);
		$ouputFileMin[] = $extension;
		$ouputFileMin = implode('.', $ouputFileMin);

		$tmpPath = $this->app->get('tmp_path', JPATH_SITE . '/tmp');

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

			// Noch nicht richtig implementiert.
			$minifier = new Minify\CSS();
			$minifier->add($newCache['compiled']);

			// Save minified CSS file.
			$minifier->minify($ouputFileMin);

			// Save gz of minified file.
			$gzFilename = $ouputFileMin . '.gz';

			if ($this->params->get('gzFiles', 1) === 1)
			{
				$minifier->gzip($gzFilename);
			}
			elseif (is_file($gzFilename))
			{
				unlink($gzFilename);
			}
		}
	}

}
