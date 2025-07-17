<?php

namespace FriendsOfRedaxo\RexQL\Resolver;

use FriendsOfRedaxo\RexQL\Resolver\ArticlesResolver;
use FriendsOfRedaxo\RexQL\Resolver\NavigationResolver;
use FriendsOfRedaxo\RexQL\Resolver\RexSystemResolver;

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
        'article' => (new ArticlesResolver())->resolve(),
        'articles' => (new ArticlesResolver())->resolve(),
        'slice' => (new SlicesResolver())->resolve(),
        'slices' => (new SlicesResolver())->resolve(),
        'navigation' => (new NavigationResolver())->resolve(),
        'rexSystem' => (new RexSystemResolver())->resolve(),
      ],
      'mutation' => [],
      'subscription' => [],
      'deferred' => [],
    ];
  }

  public function get(): array
  {
    return self::$resolvers;
  }
}
