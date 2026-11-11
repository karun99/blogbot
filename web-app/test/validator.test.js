'use strict';

const test = require('node:test');
const assert = require('node:assert/strict');

const {
  sanitizeJobInput,
  validateApiKey,
  INTERVAL_UNITS,
  TONES,
  POST_STATUSES,
} = require('../src/validator');

test('sanitizeJobInput clamps numeric fields to their documented ranges', () => {
  const job = sanitizeJobInput({
    job_name: '  Weekly roundup  ',
    num_posts: 999,
    interval_value: 0,
    word_count: 12,
  });
  assert.equal(job.job_name, 'Weekly roundup');
  assert.equal(job.num_posts, 50);
  assert.equal(job.interval_value, 1);
  assert.equal(job.word_count, 200);
});

test('sanitizeJobInput falls back to safe enum values', () => {
  const job = sanitizeJobInput({
    interval_unit: 'fortnights',
    post_status: 'deleted',
    tone: 'sarcastic',
  });
  assert.equal(job.interval_unit, 'hours');
  assert.equal(job.post_status, 'draft');
  assert.equal(job.tone, 'professional');
});

test('sanitizeJobInput keeps valid enums', () => {
  const job = sanitizeJobInput({
    interval_unit: 'weeks',
    post_status: 'publish',
    tone: 'technical',
  });
  assert.equal(job.interval_unit, 'weeks');
  assert.equal(job.post_status, 'publish');
  assert.equal(job.tone, 'technical');
});

test('sanitizeJobInput accepts only http(s) URLs', () => {
  assert.equal(sanitizeJobInput({ url: 'https://example.com' }).url, 'https://example.com');
  assert.equal(sanitizeJobInput({ url: 'javascript:alert(1)' }).url, '');
  assert.equal(sanitizeJobInput({}).url, '');
});

test('sanitizeJobInput truncates over-long strings', () => {
  const job = sanitizeJobInput({ job_name: 'a'.repeat(500) });
  assert.equal(job.job_name.length, 255);
});

test('validateApiKey accepts a well-formed OpenRouter key', () => {
  assert.equal(validateApiKey('sk-or-v1-' + 'a'.repeat(64)), true);
});

test('validateApiKey rejects empty and short keys', () => {
  assert.equal(validateApiKey(''), false);
  assert.equal(validateApiKey('short'), false);
});

test('enumerations are the documented sets', () => {
  assert.deepEqual(INTERVAL_UNITS, ['minutes', 'hours', 'days', 'weeks']);
  assert.deepEqual(POST_STATUSES, ['draft', 'publish']);
  assert.ok(TONES.includes('professional'));
});
