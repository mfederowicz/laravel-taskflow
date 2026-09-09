<?php

namespace App\Providers;

use Dedoc\Scramble\Configuration\OperationTransformers;
use Dedoc\Scramble\Scramble;
use Dedoc\Scramble\Support\Generator\OpenApi;
use Dedoc\Scramble\Support\Generator\Operation;
use Dedoc\Scramble\Support\Generator\Path;
use Dedoc\Scramble\Support\Generator\SecurityRequirement;
use Dedoc\Scramble\Support\Generator\SecurityScheme;
use Dedoc\Scramble\Support\Generator\Tag;
use Dedoc\Scramble\Support\RouteInfo;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Laravel\Passport\Passport;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        Passport::ignoreRoutes();
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Passport::enablePasswordGrant();

        Passport::tokensExpireIn(now()->addMinutes((int) env('PASSPORT_TOKEN_EXPIRATION_MINUTES', 60)));
        Passport::refreshTokensExpireIn(now()->addDays((int) env('PASSPORT_REFRESH_TOKEN_EXPIRATION_DAYS', 7)));

        Scramble::configure()
            ->expose(
                ui: '/api/docs',
                document: '/api/docs.openapi.json',
            )
            ->withDocumentTransformers(function (OpenApi $openApi): void {
                $openApi->components->addSecurityScheme(
                    'bearerAuth',
                    SecurityScheme::http('bearer', 'JWT')
                        ->as('bearerAuth')
                        ->setDescription('Bearer token — Sanctum, JWT, or Passport (OAuth2).'),
                );

                $openApi->components->addSecurityScheme(
                    'X-Auth-Method',
                    SecurityScheme::apiKey('header', 'X-Auth-Method')
                        ->as('X-Auth-Method')
                        ->setDescription('Required companion header that selects the authentication strategy (sanctum | jwt | passport). Sent alongside the Authorization bearer token — not an API key.'),
                );

                $openApi->security = [
                    new SecurityRequirement(['bearerAuth' => [], 'X-Auth-Method' => []]),
                ];

                $openApi->paths = array_values(array_filter(
                    $openApi->paths,
                    fn (Path $path): bool => ! Str::contains($path->path, ['oauth/authorize', 'oauth/device']),
                ));

                $hasOAuth = false;

                foreach ($openApi->paths as $path) {
                    if (! Str::contains($path->path, 'oauth/')) {
                        continue;
                    }

                    $hasOAuth = true;

                    foreach ($path->operations as $operation) {
                        $operation->tags = ['OAuth2'];
                    }
                }

                if ($hasOAuth) {
                    $authenticationIndex = null;

                    foreach ($openApi->tags as $index => $tag) {
                        if ($tag->name === 'Authentication') {
                            $authenticationIndex = $index;

                            break;
                        }
                    }

                    $oauthTag = new Tag('OAuth2', 'Passport OAuth2 password grant — issues and refreshes access tokens.');

                    if ($authenticationIndex !== null) {
                        array_splice($openApi->tags, $authenticationIndex + 1, 0, [$oauthTag]);
                    } else {
                        $openApi->tags[] = $oauthTag;
                    }
                }
            })
            ->withOperationTransformers(function (OperationTransformers $transformers): void {
                $transformers->prepend(function (Operation $operation, RouteInfo $routeInfo): void {
                    $hasAuth = collect($routeInfo->route->gatherMiddleware())
                        ->contains('auth.multi');

                    if (! $hasAuth) {
                        $operation->security = [];
                    }
                });
            });
    }
}
