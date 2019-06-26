<?php

namespace A17\Twill;

use A17\Twill\Http\Middleware\Impersonate;
use A17\Twill\Http\Middleware\NoDebugBar;
use A17\Twill\Http\Middleware\RedirectIfAuthenticated;
use A17\Twill\Http\Middleware\ValidateBackHistory;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Routing\Router;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Route;

class RouteServiceProvider extends ServiceProvider
{
    protected $namespace = 'A17\Twill\Http\Controllers';

    /**
     * Bootstraps the package services.
     *
     * @return void
     */
    public function boot()
    {
        $this->registerRouteMiddlewares();
        $this->registerMacros();
        parent::boot();
    }

    /**
     * @param Router $router
     * @return void
     */
    public function map(Router $router)
    {
        if (($patterns=config('twill.admin_route_patterns')) != null) {
            if (is_array($patterns)) {
                foreach ($patterns as $label => $pattern) {
                    Route::pattern($label, $pattern);
                }
            }
        }

        if (file_exists(base_path('routes/admin.php'))) {
            $router->group([
                'namespace' => config('twill.namespace', 'App') . '\Http\Controllers\Admin',
                'domain' => config('twill.admin_app_url'),
                'as' => 'admin.',
                'middleware' => [config('twill.admin_middleware_group', 'web')],
                'prefix' => rtrim(ltrim(config('twill.admin_app_path'), '/'), '/'),
            ], function ($router) {
                $router->group(['middleware' => ['twill_auth:twill_users', 'impersonate', 'validateBackHistory']], function ($router) {
                    require base_path('routes/admin.php');
                });
            });
        }

        $router->group([
            'namespace' => $this->namespace . '\Admin',
            'domain' => config('twill.admin_app_url'),
            'as' => 'admin.',
            'middleware' => [config('twill.admin_middleware_group', 'web')],
            'prefix' => rtrim(ltrim(config('twill.admin_app_path'), '/'), '/'),
        ],
            function ($router) {
                $router->group(['middleware' => ['twill_auth:twill_users', 'impersonate', 'validateBackHistory']], function ($router) {
                    require __DIR__ . '/../routes/admin.php';
                });

                $router->group(['middleware' => ['noDebugBar']], function ($router) {
                    require __DIR__ . '/../routes/auth.php';
                });

                $router->group(['middleware' => array_merge(['noDebugBar'], (app()->environment('production') ? ['twill_auth:twill_users'] : []))], function ($router) {
                    require __DIR__ . '/../routes/templates.php';
                });
            }
        );

        if (config('twill.templates_on_frontend_domain')) {
            $router->group([
                'namespace' => $this->namespace . '\Admin',
                'domain' => config('app.url'),
                'middleware' => [config('twill.admin_middleware_group', 'web')],
            ],
                function ($router) {
                    $router->group(['middleware' => array_merge(['noDebugBar'], (app()->environment('production') ? ['twill_auth:twill_users'] : []))], function ($router) {
                        require __DIR__ . '/../routes/templates.php';
                    });
                }
            );
        }
    }

    /**
     * Register Route middleware.
     *
     * @return void
     */
    private function registerRouteMiddlewares()
    {
        /*
         * See Laravel 5.4 Changelog https://laravel.com/docs/5.4/upgrade
         * The middleware method of the Illuminate\Routing\Router class has been renamed to aliasMiddleware().
         */
        $middlewareRegisterMethod = method_exists(app('router'), 'aliasMiddleware') ? 'aliasMiddleware' : 'middleware';
        Route::$middlewareRegisterMethod('noDebugBar', NoDebugBar::class);
        Route::$middlewareRegisterMethod('impersonate', Impersonate::class);
        Route::$middlewareRegisterMethod('twill_auth', \Illuminate\Auth\Middleware\Authenticate::class);
        Route::$middlewareRegisterMethod('twill_guest', RedirectIfAuthenticated::class);
        Route::$middlewareRegisterMethod('validateBackHistory', ValidateBackHistory::class);
    }

    /**
     * Registers Route macros.
     *
     * @return void
     */
    protected function registerMacros()
    {
        Route::macro('moduleShowWithPreview', function ($moduleName, $routePrefix = null, $controllerName = null) {
            if ($routePrefix === null) {
                $routePrefix = $moduleName;
            }

            if ($controllerName === null) {
                $controllerName = ucfirst(str_plural($moduleName));
            }

            $routePrefix = empty($routePrefix) ? '/' : (starts_with($routePrefix, '/') ? $routePrefix : '/' . $routePrefix);
            $routePrefix = ends_with($routePrefix, '/') ? $routePrefix : $routePrefix . '/';

            Route::name($moduleName . '.show')->get($routePrefix . '{slug}', $controllerName . 'Controller@show');
            Route::name($moduleName . '.preview')->get('/admin-preview' . $routePrefix . '{slug}', $controllerName . 'Controller@show')->middleware(['web', 'twill_auth:twill_users', 'can:list']);
        });

        Route::macro('module', function ($slug, $options = [], $resource_options = [], $resource = true) {

            $slugs = explode('.', $slug);
            $prefixSlug = str_replace('.', "/", $slug);
            $_slug = Arr::last($slugs);
            $className = implode("", array_map(function ($s) {
                return ucfirst(str_singular($s));
            }, $slugs));

            $customRoutes = $defaults = ['reorder', 'publish', 'bulkPublish', 'browser', 'feature', 'bulkFeature', 'tags', 'preview', 'restore', 'bulkRestore', 'bulkDelete', 'restoreRevision'];

            if (isset($options['only'])) {
                $customRoutes = array_intersect($defaults, (array) $options['only']);
            } elseif (isset($options['except'])) {
                $customRoutes = array_diff($defaults, (array) $options['except']);
            }

            $groupPrefix = trim(str_replace('/', '.', Route::getLastGroupPrefix()), '.');

            if (!empty(config('twill.admin_app_path'))) {
                $groupPrefix = ltrim(str_replace(config('twill.admin_app_path'), '', $groupPrefix), '.');
            }

            $customRoutePrefix = !empty($groupPrefix) ? "{$groupPrefix}.{$slug}" : "{$slug}";

            foreach ($customRoutes as $route) {
                $routeSlug = "{$prefixSlug}/{$route}";
                $mapping = ['as' => $customRoutePrefix . ".{$route}", 'uses' => "{$className}Controller@{$route}"];

                if (in_array($route, ['browser', 'tags'])) {
                    Route::get($routeSlug, $mapping);
                }

                if (in_array($route, ['restoreRevision'])) {
                    Route::get($routeSlug . "/{id}", $mapping);
                }

                if (in_array($route, ['publish', 'feature', 'restore'])) {
                    Route::put($routeSlug, $mapping);
                }

                if (in_array($route, ['preview'])) {
                    Route::put($routeSlug . "/{id}", $mapping);
                }

                if (in_array($route, ['reorder', 'bulkPublish', 'bulkFeature', 'bulkDelete', 'bulkRestore'])) {
                    Route::post($routeSlug, $mapping);
                }
            }

            if ($resource) {
                $customRoutePrefix = !empty($groupPrefix) ? "{$groupPrefix}." : "";
                Route::group(['as' => $customRoutePrefix], function () use ($slug, $className, $resource_options) {
                    Route::resource($slug, "{$className}Controller", $resource_options);
                });
            }
        });
    }
}
