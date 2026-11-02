<?php

namespace OpenAI\Testing\Responses\Fixtures\Skills;

final class SkillListResponseFixture
{
    public const ATTRIBUTES = [
        'object' => 'list',
        'data' => [
            SkillResponseFixture::ATTRIBUTES,
        ],
        'first_id' => 'skill_abc123',
        'last_id' => 'skill_abc123',
        'has_more' => false,
    ];
}
