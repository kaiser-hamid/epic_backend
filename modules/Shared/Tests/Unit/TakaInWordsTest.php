<?php

use Modules\Shared\Billing\Services\TakaInWords;

it('spells a bill amount in words, counting in lakh and crore', function (int $paisa, string $words) {
    expect((new TakaInWords)->format($paisa))->toBe($words);
})->with([
    'the paper bill' => [712000, 'Taka Seven Thousand One Hundred Twenty Only'],
    'nothing' => [0, 'Taka Zero Only'],
    'teens' => [1500, 'Taka Fifteen Only'],
    'lakh' => [25_005_000, 'Taka Two Lakh Fifty Thousand Fifty Only'],
    'crore' => [1_234_567_800, 'Taka One Crore Twenty Three Lakh Forty Five Thousand Six Hundred Seventy Eight Only'],
    'paisa' => [10050, 'Taka One Hundred and Fifty Paisa Only'],
]);
