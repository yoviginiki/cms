<?php

namespace App\Domain\Posts\Services;

use App\Models\Post;

class ReadingTimeService
{
    private const WORDS_PER_MINUTE = 238;

    public function estimate(Post $post): int
    {
        $blocks = $post->blocks()->get();
        $text = '';

        foreach ($blocks as $block) {
            $data = $block->data ?? [];
            $text .= ' ' . strip_tags($data['content'] ?? '');
            $text .= ' ' . strip_tags($data['text'] ?? '');
            $text .= ' ' . strip_tags($data['title'] ?? '');
            $text .= ' ' . strip_tags($data['subtitle'] ?? '');
        }

        $wordCount = str_word_count(trim($text));

        return max(1, (int) ceil($wordCount / self::WORDS_PER_MINUTE));
    }
}
