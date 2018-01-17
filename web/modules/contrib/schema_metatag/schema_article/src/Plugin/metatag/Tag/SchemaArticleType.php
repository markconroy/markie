<?php

namespace Drupal\schema_article\Plugin\metatag\Tag;

use \Drupal\schema_metatag\Plugin\metatag\Tag\SchemaNameBase;

/**
 * Provides a plugin for the 'schema_article_description' meta tag.
 *
 * - 'id' should be a globally unique id.
 * - 'name' should match the Schema.org element name.
 * - 'group' should match the id of the group that defines the Schema.org type.
 *
 * @MetatagTag(
 *   id = "schema_article_type",
 *   label = @Translation("@type"),
 *   description = @Translation("The type of article."),
 *   name = "@type",
 *   group = "schema_article",
 *   weight = -5,
 *   type = "string",
 *   secure = FALSE,
 *   multiple = FALSE
 * )
 */
class SchemaArticleType extends SchemaNameBase {

  /**
   * Generate a form element for this meta tag.
   */
  public function form(array $element = []) {
    $form = [
      '#type' => 'select',
      '#title' => $this->label(),
      '#description' => $this->description(),
      '#empty_option' => t('- None -'),
      '#empty_value' => '',
      '#options' => [
        'Article' => $this->t('Article'),
        'NewsArticle' => $this->t('NewsArticle'),
        'BlogPosting' => $this->t('BlogPosting'),
        'SocialMediaPosting' => $this->t('SocialMediaPosting'),
        'Report' => $this->t('Report'),
        'ScholarlyArticle' => $this->t('ScholarlyArticle'),
        'TechArticle' => $this->t('TechArticle'),
        'APIReference' => $this->t('APIReference'),
      ],
      '#default_value' => $this->value(),
    ];
    return $form;
  }

}
