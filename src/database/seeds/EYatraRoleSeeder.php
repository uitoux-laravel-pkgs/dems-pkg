<?php

namespace Uitoux\EYatra\Database\Seeds;

use App\Role;
use Illuminate\Database\Seeder;

class EYatraRoleSeeder extends Seeder {
	/**
	 * Run the database seeds.
	 *
	 * @return void
	 */
	public function run() {
		//Role::where('id', '>=', 1)->forceDelete();

		$records = [

			500 => [
				//'company_id' => 1,
				'display_order' => 1,
				'display_name' => 'eYatra Admin',
				'fixed_roles' => 0,
				'created_by' => 1,
				'permissions' => [
					//MAIN MENUS
					5000,

					//TRIPS
					5001, 5002, 5003, 5004, 5005,

					//OUTLETS
					5020, 5021, 5022, 5023,

					//EMPLOYEES
					5040, 5041, 5042, 5043,
				],
			],

			501 => [
				//'company_id' => 1,
				'display_order' => 1,
				'display_name' => 'eYatra Employee',
				'fixed_roles' => 0,
				'created_by' => 1,
				'permissions' => [
					//MAIN MENUS
					5000,

					//TRIPS
					5001, 5002, 5003, 5004,
				],
			],

			502 => [
				//'company_id' => 1,
				'display_order' => 1,
				'display_name' => 'eYatra Manager',
				'fixed_roles' => 0,
				'created_by' => 1,
				'permissions' => [
					//MAIN MENUS
					5000,

					//TRIPS
					5001, 5002, 5003, 5004,
				],
			],

			503 => [
				//'company_id' => 1,
				'display_order' => 1,
				'display_name' => 'eYatra Agent',
				'fixed_roles' => 0,
				'created_by' => 1,
				'permissions' => [
					//MAIN MENUS
					5000,

					//TRIPS
					5001, 5002, 5003, 5004,
				],
			],
		];

		foreach ($records as $id => $record_data) {
			$permissions = $record_data['permissions'];
			unset($record_data['permissions']);
			$record = Role::firstOrNew([
				'id' => $id,
			]);
			$record->fill($record_data);
			if (isset($record_data['name'])) {
				$record->name = $record_data['name'];
			} else {
				$record->name = $record_data['display_name'];
			}

			$record->save();
			//$record->perms()->syncWithoutDetaching($permissions);
			$record->perms()->sync($permissions);
		}

	}
}
