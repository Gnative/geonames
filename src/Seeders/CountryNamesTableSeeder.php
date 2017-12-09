<?php namespace Ipalaus\Geonames\Seeders;

class CountryNamesTableSeeder extends DatabaseSeeder {

	/**
	 * Run the database seeds.
	 *
	 * @return void
	 */
	public function run()
	{
		$path    = $this->command->option('path');
		$country = $this->command->option('country');
        $features = $this->command->option('features');

		$this->importer->names('geonames_names', $path . '/' . $country . '.txt', $features);
	}

}