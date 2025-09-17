<?php

namespace Drupal\comment\Hook;

use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Datetime\Entity\DateFormat;
use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\Render\BubbleableMetadata;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Hook implementations for comment.
 */
class CommentTokensHooks {

  use StringTranslationTrait;

  /**
   * Implements hook_token_info().
   */
  #[Hook('token_info')]
  public function tokenInfo(): array {
    $type = [
      'name' => $this->t('Comments'),
      'description' => $this->t('Tokens for comments posted on the site.'),
      'needs-data' => 'comment',
    ];
    $tokens = [];
    // Provides an integration for each entity type except comment.
    foreach (\Drupal::entityTypeManager()->getDefinitions() as $entity_type_id => $entity_type) {
      if ($entity_type_id == 'comment' || !$entity_type->entityClassImplements(ContentEntityInterface::class)) {
        continue;
      }
      if (\Drupal::service('comment.manager')->getFields($entity_type_id)) {
        // Get the correct token type.
        $token_type = $entity_type_id == 'taxonomy_term' ? 'term' : $entity_type_id;
        // @todo Make this work per field. See https://www.drupal.org/node/2031903.
        $tokens[$token_type]['comment-count'] = [
          'name' => $this->t("Comment count"),
          'description' => $this->t("The number of comments posted on an entity."),
        ];
        $tokens[$token_type]['comment-count-new'] = [
          'name' => $this->t("New comment count"),
          'description' => $this->t("The number of comments posted on an entity since the reader last viewed it."),
        ];
      }
    }
    // Core comment tokens
    $comment['cid'] = ['name' => $this->t("Comment ID"), 'description' => $this->t("The unique ID of the comment.")];
    $comment['uuid'] = ['name' => $this->t('UUID'), 'description' => $this->t("The UUID of the comment.")];
    $comment['hostname'] = [
      'name' => $this->t("IP Address"),
      'description' => $this->t("The IP address of the computer the comment was posted from."),
    ];
    $comment['mail'] = [
      'name' => $this->t("Email address"),
      'description' => $this->t("The email address left by the comment author."),
    ];
    $comment['homepage'] = [
      'name' => $this->t("Home page"),
      'description' => $this->t("The home page URL left by the comment author."),
    ];
    $comment['title'] = ['name' => $this->t("Title"), 'description' => $this->t("The title of the comment.")];
    $comment['body'] = [
      'name' => $this->t("Content"),
      'description' => $this->t("The formatted content of the comment itself."),
    ];
    $comment['langcode'] = [
      'name' => $this->t('Language code'),
      'description' => $this->t('The language code of the language the comment is written in.'),
    ];
    $comment['url'] = ['name' => $this->t("URL"), 'description' => $this->t("The URL of the comment.")];
    $comment['edit-url'] = [
      'name' => $this->t("Edit URL"),
      'description' => $this->t("The URL of the comment's edit page."),
    ];
    // Chained tokens for comments
    $comment['created'] = [
      'name' => $this->t("Date created"),
      'description' => $this->t("The date the comment was posted."),
      'type' => 'date',
    ];
    $comment['changed'] = [
      'name' => $this->t("Date changed"),
      'description' => $this->t("The date the comment was most recently updated."),
      'type' => 'date',
    ];
    $comment['parent'] = [
      'name' => $this->t("Parent"),
      'description' => $this->t("The comment's parent, if comment threading is active."),
      'type' => 'comment',
    ];
    $comment['entity'] = [
      'name' => $this->t("Entity"),
      'description' => $this->t("The entity the comment was posted to."),
      'type' => 'entity',
    ];
    $comment['author'] = [
      'name' => $this->t("Author"),
      'description' => $this->t("The author name of the comment."),
      'type' => 'user',
    ];
    return ['types' => ['comment' => $type], 'tokens' => ['comment' => $comment] + $tokens];
  }

  /**
   * Implements hook_tokens().
   */
  #[Hook('tokens')]
  public function tokens($type, $tokens, array $data, array $options, BubbleableMetadata $bubbleable_metadata): array {
    $token_service = \Drupal::token();
    $url_options = ['absolute' => TRUE];
    if (isset($options['langcode'])) {
      $url_options['language'] = \Drupal::languageManager()->getLanguage($options['langcode']);
      $langcode = $options['langcode'];
    }
    else {
      $langcode = NULL;
    }
    $replacements = [];
    if ($type == 'comment' && !empty($data['comment'])) {
      /** @var \Drupal\comment\CommentInterface $comment */
      $comment = $data['comment'];
      foreach ($tokens as $name => $original) {
        switch ($name) {
          // Simple key values on the comment.
          case 'cid':
            $replacements[$original] = $comment->id();
            break;

          case 'uuid':
            $replacements[$original] = $comment->uuid();
            break;

          // Poster identity information for comments.
          case 'hostname':
            $replacements[$original] = $comment->getHostname();
            break;

          case 'mail':
            $mail = $comment->getAuthorEmail();
            // Add the user cacheability metadata in case the author of the
            // comment is not the anonymous user.
            if ($comment->getOwnerId()) {
              $bubbleable_metadata->addCacheableDependency($comment->getOwner());
            }
            $replacements[$original] = $mail;
            break;

          case 'homepage':
            $replacements[$original] = UrlHelper::stripDangerousProtocols($comment->getHomepage());
            break;

          case 'title':
            $replacements[$original] = $comment->getSubject();
            break;

          case 'body':
            // "processed" returns a \Drupal\Component\Render\MarkupInterface
            // via check_markup().
            $replacements[$original] = $comment->comment_body->processed;
            break;

          case 'langcode':
            $replacements[$original] = $comment->language()->getId();
            break;

          // Comment related URLs.
          case 'url':
            $url_options['fragment'] = 'comment-' . $comment->id();
            $replacements[$original] = $comment->toUrl('canonical', $url_options)->toString();
            break;

          case 'edit-url':
            $url_options['fragment'] = NULL;
            $replacements[$original] = $comment->toUrl('edit-form', $url_options)->toString();
            break;

          case 'author':
            $name = $comment->getAuthorName();
            // Add the user cacheability metadata in case the author of the
            // comment is not the anonymous user.
            if ($comment->getOwnerId()) {
              $bubbleable_metadata->addCacheableDependency($comment->getOwner());
            }
            $replacements[$original] = $name;
            break;

          case 'parent':
            if ($comment->hasParentComment()) {
              $parent = $comment->getParentComment();
              $bubbleable_metadata->addCacheableDependency($parent);
              $replacements[$original] = $parent->getSubject();
            }
            break;

          case 'created':
            $date_format = DateFormat::load('medium');
            $bubbleable_metadata->addCacheableDependency($date_format);
            $replacements[$original] = \Drupal::service('date.formatter')->format($comment->getCreatedTime(), 'medium', '', NULL, $langcode);
            break;

          case 'changed':
            $date_format = DateFormat::load('medium');
            $bubbleable_metadata->addCacheableDependency($date_format);
            $replacements[$original] = \Drupal::service('date.formatter')->format($comment->getChangedTime(), 'medium', '', NULL, $langcode);
            break;

          case 'entity':
            $entity = $comment->getCommentedEntity();
            $bubbleable_metadata->addCacheableDependency($entity);
            $title = $entity->label();
            $replacements[$original] = $title;
            break;
        }
      }
      // Chained token relationships.
      if ($entity_tokens = $token_service->findwithPrefix($tokens, 'entity')) {
        $entity = $comment->getCommentedEntity();
        $replacements += $token_service->generate($comment->getCommentedEntityTypeId(), $entity_tokens, [$comment->getCommentedEntityTypeId() => $entity], $options, $bubbleable_metadata);
      }
      if ($date_tokens = $token_service->findwithPrefix($tokens, 'created')) {
        $replacements += $token_service->generate('date', $date_tokens, ['date' => $comment->getCreatedTime()], $options, $bubbleable_metadata);
      }
      if ($date_tokens = $token_service->findwithPrefix($tokens, 'changed')) {
        $replacements += $token_service->generate('date', $date_tokens, ['date' => $comment->getChangedTime()], $options, $bubbleable_metadata);
      }
      if (($parent_tokens = $token_service->findwithPrefix($tokens, 'parent')) && ($parent = $comment->getParentComment())) {
        $replacements += $token_service->generate('comment', $parent_tokens, ['comment' => $parent], $options, $bubbleable_metadata);
      }
      if (($author_tokens = $token_service->findwithPrefix($tokens, 'author')) && ($account = $comment->getOwner())) {
        $replacements += $token_service->generate('user', $author_tokens, ['user' => $account], $options, $bubbleable_metadata);
      }
    }
    elseif (!empty($data[$type]) && $data[$type] instanceof FieldableEntityInterface) {
      /** @var \Drupal\Core\Entity\FieldableEntityInterface $entity */
      $entity = $data[$type];
      foreach ($tokens as $name => $original) {
        switch ($name) {
          case 'comment-count':
            $count = 0;
            $fields = array_keys(\Drupal::service('comment.manager')->getFields($entity->getEntityTypeId()));
            $definitions = array_keys($entity->getFieldDefinitions());
            $valid_fields = array_intersect($fields, $definitions);
            foreach ($valid_fields as $field_name) {
              $count += $entity->get($field_name)->comment_count;
            }
            $replacements[$original] = $count;
            break;

          case 'comment-count-new':
            $replacements[$original] = \Drupal::service('comment.manager')->getCountNewComments($entity);
            break;
        }
      }
    }
    return $replacements;
  }

}
