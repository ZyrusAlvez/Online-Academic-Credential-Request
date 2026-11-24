<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * "Channel" was unclear: how a request was made, online or walk-in, is its request type
 * (REQ-26-10). Same values ('online', 'walk_in'); MySQL 8 renames columns and indexes in place.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('credential_requests', function (Blueprint $table) {
            $table->renameColumn('channel', 'request_type');
            $table->renameIndex('credential_requests_channel_status_index', 'credential_requests_request_type_status_index');
        });

        Schema::table('request_applications', function (Blueprint $table) {
            $table->renameColumn('channel', 'request_type');
        });
    }

    public function down(): void
    {
        Schema::table('request_applications', function (Blueprint $table) {
            $table->renameColumn('request_type', 'channel');
        });

        Schema::table('credential_requests', function (Blueprint $table) {
            $table->renameIndex('credential_requests_request_type_status_index', 'credential_requests_channel_status_index');
            $table->renameColumn('request_type', 'channel');
        });
    }
};
