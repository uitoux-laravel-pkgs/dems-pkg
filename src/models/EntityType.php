<?php

namespace Uitoux\EYatra;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class EntityType extends Model {
	public $timestamps = false;
	protected $fillable = [
		'id',
		'name'
	];
}
