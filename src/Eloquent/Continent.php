<?php namespace Ipalaus\Geonames\Eloquent;

use Illuminate\Database\Eloquent\Model;

class Continent extends Model {

	/**
	 * The table associated with the model.
	 *
	 * @var string
	 */
	protected $table = 'geonames_continents';

	/**
	 * The primary key for the model.
	 *
	 * @var string
	 */
	protected $primaryKey = 'code';


    /**
     * The "type" of the auto-incrementing ID.
     *
     * @var string
     */
    protected $keyType = 'string';

    /**
     * Indicates if the IDs are auto-incrementing.
     *
     * @var bool
     */
    //public $incrementing = false;



    /* -(  Relationships  )-------------------------------------------------- */


	public function countries()
	{
		return $this->hasMany(\Ipalaus\Geonames\Eloquent\Country::class,'continent_id','code');
	}

}