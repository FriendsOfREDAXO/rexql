<?php

namespace FriendsOfRedaxo\RexQL\Resolver;

use rex_category;

class NavigationResolver extends ResolverBase
{
  public function getData(): array|null
  {
    $rootCategoryId = $this->args['categoryId'] ?? null;
    $clangId = $this->args['clangId'] ?? 1;
    $depth = $this->args['depth'] ?? 1;
    $nested = $this->args['nested'] ?? false;

    $navigation = $this->getNavigation(
      $rootCategoryId,
      $clangId,
      $depth,
      $nested
    );
    return $navigation;
  }

  private function getNavigation(int|null $categoryId, int $clangId = 1, int $depth = 1, $nested = false): array
  {
    $navigation = [];

    $rootCategory = $categoryId ? rex_category::get($categoryId, $clangId) : null;

    $rootCategories = $rootCategory ? $rootCategory->getChildren(true) : rex_category::getRootCategories(true, $clangId);
    foreach ($rootCategories as $category) {
      $subNavigation = null;
      $item = $this->getItem($category);
      if ($depth > 1 && $category->getChildren()) {
        $subNavigation = $this->getNavigation($category->getId(), $clangId, $depth - 1, $nested);
        if ($nested) {
          $item['children'] = $subNavigation;
        }
      }
      $navigation[] = $item;
      if ($subNavigation && !$nested) {
        $navigation = array_merge($navigation, $subNavigation);
      }
    }
    return $navigation;
  }

  public function getItem(rex_category $category): array
  {
    $item = [
      'id' => $category->getId(),
      'parentId' => $category->getParentId(),
      'name' => $category->getName(),
      'slug' => $category->getUrl(),
    ];
    return $item;
  }
}
