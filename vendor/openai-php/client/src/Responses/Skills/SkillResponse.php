<?php

declare(strict_types=1);

namespace OpenAI\Responses\Skills;

use OpenAI\Contracts\ResponseContract;
use OpenAI\Contracts\ResponseHasMetaInformationContract;
use OpenAI\Responses\Concerns\ArrayAccessible;
use OpenAI\Responses\Concerns\HasMetaInformation;
use OpenAI\Responses\Meta\MetaInformation;
use OpenAI\Testing\Responses\Concerns\Fakeable;

/**
 * @phpstan-type SkillType array{id: string, object: 'skill', created_at: int, name: string, description: string, default_version: string, latest_version: string}
 *
 * @implements ResponseContract<SkillType>
 */
final class SkillResponse implements ResponseContract, ResponseHasMetaInformationContract
{
    /**
     * @use ArrayAccessible<SkillType>
     */
    use ArrayAccessible;

    use Fakeable;
    use HasMetaInformation;

    /**
     * @param  'skill'  $object
     */
    private function __construct(
        public readonly string $id,
        public readonly string $object,
        public readonly int $createdAt,
        public readonly string $name,
        public readonly string $description,
        public readonly string $defaultVersion,
        public readonly string $latestVersion,
        private readonly MetaInformation $meta,
    ) {}

    /**
     * Acts as static factory, and returns a new Response instance.
     *
     * @param  SkillType  $attributes
     */
    public static function from(array $attributes, MetaInformation $meta): self
    {
        return new self(
            id: $attributes['id'],
            object: $attributes['object'],
            createdAt: $attributes['created_at'],
            name: $attributes['name'],
            description: $attributes['description'],
            defaultVersion: $attributes['default_version'],
            latestVersion: $attributes['latest_version'],
            meta: $meta,
        );
    }

    /**
     * {@inheritDoc}
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'object' => $this->object,
            'created_at' => $this->createdAt,
            'name' => $this->name,
            'description' => $this->description,
            'default_version' => $this->defaultVersion,
            'latest_version' => $this->latestVersion,
        ];
    }
}
