<?php

namespace OpenAI\Testing\Responses\Fixtures\Skills\Versions;

final class SkillVersionListResponseFixture
{
    public const ATTRIBUTES = [
        'object' => 'list',
        'data' => [
            SkillVersionResponseFixture::ATTRIBUTES,
        ],
        'first_id' => 'skillver_abc123',
        'last_id' => 'skillver_abc123',
        'has_more' => false,
    ];
}
