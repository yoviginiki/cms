<?php

namespace App\Domain\Publishing\Services;

class TextFlowCalculator
{
    /**
     * Estimate how many characters fit in a text frame.
     */
    public function estimateCharsFit(
        float $frameWidth,
        float $frameHeight,
        array $typography,
        int $columns = 1,
        array $textInset = [],
    ): int {
        $fontSize = $typography['fontSize'] ?? 14;
        $lineHeight = $typography['lineHeight'] ?? 1.5;
        $insetT = $textInset['top'] ?? 8;
        $insetR = $textInset['right'] ?? 8;
        $insetB = $textInset['bottom'] ?? 8;
        $insetL = $textInset['left'] ?? 8;

        $availW = ($frameWidth - $insetL - $insetR) / $columns;
        $availH = $frameHeight - $insetT - $insetB;
        $lineH = $fontSize * $lineHeight;
        $charsPerLine = max(1, (int) ($availW / ($fontSize * 0.55)));
        $lines = max(1, (int) ($availH / $lineH)) * $columns;

        return $charsPerLine * $lines;
    }

    /**
     * Split content at a word boundary near the charsFit limit.
     *
     * @return array{0: string, 1: string} [fitContent, overflow]
     */
    public function splitContent(string $content, int $charsFit): array
    {
        if (strlen($content) <= $charsFit) {
            return [$content, ''];
        }

        $splitAt = $charsFit;
        while ($splitAt > 0 && $content[$splitAt] !== ' ' && $content[$splitAt] !== "\n") {
            $splitAt--;
        }
        if ($splitAt === 0) {
            $splitAt = $charsFit;
        }

        return [substr($content, 0, $splitAt), ltrim(substr($content, $splitAt))];
    }
}
