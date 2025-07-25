<?php

namespace FriendsOfRedaxo\RexQL\Resolver;

class LanguageResolver extends ResolverBase
{
  public function getData(): array
  {
    $this->table = 'rex_clang';

    $results = $this->query();

    return $this->typeName === 'languages' ? $results : $results[0];
  }
}
