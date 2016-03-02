# Extending Laravel's Socialite

![Bicycle](https://github.com/JaffParker/Extending-Laravel-Socialite/blob/master/bicycle.png "Reinventing the bicycle here")

When I first saw Laravel's socialite, I thought right away that it's going to simplify things SO MUCH. The only problem was that it had just few providers, did not even cover everything I needed. I thought with time they would add more, but that does not seem to be the case.

So once I just had to dive into the package and see how to extend it manually. And what do you know! It's actually extremely simple and requires a disturbingly little amount of efforts.

So here I will review how to extend Socialite with custom providers using Pinterest as an example. I guarantee that after going through this tutorial you will feel like you want and can create all the providers in the world!

Before we start, I just want to mention that there already is a giant package with a ton of custom providers for Socialite, it is actually called [SocialiteProviders](http://socialiteproviders.github.io/). Why did I not use it? Well, there are few reasons. First, I had problems with the class loader and the package and had no time or will to fix it. Second, it's always fun to take things apart and see how they work!

## Quick overview

To start creating custom providers all you need to do is create a custom Socialite manager that extends `Laravel\Socialite\SocialiteManager`. In there you would just create methods that initialize providers. In your service provider what you need to do is replace the `Laravel\Socialite\SocialiteManager` binding with the manager you created. Now let's go more into details.

## Manager

`Laravel\Socialite\SocialiteManager` uses the functionality of `Illuminate\Support\Manager`. We won't go too much into that part, you can always inspect that class on your own. Long story short, when you call `\Socialite::driver($driver)`, `Manager` calls `$this->create{$driver}Driver()` and that function must return an instance of a driver. As simple as that.

That implies that all we have to do in our manager is create a method like this:

```PHP
public function createPinterestDriver()
{
    $config = $this->app['config']['services.pinterest'];
    return $this->buildProvider(PinterestProvider::class, $config);
}
```
    
That is basically everything we need in the manager. For full class see [Socialite.php](https://github.com/JaffParker/Extending-Laravel-Socialite/blob/master/Socialite.php).

## Service provider

All you have to do in the service provider is make sure your custom manager, not the stock Socialite's manager, is bound in the container. Our service provider will extend the Socialite's service provider `Laravel\Socialite\SocialiteServiceProvider`. That way we won't have to copy-paste all the methods, but just override `register()`. Here's the final method:

```PHP
public function register()
{
    $this->app->singleton('Laravel\Socialite\Contracts\Factory', function ($app) {
        return new Socialite($app);
    });
}
```

So here we simply bind our manager to the Socialite factory. That's all. For full service provider see [SocialiteServiceProvider.php](https://github.com/JaffParker/Extending-Laravel-Socialite/blob/master/SocialiteServiceProvider.php). To read about service providers or service containers, go to [Laravel framework documentaion](https://laravel.com/docs/5.2/providers).

## The social provider

After all the preparation we are finally getting to the most interesting part - the provider itself. The class has to extend `Laravel\Socialite\Two\AbstractProvider` and implement `Laravel\Socialite\Two\ProviderInterface`. That implies that you manually have to implement 4 methods: `getAuthUrl()`, `getTokenUrl()`, `getUserByToken()` and `mapUserToObject()`.

`getAuthUrl()` and `getTokenUrl()` speak for themselves. The only thing they do is return a URL string to make calls to. For that you have to go to the API documentation that you're trying to implement. For Pinterest these methods look like this:

```PHP
/**
 * Get the authentication URL for the provider.
 *
 * @param  string $state
 * @return string
 */
protected function getAuthUrl($state)
{
    return $this->buildAuthUrlFromBase('https://api.pinterest.com/oauth/', $state);
}

/**
 * Get the token URL for the provider.
 *
 * @return string
 */
protected function getTokenUrl()
{
    return 'https://api.pinterest.com/v1/oauth/token';
}
```

These 2 are simple enough, let's move on.

```PHP
/**
 * Get the raw user for the given access token.
 *
 * @param  string $token
 * @return array
 */
protected function getUserByToken($token)
{
    $url = 'https://api.pinterest.com/v1/me';
    
    $response = $this->getHttpClient()->get($url, [
        'query' => [
            'access_token' => $token,
            'fields' => implode(',', $this->fields)
        ],
    ]);
    
    return json_decode($response->getBody(), true);
}
```

This is getting interesting now. This method is called when you call the `user()` method of the driver. In it you make a request to get user's information using [GuzzleHttp](http://docs.guzzlephp.org/en/latest/). You already need to know the URL for that call, which is `https://api.pinterest.com/v1/me` for Pinterest. For some networks you would have to include an http header `x-li-format: json` to get the response in JSON format, but Pinterest returns JSON by default. However, if you do need to include a header, simply pass an array along with `query` with header titles as keys and values as values.

Notice, about including token. Different networks require it in different ways. Pinterest requires the access token passed in the `access_token` query parameter, but many networks require an HTTP header `Authorization: Bearer {$token}`. So when you develop your own providers, make sure to pay attention to those details.

Also notice how this function returns an *array*. It is mandatory, since the result is instantly passed to the `mapUserToObject()` method, which, as we are going to see, requires an array as parameter.

So, `mapUserToObject()`. Let's start with the code:

```PHP
protected function mapUserToObject(array $user)
{
    $user = $user['data'];
    
    return (new User)->setRaw($user)->map([
        'id' => $user['id'],
        'nickname' => $user['username'],
        'name' => $user['first_name'] . ' ' . $user['last_name'],
        'email' => null,
        'avatar' => $user['image']['60x60']['url'],
        'avatar_original' => null,
    ]);
}
```

This is necessary because this method populates the `User` object which makes getting user data from networks very simple. At first, make sure to `setRaw($user)`, that way you'll have access to the original fields even after mapping.

`map()` accepts an array where keys are `User` model properties and values are... their values :) Just inspect the sample result in the API documentation and populate that array.

## Special stuff

In the source code you might have noticed that there's some stuff I didn't cover yet. Well, it's not as necessary as it is only Pinterest specific, however, I still have to go through it.

`fields` property. That array specifies which fields do I want Pinterest to return for my user model. You can see it being added to the `query` array when making the request.

Overriden `getTokenFields()` method. It is necessary because Pinterest (and some other networks too) require a parameter `grant_type` when requesting the access token. Socialite does not add it by default, that is why we have to do it ourselves.

## Conclusion

This is all you need to know to start creating your own Socialite providers. Do you need to? Well, it's up to you. But it's always good to know what you're working with and how does it function! So hope you enjoyed this tutorial and if something is not clear, you can always create an issue in this repository or even make a pull request to edit this readme.

## Links

* [Socialite GitHub page](https://github.com/laravel/socialite) - includes the documentation.
* [Laravel documentation](https://laravel.com/docs)
 * [Service providers](https://laravel.com/docs/providers)
 * [Service container](https://laravel.com/docs/container)
* [GuzzleHttp documentaion](http://docs.guzzlephp.org/en/latest/)
