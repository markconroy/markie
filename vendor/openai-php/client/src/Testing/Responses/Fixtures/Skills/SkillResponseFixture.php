<?php

namespace OpenAI\Testing\Responses\Fixtures\Skills;

final class SkillResponseFixture
{
    public const ATTRIBUTES = [
        'id' => 'skill_abc123',
        'object' => 'skill',
        'created_at' => 1710000000,
        'name' => 'basic-math',
        'description' => 'Add or multiply numbers.',
        'default_version' => '1',
        'latest_version' => '2',
    ];
}
