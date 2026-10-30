<?php

it('wyświetla stronę główną serwisu', function (): void {
    $this->get('/')
        ->assertOk()
        ->assertSee('kontroladomu.pl');
});
