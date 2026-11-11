'use strict';

const test = require('node:test');
const assert = require('node:assert/strict');

const { encrypt, decrypt, isEncrypted } = require('../src/encryption');

test('encrypt/decrypt round-trips a secret', () => {
  const secret = 'sk-or-v1-example-key';
  const payload = encrypt(secret);
  assert.notEqual(payload, secret);
  assert.equal(decrypt(payload), secret);
});

test('the same plaintext encrypts to different ciphertexts', () => {
  const a = encrypt('same');
  const b = encrypt('same');
  assert.notEqual(a, b, 'a fresh IV must be used per encryption');
});

test('decrypt returns an empty string for tampered payloads', () => {
  const payload = encrypt('sensitive');
  const tampered = payload.slice(0, -4) + 'AAAA';
  assert.equal(decrypt(tampered), '');
});

test('decrypt returns an empty string for garbage', () => {
  assert.equal(decrypt('not-base64-ciphertext'), '');
});

test('empty input round-trips as empty output', () => {
  assert.equal(encrypt(''), '');
  assert.equal(decrypt(''), '');
});

test('isEncrypted recognises real payloads only', () => {
  assert.equal(isEncrypted(encrypt('value')), true);
  assert.equal(isEncrypted(''), false);
  assert.equal(isEncrypted(null), false);
  assert.equal(isEncrypted('plain text'), false);
});
