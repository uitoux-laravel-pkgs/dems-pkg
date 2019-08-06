<?php

namespace Uitoux\EYatra;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Company extends Model {
	use SoftDeletes;
	protected $table = 'companies';

	//EYATRA RELATIONSHIPS
	public function employees() {
		return $this->hasMany('Uitoux\EYatra\Employee');
	}

	public function employeeGrades() {
		return $this->hasMany('Uitoux\EYatra\Entity')->where('entity_type_id', 500);
	}

	public function tripPurposes() {
		return $this->hasMany('Uitoux\EYatra\Entity')->where('entity_type_id', 501);
	}

	public function travelModes() {
		return $this->hasMany('Uitoux\EYatra\Entity')->where('entity_type_id', 502);
	}

	public function expenseTypes() {
		return $this->hasMany('Uitoux\EYatra\Config')->where('config_type_id', 500);
	}

	public function trips() {
		return $this->hasMany('Uitoux\EYatra\Trip');
	}

	public function users() {
		return $this->hasMany('App\User');
	}
	//ENDS EYATRA RELATIONSHIPS

}