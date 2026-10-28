<?php

declare(strict_types=1);

arch('every source file declares strict types')
    ->expect('Thbappy7706\SteadFastCourier')
    ->toUseStrictTypes();

arch('data transfer objects are final and readonly')
    ->expect('Thbappy7706\SteadFastCourier\Data')
    ->toBeFinal()
    ->toBeReadonly();

arch('source code does not use debugging helpers')
    ->expect('Thbappy7706\SteadFastCourier')
    ->not->toUse(['dd', 'dump', 'var_dump', 'print_r', 'ray', 'die']);

arch('the client contract stays an interface')
    ->expect('Thbappy7706\SteadFastCourier\Contracts\SteadFastClient')
    ->toBeInterface();
