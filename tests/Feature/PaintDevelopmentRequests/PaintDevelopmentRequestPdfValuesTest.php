<?php

declare(strict_types=1);

use App\Http\Requests\PaintDevelopmentRequests\StorePaintDevelopmentRequest;
use App\Models\PaintDevelopmentRequest;

it('shows a zero as a value and formats the technical numbers', function () {
    expect(PaintDevelopmentRequest::displayPayloadValue('application_payload', 'voc', 0))->toBe('0')
        ->and(PaintDevelopmentRequest::displayPayloadValue('application_payload', 'voc', '0'))->toBe('0')
        ->and(PaintDevelopmentRequest::displayPayloadValue('context_payload', 'temp_min', '-5.5'))->toBe('-5,5')
        ->and(PaintDevelopmentRequest::displayPayloadValue('performance_payload', 'camara_obj', 1000))->toBe('1.000');
});

it('shows the empty mark only for what has no value', function (mixed $value) {
    expect(PaintDevelopmentRequest::displayPayloadValue('application_payload', 'voc', $value))->toBe('—');
})->with([[null], [''], [[]]]);

it('leaves free text and text inside a numeric field as written', function () {
    expect(PaintDevelopmentRequest::displayPayloadValue('context_payload', 'sustrato', '1000'))->toBe('1000')
        ->and(PaintDevelopmentRequest::displayPayloadValue('application_payload', 'manos', '3,5,1'))->toBe('3,5,1')
        ->and(PaintDevelopmentRequest::displayPayloadValue('context_payload', 'preparacion', ['SA 2.5', 'St 3']))->toBe('SA 2.5, St 3');
});

it('keeps the numeric fields the same as the validation rules and the form', function () {
    $listed = collect(PaintDevelopmentRequest::NUMERIC_PAYLOAD_FIELDS)
        ->flatMap(fn (array $fields, string $payload) => array_map(fn (string $field) => "{$payload}.{$field}", $fields))
        ->sort()
        ->values()
        ->all();

    $validated = collect((new StorePaintDevelopmentRequest)->rules())
        ->filter(fn (mixed $rules) => is_array($rules) && array_intersect(['numeric', 'integer'], array_filter($rules, 'is_string')) !== [])
        ->keys()
        ->filter(fn (string $key) => str_contains($key, '_payload.'))
        ->sort()
        ->values()
        ->all();

    preg_match('/NUMERIC_FIELDS_BY_PAYLOAD = \{(.*?)\} as const/s', file_get_contents(resource_path('js/components/paint-development-requests/numeric-payload.ts')), $block);
    preg_match_all('/(\w+_payload): \[(.*?)\]/s', $block[1], $groups, PREG_SET_ORDER);

    $inForm = collect($groups)
        ->flatMap(function (array $group) {
            preg_match_all("/'(\w+)'/", $group[2], $fields);

            return array_map(fn (string $field) => "{$group[1]}.{$field}", $fields[1]);
        })
        ->sort()
        ->values()
        ->all();

    expect($listed)->toBe($validated)->toBe($inForm);
});
