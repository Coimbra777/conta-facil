<?php

namespace Tests\Feature\PublicExpense;

use Tests\TestCase;

class PublicExpenseWebRouteTest extends TestCase
{
    public function test_public_expense_without_manage_redirects_to_participant_path(): void
    {
        $hash = 'a1b2c3d4-e5f6-7890-abcd-ef1234567890';

        $response = $this->get("/public/expenses/{$hash}");

        $response->assertStatus(302);
        $response->assertRedirect("/p/{$hash}");
    }

    public function test_public_expense_legacy_redirect_puts_manage_in_fragment(): void
    {
        $hash = 'a1b2c3d4-e5f6-7890-abcd-ef1234567890';
        $token = 'test-manage-token';

        $response = $this->get("/public/expenses/{$hash}?manage=".urlencode($token));

        $response->assertStatus(302);
        $response->assertRedirect('/p/'.$hash.'#manage='.rawurlencode($token));
    }

    public function test_participant_path_returns_spa_shell(): void
    {
        $hash = 'a1b2c3d4-e5f6-7890-abcd-ef1234567890';

        $response = $this->get("/p/{$hash}");

        $response->assertOk();
        $response->assertViewIs('spa');
    }

    public function test_legacy_two_segment_participant_path_redirects_to_single_link(): void
    {
        $expenseHash = 'a1b2c3d4-e5f6-7890-abcd-ef1234567890';
        $legacyParticipant = 'qualquer-hash-antigo';

        $response = $this->get("/p/{$expenseHash}/{$legacyParticipant}");

        $response->assertRedirect("/p/{$expenseHash}");
    }

    public function test_redirect_response_does_not_contain_expense_not_found_message(): void
    {
        $hash = 'a1b2c3d4-e5f6-7890-abcd-ef1234567890';

        $response = $this->get("/public/expenses/{$hash}");

        $response->assertStatus(302);
        $this->assertStringNotContainsString('Despesa nao encontrada', $response->getContent());
    }
}
