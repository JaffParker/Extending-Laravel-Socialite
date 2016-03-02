<?php namespace App\Providers;

use Laravel\Socialite\SocialiteServiceProvider;
use App\Socialite;

class SocialiteServiceProvider extends SocialiteServiceProvider
{
    public function register()
    {
        $this->app->singleton('Laravel\Socialite\Contracts\Factory', function ($app) {
            return new Socialite($app);
        });
    }
}
