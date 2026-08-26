<?php

use App\Models\User;

/**
 * El traductor integrado de Edge/Chrome reescribe los nodos de texto que React
 * controla; al re-renderizar, React truena con "Failed to execute 'removeChild'
 * on 'Node'". El shell de Inertia se marca como no traducible para evitarlo.
 */
test('el shell de inertia se declara en espanol y no traducible', function () {
    $this->actingAs(User::factory()->create());

    $html = $this->get(route('dashboard'))->assertOk()->getContent();

    expect($html)->toContain('<html lang="es" translate="no"')
        ->and($html)->toContain('<meta name="google" content="notranslate">')
        ->and($html)->toContain('class="notranslate font-sans antialiased"');
});
