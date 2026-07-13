<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('messages')) {
            return;
        }

        Schema::table('messages', function (Blueprint $table) {
            if (! Schema::hasColumn('messages', 'meta')) {
                $table->json('meta')->nullable()->after('content');
            }

            if (! Schema::hasColumn('messages', 'external_message_id')) {
                $table->string('external_message_id')->nullable()->after('status');
            }

            if (! Schema::hasColumn('messages', 'confidence')) {
                $table->decimal('confidence', 5, 4)->nullable()->after('external_message_id');
            }

            if (! Schema::hasColumn('messages', 'source')) {
                $table->string('source', 64)->nullable()->after('confidence');
            }

            if (! Schema::hasColumn('messages', 'media_type')) {
                $table->string('media_type', 32)->nullable()->after('source');
            }

            if (! Schema::hasColumn('messages', 'media_url')) {
                $table->text('media_url')->nullable()->after('media_type');
            }

            if (! Schema::hasColumn('messages', 'filename')) {
                $table->string('filename')->nullable()->after('media_url');
            }

            if (! Schema::hasColumn('messages', 'is_read')) {
                $table->boolean('is_read')->default(false)->after('filename');
            }

            if (! Schema::hasColumn('messages', 'read_at')) {
                $table->timestamp('read_at')->nullable()->after('is_read');
            }
        });

        $this->relaxLegacyEnums();
    }

    public function down(): void
    {
        if (! Schema::hasTable('messages')) {
            return;
        }

        Schema::table('messages', function (Blueprint $table) {
            foreach ([
                'read_at',
                'is_read',
                'filename',
                'media_url',
                'media_type',
                'source',
                'confidence',
                'external_message_id',
                'meta',
            ] as $column) {
                if (Schema::hasColumn('messages', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }

    protected function relaxLegacyEnums(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver !== 'mysql') {
            return;
        }

        if (Schema::hasColumn('messages', 'direction')) {
            DB::statement("ALTER TABLE messages MODIFY direction VARCHAR(20) NOT NULL");
        }

        if (Schema::hasColumn('messages', 'type')) {
            DB::statement("ALTER TABLE messages MODIFY type VARCHAR(32) NOT NULL DEFAULT 'text'");
        }

        if (Schema::hasColumn('messages', 'status')) {
            DB::statement('ALTER TABLE messages MODIFY status VARCHAR(32) NULL');
        }

        if (Schema::hasColumn('messages', 'content')) {
            DB::statement('ALTER TABLE messages MODIFY content TEXT NULL');
        }
    }
};
