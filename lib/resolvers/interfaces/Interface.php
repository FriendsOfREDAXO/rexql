<?php

declare(strict_types=1);

namespace FriendsOfRedaxo\RexQL\Resolver\Interfaces;

interface DeferredResolverInterface
{
  /**
   * Process all roots and return all the information obtained.
   *
   * @param mixed $roots
   * @param mixed $args
   * @param mixed $context
   * @param mixed $info
   *
   * @return mixed
   */
  public function fetch($roots, $args, $context, $info);

  /**
   * Returns the data depending on the root.
   *
   * @param mixed $root
   * @param mixed $data
   *
   * @return mixed
   */
  public function pluck($root, $data);
}
