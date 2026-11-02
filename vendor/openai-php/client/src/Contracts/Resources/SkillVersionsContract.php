<?php

declare(strict_types=1);

namespace OpenAI\Contracts\Resources;

use OpenAI\Responses\Skills\Versions\SkillVersionDeleteResponse;
use OpenAI\Responses\Skills\Versions\SkillVersionListResponse;
use OpenAI\Responses\Skills\Versions\SkillVersionResponse;

interface SkillVersionsContract
{
    /**
     * Create a new immutable skill version.
     *
     * @see https://developers.openai.com/api/reference/resources/skills/subresources/versions/methods/create
     *
     * @param  array<string, mixed>  $parameters
     */
    public function create(string $skillId, array $parameters): SkillVersionResponse;

    /**
     * List skill versions for a skill.
     *
     * @see https://developers.openai.com/api/reference/resources/skills/subresources/versions/methods/list
     *
     * @param  array<string, mixed>  $parameters
     */
    public function list(string $skillId, array $parameters = []): SkillVersionListResponse;

    /**
     * Get a specific skill version.
     *
     * @see https://developers.openai.com/api/reference/resources/skills/subresources/versions/methods/retrieve
     */
    public function retrieve(string $skillId, string $version): SkillVersionResponse;

    /**
     * Download a skill version zip bundle.
     *
     * @see https://developers.openai.com/api/reference/resources/skills/subresources/versions/subresources/content/methods/retrieve
     */
    public function content(string $skillId, string $version): string;

    /**
     * Delete a skill version.
     *
     * @see https://developers.openai.com/api/reference/resources/skills/subresources/versions/methods/delete
     */
    public function delete(string $skillId, string $version): SkillVersionDeleteResponse;
}
