<?php

it('não expõe página inicial na raiz', function () {
    $this->get('/')->assertNotFound();
});