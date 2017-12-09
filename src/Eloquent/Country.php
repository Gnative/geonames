<?php namespace Ipalaus\Geonames\Eloquent;

use Illuminate\Database\Eloquent\Model;

class Country extends Model {

	/**
	 * The table associated with the model.
	 *
	 * @var string
	 */
	protected $table = 'geonames_countries';

	/**
	 * The primary key for the model.
	 *
	 * @var string
	 */
	protected $primaryKey = 'iso_alpha2';

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
    public $incrementing = false;


	/* -(  Relationships  )-------------------------------------------------- */

	public function continent()
	{
		return $this->belongsTo(\Ipalaus\Geonames\Eloquent\Continent::class,'continent_id','code');
	}


	public function names()
	{
		return $this->hasMany(\Ipalaus\Geonames\Eloquent\Name::class, 'iso_alpha2','country_id');
	}
}