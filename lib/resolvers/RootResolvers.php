<?php

namespace FriendsOfRedaxo\RexQL\Resolver;

use FriendsOfRedaxo\RexQL\Resolver\RoutesResolver;
use FriendsOfRedaxo\RexQL\Resolver\ArticleResolver;
use FriendsOfRedaxo\RexQL\Resolver\NavigationResolver;
use FriendsOfRedaxo\RexQL\Resolver\RexSystemResolver;
use FriendsOfRedaxo\RexQL\Resolver\SlicesResolver;
use FriendsOfRedaxo\RexQL\Resolver\MediaResolver;
use FriendsOfRedaxo\RexQL\Resolver\MediaCategoryResolver;
use FriendsOfRedaxo\RexQL\Resolver\LanguageResolver;
use FriendsOfRedaxo\RexQL\Resolver\ConfigResolver;
use FriendsOfRedaxo\RexQL\Resolver\ModulesResolver;
use FriendsOfRedaxo\RexQL\Resolver\TemplatesResolver;
use FriendsOfRedaxo\RexQL\Resolver\WildcardResolver;
use FriendsOfRedaxo\RexQL\Resolver\YformTableResolver;
use rex_addon;


class RootResolvers
{
  private static array $resolvers = [];

  public function __construct()
  {
    $this->registerResolvers();
  }

  protected function registerResolvers(): void
  {
    self::$resolvers = [
      'query' => [
        'routes' => (new RoutesResolver())->resolve(),
        'article' => (new ArticleResolver())->resolve(),
        'articles' => (new ArticleResolver())->resolve(),
        'slice' => (new SlicesResolver())->resolve(),
        'slices' => (new SlicesResolver())->resolve(),
        'navigation' => (new NavigationResolver())->resolve(),
        'rexSystem' => (new RexSystemResolver())->resolve(),
        'media' => (new MediaResolver())->resolve(),
        'medias' => (new MediaResolver())->resolve(),
        'mediaCategory' => (new MediaCategoryResolver())->resolve(),
        'mediaCategories' => (new MediaCategoryResolver())->resolve(),
        'language' => (new LanguageResolver())->resolve(),
        'languages' => (new LanguageResolver())->resolve(),
        'config' => (new ConfigResolver())->resolve(),
        'configs' => (new ConfigResolver())->resolve(),
        'module' => (new ModulesResolver())->resolve(),
        'modules' => (new ModulesResolver())->resolve(),
        'template' => (new TemplatesResolver())->resolve(),
        'templates' => (new TemplatesResolver())->resolve(),
        'wildcard' => (new WildcardResolver())->resolve(),
        'wildcards' => (new WildcardResolver())->resolve(),

      ],
      'mutation' => [],
      'subscription' => [],
      'deferred' => [],
    ];

    if (rex_addon::get('yform')->isAvailable()) {
      YformTableResolver::registerResolvers();
    }
  }

  public function get(): array
  {
    return self::$resolvers;
  }
}
