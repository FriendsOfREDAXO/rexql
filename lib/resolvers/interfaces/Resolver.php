<?php

declare(strict_types=1);

namespace FriendsOfRedaxo\RexQL\Resolver\Interface;

use Closure;
use FriendsOfRedaxo\RexQL\Context;
use GraphQL\Type\Definition\ResolveInfo;

interface Resolver
{
  /**
   * Process all roots and return all the information obtained.
   *
   * @param array $roots
   * @param array $args
   * @param Context $context
   * @param ResolveInfo $info
   *
   * @return Closure
   */
  public function resolve(): Closure;

  /**
   * Returns the data.
   *
   * @return array|null
   */
  public function getData(): array|null;

  /**
   * Checks if the resolver has permissions to access the data.
   *
   * @return bool
   */
  public function checkPermissions(string $typename): bool;

  /**
   * Returns the fields of the resolver.
   *
   * @param string $table
   * @param array $selection
   *
   * @return array
   */
  public function getFields(string $table, array $selection): array;

  /**
   * Logs a message.
   * 
   * @param string $message
   * 
   * @return void
   */
  public function log(string $message): void;

  /**
   * Throws an UserError error message.
   * 
   * @param string $message
   *
   * @return void
   */
  public function error(string $message): void;
}
