<?php

namespace FriendsOfRedaxo\RexQL;

use FriendsOfRedaxo\RexQL\ApiKey;

use rex_sql;

class Context
{
  public rex_sql $sql;
  public ?ApiKey $apiKey;

  protected array $data = [];

  public function __construct()
  {
    // Initialize loaders
    $this->initializeLoaders();

    $this->sql = rex_sql::factory();
  }

  public function setApiKey(ApiKey $apiKey): void
  {
    $this->apiKey = $apiKey;
  }

  public function getApiKey(): ?ApiKey
  {
    return $this->apiKey;
  }

  public function set(string $key, $value): void
  {
    $this->data[$key] = $value;
  }

  public function get(string $key, $default = null)
  {
    // Return the value if it exists, otherwise return the default value
    return $this->data[$key] ?? $default;
  }

  private function initializeLoaders()
  {
    // // User loader - loads users by ID
    // $this->userLoader = new DataLoader(function ($userIds) {
    //   try {
    //     $users = Database::findUsersByIds($userIds);
    //     $result = [];
    //     foreach ($userIds as $id) {
    //       $userData = $users[$id] ?? null;
    //       $result[$id] = $userData ? new User($userData) : null;
    //     }
    //     return $result;
    //   } catch (Exception $e) {
    //     throw new DatabaseError("Failed to load users: " . $e->getMessage());
    //   }
    // });

    // // Post loader - loads posts by ID
    // $this->postLoader = new DataLoader(function ($postIds) {
    //   try {
    //     $posts = Database::findPostsByIds($postIds);
    //     $result = [];
    //     foreach ($postIds as $id) {
    //       $postData = $posts[$id] ?? null;
    //       $result[$id] = $postData ? new Post($postData) : null;
    //     }
    //     return $result;
    //   } catch (Exception $e) {
    //     throw new DatabaseError("Failed to load posts: " . $e->getMessage());
    //   }
    // });

    // // User posts loader - loads posts by author ID
    // $this->userPostsLoader = new DataLoader(function ($authorIds) {
    //   try {
    //     $postsByAuthor = Database::findPostsByAuthorIds($authorIds);
    //     $result = [];
    //     foreach ($authorIds as $authorId) {
    //       $posts = $postsByAuthor[$authorId] ?? [];
    //       $result[$authorId] = array_map(fn($data) => new Post($data), $posts);
    //     }
    //     return $result;
    //   } catch (Exception $e) {
    //     throw new DatabaseError("Failed to load user posts: " . $e->getMessage());
    //   }
    // });

    // // Post comments loader - loads comments by post ID
    // $this->postCommentsLoader = new DataLoader(function ($postIds) {
    //   try {
    //     $commentsByPost = Database::findCommentsByPostIds($postIds);
    //     $result = [];
    //     foreach ($postIds as $postId) {
    //       $comments = $commentsByPost[$postId] ?? [];
    //       $result[$postId] = array_map(fn($data) => new Comment($data), $comments);
    //     }
    //     return $result;
    //   } catch (Exception $e) {
    //     throw new DatabaseError("Failed to load post comments: " . $e->getMessage());
    //   }
    // });
  }
}
