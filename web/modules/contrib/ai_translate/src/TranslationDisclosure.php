<?php

declare(strict_types=1);

namespace Drupal\ai_translate;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\ai_disclosure\DisclosureRecorderInterface;
use Drupal\ai_disclosure\DisclosureResolverInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Records an AI disclosure on translations that AI Translate creates.
 *
 * Works only when the optional AI Disclosure module is installed.
 */
class TranslationDisclosure implements TranslationDisclosureInterface {

  /**
   * Grade recorded on a translation produced by AI.
   */
  protected const GRADE = 'ai_translated';

  /**
   * Source name recorded with the grade.
   */
  protected const SOURCE = 'ai_translate';

  /**
   * Constructs a TranslationDisclosure object.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The configuration factory.
   * @param \Drupal\ai_disclosure\DisclosureRecorderInterface|null $recorder
   *   The AI Disclosure recorder, or NULL when the module is not installed.
   * @param \Drupal\ai_disclosure\DisclosureResolverInterface|null $resolver
   *   The AI Disclosure resolver, or NULL when the module is not installed.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface|null $loggerFactory
   *   The logger factory, used to report a recording that failed.
   */
  public function __construct(
    protected readonly ConfigFactoryInterface $configFactory,
    #[Autowire(service: 'ai_disclosure.recorder')]
    protected readonly ?DisclosureRecorderInterface $recorder = NULL,
    #[Autowire(service: 'ai_disclosure.resolver')]
    protected readonly ?DisclosureResolverInterface $resolver = NULL,
    protected readonly ?LoggerChannelFactoryInterface $loggerFactory = NULL,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function isEnabled(): bool {
    return $this->recorder !== NULL
      && (bool) $this->configFactory->get('ai_translate.settings')->get('ai_disclosure_apply');
  }

  /**
   * {@inheritdoc}
   *
   * The AI Disclosure module writes a provenance row as soon as this runs,
   * for an entity that already has an ID. If the caller's save then fails the
   * row stays behind with no translation to describe. That is accepted: the
   * row is a service note that never reaches an editor or a reader.
   *
   * Nothing happens when the bundle has no AI Disclosure field, or when that
   * field is not translatable: writing it would then change the value shared
   * with the source. The source entity is never touched.
   *
   * For a new translation the copy of the source's disclosure field is first
   * emptied, so the human review attestation, the profile and the editor
   * description of the source do not describe text nobody has read. Then the
   * grade the source resolves to is applied, and then the AI translated
   * grade. The recorder never downgrades, so the stricter of the two stays,
   * always without a human review. The source's own description is not
   * carried over.
   *
   * A failure while recording is logged as a warning and never thrown: the
   * disclosure must not break the caller's translation.
   */
  public function apply(ContentEntityInterface $translation, ?LanguageInterface $langFrom = NULL, ?ContentEntityInterface $source = NULL): void {
    if (!$this->isEnabled() || !$this->canHoldDisclosure($translation)) {
      return;
    }
    try {
      // The note is built before anything is written. Emptying the copied
      // field first and only then failing would leave the translation with
      // no grade at all, and the single warning below as the only trace.
      $note = $this->buildNote($langFrom);
      if ($source !== NULL) {
        $this->resetFromSource($translation, $source, $note);
      }
      $this->recorder->apply($translation, static::GRADE, static::SOURCE, $note, FALSE);
    }
    catch (\Throwable $exception) {
      $this->loggerFactory?->get('ai_translate')->warning('Recording the AI disclosure failed: @message', [
        '@message' => $exception->getMessage(),
      ]);
    }
  }

  /**
   * Replaces the copied disclosure with the grade the source resolves to.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $translation
   *   The new translation, holding a copy of the source's field values.
   * @param \Drupal\Core\Entity\ContentEntityInterface $source
   *   The entity the translation is made from.
   * @param string $note
   *   The translation note. A stricter source grade refuses the AI translated
   *   write that follows, so this row must say the translation was AI made.
   */
  protected function resetFromSource(ContentEntityInterface $translation, ContentEntityInterface $source, string $note = ''): void {
    $translation->get($this->getDisclosureFieldName($translation))->setValue([]);
    $resolved = $this->resolver?->resolve($source);
    if ($resolved !== NULL) {
      $this->recorder->apply($translation, (string) $resolved->getGrade()->id(), static::SOURCE, trim('Grade of the source content. ' . $note), FALSE);
    }
  }

  /**
   * Checks that the translation can hold its own disclosure.
   *
   * The recorder writes the first AI Disclosure field, so only that one
   * counts. A translatable field is needed, or the value would be shared
   * with the source. A new standalone entity, such as a cloned Layout Builder
   * block, owns its field values and needs no translatable field.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $translation
   *   The translation.
   */
  protected function canHoldDisclosure(ContentEntityInterface $translation): bool {
    $name = $this->getDisclosureFieldName($translation);
    if ($name === NULL) {
      return FALSE;
    }
    if ($translation->isNew() && $translation->isDefaultTranslation()) {
      return TRUE;
    }
    return $translation->getFieldDefinitions()[$name]->isTranslatable();
  }

  /**
   * Gets the name of the field the recorder writes, the first AI Disclosure.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $translation
   *   The translation.
   */
  protected function getDisclosureFieldName(ContentEntityInterface $translation): ?string {
    foreach ($translation->getFieldDefinitions() as $name => $definition) {
      if ($definition->getType() === 'ai_disclosure') {
        return $name;
      }
    }
    return NULL;
  }

  /**
   * Builds the note stored with the disclosure.
   *
   * The note names the source language only. Naming the provider and the
   * model would mean reading which one really runs, and the bundled chat
   * proxy makes that a different answer from the translate_text default.
   * That belongs with the proxy, not here.
   *
   * @param \Drupal\Core\Language\LanguageInterface|null $langFrom
   *   The language the text was translated from, if known.
   *
   * @return string
   *   The note.
   */
  protected function buildNote(?LanguageInterface $langFrom): string {
    return $langFrom !== NULL
      ? sprintf('Translated from %s.', $langFrom->getName())
      : 'Translated.';
  }

}
