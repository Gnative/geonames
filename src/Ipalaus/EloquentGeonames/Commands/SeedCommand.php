<?php namespace Ipalaus\EloquentGeonames\Commands;

use ZipArchive;
use ErrorException;
use RuntimeException;
use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Ipalaus\EloquentGeonames\Importer;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;


class SeedCommand extends Command {

	/**
	 * The console command name.
	 *
	 * @var string
	 */
	protected $name = 'geonames:seed';

	/**
	 * The console command description.
	 *
	 * @var string
	 */
	protected $description = 'Seed the geonames database with fresh records.';

	/**
	 * Filesystem implementation.
	 *
	 * @var \Illuminate\Filesystem\Filesystem
	 */
	protected $filesystem;

	/**
	 * Repository implementation.
	 *
	 * @var \Ipalaus\EloquentGeonames\Importer
	 */
	protected $importer;

	/**
	 * File archive instance.
	 *
	 * @var \ZipArchive
	 */
	protected $archive;

	/**
	 * GeoNames files to download.
	 *
	 * @var array
	 */
	protected $files = array(
		'names'     => 'http://download.geonames.org/export/dump/allCountries.zip',
		'alternate' => 'http://download.geonames.org/export/dump/alternateNames.zip',
		'hierarchy' => 'http://download.geonames.org/export/dump/hierarchy.zip',
		'admin1'    => 'http://download.geonames.org/export/dump/admin1CodesASCII.txt',
		'admin2'    => 'http://download.geonames.org/export/dump/admin2Codes.txt',
		'feature'   => 'http://download.geonames.org/export/dump/featureCodes_en.txt',
		'timezones' => 'http://download.geonames.org/export/dump/timeZones.txt',
		'countries' => 'http://download.geonames.org/export/dump/countryInfo.txt',
	);

	/**
	 * GeoName file for development: all cities with population > 15000 or capitals.
	 *
	 * @var string
	 */
	protected $development = 'http://download.geonames.org/export/dump/cities15000.zip';

	/**
	 * GeoName file wildcard for a concrete country.
	 *
	 * @var string
	 */
	protected $countryWildcard = 'http://download.geonames.org/export/dump/%s.zip';

	/**
	 * Create a new console command instance.
	 *
	 * @param  \Illuminate\Filesystem\Filesystem   $filesystem
	 * @param  \Ipalaus\EloquentGeonames\Importer  $importer
	 * @return void
	 */
	public function __construct(Filesystem $filesystem, Importer $importer)
	{
		$this->filesystem = $filesystem;
		$this->importer = $importer;
		$this->archive = new ZipArchive;

		parent::__construct();
	}

	/**
	 * Execute the console command.
	 *
	 * @return void
	 */
	public function fire()
	{
		$country     = $this->input->getOption('country');
		$development = $this->input->getOption('development');
		$fetchOnly   = $this->input->getOption('fetch-only');
		$wipeFiles   = $this->input->getOption('wipe-files');

		// i'm sorry but you can't have both :(
		if ($development and ! is_null($country)) {
			throw new RuntimeException("You have to select between a country or a development version of GeoNames.");
		}

		// set a development names, lighter download
		$development and $this->setDevelopment();

		// set a specific country names
		$country and $this->setCountry($country);

		// path to download our files
		$path = $this->laravel['path.storage'] . '/meta/geonames';

		// if we forced to wipe files, we will delete the directory
		$wipeFiles and $this->filesystem->deleteDirectory($path);

		// create the directory if it doesn't exists
		if ( ! $this->filesystem->isDirectory($path)) {
			$this->filesystem->makeDirectory($path);
		}

		// loop all the files that we need to donwload
		foreach ($this->getFiles() as $file) {
			$filename = basename($file);

			if ($this->fileExists($path, $filename)) {
				$this->line("<info>File exists:</info> $filename");

				continue;
			}

			$this->line("<info>Downloading:</info> $file");

			$this->downloadFile($file, $path, $filename);

			// if the file is ends with zip, we will try to unzip it and remove the zip file
			if (substr($filename, -strlen('zip')) === "zip") {
				$this->line("<info>Unzip:</info> $filename");
				$filename = $this->extractZip($path, $filename);
			}
		}

		// if we only want to fetch files, we must stop the execution of the command
		if ($fetchOnly) {
			$this->line('<info>Files fetched.</info>');
			return;
		}

		$namesFile = str_replace('.zip', '.txt', basename($this->files['names']));

		$this->importer->names('geonames_names', $path . '/' . $namesFile);
		$this->importer->countries('geonames_countries', $path . '/countryInfo.txt');
	}

	/**
	 * Sets the names file for a ligher version for development. We also ignore
	 * the alternate names.
	 *
	 * @return void
	 */
	protected function setDevelopment()
	{
		$this->files['names'] = $this->development;

		unset($this->files['alternate']);
	}

	/**
	 * Sets the name file to a specific country.
	 *
	 * @param  string $country
	 * @return void
	 */
	protected function setCountry($country)
	{
		if (strlen($country) > 2) {
			throw new RuntimeException('Country format must be in ISO Alpha 2 code.');
		}

		$this->files['names'] = sprintf($this->countryWildcard, strtoupper($country));
	}

	/**
	 * Download a file from a remote URL to a given path.
	 *
	 * @param  string  $url
	 * @param  string  $path
	 * @return void
	 */
	protected function downloadFile($url, $path, $filename)
	{
		if ( ! $fp = fopen ($path . '/' . $filename, 'w+')) {
			throw new RuntimeException('Impossible to write to path: ' . $path);
		}

		// curl looks like shit but whatever
		$ch = curl_init($url);
		curl_setopt($ch, CURLOPT_FILE, $fp);
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
		curl_exec($ch);
		curl_close($ch);
		fclose($fp);
	}

	/**
	 * Given a zip archive, extract a the file and remove the original.
	 *
	 * @param  string  $path
	 * @param  string  $filename
	 * @return string
	 */
	protected function extractZip($path, $filename)
	{
		$this->archive->open($path . '/' . $filename);

		$this->archive->extractTo($path . '/');
		$this->archive->close();

		$this->filesystem->delete($path . '/' . $filename);

		return str_replace('.zip', '.txt', $filename);
	}

	/**
	 * Checks if a file already exists on a path. If the file contains .zip in
	 * the name we will also check for matches with .txt.
	 *
	 * @param  string  $path
	 * @param  string  $filename
	 * @return bool
	 */
	protected function fileExists($path, $filename)
	{
		if (file_exists($path . '/' . $filename)) {
			return true;
		}

		if (file_exists($path . '/' . str_replace('.zip', '.txt', $filename))) {
			return true;
		}

		return false;
	}

	/**
	 * Get the files to download.
	 *
	 * @return array
	 */
	protected function getFiles()
	{
		return $this->files;
	}

	/**
	 * Get the console command options.
	 *
	 * @return array
	 */
	protected function getOptions()
	{
		return array(
			array('country', null, InputOption::VALUE_REQUIRED, 'Downloads just the specific country.'),
			array('development', null, InputOption::VALUE_NONE, 'Downloads an smaller version of names (~10MB).'),
			array('fetch-only', null, InputOption::VALUE_NONE, 'Just download the files.'),
			array('force', 'f', InputOption::VALUE_NONE, 'Forces overwriting the downloaded files.'),
			array('wipe-files', null, InputOption::VALUE_NONE, 'Wipe old downloaded files and fetch new ones.'),
		);
	}

}