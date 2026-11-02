<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SearchTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['hashing.bcrypt.rounds' => 4]);
        $this->seed();
    }

    public function test_palette_search_requires_login_and_a_query(): void
    {
        $this->getJson(route('search', ['q' => 'maria']))->assertUnauthorized();
        $this->actingAs(User::where('role', 'admin')->firstOrFail())->getJson(route('search'))->assertUnprocessable();
    }

    public function test_finds_contracts_workers_and_companies_with_their_pages(): void
    {
        $results = collect($this->actingAs(User::where('role', 'admin')->firstOrFail())
            ->getJson(route('search', ['q' => 'andinos']))->assertOk()->json('data'));

        $this->assertTrue($results->contains(fn ($item) => $item['kind'] === 'company' && str_contains($item['href'], '/empresas/')));
        $this->assertTrue($results->contains(fn ($item) => $item['kind'] === 'contract' && str_contains($item['href'], '/contratos/')));

        $workers = collect($this->getJson(route('search', ['q' => 'Gómez']))->json('data'))->where('kind', 'worker');
        $this->assertSame('María Fernanda Gómez', $workers->first()['title']);
    }

    public function test_collaborators_do_not_get_company_forms(): void
    {
        $results = collect($this->actingAs(User::where('role', 'collaborator')->firstOrFail())
            ->getJson(route('search', ['q' => 'andinos']))->assertOk()->json('data'));

        $this->assertFalse($results->contains('kind', 'company'));
    }
}
