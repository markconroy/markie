<?php

declare(strict_types=1);

namespace OpenAI\Responses\Skills\Versions;

use OpenAI\Contracts\ResponseContract;
use OpenAI\Contracts\ResponseHasMetaInformationContract;
use OpenAI\Responses\Concerns\ArrayAccessible;
use OpenAI\Responses\Concerns\HasMetaInformation;
use OpenAI\Responses\Meta\MetaInformation;
use OpenAI\Testing\Responses\Concerns\Fakeable;

/**
 * @phpstan-type SkillVersionType array{id: string, object: 'skill.version', created_at: int, skill_id: string, version: string, name: string, description: string}
 *
 * @implements ResponseContract<SkillVersionType>
 */
final class SkillVersionResponse implements ResponseContract, ResponseHasMetaInformationContract
{
    /**
     * @use ArrayAccessible<SkillVersionType>
     */
    use ArrayAccessible;

    use Fakeable;
    use HasMetaInformation;

    /**
     * @param  'skill.version'  $object
     */
    private function __construct(
        public readonly string $id,
        public readonly string $object,
        public readonly int $createdAt,
        public readonly string $skillId,
        public readonly string $version,
        public readonly string $name,
        public readonly string $description,
        private readonly MetaInformation $meta,
    ) {}

    /**
     * Acts as static factory, and returns a new Response instance.
     *
     * @param  SkillVersionType  $attributes
     */
    public static function from(array $attributes, MetaInformation $meta): self
    {
        return new self(
            id: $attributes['id'],
            object: $attributes['object'],
            createdAt: $attributes['created_at'],
            skillId: $attributes['skill_id'],
            version: $attributes['version'],
            name: $attributes['name'],
            description: $attributes['description'],
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
            'skill_id' => $this->skillId,
            'version' => $this->version,
            'name' => $this->name,
            'description' => $this->description,
        ];
    }
}
