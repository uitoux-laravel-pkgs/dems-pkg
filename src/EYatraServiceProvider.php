<?php

namespace Uitoux\EYatra;

use Illuminate\Support\ServiceProvider;

class EYatraServiceProvider extends ServiceProvider {
	/**
	 * Bootstrap services.
	 *
	 * @return void
	 */
	public function boot() {
		include __DIR__ . '/routes.php';
		$this->loadMigrationsFrom(__DIR__ . '/migrations');
		$this->publishes([
			__DIR__ . '/config/eyatra.php' => config_path('eyatra.php'),
		]);
	}

	/**
	 * Register services.
	 *
	 * @return void
	 */
	public function register() {
		$this->app->make('Uitoux\EYatra\EmployeeController');
		$this->loadViewsFrom(__DIR__ . '/views', 'eyatra');
	}
}
