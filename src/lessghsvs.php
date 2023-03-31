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
*/

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Filesystem\Path;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Filesystem\File;
use MatthiasMullie\Minify;
use Joomla\CMS\Log\Log;

class plgSystemLessghsvs extends CMSPlugin
{
	/**
	 * @var $app
	 */
	protected $app;
	private $execute = false;
	private $debug = false;

	/**
	 * override constructor to load classes as soon as possible
	 * @param $subject
	 * @param $config
	 */
	public function __construct(&$subject, $config)
	{
		parent::__construct($subject, $config);

		if ($this->app->isClient('api')) {
			$this->execute = false;
			return;
		}

		// Da irgendwelche Probleme mit Debugmodus, ggf. hier deaktivieren.
		$this->debug = $this->params->get('debug', 0) === 1;

		// E.g. Log::add('Hello World', Log::WARNING, 'lessghsvs');
		$options = array(
			'text_file' => 'plgSystemLessghsvs.php',
			'text_entry_format' => '{DATETIME}	{PRIORITY}	{MESSAGE}',
		);

		Log::addLogger($options, Log::ALL, ['lessghsvs']);

		### VORSICHT BEI DEM GANZEN SCHEISS. Das Plugin plg_system_bs3ghsvsv lädt lessc ebenfalls!!!
		### Verwendet dabei die Einstellungen dieses Plugins hier.
		### Darf also nicht verwundern, wenn class_exists hier ein true zurückliefert!

		if ($this->app->isClient('site'))
		{
			// check if lessc already exists but bypass autoloader
			if (class_exists('lessc', false))
			{
				if ($this->debug === true)
				{
					$classPath = new \ReflectionClass('lessc');
					$classPath = $classPath->getFileName();
					$this->log('Class "lessc" already exists. Is using file '. $classPath, LOG::WARNING);
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

					if ($this->debug === true) {
						$this->log("Loaded lessc file $name");
					}

					$this->execute = true;
				}
				else
				{
					if ($this->debug === true) {
						$this->log("Could not load $name", Log::ERROR);
					}
				}
			}

			// Load lessc but don't do anything else.
			if ((int) $this->params->get('mode', -1) === -1)
			{
				$this->execute = false;

				if ($this->debug === true) {
					$this->log('Mode in plugin settings is "Only load lessc". Stopping work after loading lessc.');
				}
			}

			if (! is_array($this->params->get('templates')) || empty($this->params->get('templates')))
			{
				if ($this->debug === true) {
					$this->log('No template selected in plugin settings.', Log::ALERT);
				}
				$this->execute = false;
			}
		}
	}

	function onBeforeRender()
	{
		if (!$this->execute || Factory::getDocument()->getType() !== 'html')
		{
			return;
		}

		require __DIR__ . '/vendor/autoload.php';

		$templateName = $this->app->getTemplate();

		if (!in_array($templateName, $this->params->get('templates', [])))
		{
			$this->execute = false;

			if ($this->debug === true) {
				$this->log('Current template ' . $templateName . ' not selected in plugin settings. Stopping my work.');
			}
			return;
		}

		if (!($lessFiles = trim($this->params->get('lessfile', ''))))
		{
			$this->execute = false;

			if ($this->debug === true) {
				$this->log('No LESS file(s) entered in plugin settings.', Log::ERROR);
			}

			return;
		}

		if (!($cssFiles = trim($this->params->get('cssfile', ''))))
		{
			$this->execute = false;

			if ($this->debug === true) {
				$this->log('No CSS file(s) entered in plugin settings.', Log::ERROR);
			}

			return;
		}

		$lessFiles = array_map("trim", explode("\n", str_replace("\r", '', $lessFiles)));
		$cssFiles  = array_map("trim", explode("\n", str_replace("\r", '', $cssFiles)));

		if (count($lessFiles) !== count($cssFiles))
		{
			$this->execute = false;

			if ($this->debug === true) {
				$this->log('Number of entered LESS files differ from number of entered CSS files in plugin settings.', Log::ERROR);
			}

			return;
		}

		if(count(array_filter($lessFiles)) !== count($lessFiles)
			|| count(array_filter($cssFiles)) !== count($cssFiles))
		{
			$this->execute = false;

			if ($this->debug === true) {
				$this->log('Empty lines in LESS or CSS field found. Not allowed.', Log::ERROR);
			}

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

				if ($this->debug === true) {
					$this->log('Can not read LESS file ' . $lessFile, Log::ERROR);
				}

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

					if ($this->debug === true) {
						$this->log('Created backup file of previous, already existing CSS file: ' . $bakFile);
					}
				}
			}

			try
			{
				if ($this->debug === true) {
					$this->log('Starting autoCompileLess with LESS file to CSS file: ' . $lessFile . ' to '. $cssFile);
				}

				$this->autoCompileLess($lessFile, $cssFile);
			}
			catch (Exception $e)
			{
				//echo 'plg_system_lessghsvs error: ' . $e->getMessage();
				if ($this->debug === true) {
					$this->log('plg_system_lessghsvs Critical compiler error: ' . $e->getMessage(), Log::CRITICAL);
				}
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
			if ($this->debug === true) {
				$this->log('Creating cache file ' . $cacheFile);
			}

			file_put_contents($cacheFile, serialize($newCache));

			if ($this->debug === true) {
				$this->log('Creating CSS file ' . $outputFile);
			}

			file_put_contents($outputFile, $newCache['compiled']);

			// Noch nicht richtig implementiert.
			$minifier = new Minify\CSS();
			$minifier->add($outputFile);

			// Save minified CSS file.
			if ($this->debug === true) {
				$this->log('Creating minified CSS file ' . $ouputFileMin);
			}

			$minifier->minify($ouputFileMin);

			// Save gz of minified file.
			$gzFilename = $ouputFileMin . '.gz';

			if ($this->params->get('gzFiles', 1) === 1)
			{
				if ($this->debug === true) {
					$this->log('Creating gz variant of minified CSS file ' . $gzFilename);
				}

				$minifier->gzip($gzFilename);
			}
			elseif (is_file($gzFilename))
			{
				if ($this->debug === true) {
					$this->log('Deleting gz variant of minified CSS file because feature disabled in plugin settings ' . $gzFilename);
				}

				unlink($gzFilename);
			}
		}
		else {
			if ($this->debug === true) {
				$this->log('Cache file checked. Nothing to do with Input/Output ' . $inputFile . '/' . $outputFile);
			}
		}
	}

	/*
	const EMERGENCY = 'emergency';
	const ALERT = 'alert';
	const CRITICAL = 'critical';
	const ERROR = 'error';
	const WARNING = 'warning';
	const NOTICE = 'notice';
	const INFO = 'info';
	const DEBUG = 'debug';

	*/
	protected function log(string $msg, string $level = Log::INFO)
	{
		Log::add($msg, $level, 'lessghsvs');
	}
}
