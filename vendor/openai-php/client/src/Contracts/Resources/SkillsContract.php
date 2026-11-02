<?php

declare(strict_types=1);

namespace OpenAI\Contracts\Resources;

use OpenAI\Responses\Skills\SkillDeleteResponse;
use OpenAI\Responses\Skills\SkillListResponse;
use OpenAI\Responses\Skills\SkillResponse;

interface SkillsContract
{
    /**
     * Create a new skill by uploading its files (directory upload) or a single zip bundle.
     *
     * @see https://developers.openai.com/api/reference/resources/skills/methods/create
     *
     * @param  array<string, mixed>  $parameters
     */
    public function create(array $parameters): SkillResponse;

    /**
     * List all skills for the current project.
     *
     * @see https://developers.openai.com/api/reference/resources/skills/methods/list
     *
     * @param  array<string, mixed>  $parameters
     */
    public function list(array $parameters = []): SkillListResponse;

    /**
     * Get a skill by its ID.
     *
     * @see https://developers.openai.com/api/reference/resources/skills/methods/retrieve
     */
    public function retrieve(string $id): SkillResponse;

    /**
     * Update the default version pointer for a skill.
     *
     * @see https://developers.openai.com/api/reference/resources/skills/methods/update
     *
     * @param  array<string, mixed>  $parameters
     */
    public function update(string $id, array $parameters): SkillResponse;

    /**
     * Download a skill zip bundle by its ID.
     *
     * @see https://developers.openai.com/api/reference/resources/skills/subresources/content/methods/retrieve
     */
    public function content(string $id): string;

    /**
     * Delete a skill by its ID.
     *
     * @see https://developers.openai.com/api/reference/resources/skills/methods/delete
     */
    public function delete(string $id): SkillDeleteResponse;

    /**
     * Manage the immutable versions of a skill.
     *
     * @see https://developers.openai.com/api/reference/resources/skills/subresources/versions
     */
    public function versions(): SkillVersionsContract;
}
