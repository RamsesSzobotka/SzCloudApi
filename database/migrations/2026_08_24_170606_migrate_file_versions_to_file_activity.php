<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $versions = DB::table('file_versions')->orderBy('version', 'asc')->get();

        foreach ($versions as $v) {
            DB::table('file_activities')->insert([
                'id' => \Illuminate\Support\Str::uuid(),
                'file_id' => $v->file_id,
                'action' => 'content_change',
                'old_values' => null,
                'new_values' => json_encode([
                    'storage_name' => $v->storage_name,
                    'storage_path' => $v->storage_path,
                    'mime_type' => $v->mime_type,
                    'size' => $v->size,
                    'version' => $v->version,
                ]),
                'created_at' => $v->created_at,
                'updated_at' => $v->updated_at,
            ]);
        }
    }

    public function down(): void
    {
        DB::table('file_activities')->where('action', 'content_change')->delete();
    }
};
