<?php

namespace Tests\Unit;

use App\Support\Like;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class LikeTest extends TestCase
{
    public function test_wildcards_and_escape_char_are_escaped()
    {
        $this->assertSame('%100!% wool!_x!!%', Like::pattern('100% wool_x!'));
    }

    public function test_column_names_must_be_plain_identifiers()
    {
        $this->expectException(\InvalidArgumentException::class);
        Like::where(DB::table('users'), 'name) OR 1=1 --', 'x');
    }
}
