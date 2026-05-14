<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Migrate quote blocks to pullquote: rename type and transform data keys
        $quoteBlocks = DB::table('blocks')->where('type', 'quote')->get();

        foreach ($quoteBlocks as $block) {
            $data = json_decode($block->data, true) ?? [];

            // Map old quote keys to pullquote keys
            $newData = $data;
            if (isset($data['content']) && !isset($data['text'])) {
                $newData['text'] = $data['content'];
                unset($newData['content']);
            }
            if (isset($data['citation']) && !isset($data['attribution'])) {
                $newData['attribution'] = $data['citation'];
                unset($newData['citation']);
            }
            if (!isset($newData['style'])) {
                $newData['style'] = 'large-text';
            }

            DB::table('blocks')->where('id', $block->id)->update([
                'type' => 'pullquote',
                'data' => json_encode($newData),
            ]);
        }
    }

    public function down(): void
    {
        // Only revert blocks that were originally quotes (have migrated data shape)
        // We detect these by checking for the 'text' key which pullquote uses
        $pullquoteBlocks = DB::table('blocks')->where('type', 'pullquote')->get();

        foreach ($pullquoteBlocks as $block) {
            $data = json_decode($block->data, true) ?? [];

            // Only revert if this looks like a migrated quote (no 'style' was explicitly set
            // by the user — we set 'large-text' as default during migration).
            // For safety, revert all pullquotes back to quote format.
            $newData = $data;
            if (isset($data['text']) && !isset($data['content'])) {
                $newData['content'] = $data['text'];
                unset($newData['text']);
            }
            if (isset($data['attribution']) && !isset($data['citation'])) {
                $newData['citation'] = $data['attribution'];
                unset($newData['attribution']);
            }
            unset($newData['style']);

            DB::table('blocks')->where('id', $block->id)->update([
                'type' => 'quote',
                'data' => json_encode($newData),
            ]);
        }
    }
};
