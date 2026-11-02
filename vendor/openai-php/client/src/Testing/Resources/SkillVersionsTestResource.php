<?php

namespace OpenAI\Testing\Resources;

use OpenAI\Contracts\Resources\SkillVersionsContract;
use OpenAI\Resources\SkillVersions;
use OpenAI\Responses\Skills\Versions\SkillVersionDeleteResponse;
use OpenAI\Responses\Skills\Versions\SkillVersionListResponse;
use OpenAI\Responses\Skills\Versions\SkillVersionResponse;
use OpenAI\Testing\Resources\Concerns\Testable;

final class SkillVersionsTestResource implements SkillVersionsContract
{
    use Testable;

    public function resource(): string
    {
        return SkillVersions::class;
    }

    public function create(string $skillId, array $parameters): SkillVersionResponse
    {
        return $this->record(__FUNCTION__, func_get_args());
    }

    public function list(string $skillId, array $parameters = []): SkillVersionListResponse
    {
        return $this->record(__FUNCTION__, func_get_args());
    }

    public function retrieve(string $skillId, string $version): SkillVersionResponse
    {
        return $this->record(__FUNCTION__, func_get_args());
    }

    public function content(string $skillId, string $version): string
    {
        return $this->record(__FUNCTION__, func_get_args());
    }

    public function delete(string $skillId, string $version): SkillVersionDeleteResponse
    {
        return $this->record(__FUNCTION__, func_get_args());
    }
}
