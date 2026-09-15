<?php

use Illuminate\Support\Facades\DB;

test('the test database has the pgvector extension enabled', function () {
    $extensionExists = DB::table('pg_extension')
        ->where('extname', 'vector')
        ->exists();

    expect($extensionExists)->toBeTrue();
});
