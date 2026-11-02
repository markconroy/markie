<?php

namespace OpenAI\Testing\Responses\Fixtures\Skills;

final class SkillDeleteResponseFixture
{
    public const ATTRIBUTES = [
        'id' => 'skill_abc123',
        'object' => 'skill.deleted',
        'deleted' => true,
    ];
}
