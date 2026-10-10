// Run with: node --test tests/js/
import { test } from 'node:test';
import assert from 'node:assert/strict';
import { createRequire } from 'node:module';

const require = createRequire(import.meta.url);
const { shuffle } = require('../../resources/dist/filament-spid-button.js');

/**
 * A generator that replays one discrete Fisher–Yates draw: for i = n-1..1 it
 * returns a value that floors to choices[k] in [0, i].
 */
function replay(choices) {
    let k = 0;

    return () => {
        const i = choices.length - k; // range size for this step is i + 1
        const value = (choices[k] + 0.5) / (i + 1);
        k++;

        return value;
    };
}

/** Every sequence of draws Fisher–Yates can make for n items. */
function allDraws(n) {
    let sequences = [[]];
    for (let i = n - 1; i > 0; i--) {
        sequences = sequences.flatMap((seq) => Array.from({ length: i + 1 }, (_, j) => [...seq, j]));
    }

    return sequences;
}

test('every permutation of three providers is produced exactly once', () => {
    const counts = new Map();

    for (const draws of allDraws(3)) {
        const key = shuffle(['A', 'B', 'C'], replay(draws)).join('');
        counts.set(key, (counts.get(key) ?? 0) + 1);
    }

    assert.equal(counts.size, 6);
    assert.deepEqual([...counts.values()], [1, 1, 1, 1, 1, 1]);
});

test('every permutation of four providers is produced exactly once', () => {
    const keys = allDraws(4).map((draws) => shuffle(['A', 'B', 'C', 'D'], replay(draws)).join(''));

    assert.equal(keys.length, 24);
    assert.equal(new Set(keys).size, 24);
});

test('zero, one and two providers', () => {
    assert.deepEqual(shuffle([], Math.random), []);
    assert.deepEqual(shuffle(['A'], Math.random), ['A']);
    assert.deepEqual(shuffle(['A', 'B'], replay([0])), ['B', 'A']);
    assert.deepEqual(shuffle(['A', 'B'], replay([1])), ['A', 'B']);
});

test('the input is not mutated and nothing is duplicated or lost', () => {
    const input = ['A', 'B', 'C', 'D', 'E'];

    for (let run = 0; run < 50; run++) {
        const output = shuffle(input, Math.random);
        assert.deepEqual([...output].sort(), input);
    }

    assert.deepEqual(input, ['A', 'B', 'C', 'D', 'E']);
});

test('Math.random is the default generator', () => {
    assert.equal(shuffle(['A', 'B', 'C']).length, 3);
});
