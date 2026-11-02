<?php

namespace OpenAI\Testing\Resources;

use OpenAI\Contracts\Resources\SkillsContract;
use OpenAI\Contracts\Resources\SkillVersionsContract;
use OpenAI\Resources\Skills;
use OpenAI\Responses\Skills\SkillDeleteResponse;
use OpenAI\Responses\Skills\SkillListResponse;
use OpenAI\Responses\Skills\SkillResponse;
use OpenAI\Testing\Resources\Concerns\Testable;

final class SkillsTestResource implements SkillsContract
{
    use Testable;

    public function resource(): string
    {
        return Skills::class;
    }

    public function create(array $parameters): SkillResponse
    {
        return $this->record(__FUNCTION__, func_get_args());
    }

    public function list(array $parameters = []): SkillListResponse
    {
        return $this->record(__FUNCTION__, func_get_args());
    }

    public function retrieve(string $id): SkillResponse
    {
        return $this->record(__FUNCTION__, func_get_args());
    }

    public function update(string $id, array $parameters): SkillResponse
    {
        return $this->record(__FUNCTION__, func_get_args());
    }

    public function content(string $id): string
    {
        return $this->record(__FUNCTION__, func_get_args());
    }

    public function delete(string $id): SkillDeleteResponse
    {
        return $this->record(__FUNCTION__, func_get_args());
    }

    public function versions(): SkillVersionsContract
    {
        return new SkillVersionsTestResource($this->fake);
    }
}
