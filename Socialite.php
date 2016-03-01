<?php namespace App\Socialite;

use Laravel\Socialite\SocialiteManager;

class Socialite extends SocialiteManager
{
    public function createPinterestDriver()
    {
        $config = $this->app['config']['services.pinterest'];

        return $this->buildProvider(PinterestProvider::class, $config);
    }
}
