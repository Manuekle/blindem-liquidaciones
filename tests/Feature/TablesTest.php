<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Worker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TablesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['hashing.bcrypt.rounds' => 4]);
        $this->seed();
        $this->actingAs(User::where('role', 'admin')->firstOrFail());
    }

    public function test_lists_sort_by_whitelisted_columns_in_both_directions(): void
    {
        $names = Worker::orderBy('full_name')->pluck('full_name')->all();
        $this->get('/trabajadores?sort=name&dir=asc')->assertOk()->assertSeeInOrder($names);
        $this->get('/trabajadores?sort=name&dir=desc')->assertOk()->assertSeeInOrder(array_reverse($names));

        foreach (['/contratos?sort=worker', '/contratos?sort=company&dir=desc', '/contratos?sort=salary', '/empresas?sort=contracts&dir=desc', '/usuarios?sort=status'] as $url) {
            $this->get($url)->assertOk()->assertSee('aria-sort=', false);
        }
        // Unknown keys fall back to the default order instead of reaching the query.
        $this->get('/trabajadores?sort=password&dir=desc')->assertOk()->assertDontSee('aria-sort=', false);
    }

    public function test_rows_link_to_their_record_and_page_size_is_limited(): void
    {
        $worker = Worker::firstOrFail();
        $this->get('/trabajadores')->assertOk()->assertSee('data-href="'.route('workers.show', $worker).'"', false)->assertSee('Filas por página');

        $this->get('/trabajadores?per_page=25')->assertOk()->assertSee('<option value="25" selected', false);
        $this->get('/trabajadores?per_page=1000')->assertOk()->assertSee('<option value="12" selected', false);
    }
}
