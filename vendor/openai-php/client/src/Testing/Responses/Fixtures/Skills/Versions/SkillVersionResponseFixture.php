<?php

namespace OpenAI\Testing\Responses\Fixtures\Skills\Versions;

final class SkillVersionResponseFixture
{
    public const ATTRIBUTES = [
        'id' => 'skillver_abc123',
        'object' => 'skill.version',
        'created_at' => 1710000000,
        'skill_id' => 'skill_abc123',
        'version' => '1',
        'name' => 'basic-math',
        'description' => 'Add or multiply numbers.',
    ];
}
